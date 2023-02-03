<?php
/**
 * Interface for Storage Connectors.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Infrastructure\SharedService;

/**
 * Interface StorageConnectorInterface
 *
 * @package TenUp\WPSnapshots
 */
interface DBConnectorInterface extends SharedService {

	/**
	 * Searches the database.
	 *
	 * @param  string|array $query Search query string
	 * @param  string       $repository Repository name
	 * @param string       $region AWS region
	 * @return array
	 */
	public function search( $query, string $repository, string $region ) : array;

	/**
	 * Get a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @param  string $repository Repository name
	 * @param string $region AWS region
	 * @return mixed
	 */
	public function get_snapshot( string $id, string $repository, string $region );

	/**
	 * Create default DB tables. Only need to do this once ever for repo setup.
	 *
	 * @param string $repository Repository name
	 * @param string $region AWS region
	 */
	public function create_tables( string $repository, string $region );

	/**
	 * Insert a snapshot into the DB
	 *
	 * @param  string $id Snapshot ID
	 * @param  string $repository Repository name.
	 * @param string $region AWS region.
	 * @param array  $meta Snapshot meta.
	 */
	public function insert_snapshot( string $id, string $repository, string $region, array $meta ) : void;

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function delete_snapshot( string $id, string $repository, string $region ) : void;
}
