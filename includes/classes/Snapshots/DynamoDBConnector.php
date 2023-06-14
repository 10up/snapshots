<?php
/**
 * Amazon Dynamo wrapper functionality
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws;
use TenUp\Snapshots\Exceptions\SnapshotsException;

/**
 * Class for handling Amazon dynamodb calls
 */
class DynamoDBConnector implements DBConnectorInterface {

	/**
	 * Client
	 *
	 * @var ?DynamoDbClient
	 */
	private $client = null;

	/**
	 * Searches the database.
	 *
	 * @param  string|array $query Search query string
	 * @param  array        $config AWS config
	 * @return array
	 */
	public function search( $query, array $config ) : array {
		$marshaler = new Marshaler();

		$args = [
			'TableName' => 'wpsnapshots-' . $config['repository'],
		];

		if ( ! is_array( $query ) ) {
			$query = [ $query ];
		}

		if ( ! in_array( '*', $query, true ) ) {
			$attribute_value_list = array_map(
				function( $text ) {
					return [ 'S' => strtolower( $text ) ];
				},
				$query
			);

			$is_multiple_queries = count( $attribute_value_list ) > 1;

			$args['ConditionalOperator'] = 'OR';
			$args['ScanFilter']          = [
				'project' => [
					'AttributeValueList' => $attribute_value_list,
					'ComparisonOperator' => $is_multiple_queries ? 'IN' : 'CONTAINS',
				],
				'id'      => [
					'AttributeValueList' => $attribute_value_list,
					'ComparisonOperator' => $is_multiple_queries ? 'IN' : 'EQ',
				],
			];
		}

		$search_scan = $this->get_client( $config )->getIterator( 'Scan', $args );

		$instances = [];

		foreach ( $search_scan as $item ) {
			$instances[] = $marshaler->unmarshalItem( (array) $item );
		}

		return $instances;
	}

	/**
	 * Get a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $config AWS Config
	 * @return mixed
	 */
	public function get_snapshot( string $id, array $config ) {
		$result = $this->get_client( $config )->getItem(
			[
				'ConsistentRead' => true,
				'TableName'      => 'wpsnapshots-' . $config['repository'],
				'Key'            => [
					'id' => [
						'S' => $id,
					],
				],
			]
		);

		if ( empty( $result['Item'] ) ) {
			return false;
		}

		if ( ! empty( $result['Item']['error'] ) ) {
			return false;
		}

		$marshaler = new Marshaler();

		return $marshaler->unmarshalItem( $result['Item'] );
	}

	/**
	 * Create default DB tables. Only need to do this once ever for repo setup.
	 *
	 * @param array $config AWS config
	 */
	public function create_tables( array $config ) {
		$table_name = 'wpsnapshots-' . $config['repository'];
		$client     = $this->get_client( $config );

		$client->createTable(
			[
				'TableName'             => $table_name,
				'AttributeDefinitions'  => [
					[
						'AttributeName' => 'id',
						'AttributeType' => 'S',
					],
				],
				'KeySchema'             => [
					[
						'AttributeName' => 'id',
						'KeyType'       => 'HASH',
					],
				],
				'ProvisionedThroughput' => [
					'ReadCapacityUnits'  => 10,
					'WriteCapacityUnits' => 20,
				],
			]
		);

		$client->waitUntil(
			'TableExists',
			[
				'TableName' => $table_name,
			]
		);
	}

	/**
	 * Insert a snapshot into the DB
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $config AWS config
	 * @param array  $meta Snapshot meta.
	 */
	public function insert_snapshot( string $id, array $config, array $meta ) : void {
		$marshaler = new Marshaler();

		$snapshot_item = [
			'project' => strtolower( $meta['project'] ),
			'id'      => $id,
			'time'    => time(),
		];

		$snapshot_item = array_merge( $snapshot_item, $meta );
		$snapshot_json = json_encode( $snapshot_item ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		$this->get_client( $config )->putItem(
			[
				'TableName' => 'wpsnapshots-' . $config['repository'],
				'Item'      => $marshaler->marshalJson( $snapshot_json ),
			]
		);
	}

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $config AWS config
	 */
	public function delete_snapshot( string $id, array $config ) : void {
		$this->get_client( $config )->deleteItem(
			[
				'TableName' => 'wpsnapshots-' . $config['repository'],
				'Key'       => [
					'id' => [
						'S' => $id,
					],
				],
			]
		);
	}

	/**
	 * Provides the client.
	 *
	 * @param array $config AWS config
	 *
	 * @return DynamoDbClient
	 * @throws SnapshotsException Failed to assume ARN role
	 */
	private function get_client( array $config ) : DynamoDbClient {
		if ( ! is_null( $this->client ) ) {
			return $this->client;
		}

		$args = [
			'region'  => $config['region'],
			'version' => '2012-08-10',
			'csm'     => false,
		];

		// if role_arn has a value use STS to assume the role
		// and pass the credential info to DynamoDbClient later on
		if ( ! empty( $config['role_arn'] ) ) {
			$args['roleArn'] = $config['role_arn'];

			$temp_creds = $this->assume_role( $args );

			if ( ! is_array( $temp_creds ) ) {
				throw new SnapshotsException( sprintf( "Failed to assume role '%s'.", $args['roleArn'] ) );
			}

			$args['credentials'] = [
				'key'    => $temp_creds['AccessKeyId'],
				'secret' => $temp_creds['SecretAccessKey'],
				'token'  => $temp_creds['SessionToken'],
			];
		} elseif ( ! empty( $config['profile'] ) ) {
			$args['profile'] = $config['profile'];
		}

		$this->client = new DynamoDbClient( $args );

		return $this->client;
	}

	/**
	 * Performs STS
	 *
	 * @param array $connection_parameters Parameters for connection
	 * @return array
	 */
	private function assume_role( $connection_parameters ) : array {
		$sts_client = new Aws\Sts\StsClient(
			[
				'region'  => 'us-east-1',
				'version' => '2011-06-15',
			]
		);

		$result = $sts_client->AssumeRole(
			[
				'RoleArn'         => $connection_parameters['roleArn'],
				'RoleSessionName' => 'wpsnapshots',
			]
		);

		return $result['Credentials'];
	}
}
