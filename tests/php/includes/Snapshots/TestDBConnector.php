<?php
/**
 * Tests for the DynamoDBConnector class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use PHPUnit\Framework\MockObject\MockObject;
use TenUp\Snapshots\Snapshots\DynamoDBConnector;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestDBConnector
 *
 * @package TenUp\Snapshots\Tests\Snapshots
 *
 * @coversDefaultClass \TenUp\Snapshots\Snapshots\DynamoDBConnector
 */
class TestDBConnector extends TestCase {

	use PrivateAccess;

	/**
	 * DynamoDBConnector instance.
	 *
	 * @var DynamoDBConnector
	 */
	private $connector;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->connector = new DynamoDBConnector();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		unset( $this->connector );
	}

	public function test_constructor() {
		$this->assertInstanceOf( DynamoDBConnector::class, $this->connector );
	}

	/** @covers ::get_client */
	public function test_get_client() {
		$client = $this->call_private_method( $this->connector, 'get_client', [ [ 'profile' => 'default', 'repository' => 'test-repo', 'region' => 'us-west-1' ] ] );

		$this->assertInstanceOf( DynamoDbClient::class, $client );
	}

	/** @covers ::search */
	public function test_search_client_is_called_with_expected_args() {
		/**
		 * DynamoDBClient mock.
		 *
		 * @var MockObject $client
		 */
		$client = $this->createMock( DynamoDbClient::class );
		$client->method( 'getIterator' )->willReturn( [] );

		// Set client as private property.
		$this->set_private_property( $this->connector, 'clients', [ 'default_test-region' => $client ] );

		// Assert that client's getIterator method was called with expected args.
		$client->expects( $this->once() )->method( 'getIterator' )->with(
			'Scan',
			[
				'TableName' => 'wpsnapshots-test-repo',
				'ConditionalOperator' => 'OR',
				'ScanFilter' => [
					'project' => [
						'AttributeValueList' => [ [ 'S' => 'search term' ] ],
						'ComparisonOperator' => 'CONTAINS',
					],
					'id' => [
						'AttributeValueList' => [ [ 'S' => 'search term' ] ],
						'ComparisonOperator' => 'EQ',
					],
				],
			]
		);

		// Call search method.
		$this->connector->search( 'search term', [ 'profile' => 'default', 'repository' => 'test-repo', 'region' => 'us-west-1' ] );
	}

	/** @covers ::get_snapshot */
	public function test_get_snapshot_client_is_called_with_expected_args() {
		/**
		 * DynamoDBClient mock.
		 *
		 * @var MockObject $client
		 */
		$client = $this->getMockBuilder( DynamoDbClient::class )
			->disableOriginalConstructor()
			->addMethods( [ 'getItem' ] )
			->getMock();
		$client->method( 'getItem' )->willReturn( [] );

		// Set client as private property.
		$this->set_private_property( $this->connector, 'clients', [ 'default_test-region' => $client ] );

		// Assert that client's getItem method was called with expected args.
		$client->expects( $this->once() )->method( 'getItem' )->with(
			[
				'ConsistentRead' => true,
				'TableName' => 'wpsnapshots-test-repo',
				'Key' => [
					'id' => [ 'S' => 'snapshot-id' ],
				],
			]
		);

		// Call get_snapshot method.
		$this->connector->get_snapshot( 'snapshot-id', [ 'profile' => 'default', 'repository' => 'test-repo', 'region' => 'us-west-1' ] );
	}

	/** @covers ::create_tables */
	public function test_create_tables_client_is_called_with_expected_args() {
		/**
		 * DynamoDBClient mock.
		 *
		 * @var MockObject $client
		 */
		$client = $this->getMockBuilder( DynamoDbClient::class )
			->disableOriginalConstructor()
			->addMethods( [ 'createTable' ] )
			->onlyMethods( [ 'waitUntil' ] )
			->getMock();
		$client->method( 'createTable' )->willReturn( [] );
		$client->method( 'waitUntil' )->willReturn( [] );

		// Set client as private property.
		$this->set_private_property( $this->connector, 'clients', [ 'default_test-region' => $client ] );

		// Assert that client's createTable method was called with expected args.
		$client->expects( $this->once() )->method( 'createTable' )->with(
			[
				'AttributeDefinitions' => [
					[
						'AttributeName' => 'id',
						'AttributeType' => 'S',
					],
				],
				'KeySchema' => [
					[
						'AttributeName' => 'id',
						'KeyType' => 'HASH',
					],
				],
				'ProvisionedThroughput' => [
					'ReadCapacityUnits' => 10,
					'WriteCapacityUnits' => 20,
				],
				'TableName' => 'wpsnapshots-test-repo',
			]
		);

		$client->expects( $this->once() )->method( 'waitUntil' )->with(
			'TableExists',
			[
				'TableName' => 'wpsnapshots-test-repo',
			]
		);

		// Call create_tables method.
		$this->connector->create_tables( [ 'profile' => 'default', 'repository' => 'test-repo', 'region' => 'test-region', 'role_arn' => '' ] );
	}
}
