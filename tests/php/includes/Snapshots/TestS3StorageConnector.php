<?php
/**
 * Tests for the S3StorageConnector class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Snapshots;

use Aws\S3\S3Client;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\ProgressBar\ProgressBarInterface;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Snapshots\S3StorageConnector;
use TenUp\Snapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestS3StorageConnector
 *
 * @package TenUp\Snapshots\Tests\Snapshots
 *
 * @coversDefaultClass \TenUp\Snapshots\Snapshots\S3StorageConnector
 */
class TestS3StorageConnector extends TestCase {

	use PrivateAccess, DirectoryFiltering;

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

		$this->connector = ( new Snapshots() )->get_instance( S3StorageConnector::class );
		$this->set_private_property( $this->connector, 'progress_bar', $this->createMock( ProgressBarInterface::class ) );

		$this->set_up_directory_filtering();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_directory_filtering();
	}

	public function test_constructor() {
		$this->assertInstanceOf( S3StorageConnector::class, $this->connector );
	}

	/** @covers ::get_bucket_name */
	public function test_get_bucket_name() {
		$this->assertEquals( 'wpsnapshots-test-repo', $this->call_private_method( $this->connector, 'get_bucket_name', [ 'test-repo' ] ) );
	}

	/** @covers ::download_snapshot */
	public function test_download_snapshot_get_object_is_caleld_with_correct_args_when_contains_db() {
		$client = $this->getMockBuilder( S3Client::class )
			->disableOriginalConstructor()
			->addMethods( [ 'getObject' ] )
			->getMock();

		$client->expects( $this->once() )
			->method( 'getObject' )
			->with(
				$this->get_object_args_matcher( 'test-project/test-id/data.sql.gz', '/test-id/data.sql.gz' )
			);

		$this->set_private_property( $this->connector, 'clients', [ 'default_test-region' => $client ] );

		$this->connector->download_snapshot(
			'test-id',
			[
				'profile'    => 'default',
				'repository' => 'test-repo',
				'region'     => 'test-region',
				'role_arn'   => '',
			],
			[
				'contains_db'    => true,
				'contains_files' => false,
				'project'        => 'test-project',
				'db_size'        => 1024,
			]
		);
	}

	/** @covers ::download_snapshot */
	public function test_download_snapshot_get_object_is_caleld_with_correct_args_when_contains_files() {
		$client = $this->getMockBuilder( S3Client::class )
			->disableOriginalConstructor()
			->addMethods( [ 'getObject' ] )
			->getMock();

		$client->expects( $this->once() )
			->method( 'getObject' )
			->with(
				$this->get_object_args_matcher( 'test-project/test-id/files.tar.gz', '/test-id/files.tar.gz' )
			);

		$this->set_private_property( $this->connector, 'clients', [ 'default_test-region' => $client ] );

		$this->connector->download_snapshot(
			'test-id',
			[
				'profile'    => 'default',
				'repository' => 'test-repo',
				'region'     => 'test-region',
				'role_arn'   => '',
			],
			[
				'contains_db'    => false,
				'contains_files' => true,
				'project'        => 'test-project',
				'files_size'     => 1024,
			]
		);
	}

	/** @covers ::download_snapshot */
	public function test_download_snapshot_get_object_is_caleld_with_correct_args_when_contains_db_and_files() {
		$client = $this->getMockBuilder( S3Client::class )
			->disableOriginalConstructor()
			->addMethods( [ 'getObject' ] )
			->getMock();

		$client->expects( $this->exactly( 2 ) )
			->method( 'getObject' )
			->withConsecutive(
				[
					$this->get_object_args_matcher( 'test-project/test-id/data.sql.gz', '/test-id/data.sql.gz' ),
				],
				[
					$this->get_object_args_matcher( 'test-project/test-id/files.tar.gz', '/test-id/files.tar.gz' ),
				]
			);

		$this->set_private_property( $this->connector, 'clients', [ 'default_test-region' => $client ] );

		$this->connector->download_snapshot(
			'test-id',
			[
				'profile'    => 'default',
				'repository' => 'test-repo',
				'region'     => 'test-region',
				'role_arn'   => '',
			],
			[
				'contains_db'    => true,
				'contains_files' => true,
				'project'        => 'test-project',
				'db_size'        => 1024,
				'files_size'     => 1024,
			]
		);
	}

	/** @covers ::create_bucket */
	public function test_create_bucket_is_called_with_correct_args() {
		$client = $this->getMockBuilder( S3Client::class )
			->disableOriginalConstructor()
			->addMethods( [ 'createBucket', 'listBuckets' ] )
			->getMock();
		$client->method( 'listBuckets' )->willReturn( [ 'Buckets' => [] ] );

		$client->expects( $this->once() )
			->method( 'createBucket' )
			->with(
				[
					'Bucket'             => 'wpsnapshots-test-repo',
					'LocationConstraint' => 'test-region',
				]
			);

		$this->set_private_property( $this->connector, 'clients', [ 'default_test-region' => $client ] );

		$this->connector->create_bucket(
			[
				'profile'    => 'default',
				'repository' => 'test-repo',
				'region'     => 'test-region',
				'role_arn'   => '',
			]
		);
	}

	/**
	 * @covers ::create_bucket
	 * @covers ::get_bucket_already_exists_message
	 */
	public function test_create_bucket_throws_if_bucket_already_exists() {
		$client = $this->getMockBuilder( S3Client::class )
			->disableOriginalConstructor()
			->addMethods( [ 'createBucket', 'listBuckets' ] )
			->getMock();
		$client->method( 'listBuckets' )->willReturn( [ 'Buckets' => [ [ 'Name' => 'wpsnapshots-test-repo' ] ] ] );

		$client->expects( $this->never() )
			->method( 'createBucket' );

		$this->set_private_property( $this->connector, 'clients', [ 'default_test-region' => $client ] );

		$this->expectException( SnapshotsException::class );
		$this->expectExceptionMessage( 'S3 bucket already exists.' );

		$this->connector->create_bucket(
			[
				'profile'    => 'default',
				'repository' => 'test-repo',
				'region'     => 'test-region',
				'role_arn'   => '',
			]
		);

	}

	/** @covers ::get_client */
	public function test_get_client_returns_client() {
		$client = $this->call_private_method(
			$this->connector,
			'get_client',
			[
				[
					'profile'    => 'default',
					'repository' => 'test-repo',
					'region'     => 'test-region',
					'role_arn'   => '',
				],
			]
		);

		$this->assertInstanceOf( S3Client::class, $client );
	}

	/**
	 * Builds a constraint matching the args passed to S3Client::getObject() for a download.
	 *
	 * @param string $key  Expected object key.
	 * @param string $file Expected file path relative to the snapshots directory.
	 * @return \PHPUnit\Framework\Constraint\Callback
	 */
	private function get_object_args_matcher( string $key, string $file ) {
		return $this->callback(
			function( $args ) use ( $key, $file ) {
				return 'wpsnapshots-test-repo' === $args['Bucket']
					&& $key === $args['Key']
					&& $this->get_directory_path() . $file === $args['SaveAs']
					&& is_callable( $args['@http']['progress'] ?? null );
			}
		);
	}
}
