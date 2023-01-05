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
interface DBConnectorInterface {

	/**
	 * Searches the database.
	 *
	 * @param  string|array      $query Search query string
	 * @param  AWSAuthentication $aws_authentication Authentication object.
	 * @return array
	 */
	public function search( $query, AWSAuthentication $aws_authentication ) : array;

	/**
	 * Get a snapshot given an id
	 *
	 * @param  string            $id Snapshot ID
	 * @param  AWSAuthentication $aws_authentication AWS authentication instance.
	 * @return mixed
	 */
	public function get_snapshot( string $id, AWSAuthentication $aws_authentication );
}
