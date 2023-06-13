<?php
/**
 * Interface for Storage Connectors.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use TenUp\Snapshots\Infrastructure\SharedService;

/**
 * Interface StorageConnectorInterface
 *
 * @package TenUp\Snapshots
 */
interface DBConnectorInterface extends SharedService {

	/**
	 * Searches the database.
	 *
	 * @param  string|array $query Search query string
	 * @param array        $config AWS config
	 * @return array
	 */
	public function search( $query, array $config ) : array;

	/**
	 * Get a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $config AWS config
	 * @return mixed
	 */
	public function get_snapshot( string $id, array $config );

	/**
	 * Create default DB tables. Only need to do this once ever for repo setup.
	 *
	 * @param array $config AWS config
	 */
	public function create_tables( array $config );

	/**
	 * Insert a snapshot into the DB
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $config AWS config
	 * @param array  $meta Snapshot meta.
	 */
	public function insert_snapshot( string $id, array $config, array $meta ) : void;

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $config AWS config
	 */
	public function delete_snapshot( string $id, array $config ) : void;
}
