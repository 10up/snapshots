<?php
/**
 * Tests covering the Pull command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\FileSystem;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\SnapshotMeta;
use TenUp\WPSnapshots\WPSnapshotsDirectory;
use TenUp\WPSnapshots\Tests\Fixtures\{CommandTests, DirectoryFiltering, PrivateAccess, WPCLIMocking};
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\WPCLI\WPCLICommand;
use TenUp\WPSnapshots\WPCLICommands\Pull;
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacer;
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacerFactory;
use WP_Filesystem_Base;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

use function TenUp\WPSnapshots\Utils\wpsnapshots_wp_content_dir;

/**
 * Class TestPull
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Pull
 */
class TestPull extends TestCase {

	use PrivateAccess, WPCLIMocking, CommandTests, DirectoryFiltering;

	/**
	 * Pull instance.
	 * 
	 * @var Pull
	 */
	private $command;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->command = ( new Plugin() )->get_instance( Pull::class );

		$this->set_up_wp_cli_mock();

		$this->command->set_args( [ 'test-id' ] );
		$this->set_up_directory_filtering();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
		$this->tear_down_directory_filtering();
	}

	/**
	 * Test that the command instance extends WPCLICommand.
	 * 
	 * @covers ::__construct
	 */
	public function test_command_instance() {
		$this->assertInstanceOf( WPCLICommand::class, $this->command );
		$this->test_command_tests();
	}

	/** @covers ::get_command */
	public function test_get_command() {
		$this->assertEquals( 'pull', $this->call_private_method( $this->command, 'get_command' ) );
	}

	/**
	 * @covers ::get_meta
	 * @covers ::set_up_meta
	 * @covers ::get_id
	 * @covers ::get_repository_name
	 * @covers ::get_assoc_arg
	 */
	public function test_get_meta_when_both_local_and_remote_are_empty() {
		/**
		 * Mock the SnapshotMeta class.
		 * 
		 * @var MockObject $snapshot_meta_mock
		 */
		$snapshot_meta_mock = $this->createMock( SnapshotMeta::class );
		$snapshot_meta_mock->method( 'get_remote' )->willReturn( [] );
		$snapshot_meta_mock->method( 'get_local' )->willReturn( [] );

		$this->command->set_assoc_args( [ 'region' => 'test-region', 'repository' => 'test-repository' ] );

		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Snapshot does not exist.' );

		$this->set_private_property( $this->command, 'snapshot_meta', $snapshot_meta_mock );

		$snapshot_meta_mock->expects( $this->once() )->method( 'get_remote' )
			->with( 'test-id', 'test-repository', 'test-region' );
		$snapshot_meta_mock->expects( $this->once() )->method( 'get_local' )
			->with( 'test-id', 'test-repository' );

		$this->call_private_method( $this->command, 'get_meta' );
	}

	/**
	 * @covers ::get_meta
	 * @covers ::set_up_meta
	 * @covers ::get_id
	 * @covers ::get_should_download
	 */
	public function test_get_meta_when_local_is_empty() {
		$test_remote_meta = [ 'contains_files' => true, 'contains_db' => true ];

		/**
		 * Mock the SnapshotMeta class.
		 * 
		 * @var MockObject $snapshot_meta_mock
		 */
		$snapshot_meta_mock = $this->createMock( SnapshotMeta::class );
		$snapshot_meta_mock->method( 'get_remote' )->willReturn( $test_remote_meta );
		$snapshot_meta_mock->method( 'get_local' )->willReturn( [] );

		$this->command->set_assoc_args( [ 'region' => 'test-region', 'repository' => 'test-repository' ] );

		$this->set_private_property( $this->command, 'snapshot_meta', $snapshot_meta_mock );

		$snapshot_meta_mock->expects( $this->once() )->method( 'get_remote' );
		$snapshot_meta_mock->expects( $this->once() )->method( 'get_local' );

		$this->assertEquals( $test_remote_meta, $this->call_private_method( $this->command, 'get_meta' ) );
		// Run set_up_meta a second time to confirm mocked methods are not called again.
		$this->call_private_method( $this->command, 'set_up_meta' );

		$this->assertTrue( $this->call_private_method( $this->command, 'get_should_download' ) );
	}

	/**
	 * @covers ::set_up_meta
	 * @covers ::get_id
	 * @covers ::get_should_download
	 */
	public function test_get_meta_when_remote_is_empty() {
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true ];

		/**
		 * Mock the SnapshotMeta class.
		 * 
		 * @var MockObject $snapshot_meta_mock
		 */
		$snapshot_meta_mock = $this->createMock( SnapshotMeta::class );
		$snapshot_meta_mock->method( 'get_remote' )->willReturn( [] );
		$snapshot_meta_mock->method( 'get_local' )->willReturn( $test_local_meta );

		$this->command->set_assoc_args( [ 'region' => 'test-region', 'repository' => 'test-repository' ] );

		$this->set_private_property( $this->command, 'snapshot_meta', $snapshot_meta_mock );

		$snapshot_meta_mock->expects( $this->once() )->method( 'get_remote' );
		$snapshot_meta_mock->expects( $this->once() )->method( 'get_local' );

		$this->assertFalse( $this->call_private_method( $this->command, 'get_should_download' ) );
		// Run set_up_meta a second time to confirm mocked methods are not called again.
		$this->call_private_method( $this->command, 'set_up_meta' );

		$this->assertEquals( $test_local_meta, $this->call_private_method( $this->command, 'get_meta' ) );
	}

	/**
	 * @covers ::get_meta
	 * @covers ::set_up_meta
	 * @covers ::get_id
	 * @covers ::get_should_download
	 */
	public function test_get_meta_when_both_local_and_remote_are_not_empty() {
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true ];
		$test_remote_meta = [ 'contains_files' => true, 'contains_db' => true ];

		/**
		 * Mock the SnapshotMeta class.
		 * 
		 * @var MockObject $snapshot_meta_mock
		 */
		$snapshot_meta_mock = $this->createMock( SnapshotMeta::class );
		$snapshot_meta_mock->method( 'get_remote' )->willReturn( $test_remote_meta );
		$snapshot_meta_mock->method( 'get_local' )->willReturn( $test_local_meta );

		$this->command->set_assoc_args( [ 'region' => 'test-region', 'repository' => 'test-repository', 'overwrite_local_copy' => true ] );

		$this->set_private_property( $this->command, 'snapshot_meta', $snapshot_meta_mock );

		$snapshot_meta_mock->expects( $this->once() )->method( 'get_remote' );
		$snapshot_meta_mock->expects( $this->once() )->method( 'get_local' );

		$this->assertEquals( $test_remote_meta, $this->call_private_method( $this->command, 'get_meta' ) );
		// Run set_up_meta a second time to confirm mocked methods are not called again.
		$this->call_private_method( $this->command, 'set_up_meta' );

		$this->assertTrue( $this->call_private_method( $this->command, 'get_should_download' ) );
	}

	/** @covers ::set_up_meta */
	public function test_set_up_meta_throws_when_contains_files_and_contains_db_are_not_set() {
		$test_local_meta = [ 'id' => 'test-id' ];
		$test_remote_meta = [ 'id' => 'test-id' ];

		/**
		 * Mock the SnapshotMeta class.
		 * 
		 * @var MockObject $snapshot_meta_mock
		 */
		$snapshot_meta_mock = $this->createMock( SnapshotMeta::class );
		$snapshot_meta_mock->method( 'get_remote' )->willReturn( $test_remote_meta );
		$snapshot_meta_mock->method( 'get_local' )->willReturn( $test_local_meta );

		$this->command->set_assoc_args( [ 'region' => 'test-region', 'repository' => 'test-repository' ] );

		$this->set_private_property( $this->command, 'snapshot_meta', $snapshot_meta_mock );

		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Snapshot is not valid.' );

		$this->call_private_method( $this->command, 'set_up_meta' );
	}

	/** @covers ::get_should_update_wp */
	public function test_get_should_update_wp() {
		$remote_meta = [ 'contains_files' => true, 'contains_db' => true, 'wp_version' => '5.0' ];

		/**
		 * Mock the SnapshotMeta class.
		 * 
		 * @var MockObject $snapshot_meta_mock
		 */
		$snapshot_meta_mock = $this->createMock( SnapshotMeta::class );
		$snapshot_meta_mock->method( 'get_remote' )->willReturn( $remote_meta );
		$snapshot_meta_mock->method( 'get_local' )->willReturn( [] );

		$this->set_private_property( $this->command, 'snapshot_meta', $snapshot_meta_mock );
		$this->command->set_assoc_arg( 'repository', '10up' );

		$this->assertFalse( $this->call_private_method( $this->command, 'get_should_update_wp' ) );
	}

	/** @covers ::get_skip_table_search_replace */
	public function test_get_skip_table_search_replace() {
		$this->command->set_assoc_args( [ 'skip_table_search_replace' => 'table1,table2' ] );

		$this->assertEquals( [ 'table1', 'table2' ], $this->call_private_method( $this->command, 'get_skip_table_search_replace' ) );

		$this->command->set_assoc_args( [] );

		$this->assertEquals( [], $this->call_private_method( $this->command, 'get_skip_table_search_replace' ) );
	}

	/** @covers ::download_snapshot */
	public function test_download_snapshot() {
		$this->command->set_assoc_args( [ 'region' => 'test-region', 'repository' => 'test-repository' ] );

		$this->call_private_method( $this->command, 'download_snapshot' );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			1,
			[
				[
					'wpsnapshots download test-id --quiet --repository=test-repository --region=test-region --include_db --include_files', [ 'launch' => true, 'exit_error' => false, 'return' => 'all' ],
				],
			]
		);

		$this->get_wp_cli_mock()->assertMethodCalled(
			'line',
			1,
			[
				[ 'Snapshot downloaded.' ],
			]
		);
	}

	/** @covers ::pull_db */
	public function test_pull_db() {
		/**
		 * Mock the snapshots_filesystem variable.
		 * 
		 * @var MockObject|WPSnapshotsDirectory $snapshots_filesystem_mock
		 */
		$snapshots_filesystem_mock = $this->createMock( WPSnapshotsDirectory::class );
		$snapshots_filesystem_mock->method( 'get_file_path' )->willReturn( 'test' );

		$this->set_private_property( $this->command, 'snapshots_filesystem', $snapshots_filesystem_mock );

		/**
		 * Mock WP_File_System
		 * 
		 * @var MockObject|WP_Filesystem_Base $wp_filesystem_mock
		 */
		$wp_filesystem_mock = $this->createMock( WP_Filesystem_Base::class );

		/**
		 * Mock the filesystem variable.
		 * 
		 * @var MockObject|FileSystem $filesystem
		 */
		$filesystem = $this->createMock( FileSystem::class );
		$filesystem->method( 'get_wp_filesystem' )->willReturn( $wp_filesystem_mock );

		$filesystem->expects( $this->once() )
			->method( 'unzip_file' )
			->with( 'test', 'test' );

		$filesystem->expects( $this->once() )
			->method( 'get_wp_filesystem' );

		$this->set_private_property( $this->command, 'filesystem', $filesystem );

		$this->call_private_method( $this->command, 'pull_db' );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			1,
			[
				[
					'db import test --quiet --skip-themes --skip-plugins --skip-packages', [ 'launch' => true, 'return' => 'all', 'exit_error' => false ],
				],
			]
		);

		$this->get_wp_cli_mock()->assertMethodCalled(
			'line',
			2,
			[
				[ 'Importing database...' ],
				[ 'Database imported.' ],
			]
		);
	}

	/** @covers ::rename_tables */
	public function test_rename_tables() {
		// Set up meta.
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true, 'table_prefix' => 'test_' ];

		$this->command->set_assoc_args( [ 'region' => 'test-region', 'repository' => 'test-repository' ] );

		$this->mock_snapshot_meta( $test_local_meta );

		/**
		 * Mock the Database class.
		 * 
		 * @var MockObject $database_mock
		 */
		$database_mock = $this->createMock( Database::class );
		$database_mock->method( 'get_tables' )->willReturn( [ 'test_table1', 'test_table2' ] );
		$database_mock->method( 'get_blog_prefix' )->willReturn( 'wp_' );

		// Expect the database rename_table method to be called twice.
		$database_mock->expects( $this->exactly( 2 ) )
			->method( 'rename_table' )
			->withConsecutive(
				[ 'test_table1', 'wp_table1' ],
				[ 'test_table2', 'wp_table2' ]
			);

		$this->set_private_property( $this->command, 'wordpress_database', $database_mock );

		$this->call_private_method( $this->command, 'rename_tables' );
	}

	/** @covers ::update_wp */
	public function test_update_wp() {
		$this->call_private_method( $this->command, 'update_wp', [ '4.9.8' ] );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			1,
			[
				[
					'core update --version=4.9.8 --force --quiet --skip-themes --skip-plugins --skip-packages', [ 'launch' => true, 'return' => 'all', 'exit_error' => false ]
				],
			]
		);

		$this->get_wp_cli_mock()->assertMethodCalled(
			'line',
			2,
			[
				[ 'Updating WordPress to version 4.9.8...' ],
				[ 'WordPress updated.' ],
			]
		);
	}

	/** @covers ::pull_files */
	public function test_pull_files_with_no_errors() {

		/**
		 * Mock snapshot_filesystem.
		 * 
		 * @var MockObject $snapshots_filesystem_mock
		 */
		$snapshots_filesystem_mock = $this->createMock( WPSnapshotsDirectory::class );
		$snapshots_filesystem_mock->method( 'unzip_snapshot_files' )->willReturn( [] );

		$snapshots_filesystem_mock->expects( $this->once() )
			->method( 'unzip_snapshot_files' )
			->with( 'test-id', wpsnapshots_wp_content_dir() );

		$this->set_private_property( $this->command, 'snapshots_filesystem', $snapshots_filesystem_mock );

		$this->call_private_method( $this->command, 'pull_files' );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'line',
			2,
			[
				[ 'Pulling files and replacing /wp-content. This could take a while...' ],
				[ 'Files pulled.' ],
			]
		);
	}

	/** @covers ::pull_files */
	public function test_pull_files_with_errors() {

		/**
		 * Mock snapshot_filesystem.
		 * 
		 * @var MockObject $snapshots_filesystem_mock
		 */
		$snapshots_filesystem_mock = $this->createMock( WPSnapshotsDirectory::class );
		$snapshots_filesystem_mock->method( 'unzip_snapshot_files' )->willReturn( [ 'test-error' ] );

		$this->set_private_property( $this->command, 'snapshots_filesystem', $snapshots_filesystem_mock );

		$this->call_private_method( $this->command, 'pull_files' );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'line',
			4,
			[
				[ 'Pulling files and replacing /wp-content. This could take a while...' ],
				[ 'There were errors pulling files:' ],
				[ 'test-error' ],
				[ 'Files pulled.' ],
			]
		);
	}

	/** @covers ::activate_this_plugin */
	public function test_activate_this_plugin() {
		$this->call_private_method( $this->command, 'activate_this_plugin' );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			1,
			[
				[
					'plugin activate snapshots-command --skip-themes --skip-plugins --skip-packages', [ 'launch' => true, 'return' => 'all', 'exit_error' => false ]
				],
			]
		);
	}

	/** @covers ::replace_urls */
	public function test_replace_urls_single_site() {
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true, 'multisite' => false ];

		$this->command->set_assoc_args( [ 'region' => 'test-region', 'repository' => 'test-repository' ] );

		$this->mock_snapshot_meta( $test_local_meta );

		/**
		 * Mock URLReplacerFactory.
		 * 
		 * @var MockObject $url_replacer_factory_mock
		 */
		$url_replacer_factory_mock = $this->createMock( URLReplacerFactory::class );

		/**
		 * Mock URLReplacer.
		 * 
		 * @var MockObject $url_replacer_mock
		 */
		$url_replacer_mock = $this->createMock( URLReplacer::class );
		$url_replacer_factory_mock->method( 'get' )->willReturn( $url_replacer_mock );

		$this->set_private_property( $this->command, 'url_replacer_factory', $url_replacer_factory_mock );

		$url_replacer_factory_mock->expects( $this->once() )
			->method( 'get' )
			->with(
				'single',
				$test_local_meta,
				[],
				[],
				false,
				null
			);

		$url_replacer_mock->expects( $this->once() )
			->method( 'replace_urls' );

		$this->call_private_method( $this->command, 'replace_urls' );
	}

	/** @covers ::replace_urls */
	public function test_replace_urls_multisite() {
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true, 'multisite' => true ];

		$this->command->set_assoc_args( [ 'region' => 'test-region', 'repository' => 'test-repository', 'skip_table_search_replace' => 'table1,table2', 'main_domain' => 'test-domain' ] );

		$this->mock_snapshot_meta( $test_local_meta );

		/**
		 * Mock URLReplacerFactory.
		 * 
		 * @var MockObject $url_replacer_factory_mock
		 */
		$url_replacer_factory_mock = $this->createMock( URLReplacerFactory::class );

		/**
		 * Mock URLReplacer.
		 * 
		 * @var MockObject $url_replacer_mock
		 */
		$url_replacer_mock = $this->createMock( URLReplacer::class );
		$url_replacer_factory_mock->method( 'get' )->willReturn( $url_replacer_mock );

		$this->set_private_property( $this->command, 'url_replacer_factory', $url_replacer_factory_mock );

		$url_replacer_factory_mock->expects( $this->once() )
			->method( 'get' )
			->with(
				'multi',
				$test_local_meta,
				[],
				[ 'table1', 'table2' ],
				false,
				'test-domain'
			);

		$url_replacer_mock->expects( $this->once() )
			->method( 'replace_urls' );

		$this->call_private_method( $this->command, 'replace_urls' );
	}

	/** @covers ::create_wpsnapshots_user */
	public function test_create_snapshots_user() {

		// Assert user does not exist.
		$this->assertFalse( get_user_by( 'login', 'wpsnapshots' ) );

		$this->call_private_method( $this->command, 'create_wpsnapshots_user', [ false ] );

		// Assert user exists.
		$this->assertNotFalse( get_user_by( 'login', 'wpsnapshots' ) );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'line',
			1,
			[
				[ 'Creating wpsnapshots user...' ],
			]
		);
	}

	/** @covers ::get_site_mapping */
	public function test_get_site_mapping_when_passed_as_string() {
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true, 'multisite' => true ];

		$this->mock_snapshot_meta( $test_local_meta );

		$to_encode = [
			[
				'old_url' => 'http://old-url.com',
				'new_url' => 'http://new-url.com',
				'blog_id' => 77,
			],
			[
				'old_url' => 'http://old-url2.com',
				'new_url' => 'http://new-url2.com',
				'blog_id' => 99,
			],
		];

		$this->command->set_assoc_arg( 'site_mapping', json_encode( $to_encode ) );
		$this->command->set_assoc_arg( 'repository', '10up' );

		$this->assertEquals(
			[
				77 => $to_encode[0],
				99 => $to_encode[1],
			],
			$this->call_private_method( $this->command, 'get_site_mapping' )
		);
	}

	/** @covers ::get_site_mapping */
	public function test_get_site_mapping_when_passed_as_file() {
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true, 'multisite' => true ];

		$this->mock_snapshot_meta( $test_local_meta );

		$to_encode = [
			[
				'old_url' => 'http://old-url.com',
				'new_url' => 'http://new-url.com',
			],
			[
				'old_url' => 'http://old-url2.com',
				'new_url' => 'http://new-url2.com',
			],
		];

		$filename = tempnam( sys_get_temp_dir(), 'test' );
		file_put_contents( $filename, json_encode( $to_encode ) );

		$this->command->set_assoc_arg( 'site_mapping', $filename );
		$this->command->set_assoc_arg( 'repository', '10up' );

		$this->assertEquals(
			[
				$to_encode[0],
				$to_encode[1],
			],
			$this->call_private_method( $this->command, 'get_site_mapping' )
		);

		unlink( $filename );
	}

	/** @covers ::get_site_mapping */
	public function test_get_site_mapping_with_single_site() {
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true, 'multisite' => false ];

		$to_encode = [
			[
				'old_url' => 'http://old-url.com',
				'new_url' => 'http://new-url.com',
				'blog_id' => 77,
			],
		];

		$this->command->set_assoc_arg( 'site_mapping', json_encode( $to_encode ) );
		$this->command->set_assoc_arg( 'repository', '10up' );

		$this->mock_snapshot_meta( $test_local_meta );

		$this->assertEquals(
			[
				$to_encode[0],
			],
			$this->call_private_method( $this->command, 'get_site_mapping' )
		);
	}

	/** @covers ::needs_multisite_constants_update */
	public function test_needs_multisite_constants_update() {
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true, 'multisite' => true ];

		$this->mock_snapshot_meta( $test_local_meta );

		$this->assertTrue( $this->call_private_method( $this->command, 'needs_multisite_constants_update' ) );
	}

	/** @covers ::get_main_domain */
	public function test_get_main_domain() {
		$test_local_meta = [ 'contains_files' => true, 'contains_db' => true, 'multisite' => true ];

		$this->command->set_assoc_arg( 'main_domain', 'http://example.org' );

		$this->mock_snapshot_meta( $test_local_meta );

		$this->assertEquals( 'http://example.org', $this->call_private_method( $this->command, 'get_main_domain' ) );
	}

	/** @covers ::get_main_domain */
	public function test_get_main_domain_from_meta() {
		$test_local_meta = [
			'contains_files' => true,
			'contains_db' => true,
			'multisite' => true,
			'domain_current_site' => 'http://example.org',
		];

		$this->command->set_assoc_arg( 'repository', '10up' );

		$this->mock_snapshot_meta( $test_local_meta );

		$this->call_private_method( $this->command, 'get_main_domain' );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'readline',
			1,
			[
				[ 'Main domain (defaults to main domain in the snapshot: http://example.org): ' ],
			]
		);
	}

	/** @covers ::get_main_domain */
	public function test_get_main_domain_from_meta_with_no_domain() {
		$test_local_meta = [
			'contains_files' => true,
			'contains_db' => true,
			'multisite' => true,
		];

		$this->command->set_assoc_arg( 'repository', '10up' );

		$this->mock_snapshot_meta( $test_local_meta );

		$this->call_private_method( $this->command, 'get_main_domain' );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'readline',
			1,
			[
				[ 'Main domain (mysite.test for example): ' ],
			]
		);
	}

	private function mock_snapshot_meta( $meta = [ 'contains_files' => true, 'contains_db' => true ] ) {
		$meta = array_merge(
			[
				'contains_files' => true,
				'contains_db' => true,
			],
			$meta
		);

		/**
		 * Mock snapshot_meta.
		 * 
		 * @var MockObject $snapshot_meta_mock
		 */
		$snapshot_meta_mock = $this->createMock( SnapshotMeta::class );
		$snapshot_meta_mock->method( 'get_remote' )->willReturn( [] );
		$snapshot_meta_mock->method( 'get_local' )->willReturn( $meta );

		$this->set_private_property( $this->command, 'snapshot_meta', $snapshot_meta_mock );
	}
}
