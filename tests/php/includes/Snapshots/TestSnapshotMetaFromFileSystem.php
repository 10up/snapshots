<?php
/**
 * Tests for the SnapshotMetaFromFileSystem class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Snapshots;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Snapshots\DynamoDBConnector;
use TenUp\Snapshots\Snapshots\SnapshotMeta;
use TenUp\Snapshots\Snapshots\SnapshotMetaFromFileSystem;
use TenUp\Snapshots\SnapshotsDirectory;
use TenUp\Snapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestSnapshotMetaFromFileSystem
 *
 * @package TenUp\Snapshots\Tests\Snapshots
 *
 * @coversDefaultClass \TenUp\Snapshots\Snapshots\SnapshotMetaFromFileSystem
 */
class TestSnapshotMetaFromFileSystem extends TestCase {

	use PrivateAccess, DirectoryFiltering;

	/**
	 * Test instance
	 *
	 * @var SnapshotMetaFromFileSystem
	 */
	private $meta;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->meta = ( new Snapshots() )->get_instance( SnapshotMetaFromFileSystem::class );
		$this->set_up_directory_filtering();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_directory_filtering();
	}

	/**
	 * @covers ::__construct
	 * @covers \TenUp\Snapshots\Snapshots\SnapshotMeta::__construct
	 */
	public function test_constructor() {
		$this->assertInstanceOf( SnapshotMetaFromFileSystem::class, $this->meta );
		$this->assertInstanceOf( SnapshotMeta::class, $this->meta );

		$this->assertInstanceOf( DynamoDBConnector::class, $this->get_private_property( $this->meta, 'db' ) );
		$this->assertInstanceOf( SnapshotsDirectory::class, $this->get_private_property( $this->meta, 'snapshot_files' ) );
	}

	/**
	 * @covers ::save_local
	 * @covers ::get_local
	 */
	public function test_save_and_getlocal() {
		$this->meta->save_local( 'test-id', [ 'test' => 'data', 'repository' => '10up', 'contains_files' => true ] );

		$this->assertFileExists( $this->get_directory_path() . '/test-id/meta.json' );

		$this->assertEquals( '{"test":"data","repository":"10up","contains_files":true}', file_get_contents( $this->get_directory_path() . '/test-id/meta.json' ) );
		$this->assertGreaterThan( 0, filesize( $this->get_directory_path() . '/test-id/meta.json' ) );

		$this->assertEquals( [ 'test' => 'data', 'repository' => '10up', 'contains_files' => true ], $this->meta->get_local( 'test-id', '10up' ) );
	}

	/** @covers ::get_remote */
	public function test_get_remote_when_snapshot_is_empty() {
		/**
		 * DynamoDBClient mock.
		 *
		 * @var MockObject|DynamoDBConnector $mock_db
		 */
		$mock_db = $this->getMockBuilder( DynamoDBConnector::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_db->method( 'get_snapshot' )
			->willReturn( [] );

		$this->set_private_property( $this->meta, 'db', $mock_db );

		$this->assertEquals( [], $this->meta->get_remote( 'test-id', 'test-repo', 'test-region' ) );
	}

	/** @covers ::get_remote */
	public function test_get_remote_when_snapshot_is_not_empty() {
		/**
		 * DynamoDBClient mock.
		 *
		 * @var MockObject|DynamoDBConnector $mock_db
		 */
		$mock_db = $this->getMockBuilder( DynamoDBConnector::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_db->method( 'get_snapshot' )
			->willReturn( [ 'test' => 'data' ] );

		$this->set_private_property( $this->meta, 'db', $mock_db );

		$this->assertEquals( [ 'test' => 'data', 'contains_files' => true, 'contains_db' => true, 'repository' => 'test-repo' ], $this->meta->get_remote( 'test-id', 'test-repo', 'test-region' ) );
	}

	/** @covers ::get_remote */
	public function test_get_remote_when_snapshot_is_not_empty_and_contains_files_and_contains_db_are_false() {
		/**
		 * DynamoDBClient mock.
		 *
		 * @var MockObject|DynamoDBConnector $mock_db
		 */
		$mock_db = $this->getMockBuilder( DynamoDBConnector::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_db->method( 'get_snapshot' )
			->willReturn( [ 'test' => 'data', 'contains_files' => false, 'contains_db' => false ] );

		$this->set_private_property( $this->meta, 'db', $mock_db );

		$this->assertEquals( [ 'test' => 'data', 'contains_files' => false, 'contains_db' => false, 'repository' => 'test-repo' ], $this->meta->get_remote( 'test-id', 'test-repo', 'test-region' ) );
	}

	/** @covers ::generate */
	public function test_generate() {
		global $wp_version;

		$this->meta->generate(
			'test-id',
			[
				'author' => [
					'name' => 'Test Author',
					'email' => 'e@ma.il',
				],
				'repository' => 'test-repo',
				'description' => 'Test description',
				'project' => 'test-project',
				'contains_files' => true,
				'contains_db' => true,
			]
		);

		$this->assertFileExists( $this->get_directory_path() . '/test-id/meta.json' );

		$this->assertEquals(
			[
				'author' => [
					'name' => 'Test Author',
					'email' => 'e@ma.il',
				],
				'repository' => 'test-repo',
				'description' => 'Test description',
				'project' => 'test-project',
				'contains_files' => true,
				'contains_db' => true,
				'multisite' => false,
				'subdomain_install' => false,
				'domain_current_site' => false,
				'path_current_site' => false,
				'site_id_current_site' => false,
				'blog_id_current_site' => false,
				'wp_version' => $wp_version,
				'sites' => [
					[
						'site_url' => home_url(),
						'home_url' => home_url(),
						'blogname' => 'Test Blog',
					]
				],
				'table_prefix' => 'wp_',
				'db_size' => 0,
				'files_size' => 0,
			],
			json_decode( file_get_contents( $this->get_directory_path() . '/test-id/meta.json' ), true )
		);
	}

	/** @covers ::generate */
	public function test_generate_when_multisite() {
		global $wp_version;

		$this->meta->generate(
			'test-id-2',
			[
				'author' => [
					'name' => 'Test Author',
					'email' => 'e@ma.il',
				],
				'repository' => 'test-repo',
				'description' => 'Test description',
				'project' => 'test-project',
				'contains_files' => true,
				'contains_db' => true,
			],
			true,
		);

		$this->assertFileExists( $this->get_directory_path() . '/test-id-2/meta.json' );

		$this->assertEquals(
			[
				'author' => [
					'name' => 'Test Author',
					'email' => 'e@ma.il',
				],
				'repository' => 'test-repo',
				'description' => 'Test description',
				'project' => 'test-project',
				'contains_files' => true,
				'contains_db' => true,
				'multisite' => true,
				'subdomain_install' => false,
				'domain_current_site' => false,
				'path_current_site' => false,
				'site_id_current_site' => false,
				'blog_id_current_site' => false,
				'wp_version' => $wp_version,
				'sites' => [],
				'table_prefix' => 'wp_',
				'db_size' => 0,
				'files_size' => 0,
			],
			json_decode( file_get_contents( $this->get_directory_path() . '/test-id-2/meta.json' ), true )
		);
	}
}
