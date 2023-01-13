<?php
/**
 * Tests for the S3StorageConnector class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Snapshots;

use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\S3StorageConnector;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestS3StorageConnector
 *
 * @package TenUp\WPSnapshots\Tests\Snapshots
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Snapshots\S3StorageConnector
 */
class TestS3StorageConnector extends TestCase {

	use PrivateAccess;

	/**
	 * S3StorageConnector instance.
	 * 
	 * @var S3StorageConnector
	 */
	private $connector;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->connector = ( new Plugin() )->get_instance( S3StorageConnector::class );
	}

	public function test_constructor() {
		$this->assertInstanceOf( S3StorageConnector::class, $this->connector );
	}

	/** @covers ::get_bucket_name */
	public function test_get_bucket_name() {
		$this->assertEquals( 'wpsnapshots-test-repo', $this->call_private_method( $this->connector, 'get_bucket_name', [ 'test-repo' ] ) );
	}
}
