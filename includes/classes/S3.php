<?php
/**
 * Class handling S3 interactions.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use Aws\S3\S3Client;
use Exception;

/**
 * Class S3
 *
 * @package TenUp\WPSnapshots
 */
final class S3 {

	/**
	 * S3 client.
	 *
	 * @var S3Client
	 */
	private $client;

	/**
	 * Repository name.
	 *
	 * @var string
	 */
	private $repository;

	/**
	 * Class constructor.
	 *
	 * @param string $repository Repository to use.
	 * @param string $key Key to use.
	 * @param string $secret Secret to use.
	 * @param string $region Region to use.
	 */
	public function __construct( string $repository, string $key, string $secret, string $region ) {
		$this->client = new S3Client(
			[
				'version'     => 'latest',
				'region'      => $region,
				'credentials' => compact( 'key', 'secret' ),
			]
		);

		$this->repository = $repository;
	}

	/**
	 * Tests the S3 connection.
	 *
	 * @return bool
	 */
	public function test_connection() {
		try {
			$this->client->listObjects( [ 'Bucket' => $this->get_bucket_name( $this->repository ) ] );
		} catch ( Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Get bucket name
	 *
	 * @param  string $repository Repository name
	 * @return string
	 */
	private function get_bucket_name( string $repository ) : string {
		return 'wpsnapshots-' . $repository;
	}

}
