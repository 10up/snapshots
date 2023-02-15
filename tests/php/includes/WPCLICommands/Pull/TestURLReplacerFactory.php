<?php
/**
 * Tests for URLReplacerFactory.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\WPCLICommands\Pull;

use TenUp\Snapshots\Exceptions\WPSnapshotsException;
use TenUp\Snapshots\Exceptions\WPSnapshotsInputValidationException;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\WPSnapshotsDirectory;
use TenUp\Snapshots\WordPress\Database;
use TenUp\Snapshots\WPCLI\Prompt;
use TenUp\Snapshots\WPCLICommands\Pull\MultisiteURLReplacer;
use TenUp\Snapshots\WPCLICommands\Pull\SingleSiteURLReplacer;
use TenUp\Snapshots\WPCLICommands\Pull\URLReplacerFactory;
use TenUp\Snapshots\WPCLICommands\Pull\URLReplacer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestURLReplacerFactory
 * 
 * @package TenUp\Snapshots\Tests\WPCLICommands\Pull
 * 
 * @coversDefaultClass \TenUp\Snapshots\WPCLICommands\Pull\URLReplacerFactory
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

		$plugin = new Snapshots();
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
