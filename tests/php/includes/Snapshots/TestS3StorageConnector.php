<?php
/**
 * Tests for the S3StorageConnector class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Snapshots;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Snapshots\AWSAuthentication;
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

		$this->connector = new S3StorageConnector();
	}

	public function test_constructor() {
		$this->assertInstanceOf( S3StorageConnector::class, $this->connector );
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

	/**
	 * @covers ::test_connection
	 * @covers ::configure_client
	 */
	public function test_test_connection() {
		$config = $this->get_valid_s3_config();

		$this->connector->set_configuration( $config );

		add_filter(
			'wpsnapshots_s3_test_callable',
			function () {
				return '__return_true';
			}
		);

		$this->assertTrue( $this->connector->test_connection() );

		remove_all_filters( 'wpsnapshots_s3_test_callable' );
	}

	/**
	 * @covers ::configure_client
	 */
	public function test_invalid_client_throws() {
		$config = new AWSAuthentication( [] );

		$this->connector->set_configuration( $config );

		$this->expectException( WPSnapshotsException::class );

		$this->call_private_method( $this->connector, 'configure_client' );
	}

	/** @covers ::test_connection */
	public function test_test_connection_throws_when_invalid_callable() {
		$config = $this->get_valid_s3_config();

		$this->connector->set_configuration( $config );

		add_filter(
			'wpsnapshots_s3_test_callable',
			function () {
				return '';
			}
		);

		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Invalid test callable.' );

		$this->connector->test_connection();
	}

	/** @covers ::test_connection */
	public function test_test_connection_throws_when_callable_throws() {
		$config = $this->get_valid_s3_config();

		$this->connector->set_configuration( $config );

		add_filter(
			'wpsnapshots_s3_test_callable',
			function () {
				throw new WPSnapshotsException( 'Test exception.' );
			}
		);

		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Test exception.' );

		$this->connector->test_connection();
	}

	/** @covers ::get_bucket_name */
	public function test_get_bucket_name() {
		$config = $this->get_valid_s3_config();

		$this->connector->set_configuration( $config );

		$this->assertEquals( 'wpsnapshots-test-repo', $this->call_private_method( $this->connector, 'get_bucket_name' ) );
	}

	private function get_valid_s3_config() {
		return new AWSAuthentication( [
			'repository' => 'test-repo',
			'key'        => 'test-key',
			'secret'     => 'test-secret',
			'region'     => 'test-region',
		] );
	}

}
