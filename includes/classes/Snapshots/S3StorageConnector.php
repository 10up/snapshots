<?php
/**
 * Class handling Storage interactions.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use Aws\S3\S3Client;
use Exception;
use TenUp\WPSnapshots\Infrastructure\{Service, Shared};
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\SnapshotsFileSystem;

/**
 * Class S3StorageConnector
 *
 * @package TenUp\WPSnapshots
 */
class S3StorageConnector implements StorageConnectorInterface, Shared, Service {

	/**
	 * SnapshotsFileSystem instance.
	 *
	 * @var SnapshotsFileSystem
	 */
	private $snapshots_file_system;

	/**
	 * Class constructor.
	 *
	 * @param SnapshotsFileSystem $snapshots_file_system SnapshotsFileSystem instance.
	 */
	public function __construct( SnapshotsFileSystem $snapshots_file_system ) {
		$this->snapshots_file_system = $snapshots_file_system;
	}

	/**
	 * Tests the Storage connection.
	 *
	 * @param AWSAuthentication $aws_authentication Authentication object.
	 * @return bool
	 *
	 * @throws WPSnapshotsException If no authentication is set.
	 */
	public function test_connection( AWSAuthentication $aws_authentication ) : bool {
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

			return call_user_func( $test_callable, $aws_authentication );
		} catch ( Exception $e ) {
			throw new WPSnapshotsException( $e->getMessage() );
		}
	}

	/**
	 * Download a snapshot given an id. Must specify where to download files/data
	 *
	 * @param  string            $id Snapshot ID
	 * @param array             $snapshot_meta Snapshot meta.
	 * @param AWSAuthentication $aws_authentication Authentication object.
	 */
	public function download_snapshot( string $id, array $snapshot_meta, AWSAuthentication $aws_authentication ) {
		$this->snapshots_file_system->create_directory( $id );

		if ( $snapshot_meta['contains_db'] ) {
			$this->get_client( $aws_authentication )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $aws_authentication ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/data.sql.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ),
				]
			);
		}

		if ( $snapshot_meta['contains_files'] ) {
			$this->get_client( $aws_authentication )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $aws_authentication ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/files.tar.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ),
				]
			);
		}
	}

	/**
	 * Configures the client.
	 *
	 * @param AWSAuthentication $aws_authentication Authentication object.
	 * @return S3Client
	 *
	 * @throws WPSnapshotsException If the authentication is invalid.
	 */
	private function get_client( AWSAuthentication $aws_authentication ) {
		return new S3Client(
			[
				'version'     => 'latest',
				'region'      => $aws_authentication->get_region(),
				'credentials' => [
					'key'    => $aws_authentication->get_key(),
					'secret' => $aws_authentication->get_secret(),
				],
			]
		);
	}

	/**
	 * Default test connection callable.
	 *
	 * @param AWSAuthentication $aws_authentication Authentication object.
	 * @return bool
	 */
	private function test_connection_default( AWSAuthentication $aws_authentication ) : bool {
		return (bool) $this->get_client( $aws_authentication )->listObjects( [ 'Bucket' => $this->get_bucket_name( $aws_authentication ) ] );
	}

	/**
	 * Get bucket name
	 *
	 * @param AWSAuthentication $aws_authentication Authentication object.
	 * @return string
	 */
	private function get_bucket_name( AWSAuthentication $aws_authentication ) : string {
		return 'wpsnapshots-' . $aws_authentication->get_repository();
	}

}
