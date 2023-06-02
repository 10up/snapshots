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
	 * Clients keyed by region.
	 *
	 * @var DynamoDbClient[]
	 */
	private $clients = [];

	/**
	 * Searches the database.
	 *
	 * @param  string|array $query Search query string
	 * @param string       $profile AWS profile.
	 * @param  string       $repository Repository name
	 * @param string       $region AWS region
	 * @return array
	 */
	public function search( $query, string $profile, string $repository, string $region ) : array {
		$marshaler = new Marshaler();

		$args = [
			'TableName' => 'wpsnapshots-' . $repository,
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

		$search_scan = $this->get_client( $profile, $region )->getIterator( 'Scan', $args );

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
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 * @return mixed
	 */
	public function get_snapshot( string $id, string $profile, string $repository, string $region ) {
		$result = $this->get_client( $profile, $region )->getItem(
			[
				'ConsistentRead' => true,
				'TableName'      => 'wpsnapshots-' . $repository,
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
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function create_tables( string $profile, string $repository, string $region ) {
		$table_name = 'wpsnapshots-' . $repository;
		$client     = $this->get_client( $profile, $region );

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
	 * @param string $profile AWS profile.
	 * @param  string $repository Repository name.
	 * @param string $region AWS region.
	 * @param array  $meta Snapshot meta.
	 */
	public function insert_snapshot( string $id, string $profile, string $repository, string $region, array $meta ) : void {
		$marshaler = new Marshaler();

		$snapshot_item = [
			'project' => strtolower( $meta['project'] ),
			'id'      => $id,
			'time'    => time(),
		];

		$snapshot_item = array_merge( $snapshot_item, $meta );
		$snapshot_json = json_encode( $snapshot_item ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		$this->get_client( $profile, $region )->putItem(
			[
				'TableName' => 'wpsnapshots-' . $repository,
				'Item'      => $marshaler->marshalJson( $snapshot_json ),
			]
		);
	}

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function delete_snapshot( string $id, string $profile, string $repository, string $region ) : void {
		$this->get_client( $profile, $region )->deleteItem(
			[
				'TableName' => 'wpsnapshots-' . $repository,
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
	 * @param string $profile AWS profile.
	 * @param string $region AWS region.
	 *
	 * @return DynamoDbClient
	 */
	private function get_client( string $profile, string $region ) : DynamoDbClient {
		$client_key = $profile . '_' . $region;
		$role_arn = $_ENV['role_arn'];

		$args = [
			'region'  => $region,
			'version' => '2012-08-10',
			'csm'     => false,
		];

		// if role_arn has a value use STS to assume the role
		// and pass the credential info to DynamoDbClient later on
		if ( $role_arn != "" ) {
			$args['roleArn'] = $role_arn;

			$temporaryCredentials = $this->assumeRole($args);

			if ( ! is_array( $temporaryCredentials ) ) {
				throw new SnapshotsException( sprintf( "Failed to assume role '%s'.", $args['roleArn'] ) );
			}

			$args['credentials'] = [
				'key'    => $temporaryCredentials['AccessKeyId'],
				'secret' => $temporaryCredentials['SecretAccessKey'],
				'token'  => $temporaryCredentials['SessionToken']
			];
		}

		if ( $role_arn == "" && $profile != "" ) {
			$args['profile'] = $profile;
		}

		if ( ! isset( $this->clients[ $client_key ] ) ) {
			$this->clients[ $client_key ] = new DynamoDbClient( $args );
		}

		return $this->clients[ $client_key ];
	}

	/**
	 * Performs STS
	 * 
	 * @param string $role_arn AWS role_arn
	 */
	private function assumeRole( $connectionParameters ) : array {
		$stsClient = new Aws\Sts\StsClient([
			'region' => 'us-east-1',
			'version' => '2011-06-15'
		]);

		$result = $stsClient->AssumeRole([
					'RoleArn'         => $connectionParameters['roleArn'],
					'RoleSessionName' => "wpsnapshots",
		]);

		return $result['Credentials'];
	}
}
