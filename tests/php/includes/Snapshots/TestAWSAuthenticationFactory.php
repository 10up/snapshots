<?php
/**
 * Tests for the AWSAuthenticationFactory class
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Snapshots;

use TenUp\WPSnapshots\Snapshots\AWSAuthentication;
use TenUp\WPSnapshots\Snapshots\AWSAuthenticationFactory;
use TenUp\WPSnapshots\Plugin;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestAWSAuthenticationFactory
 *
 * @package TenUp\WPSnapshots\Tests\Snapshots
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Snapshots\AWSAuthenticationFactory
 */
class TestAWSAuthenticationFactory extends TestCase {
	
	/**
	 * AWSAuthenticationFactory instance.
	 * 
	 * @var AWSAuthenticationFactory
	 */
	private $factory;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->factory = ( new Plugin() )->get_instance( AWSAuthenticationFactory::class );
	}

	public function test_constructor() {
		$this->assertInstanceOf( AWSAuthenticationFactory::class, $this->factory );
	}

	/** @covers ::get */
	public function test_create() {
		$config = $this->factory->get( [] );

		$this->assertInstanceOf( AWSAuthentication::class, $config );

		$this->assertEquals( '', $config->get_repository() );
	}
}
