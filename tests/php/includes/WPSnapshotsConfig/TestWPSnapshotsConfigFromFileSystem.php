<?php
/**
 * Tests for the SnapshotsConfigFromFileSystem class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Config;

use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\Snapshots\SnapshotsConfig\SnapshotsConfigFromFileSystem;
use TenUp\Snapshots\SnapshotsConfig\SnapshotsConfigInterface;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestConfigFromFileSystem
 *
 * @package TenUp\Snapshots\Tests\Config
 *
 * @coversDefaultClass \TenUp\Snapshots\SnapshotsConfig\SnapshotsConfigFromFileSystem
 */
class TestConfigFromFileSystem extends TestCase {

    use DirectoryFiltering;

    /**
     * SnapshotsConfigFromFileSystem instance.
     *
     * @var SnapshotsConfigFromFileSystem
     */
    private $config;

    /**
     * Test setup.
     */
    public function set_up() {
        parent::set_up();

        $this->config = ( new Snapshots() )->get_instance( SnapshotsConfigFromFileSystem::class );

        $this->set_up_directory_filtering();
    }

    /**
     * Test teardown.
     */
    public function tear_down() {
        parent::tear_down();

        $this->tear_down_directory_filtering();
    }

    /** @covers ::__construct */
    public function test_constructor() {
        $this->assertInstanceOf( SnapshotsConfigFromFileSystem::class, $this->config );
        $implements = class_implements( $this->config );
        $this->assertArrayHasKey( SnapshotsConfigInterface::class, $implements );
    }

    /**
     * @covers ::get_user_name
     * @covers ::set_user_name
     * @covers ::save
     * @covers ::load
     * @covers ::get_config
     * @covers ::get_defaults
     */
    public function test_get_user_name_method_returns_default_value() {
        // Delete file
        exec( 'rm -rf ' . $this->get_directory_path() );

        $this->assertFileDoesNotExist( $this->get_directory_path() );

        $this->config->set_user_name( 'test' );

        $this->assertFileExists( $this->get_directory_path() );

        $this->assertEquals( 'test', $this->config->get_user_name() );
    }

    /**
     * @covers ::get_user_email
     * @covers ::set_user_email
     */
    public function test_get_user_email_method_returns_default_value() {
        $this->assertEquals( '', $this->config->get_user_email() );

        $this->config->set_user_email( 'test' );

        $this->assertEquals( 'test', $this->config->get_user_email() );
    }

    /**
     * @covers ::get_repositories
     * @covers ::set_repositories
     */
    public function test_get_repositories_method_returns_default_value() {
        $this->assertEquals( [], $this->config->get_repositories() );

        $this->config->set_repositories( [ 'test' => [] ] );

        $this->assertEquals( [ 'test' => [] ], $this->config->get_repositories() );
    }

    /**
     * @covers ::get_repository_settings
     */
    public function test_get_repository_settings_method_returns_default_value() {
        $this->config->set_repositories( [ 'test' => [ 'test' => 'test' ] ] );

        $this->assertEquals( [ 'test' => 'test' ], $this->config->get_repository_settings( 'test' ) );
    }
}



