<?php
/**
 * Trait providing basic tests for every CLI command.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Fixtures;

use TenUp\Snapshots\WPCLI\{Prompt, WPCLICommand};

/**
 * Trait CommandTests
 * 
 * @package TenUp\Snapshots\Tests\Fixtures
 */
trait CommandTests {

    /**
     * @covers ::is_needed
     * @covers ::__construct
     * @covers ::register
     */
    public function test_command_tests() {
        $this->assertTrue( $this->command::is_needed() );

        $this->assertInstanceOf( Prompt::class, $this->get_private_property( $this->command, 'prompt' ) );
        $this->assertInstanceOf( WPCLICommand::class, $this->command );

        $this->command->register();

        // Confirm that the command wp snapshots <command> is registered.
        $command = $this->call_private_method( $this->command, 'get_command' );
        $calls = $this->get_wp_cli_mock()->assertMethodCalled( 'add_command', 1 );

        $this->assertEquals( "snapshots $command", $calls[0][0] );
    }

    /** @covers ::get_command_parameters */
	public function test_get_command_parameters() {
        $params = [
            'shortdesc',
            'synopsis',
            'when',
        ];

        if ( 'configure' === $this->call_private_method( $this->command, 'get_command' ) ) {
            $params[] = 'longdesc';
        }

		$this->assertEqualsCanonicalizing(
			$params,
			array_keys( $this->call_private_method( $this->command, 'get_command_parameters' ) )
		);
	}
}
