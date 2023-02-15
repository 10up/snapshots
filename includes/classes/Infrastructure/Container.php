<?php
/**
 * Auto-initialize all services.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Infrastructure;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use TenUp\Snapshots\Exceptions\WPSnapshotsException;

/**
 * Container class.
 *
 * @package TenUp\Snapshots\Infrastructure
 */
abstract class Container {

	/**
	 * Associative array of shared service instances.
	 *
	 * @var array<Service|Module>
	 */
	protected $shared_instances = [];

	/**
	 * Provides names of modules to instantiate.
	 *
	 * @return array<?string>
	 */
	abstract protected function get_modules() : array;

	/**
	 * Provides the services.
	 *
	 * Services are classes that are instantiated on demand when modules are instantiated.
	 *
	 * @return array<?string>
	 */
	abstract protected function get_services() : array;

	/**
	 * Performs setup functions.
	 */
	public function register() : void {
		$instances = [];

		foreach ( $this->get_modules() as $module ) {
			$implements = class_implements( $module );
			if ( is_array( $implements ) && in_array( Conditional::class, $implements, true ) && ! $module::is_needed() ) {
				continue;
			}

			$instances[] = $this->get_instance( $module );
		}

		foreach ( $instances as $instance ) {
			if ( method_exists( $instance, 'register' ) ) {
				$instance->register();
			}
		}
	}

	/**
	 * Sets up a module.
	 *
	 * @param string $class Module or service class.
	 * @return object
	 *
	 * @throws WPSnapshotsException If an unknown module or service is encountered.
	 */
	public function get_instance( string $class ) {
		$class_implements = class_implements( $class );
		$is_shared        = is_array( $class_implements ) && in_array( SharedService::class, $class_implements, true );

		if ( $is_shared && isset( $this->shared_instances[ $class ] ) ) {
			return $this->shared_instances[ $class ];
		}

		// Verify the class is either a module or a service.
		if ( ! in_array( $class, array_merge( $this->get_modules(), $this->get_services() ), true ) ) {
			throw new WPSnapshotsException( sprintf( 'Unknown module or service: %s', $class ) );
		}

		if ( ! in_array( Module::class, $class_implements, true ) && ! in_array( Service::class, $class_implements, true ) ) {
			throw new WPSnapshotsException( sprintf( 'Class is neither a module nor a service: %s', $class ) );
		}

		// Modules shouldn't be shared.
		if ( $is_shared && is_a( Module::class, $class, true ) ) {
			throw new WPSnapshotsException( sprintf( 'Modules should not be shared: %s', $class ) );
		}

		$reflection  = new ReflectionClass( $class );
		$constructor = $reflection->getConstructor();

		// If the constructor is null, walk through parent classes to find a constructor.
		if ( ! $constructor ) {
			do {
				$reflection  = $reflection->getParentClass();
				$constructor = $reflection ? $reflection->getConstructor() : null;
			} while ( ! $constructor && $reflection );
		}
		$dependency_instances = $constructor ? $this->get_dependency_instances_from_constructor( $constructor ) : [];

		$instance = new $class( ...$dependency_instances );

		if ( $is_shared ) {
			$this->shared_instances[ $class ] = $instance;
		}

		return $instance;
	}

	/**
	 * Gets an instance for a given parameter.
	 *
	 * @param ReflectionParameter $parameter Parameter.
	 * @param ReflectionMethod    $constructor Constructor.
	 * @return object|array
	 *
	 * @throws WPSnapshotsException If an unknown module or service is encountered.
	 */
	private function get_instance_from_parameter( ReflectionParameter $parameter, ReflectionMethod $constructor ) : object|array {
		$type = $parameter->getType();

		// If the parameter is ...$args, get instances from the parent class's constructor.
		if ( null === $type && $parameter->isVariadic() && $constructor->getDeclaringClass()->getParentClass() ) {
			return $this->get_dependency_instances_from_constructor( $constructor->getDeclaringClass()->getParentClass()->getConstructor() );
		}

		if ( ! is_a( $type, ReflectionNamedType::class, true ) ) {
			throw new WPSnapshotsException( sprintf( 'Unable to get type for parameter: %s', $parameter->getName() ) );
		}

		$dependency_class            = $type->getName();
		$dependency_class_reflection = new ReflectionClass( $dependency_class );

		if ( $dependency_class_reflection->isInterface() ) {
			$dependency_class = $this->get_concrete_service_name( $dependency_class );
		}

		return $this->get_instance( $dependency_class );
	}

	/**
	 * Gets dependency instances from a constructor.
	 *
	 * @param ReflectionMethod $constructor Constructor.
	 * @return array
	 */
	private function get_dependency_instances_from_constructor( ReflectionMethod $constructor ) {
		return array_reduce(
			$constructor->getParameters(),
			function( $instances, ReflectionParameter $parameter ) use ( $constructor ) {
				$received_instances = $this->get_instance_from_parameter( $parameter, $constructor );
				if ( ! is_array( $received_instances ) ) {
					$received_instances = [ $received_instances ];
				}

				return array_merge( $instances, $received_instances );
			},
			[]
		);
	}

	/**
	 * Gets the concrete class name for a given interface.
	 *
	 * @param string $interface Interface name.
	 * @return string
	 *
	 * @throws WPSnapshotsException If no concrete class is found for the given interface.
	 */
	private function get_concrete_service_name( string $interface ) : string {
		$services = $this->get_services();

		$services_implementing_interface = array_filter(
			$services,
			function ( $service ) use ( $interface ) {
				$implements = class_implements( $service );
				return is_array( $implements ) && in_array( $interface, $implements, true );
			}
		);

		if ( 0 === count( $services_implementing_interface ) ) {
			throw new WPSnapshotsException( sprintf( 'No concrete service class found for interface %s', $interface ) );
		}

		if ( 1 < count( $services_implementing_interface ) ) {
			throw new WPSnapshotsException( sprintf( 'Multiple concrete service classes found for interface %s. Only one is allowed in the system at a time.', $interface ) );
		}

		return reset( $services_implementing_interface );
	}
}
