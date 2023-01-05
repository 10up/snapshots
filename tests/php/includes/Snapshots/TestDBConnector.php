<?php
/**
 * Tests for the DBConnector class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Snapshots\AWSAuthentication;
use TenUp\WPSnapshots\Snapshots\DBConnector;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestDBConnector
 *
 * @package TenUp\WPSnapshots\Tests\Snapshots
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Snapshots\DBConnector
 */
class TestDBConnector extends TestCase {

	use PrivateAccess;

	/**
	 * DBConnector instance.
	 * 
	 * @var DBConnector
	 */
	private $connector;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->connector = new DBConnector();
	}

	public function test_constructor() {
		$this->assertInstanceOf( DBConnector::class, $this->connector );
	}

	/**
	 * @covers ::set_configuration
	 * @covers ::get_configuration
	 */
	public function test_set_get_configuration() {
		$config = $this->createMock( AWSAuthentication::class );

		$this->connector->set_configuration( $config );

		$this->assertEquals( $config, $this->connector->get_configuration() );
	}

	/** @covers ::get_configuration */
	public function test_get_configuration_no_config() {
		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'No configuration set.' );

		$this->connector->get_configuration();
	}

	/** @covers ::get_client */
	public function test_get_client() {
		$this->connector->set_configuration( $this->get_valid_s3_config() );

		$client = $this->call_private_method( $this->connector, 'get_client' );

		$this->assertInstanceOf( DynamoDbClient::class, $client );
	}

	/** @covers ::search */
	public function test_search() {
		$this->connector->set_configuration( $this->get_valid_s3_config() );
		$mock_client = $this->createMock( DynamoDbClient::class ) ;

		$return_array = [];

		// Get the args passed to getIterator method.
		$mock_client->method( 'getIterator' )->willReturnCallback(
			function ( $method, $args ) use ( $return_array ) {
				$this->assertEquals(
					[
						'TableName' => 'wpsnapshots-test-repo',
						'ConditionalOperator' => 'OR',
						'ScanFilter' => [
							'project' => [
								'AttributeValueList' => [
									[
										'S' => 'search term',
									],
								],
								'ComparisonOperator' => 'CONTAINS',
							],
							'id' => [
								'AttributeValueList' => [
									[
										'S' => 'search term',
									],
								],
								'ComparisonOperator' => 'EQ',
							],
						],
					],
					$args
				);
				return $return_array;
			}
		);

		$this->set_private_property( $this->connector, 'client', $mock_client );

		$results = $this->connector->search( 'search term' );

		$this->assertEquals( $return_array, $results );
	}

	private function get_valid_s3_config() {
		return new AWSAuthentication( [
			'repository' => 'test-repo',
			'key'        => 'test-key',
			'secret'     => 'test-secret',
			'region'     => 'us-east-1',
		] );
	}
}
