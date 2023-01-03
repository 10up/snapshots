<?php
/**
 * AWSAuthenticationFactory class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Infrastructure\{Factory, Shared, Service};

/**
 * Class AWSAuthentication
 *
 * @package TenUp\WPSnapshots
 */
class AWSAuthenticationFactory implements Shared, Service, Factory {

	/**
	 * Creates an instance.
	 *
	 * @param mixed ...$args Arguments to pass to the constructor.
	 *
	 * @return AWSAuthentication
	 */
	public function get( ...$args ) : object {
		$config_args = reset( $args );

		return new AWSAuthentication( $config_args );
	}
}
