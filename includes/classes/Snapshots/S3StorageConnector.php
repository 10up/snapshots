<?php
/**
 * Class handling Storage interactions.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use Aws\S3\S3Client;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\SnapshotsDirectory;

/**
 * Class S3StorageConnector
 *
 * @package TenUp\Snapshots
 */
class S3StorageConnector implements StorageConnectorInterface {

	/**
	 * Clients keyed by region.
	 *
	 * @var S3Client[]
	 */
	private $clients = [];

	/**
	 * SnapshotsDirectory instance.
	 *
	 * @var SnapshotsDirectory
	 */
	private $snapshots_file_system;

	/**
	 * Snapshot meta instance.
	 *
	 * @var SnapshotMetaInterface
	 */
	private $snapshot_meta;

	/**
	 * Class constructor.
	 *
	 * @param SnapshotsDirectory    $snapshots_file_system SnapshotsDirectory instance.
	 * @param SnapshotMetaInterface $snapshot_meta SnapshotMeta instance.
	 */
	public function __construct( SnapshotsDirectory $snapshots_file_system, SnapshotMetaInterface $snapshot_meta ) {
		$this->snapshots_file_system = $snapshots_file_system;
		$this->snapshot_meta         = $snapshot_meta;
	}

	/**
	 * Download a snapshot given an id. Must specify where to download files/data
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $snapshot_meta Snapshot meta.
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function download_snapshot( string $id, array $snapshot_meta, string $profile, string $repository, string $region ) {
		$this->snapshots_file_system->create_directory( $id );

		if ( $snapshot_meta['contains_db'] ) {
			$this->get_client( $profile, $region )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $repository ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/data.sql.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ),
				]
			);
		}

		if ( $snapshot_meta['contains_files'] ) {
			$this->get_client( $profile, $region )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $repository ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/files.tar.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ),
				]
			);
		}
	}

	/**
	 * Create Snapshots S3 bucket
	 *
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 *
	 * @throws SnapshotsException If bucket already exists.
	 */
	public function create_bucket( string $profile, string $repository, string $region ) {
		$client              = $this->get_client( $profile, $region );
		$list_buckets_result = $client->listBuckets();
		$bucket_name         = $this->get_bucket_name( $repository );

		foreach ( $list_buckets_result['Buckets'] as $bucket ) {
			if ( $bucket_name === $bucket['Name'] ) {
				throw new SnapshotsException( $this->get_bucket_already_exists_message() );
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
	 * Upload a snapshot to S3
	 *
	 * @param  string $id Snapshot ID
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function put_snapshot( string $id, string $profile, string $repository, string $region ) : void {
		$meta   = $this->snapshot_meta->get_local( $id, $repository );
		$client = $this->get_client( $profile, $region );

		if ( $meta['contains_db'] && file_exists( $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ) ) ) {
			$client->putObject(
				[
					'Bucket'     => $this->get_bucket_name( $repository ),
					'Key'        => $meta['project'] . '/' . $id . '/data.sql.gz',
					'SourceFile' => realpath( $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ) ),
					'ContentMD5' => base64_encode( md5_file( $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ), true ) ), // phpcs:ignore
				]
			);
		}

		if ( $meta['contains_files'] && file_exists( $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ) ) ) {
			$client->putObject(
				[
					'Bucket'     => $this->get_bucket_name( $repository ),
					'Key'        => $meta['project'] . '/' . $id . '/files.tar.gz',
					'SourceFile' => realpath( $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ) ),
					'ContentMD5' => base64_encode( md5_file( $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ), true ) ), // phpcs:ignore
				]
			);
		}

		/**
		 * Wait for files first since that will probably take longer
		 */
		if ( $meta['contains_files'] ) {
			$client->waitUntil(
				'ObjectExists',
				[
					'Bucket' => $this->get_bucket_name( $repository ),
					'Key'    => $meta['project'] . '/' . $id . '/files.tar.gz',
				]
			);
		}

		if ( $meta['contains_db'] ) {
			$client->waitUntil(
				'ObjectExists',
				[
					'Bucket' => $this->get_bucket_name( $repository ),
					'Key'    => $meta['project'] . '/' . $id . '/data.sql.gz',
				]
			);
		}
	}

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot id
	 * @param  string $project Project name
	 * @param string $profile AWS profile.
	 * @param  string $repository Repository name
	 * @param  string $region AWS region
	 */
	public function delete_snapshot( string $id, string $project, string $profile, string $repository, string $region ) : void {
		$this->get_client( $profile, $region )->deleteObjects(
			[
				'Bucket' => $this->get_bucket_name( $repository ),
				'Delete' => [
					'Objects' => [
						[
							'Key' => $project . '/' . $id . '/files.tar.gz',
						],
						[
							'Key' => $project . '/' . $id . '/data.sql',
						],
						[
							'Key' => $project . '/' . $id . '/data.sql.gz',
						],
					],
				],
			]
		);
	}

	/**
	 * Tests the user's AWS credentials.
	 *
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function test( string $profile, string $repository, string $region ) {
		$client = $this->get_client( $profile, $region );

		$bucket_name = $this->get_bucket_name( $repository );

		$client->listObjects( [ 'Bucket' => $bucket_name ] );
	}

	/**
	 * Configures the client.
	 *
	 * @param string $profile AWS profile.
	 * @param string $region AWS region.
	 * @return S3Client
	 */
	private function get_client( string $profile, string $region ) : S3Client {
		$client_key = $profile . '_' . $region;

		if ( ! isset( $this->clients[ $client_key ] ) ) {
			$this->clients[ $client_key ] = new S3Client(
				[
					'region'    => $region,
					'profile'   => $profile,
					'signature' => 'v4',
					'version'   => '2006-03-01',
					'csm'       => false,
				]
			);
		}

		return $this->clients[ $client_key ];
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
