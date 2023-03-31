<?php
/**
 * Amazon Dynamo wrapper functionality
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

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

		$args = [
			'region'  => $region,
			'profile' => $profile,
			'version' => '2012-08-10',
			'csm'     => false,
		];

		// Check if the necessary AWS env vars are set; if so, the profile arg is not needed.
		// These are the same env vars the SDK checks for in CredentialProvider.php.
		if ( getenv( 'AWS_ACCESS_KEY_ID' ) && getenv( 'AWS_SECRET_ACCESS_KEY' ) ) {
			unset( $args['profile'] );
		}

		if ( ! isset( $this->clients[ $client_key ] ) ) {
			$this->clients[ $client_key ] = new DynamoDbClient( $args );
		}

		return $this->clients[ $client_key ];
	}
}
