<?php
/**
 * Interface for Storage Connectors.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\Infrastructure\SharedService;

/**
 * Interface StorageConnectorInterface
 *
 * @package TenUp\Snapshots
 */
interface StorageConnectorInterface extends SharedService {

	/**
	 * Download a snapshot given an id. Must specify where to download files/data
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $snapshot_meta Snapshot meta.
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name
	 * @param string $region AWS region
	 */
	public function download_snapshot( string $id, array $snapshot_meta, string $profile, string $repository, string $region );

	/**
	 * Create Snapshots S3 bucket
	 *
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 *
	 * @throws SnapshotsException If bucket already exists.
	 */
	public function create_bucket( string $profile, string $repository, string $region );

	/**
	 * Gets the bucket already exists error message.
	 *
	 * @return string
	 */
	public function get_bucket_already_exists_message();

	/**
	 * Upload a snapshot to S3
	 *
	 * @param  string $id Snapshot ID
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function put_snapshot( string $id, string $profile, string $repository, string $region ) : void;

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot id
	 * @param  string $project Project name
	 * @param string $profile AWS profile.
	 * @param  string $repository Repository name
	 * @param  string $region AWS region
	 */
	public function delete_snapshot( string $id, string $project, string $profile, string $repository, string $region ) : void;

	/**
	 * Tests the user's AWS credentials.
	 *
	 * @param string $profile AWS profile.
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function test( string $profile, string $repository, string $region );
}
