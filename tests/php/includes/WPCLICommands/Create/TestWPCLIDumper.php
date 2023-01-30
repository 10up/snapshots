<?php
/**
 * Tests for WPCLIDumper.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\WPCLICommands\Create;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsInputValidationException;
use TenUp\WPSnapshots\Log\WPCLILogger;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\SnapshotFiles;
use TenUp\WPSnapshots\Snapshots\Trimmer;
use TenUp\WPSnapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use TenUp\WPSnapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\WPCLI\Prompt;
use TenUp\WPSnapshots\WPCLICommands\Create\Scrubber;
use TenUp\WPSnapshots\WPCLICommands\Create\WPCLIDumper;
use TenUp\WPSnapshots\WPCLICommands\Pull\MultisiteURLReplacer;
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestWPCLIDumper
 * 
 * @package TenUp\WPSnapshots\Tests\WPCLICommands\Create
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Create\WPCLIDumper
 */
class TestWPCLIDumper extends TestCase {

	use WPCLIMocking, PrivateAccess, DirectoryFiltering;

	/**
	 * WPCLIDumper instance.
	 * 
	 * @var WPCLIDumper
	 */
	protected $wpcli_dumper;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_up_wp_cli_mock();
		$this->set_up_directory_filtering();

		$this->wpcli_dumper = ( new Plugin() )->get_instance( WPCLIDumper::class );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
		$this->tear_down_directory_filtering();
	}

	/** @covers ::__construct */
	public function test_constructor() {
		$this->assertInstanceOf( WPCLIDumper::class, $this->wpcli_dumper );
	}


	/**
	 * @covers ::dump
	 * @covers ::run_command
	 * @covers ::scrub
	 */
	public function test_dump() {
		/**
		 * Trimmer mock object
		 * 
		 * @var MockObject $trimmer
		 */
		$trimmer = $this->createMock( Trimmer::class );

		/**
		 * Scrubber mock object
		 * 
		 * @var MockObject $scrubber
		 */
		$scrubber = $this->createMock( Scrubber::class );

		$this->set_private_property( $this->wpcli_dumper, 'trimmer', $trimmer );
		$this->set_private_property( $this->wpcli_dumper, 'scrubber', $scrubber );

		$test_id = 'test-id';
		$test_args = [
			'small' => true,
		];

		$trimmer->expects( $this->once() )
			->method( 'trim' )
			->with(  null );

		$scrubber->expects( $this->once() )
			->method( 'scrub' )
			->with( $test_id );

		// Create test data.sql file.
		$file_path = '/wpsnapshots-tmp/test-id/data.sql';
		$this->create_file( $file_path );

		$this->wpcli_dumper->dump( $test_id, $test_args );

		// Confirm the command ran.
		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			1,
			[
				[
					'db export /wpsnapshots-tmp/test-id/data.sql --tables=wp_commentmeta,wp_comments,wp_links,wp_options,wp_postmeta,wp_posts,wp_term_relationships,wp_term_taxonomy,wp_termmeta,wp_terms,',
					[
						'launch' => true,
						'return' => 'all',
						'exit_error' => false,
					],
				],
			]
		);

		// Confirm the file was deleted.
		$this->assertFalse( file_exists( $file_path ) );

		// Confirm the gz file was created.
		$this->assertTrue( file_exists( '/wpsnapshots-tmp/test-id/data.sql.gz' ) );
	}

	/**
	 * Creates a test sql file.
	 * 
	 * @param string $file_path File path.
	 */
	protected function create_file( $file_path ) {
		$dir = dirname( $file_path );
		if ( ! file_exists( $dir ) ) {
			mkdir( $dir, 0777, true );
		}

		$handle = fopen( $file_path, 'w' );
		fwrite( $handle, 'test' );
		fclose( $handle );
	}
}
