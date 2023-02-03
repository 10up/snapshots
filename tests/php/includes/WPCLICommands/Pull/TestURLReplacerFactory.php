<?php
/**
 * Tests for URLReplacerFactory.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\WPCLICommands\Pull;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsInputValidationException;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\WPSnapshotsDirectory;
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\WPCLI\Prompt;
use TenUp\WPSnapshots\WPCLICommands\Pull\MultisiteURLReplacer;
use TenUp\WPSnapshots\WPCLICommands\Pull\SingleSiteURLReplacer;
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacerFactory;
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestURLReplacerFactory
 * 
 * @package TenUp\WPSnapshots\Tests\WPCLICommands\Pull
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacerFactory
 */
class TestURLReplacerFactory extends TestCase {
	/**
	 * URLReplacerFactory instance.
	 * 
	 * @var URLReplacerFactory
	 */
	protected $url_replacer_factory;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$plugin = new Plugin();
		$this->url_replacer_factory = $plugin->get_instance( URLReplacerFactory::class );
	}

	/** @covers ::__construct */
	public function test_constructor() {
		$this->assertInstanceOf( URLReplacerFactory::class, $this->url_replacer_factory );
	}

	/** @covers ::get */
	public function test_get() {
		$url_replacer = $this->url_replacer_factory->get(
			'single',
			[],
			[]
		);

		$this->assertInstanceOf( SingleSiteURLReplacer::class, $url_replacer );

		$url_replacer = $this->url_replacer_factory->get(
			'multi',
			[],
			[]
		);

		$this->assertInstanceOf( MultisiteURLReplacer::class, $url_replacer );

		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Invalid argument passed to URLReplacerFactory::get()' );

		$this->url_replacer_factory->get(
			'invalid',
			[],
			[]
		);

	}
}
