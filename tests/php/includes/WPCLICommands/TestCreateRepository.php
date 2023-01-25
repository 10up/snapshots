<?php
/**
 * Tests covering the CreateRepository command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Tests\Fixtures\{CommandTests, PrivateAccess, WPCLIMocking};
use TenUp\WPSnapshots\WPCLI\WPCLICommand;
use TenUp\WPSnapshots\WPCLICommands\CreateRepository;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestCreateRepository
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\CreateRepository
 */
class TestCreateRepository extends TestCase {

	use PrivateAccess, WPCLIMocking, CommandTests;

	/**
	 * CreateRepository instance.
	 * 
	 * @var CreateRepository
	 */
	private $command;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->command = ( new Plugin() )->get_instance( CreateRepository::class );

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
		$this->assertEquals( 'create-repository', $this->call_private_method( $this->command, 'get_command' ) );
	}
}
