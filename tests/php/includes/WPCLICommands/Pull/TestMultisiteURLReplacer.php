<?php
/**
 * Tests for MultisiteURLReplacer.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\WPCLICommands\Pull;

use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\SnapshotsFileSystem;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use TenUp\WPSnapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\WPCLI\Prompt;
use TenUp\WPSnapshots\WPCLICommands\Pull\MultisiteURLReplacer;
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestMultisiteURLReplacer
 * 
 * @package TenUp\WPSnapshots\Tests\WPCLICommands\Pull
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Pull\MultisiteURLReplacer
 */
class TestMultisiteURLReplacer extends TestCase {

	use WPCLIMocking, PrivateAccess;

	/**
	 * URLReplacer instance.
	 * 
	 * @var MultisiteURLReplacer
	 */
	protected $url_replacer;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_up_wp_cli_mock();

		$plugin = new Plugin();
		$this->url_replacer = new MultisiteURLReplacer(
			$plugin->get_instance( Prompt::class ),
			$plugin->get_instance( SnapshotsFileSystem::class ),
			$plugin->get_instance( Database ::class ),
			[
				'sites' => [
					[
						'blog_id' => 1,
						'home_url'  => 'http://home-url.com',
						'site_url'  => 'http://site-url.com'
					],
				],
			],
			[],
			[],
			false,
			null
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
	}

	/** @covers ::__construct */
	public function test_constructor() {
		$this->assertInstanceOf( MultisiteURLReplacer::class, $this->url_replacer );
		$this->assertInstanceOf( URLReplacer::class, $this->url_replacer );
	}

	
}
