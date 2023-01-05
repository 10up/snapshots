<?php
/**
 * Interface for Storage Connectors.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

/**
 * Interface StorageConnectorInterface
 *
 * @package TenUp\WPSnapshots
 */
interface StorageConnectorInterface {

	/**
	 * Download a snapshot given an id. Must specify where to download files/data
	 *
	 * @param  string            $id Snapshot ID
	 * @param array             $snapshot_meta Snapshot meta.
	 * @param AWSAuthentication $aws_authentication Authentication object.
	 */
	public function download_snapshot( string $id, array $snapshot_meta, AWSAuthentication $aws_authentication );

	/**
	 * Tests the Storage connection.
	 *
	 * @param AWSAuthentication $aws_authentication Authentication object.
	 * @return bool
	 */
	public function test_connection( AWSAuthentication $aws_authentication ) : bool;
}
