<?php
/**
 * Class handling Storage interactions.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use Aws\S3\S3Client;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\{Service, Shared};
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
	 * Download a snapshot given an id. Must specify where to download files/data
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $snapshot_meta Snapshot meta.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function download_snapshot( string $id, array $snapshot_meta, string $repository, string $region ) {
		$this->snapshots_file_system->create_directory( $id );

		if ( $snapshot_meta['contains_db'] ) {
			$this->get_client( $region )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $repository ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/data.sql.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ),
				]
			);
		}

		if ( $snapshot_meta['contains_files'] ) {
			$this->get_client( $region )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $repository ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/files.tar.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ),
				]
			);
		}
	}

	/**
	 * Create WP Snapshots S3 bucket
	 *
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 *
	 * @throws WPSnapshotsException If bucket already exists.
	 */
	public function create_bucket( string $repository, string $region ) {
		$client              = $this->get_client( $region );
		$list_buckets_result = $client->listBuckets();
		$bucket_name         = $this->get_bucket_name( $repository );

		foreach ( $list_buckets_result['Buckets'] as $bucket ) {
			if ( $bucket_name === $bucket['Name'] ) {
				throw new WPSnapshotsException( $this->get_bucket_already_exists_message() );
			}
		}

		$client->createBucket(
			[
				'Bucket'             => $bucket_name,
				'LocationConstraint' => $region,
			]
		);
	}

	/**
	 * Gets the bucket already exists error message.
	 *
	 * @return string
	 */
	public function get_bucket_already_exists_message() : string {
		return 'S3 bucket already exists.';
	}


	/**
	 * Configures the client.
	 *
	 * @param string $region AWS region.
	 * @return S3Client
	 */
	private function get_client( string $region ) : S3Client {
		return new S3Client(
			[
				'version' => 'latest',
				'region'  => $region,
			]
		);
	}

	/**
	 * Get bucket name
	 *
	 * @param string $repository Repository name.
	 * @return string
	 */
	private function get_bucket_name( string $repository ) : string {
		return 'wpsnapshots-' . $repository;
	}

}
