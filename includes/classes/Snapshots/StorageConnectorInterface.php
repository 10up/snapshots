<?php
/**
 * Interface for Storage Connectors.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\SharedService;

/**
 * Interface StorageConnectorInterface
 *
 * @package TenUp\WPSnapshots
 */
interface StorageConnectorInterface extends SharedService {

	/**
	 * Download a snapshot given an id. Must specify where to download files/data
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $snapshot_meta Snapshot meta.
	 * @param string $repository Repository name
	 * @param string $region AWS region
	 */
	public function download_snapshot( string $id, array $snapshot_meta, string $repository, string $region );

	/**
	 * Create WP Snapshots S3 bucket
	 *
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 *
	 * @throws WPSnapshotsException If bucket already exists.
	 */
	public function create_bucket( string $repository, string $region );

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
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function put_snapshot( string $id, string $repository, string $region ) : void;
}
