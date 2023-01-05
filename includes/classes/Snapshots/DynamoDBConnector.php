<?php
/**
 * Amazon Dynamo wrapper functionality
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\{Service, Shared};

/**
 * Class for handling Amazon dynamodb calls
 */
class DynamoDBConnector implements Shared, Service, DBConnectorInterface {

	/**
	 * Searches the database.
	 *
	 * @param  string|array      $query Search query string
	 * @param  AWSAuthentication $aws_authentication Authentication object.
	 * @return array
	 */
	public function search( $query, AWSAuthentication $aws_authentication ) : array {
		$marshaler = new Marshaler();

		$args = [
			'TableName' => 'wpsnapshots-' . $aws_authentication->get_repository(),
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

		$search_scan = $this->get_client( $aws_authentication )->getIterator( 'Scan', $args );

		$instances = [];

		foreach ( $search_scan as $item ) {
			$instances[] = $marshaler->unmarshalItem( (array) $item );
		}

		return $instances;
	}

	/**
	 * Get a snapshot given an id
	 *
	 * @param  string            $id Snapshot ID
	 * @param AWSAuthentication $aws_authentication AWS authentication instance.
	 * @return mixed
	 */
	public function get_snapshot( string $id, AWSAuthentication $aws_authentication ) {
		$result = $this->get_client( $aws_authentication )->getItem(
			[
				'ConsistentRead' => true,
				'TableName'      => 'wpsnapshots-' . $aws_authentication->get_repository(),
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
	 * Provides the client.
	 *
	 * @param  AWSAuthentication $aws_authentication AWS authentication instance.
	 * @return DynamoDbClient
	 *
	 * @throws WPSnapshotsException If no authentication is set.
	 */
	private function get_client( AWSAuthentication $aws_authentication ) : DynamoDbClient {
		return new DynamoDbClient(
			[
				'credentials' => [
					'key'    => $aws_authentication->get_key(),
					'secret' => $aws_authentication->get_secret(),
				],
				'region'      => $aws_authentication->get_region(),
				'version'     => '2012-08-10',
				'csm'         => false,
			]
		);
	}
}
