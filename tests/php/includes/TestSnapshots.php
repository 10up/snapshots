<?php
/**
 * Tests covering general plugin functionality.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests;

use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestSnapshots
 *
 * @package TenUp\Snapshots\Tests
 * 
 * @coversDefaultClass \TenUp\Snapshots\Snapshots
 */
class TestSnapshots extends TestCase {

    use PrivateAccess;

    /**
     * @covers ::get_modules
     */
    public function test_get_modules() {
        $plugin = new Snapshots();
        $modules = $this->call_private_method( $plugin, 'get_modules' );

        $this->assertIsArray( $modules );
        $this->assertNotEmpty( $modules );
    }

    /**
     * @covers ::get_services
     */
    public function test_get_services() {
        $plugin = new Snapshots();
        $services = $this->call_private_method( $plugin, 'get_services' );

        $this->assertIsArray( $services );
        $this->assertNotEmpty( $services );
    }
}
