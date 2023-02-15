<?php
/**
 * Tests for WPCLIDBExport.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\WPCLICommands\Create;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use TenUp\Snapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\Snapshots\WPCLICommands\Create\Scrubber;
use TenUp\Snapshots\WPCLICommands\Create\Trimmer;
use TenUp\Snapshots\WPCLICommands\Create\WPCLIDBExport;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestWPCLIDBExport
 * 
 * @package TenUp\Snapshots\Tests\WPCLICommands\Create
 * 
 * @coversDefaultClass \TenUp\Snapshots\WPCLICommands\Create\WPCLIDBExport
 */
class TestWPCLIDBExport extends TestCase {

	use WPCLIMocking, PrivateAccess, DirectoryFiltering;

	/**
	 * WPCLIDBExport instance.
	 * 
	 * @var WPCLIDBExport
	 */
	protected $wpcli_dumper;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_up_wp_cli_mock();
		$this->set_up_directory_filtering();

		$this->wpcli_dumper = ( new Snapshots() )->get_instance( WPCLIDBExport::class );
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
		$this->assertInstanceOf( WPCLIDBExport::class, $this->wpcli_dumper );
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
		$file_path = '/tenup-snapshots-tmp/test-id/data.sql';
		$this->create_file( $file_path );

		$this->wpcli_dumper->dump( $test_id, $test_args );

		// Confirm the command ran.
		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			1,
			[
				[
					'db export /tenup-snapshots-tmp/test-id/data.sql --tables=wp_commentmeta,wp_comments,wp_links,wp_options,wp_postmeta,wp_posts,wp_term_relationships,wp_term_taxonomy,wp_termmeta,wp_terms,',
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
		$this->assertTrue( file_exists( '/tenup-snapshots-tmp/test-id/data.sql.gz' ) );
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
