<?php
/**
 * Tests for the AWSAuthentication class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Snapshots;

use TenUp\WPSnapshots\Snapshots\AWSAuthentication;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestAWSAuthentication
 *
 * @package TenUp\WPSnapshots\Tests\Snapshots
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Snapshots\AWSAuthentication
 */
class TestAWSAuthentication extends TestCase {

    /**
     * AWSAuthentication instance.
     * 
     * @var AWSAuthentication
     */
    private $authentication;

    /**
     * Test setup.
     */
    public function set_up() {
        parent::set_up();

        $this->authentication = new AWSAuthentication(
            [
                'repository' => 'test-repo',
                'region'     => 'test-region',
                'key'        => 'test-key',
                'secret'     => 'test-secret',
            ]
        );
    }

    /** @covers ::__construct */
    public function test_constructor() {
        $this->assertInstanceOf( AWSAuthentication::class, $this->authentication );
    }

    /** @covers ::get_repository */
    public function test_get_repository() {
        $this->assertEquals( 'test-repo', $this->authentication->get_repository() );
    }

    /** @covers ::get_region */
    public function test_get_region() {
        $this->assertEquals( 'test-region', $this->authentication->get_region() );
    }

    /** @covers ::get_key */
    public function test_get_key() {
        $this->assertEquals( 'test-key', $this->authentication->get_key() );
    }

    /** @covers ::get_secret */
    public function test_get_secret() {
        $this->assertEquals( 'test-secret', $this->authentication->get_secret() );
    }
}

