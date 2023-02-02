<?php
/**
 * Tests for Scrubber.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\WPCLICommands\Create;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsInputValidationException;
use TenUp\WPSnapshots\Log\WPCLILogger;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\SnapshotFiles;
use TenUp\WPSnapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use TenUp\WPSnapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\WPCLI\Prompt;
use TenUp\WPSnapshots\WPCLICommands\Create\Scrubber;
use TenUp\WPSnapshots\WPCLICommands\Create\WPCLIDumper;
use TenUp\WPSnapshots\WPCLICommands\Pull\MultisiteURLReplacer;
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacer;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Class Scrubber
 * 
 * @package TenUp\WPSnapshots\Tests\WPCLICommands\Create
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Create\Scrubber
 */
class TestScrubber extends TestCase {

	use WPCLIMocking, PrivateAccess, DirectoryFiltering;

	/**
	 * Scrubber instance.
	 * 
	 * @var Scrubber
	 */
	protected $scrubber;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_up_wp_cli_mock();
		$this->set_up_directory_filtering();

		$this->scrubber = ( new Plugin() )->get_instance( Scrubber::class );
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
		$this->assertInstanceOf( Scrubber::class, $this->scrubber );
	}

	/**
	 * @covers ::scrub
	 * @covers ::get_dummy_users
	 * @covers ::export_temp_table
	 */
	public function test_scrub() {
		// Create a user.
		$this->factory()->user->create( [
			'user_login' => 'test_user',
			'user_pass'  => 'test_password',
			'user_email' => 'my@coolemail.com',
		] );

		$id = 'test-id';

		$test_user_meta_table_path = '/wpsnapshots-tmp/test-id/data-usermeta.sql';
		$test_user_table_path = '/wpsnapshots-tmp/test-id/data-users.sql';

		// Create file.
		$this->create_file( $test_user_meta_table_path, 'test' );
		$this->create_file( $test_user_table_path, 'test' );

		$this->scrubber->scrub( $id );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			2,
			[
				[
					'db export /wpsnapshots-tmp/test-id/data-users.sql --tables=wp_users_temp',
					[
						'launch' => true,
						'return' => 'all',
						'exit_error' => false,
					],
				],
				[
					'db export /wpsnapshots-tmp/test-id/data-usermeta.sql --tables=wp_usermeta_temp',
					[
						'launch' => true,
						'return' => 'all',
						'exit_error' => false,
					],
				]
			]

		);
	}

	/**
	 * Creates a test sql file.
	 */
	protected function create_file( $path, $contents ) {
		$dir = dirname( $path );

		if ( ! file_exists( $dir ) ) {
			mkdir( $dir, 0777, true );
		}

		file_put_contents( $path, $contents );
	}
}
