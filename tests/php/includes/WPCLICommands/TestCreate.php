<?php
/**
 * Tests covering the Create command class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\SnapshotCreator;
use TenUp\WPSnapshots\Tests\Fixtures\{CommandTests, PrivateAccess, WPCLIMocking};
use TenUp\WPSnapshots\WPCLI\WPCLICommand;
use TenUp\WPSnapshots\WPCLICommands\Create;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestCreate
 *
 * @package TenUp\WPSnapshots\Tests\Commands
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Create
 */
class TestCreate extends TestCase {

	use PrivateAccess, WPCLIMocking, CommandTests;

	/**
	 * Create instance.
	 * 
	 * @var Create
	 */
	private $command;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->command = ( new Plugin() )->get_instance( Create::class );

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
		$this->assertEquals( 'create', $this->call_private_method( $this->command, 'get_command' ) );
	}

	/**
	 * @covers ::execute
	 * @covers ::get_create_args
	 * @covers ::run
	 */
	public function test_execute() {
		$snapshot_creator = $this->get_snapshot_creator_mock();

		$this->set_private_property( $this->command, 'snapshot_creator', $snapshot_creator );

		$snapshot_creator->expects( $this->once() )->method( 'create' )
			->with(
				[
					'author' => [
						'name'  => 'readline8',
						'email' => 'readline9',
					],
					'contains_db' => true,
					'contains_files' => false,
					'description' => 'readline10',
					'exclude_uploads' => false,
					'excludes' => [],
					'project' => 'readline11',
					'region' => 'us-west-1',
					'repository' => 'readline12',
					'small' => false,
					'wp_version' => '',
				]
			);

		$this->command->execute( [], [ 'include_db' => true ] );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'line',
			5,
			[
				[
					'Your name (enter x to cancel):'
				],
				[
					'Your email (enter x to cancel):',
				],
				[
					'Snapshot Description (e.g. Local environment) (enter x to cancel):',
				],
				[
					'Project Slug (letters, numbers, _, and - only) (enter x to cancel):',
				],
				[
					'Repository Slug (letters, numbers, _, and - only) (enter x to cancel):',
				]
			]
		);
	}

	/**
	 * @covers ::execute
	 * @covers ::run
	 * @covers ::get_create_args
	 * @covers ::get_success_message
	 */
	public function test_execute_with_args_passed_in() {
		$snapshot_creator = $this->get_snapshot_creator_mock();

		$this->set_private_property( $this->command, 'snapshot_creator', $snapshot_creator );

		$snapshot_creator->expects( $this->once() )->method( 'create' )
			->with(
				[
					'author' => [
						'name'  => 'Test Author',
						'email' => 'email@email.com',
					],
					'contains_db' => true,
					'contains_files' => true,
					'description' => 'Description!',
					'exclude_uploads' => false,
					'excludes' => [
						'files',
						'directory/file.txt',
					],
					'project' => 'slug3',
					'region' => 'us-west-1',
					'repository' => 'my-repo',
					'small' => true,
					'wp_version' => '',
				]
			);

		$this->command->execute( [], [
			'author_name' => 'Test Author',
			'description' => 'Description!',
			'author_email' => 'email@email.com',
			'exclude' => 'files,directory/file.txt',
			'include_db' => true,
			'include_files' => true,
			'repository' => 'my-repo',
			'slug' => 'Slug3',
			'small' => true,
		] );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'success',
			1,
			[
				[
					'Snapshot  created.'
				]
			]
		);
	}

	/** @covers ::execute */
	public function test_execute_throws_when_db_and_files_not_included() {
		$snapshot_creator = $this->get_snapshot_creator_mock();

		$this->set_private_property( $this->command, 'snapshot_creator', $snapshot_creator );

		$this->command->execute( [], [ 'include_db' => false, 'include_files' => false ] );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'error',
			1,
			[
				[
					'You must include either the database or files in the snapshot.'
				]
			]
		);
	}

	/** @covers ::validate_slug */
	public function test_validate_slug() {
		$this->assertTrue( $this->call_private_method( $this->command, 'validate_slug', [ 'slug' ] ) );

		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Input must be letters, numbers, _, and - only.' );

		$this->call_private_method( $this->command, 'validate_slug', [ 'slug!' ] );
	}


	/**
	 * Gets snapshot creator mock.
	 * 
	 * @return SnapshotCreator|MockObject
	 */
	private function get_snapshot_creator_mock() {
		/**
		 * SnapshotCreator Mock
		 * 
		 * @var SnapshotCreator|MockObject $snapshot_creator
		 */
		$snapshot_creator = $this->createMock( SnapshotCreator::class );
		$snapshot_creator->method( 'create' );

		return $snapshot_creator;
	}
	
}
