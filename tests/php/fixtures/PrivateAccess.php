<?php
/**
 * Helper trait allowinga access to private and protected methods and properties.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Fixtures;

use ReflectionClass;

/**
 * Trait PrivateAccess
 *
 * @package TenUp\WPSnapshots\Tests\Fixtures
 */
trait PrivateAccess {
	/**
	 * Get a private or protected property from an object.
	 *
	 * @param object $object Object to get property from.
	 * @param string $property Property to get.
	 * @return mixed
	 */
	public function get_private_property( $object, $property ) {
		$reflection = new ReflectionClass( $object );
		$property   = $reflection->getProperty( $property );
		$property->setAccessible( true );

		return $property->getValue( $object );
	}

	/**
	 * Set a private or protected property on an object.
	 *
	 * @param object $object Object to set property on.
	 * @param string $property Property to set.
	 * @param mixed $value Value to set.
	 */
	public function set_private_property( $object, $property, $value ) {
		$reflection = new ReflectionClass( $object );
		$property   = $reflection->getProperty( $property );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}

	/**
	 * Call a private or protected method on an object.
	 *
	 * @param object $object Object to call method on.
	 * @param string $method Method to call.
	 * @param array $args Arguments to pass to method.
	 * @return mixed
	 */
	public function call_private_method( $object, $method, array $args = [] ) {
		$reflection = new ReflectionClass( $object );
		$method     = $reflection->getMethod( $method );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $args );
	}
}
