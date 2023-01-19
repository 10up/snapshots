<?php
/**
 * Tests covering the CreateRepository command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\DynamoDBConnector;
use TenUp\WPSnapshots\Snapshots\S3StorageConnector;
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

	/**
	 * @covers ::set_assoc_args
	 * @covers ::get_assoc_args
	 * @covers ::set_args
	 * @covers ::get_args
	 */
	public function test_setters_and_getters() {
		$this->command->set_assoc_args( [ 'foo' => 'bar' ] );
		$this->assertEquals( [ 'foo' => 'bar' ], $this->command->get_assoc_args() );

		$this->command->set_args( [ 'foo' ] );
		$this->assertEquals( [ 'foo' ], $this->command->get_args() );
	}

	/**
	 * @covers ::execute
	 */
	public function test_execute() {
		/**
		 * @var MockObject $storage_connector
		 */
		$storage_connector = $this->createMock( S3StorageConnector::class );
		$storage_connector->expects( $this->once() )
			->method( 'create_bucket' )
			->with( 'test-repo', 'test-region' );

		/**
		 * @var MockObject $db_connector
		 */
		$db_connector = $this->createMock( DynamoDBConnector::class );
		$db_connector->expects( $this->once() )
			->method( 'create_tables' )
			->with( 'test-repo', 'test-region' );

		$this->set_private_property( $this->command, 'storage_connector', $storage_connector );
		$this->set_private_property( $this->command, 'db_connector', $db_connector );

		$this->command->execute( [ 'test-repo' ], [ 'region' => 'test-region' ] );

		// Check success message.
		$this->get_wp_cli_mock()->assertMethodCalled( 'success', 1, [ [ 'Repository created.' ] ] );
	}

}
