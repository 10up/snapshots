<?php
/**
 * Trait providing basic tests for every CLI command.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Fixtures;

use TenUp\WPSnapshots\WPCLI\{Prompt, WPCLICommand};

/**
 * Trait CommandTests
 * 
 * @package TenUp\WPSnapshots\Tests\Fixtures
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

        // Confirm that the command wp wpsnapshots <command> is registered.
        $command = $this->call_private_method( $this->command, 'get_command' );
        $calls = $this->get_wp_cli_mock()->assertMethodCalled( 'add_command', 1 );

        $this->assertEquals( "wpsnapshots $command", $calls[0][0] );
    }
}
