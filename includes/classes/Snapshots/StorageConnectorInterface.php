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
	 * @param string $id Snapshot ID
	 * @param array  $config AWS config array
	 * @param array  $snapshot_meta Snapshot meta.
	 */
	public function download_snapshot( string $id, array $config, array $snapshot_meta );

	/**
	 * Create Snapshots S3 bucket
	 *
	 * @param array $config AWS config
	 *
	 * @throws SnapshotsException If bucket already exists.
	 */
	public function create_bucket( array $config );

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
	 * @param array  $config AWS config
	 */
	public function put_snapshot( string $id, array $config ) : void;

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot id
	 * @param  string $project Project name
	 * @param array  $config AWS config
	 */
	public function delete_snapshot( string $id, string $project, array $config ) : void;

	/**
	 * Tests the user's AWS credentials.
	 *
	 * @param array $config AWS config
	 */
	public function test( array $config );
}
