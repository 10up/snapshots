<?php
/**
 * AWSAuthentication class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

/**
 * Class AWSAuthentication
 *
 * @package TenUp\WPSnapshots
 */
class AWSAuthentication {

	/**
	 * Config array.
	 *
	 * @var array
	 */
	private $config = [];

	/**
	 * Constructor.
	 *
	 * @param array $configuration Configuration.
	 */
	public function __construct( array $configuration ) {
		$this->config = array_merge(
			[
				'repository' => '',
				'region'     => '',
				'key'        => '',
				'secret'     => '',
			],
			$configuration
		);
	}

	/**
	 * Gets the repository.
	 *
	 * @return string
	 */
	public function get_repository() : string {
		return $this->config['repository'];
	}

	/**
	 * Gets the region.
	 *
	 * @return string
	 */
	public function get_region() : string {
		return $this->config['region'];
	}

	/**
	 * Gets the key.
	 *
	 * @return string
	 */
	public function get_key() : string {
		return $this->config['key'];
	}

	/**
	 * Gets the secret.
	 *
	 * @return string
	 */
	public function get_secret() : string {
		return $this->config['secret'];
	}
}
