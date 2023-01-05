<?php
/**
 * Snapshot meta interface
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Snapshots\AWSAuthentication;

/**
 * Interface for napshot meta wrapper with support for downloading remote meta
 *
 * @package TenUp\WPSnapshots\Snapshots
 */
interface SnapshotMetaInterface {

	/**
	 * Save snapshot meta locally
	 *
	 * @param string $id Snapshot ID.
	 * @param array  $meta Snapshot meta.
	 * @return int Number of bytes written
	 */
	public function save_local( string $id, array $meta );

	/**
	 * Download meta from remote DB
	 *
	 * @param string            $id Snapshot ID
	 * @param AWSAuthentication $aws_authentication AWS authentication instance.
	 * @return array
	 */
	public function get_remote_meta( string $id, AWSAuthentication $aws_authentication );

	/**
	 * Get local snapshot meta
	 *
	 * @param  string $id Snapshot ID
	 * @param  string $repository Repository name
	 * @return mixed
	 *
	 * @throws WPSnapshotsException Snapshot meta invalid.
	 */
	public function get_local( string $id, string $repository );
}
