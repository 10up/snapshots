<?php
/**
 * Tests for the DynamoDBConnector class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use TenUp\WPSnapshots\Snapshots\AWSAuthentication;
use TenUp\WPSnapshots\Snapshots\DynamoDBConnector;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestDBConnector
 *
 * @package TenUp\WPSnapshots\Tests\Snapshots
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Snapshots\DynamoDBConnector
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

	public function test_constructor() {
		$this->assertInstanceOf( DynamoDBConnector::class, $this->connector );
	}

	/** @covers ::get_client */
	public function test_get_client() {
		$client = $this->call_private_method( $this->connector, 'get_client', [ $this->get_valid_s3_config() ] );

		$this->assertInstanceOf( DynamoDbClient::class, $client );
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
