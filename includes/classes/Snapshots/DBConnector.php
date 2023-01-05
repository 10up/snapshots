<?php
/**
 * Amazon Dynamo wrapper functionality
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\Exception\AwsException;
use stdClass;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\Service;

/**
 * Class for handling Amazon dynamodb calls
 */
class DBConnector implements Service, DBConnectorInterface {

	/**
	 * Instance of DynamoDB client
	 *
	 * @var ?DynamoDbClient
	 */
	public $client;

	/**
	 * AWSAuthentication instance.
	 *
	 * @var ?AWSAuthentication
	 */
	private $configuration;

	/**
	 * Sets configuration.
	 *
	 * @param object $configuration Configuration.
	 */
	public function set_configuration( object $configuration ) {
		$this->configuration = $configuration;
	}

	/**
	 * Gets configuration.
	 *
	 * @return AWSAuthentication
	 *
	 * @throws WPSnapshotsException If no configuration is set.
	 */
	public function get_configuration() : AWSAuthentication {
		if ( ! $this->configuration ) {
			throw new WPSnapshotsException( 'No configuration set.' );
		}

		return $this->configuration;
	}

	/**
	 * Provides the client.
	 *
	 * @return DynamoDbClient
	 *
	 * @throws WPSnapshotsException If no configuration is set.
	 */
	private function get_client() : DynamoDbClient {
		$configuration = $this->get_configuration();

		if ( ! $this->client ) {
			$this->client = new DynamoDbClient(
				[
					'credentials' => [
						'key'    => $configuration->get_key(),
						'secret' => $configuration->get_secret(),
					],
					'region'      => $configuration->get_region(),
					'version'     => '2012-08-10',
					'csm'         => false,
				]
			);
		}

		return $this->client;
	}

	/**
	 * Searches the database.
	 *
	 * @param  string|array $query Search query string
	 * @return array
	 */
	public function search( $query ) : array {
		$marshaler = new Marshaler();

		$args = [
			'TableName' => 'wpsnapshots-' . $this->configuration->get_repository(),
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

		$search_scan = $this->get_client()->getIterator( 'Scan', $args );

		$instances = [];

		foreach ( $search_scan as $item ) {
			$instances[] = $marshaler->unmarshalItem( (array) $item );
		}

		return $instances;
	}
}
