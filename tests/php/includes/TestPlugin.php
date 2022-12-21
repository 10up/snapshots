<?php
/**
 * Tests covering general plugin functionality.
 * 
 * @package Tenup\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests;

use WP_CLI;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class PluginTest
 *
 * @package TenUp\WPSnapshots\Tests
 */
class PluginTest extends TestCase {

    /**
    * Test that the WP CLI command is registered.
    */
    public function test_wp_cli_exists() {
        $this->assertTrue( class_exists( WP_CLI::class ) );
    }
}
