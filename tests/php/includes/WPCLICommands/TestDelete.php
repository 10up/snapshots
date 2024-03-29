<?php
/**
 * Tests covering the Delete command class.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Commands;

use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Tests\Fixtures\{CommandTests, PrivateAccess, WPCLIMocking};
use TenUp\Snapshots\WPCLI\WPCLICommand;
use TenUp\Snapshots\WPCLICommands\Delete;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestDelete
 *
 * @package TenUp\Snapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\Snapshots\WPCLICommands\Delete
 */
class TestDelete extends TestCase {

	use PrivateAccess, WPCLIMocking, CommandTests;

	/**
	 * Delete instance.
	 * 
	 * @var Delete
	 */
	private $command;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->command = ( new Snapshots() )->get_instance( Delete::class );

		$this->set_up_wp_cli_mock();

	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
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
		$this->assertEquals( 'delete', $this->call_private_method( $this->command, 'get_command' ) );
	}
}
