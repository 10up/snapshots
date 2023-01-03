<?php
/**
 * Class handling Storage interactions.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use Aws\S3\S3Client;
use Exception;
use TenUp\WPSnapshots\Infrastructure\Service;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;

/**
 * Class S3StorageConnector
 *
 * @package TenUp\WPSnapshots
 */
class S3StorageConnector implements StorageConnectorInterface, Service {

	/**
	 * Storage client.
	 *
	 * @var S3Client
	 */
	private $client;

	/**
	 * Connection configuration.
	 *
	 * @var AWSAuthentication
	 */
	private $configuration;

	/**
	 * Sets the configuration.
	 *
	 * @param object $configuration Configuration.
	 */
	public function set_configuration( object $configuration ) {
		$this->configuration = $configuration;
	}

	/**
	 * Gets the configuration.
	 *
	 * @return object
	 *
	 * @throws WPSnapshotsException If no configuration is set.
	 */
	public function get_configuration() : object {
		if ( ! $this->configuration ) {
			throw new WPSnapshotsException( 'No configuration set.' );
		}

		return $this->configuration;
	}

	/**
	 * Tests the Storage connection.
	 *
	 * @return bool
	 *
	 * @throws WPSnapshotsException If no configuration is set.
	 */
	public function test_connection() {
		if ( is_null( $this->client ) ) {
			$this->configure_client();
		}

		try {
			/**
			 * Filters the test callable.
			 *
			 * @param callable $test_callable The test callable.
			 */
			$test_callable = apply_filters( 'wpsnapshots_s3_test_callable', [ $this, 'test_connection_default' ] );

			if ( ! is_callable( $test_callable ) ) {
				throw new WPSnapshotsException( 'Invalid test callable.' );
			}

			return call_user_func( $test_callable );
		} catch ( Exception $e ) {
			throw new WPSnapshotsException( $e->getMessage() );
		}
	}

	/**
	 * Configures the client.
	 *
	 * @throws WPSnapshotsException If the configuration is invalid.
	 */
	private function configure_client() {
		try {
			$configuration = $this->get_configuration();
			$this->client  = new S3Client(
				[
					'version'     => 'latest',
					'region'      => $configuration->get_region(),
					'credentials' => [
						'key'    => $configuration->get_key(),
						'secret' => $configuration->get_secret(),
					],
				]
			);
		} catch ( Exception $e ) {
			throw new WPSnapshotsException( $e->getMessage() );
		}
	}

	/**
	 * Default test connection callable.
	 *
	 * @return bool
	 */
	private function test_connection_default() {
		return (bool) $this->client->listObjects( [ 'Bucket' => $this->get_bucket_name() ] );
	}

	/**
	 * Get bucket name
	 *
	 * @return string
	 */
	private function get_bucket_name() : string {
		return 'wpsnapshots-' . $this->get_configuration()->get_repository();
	}

}
