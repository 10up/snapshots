<?php
/**
 * Snapshot meta abstract class
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\Snapshots\DBConnectorInterface;

/**
 * Snapshot meta wrapper with support for downloading remote meta
 *
 * @package TenUp\Snapshots\Snapshots
 */
abstract class SnapshotMeta implements SnapshotMetaInterface {

	/**
	 * Database connector instance.
	 *
	 * @var DBConnectorInterface
	 */
	protected $db;

	/**
	 * Meta constructor
	 *
	 * @param DBConnectorInterface $db Database connector instance.
	 */
	public function __construct( DBConnectorInterface $db ) {
		$this->db = $db;
	}

	/**
	 * Save snapshot meta locally
	 *
	 * @param string $id Snapshot ID.
	 * @param array  $meta Snapshot meta.
	 * @return int Number of bytes written
	 */
	abstract public function save_local( string $id, array $meta );

	/**
	 * Get local snapshot meta
	 *
	 * @param  string $id Snapshot ID
	 * @param  string $repository Repository name
	 * @return mixed
	 *
	 * @throws SnapshotsException Snapshot meta invalid.
	 */
	abstract public function get_local( string $id, string $repository );

	/**
	 * Download meta from remote DB
	 *
	 * @param string $id Snapshot ID
	 * @param string $repository Repository name
	 * @param string $region AWS region
	 * @return array
	 */
	public function get_remote( string $id, string $repository, string $region ) : array {
		$snapshot_meta = $this->db->get_snapshot( $id, $repository, $region );

		if ( empty( $snapshot_meta ) ) {
			return [];
		}

		// Backwards compat since these previously were not set.
		if ( ! isset( $snapshot_meta['contains_files'] ) ) {
			$snapshot_meta['contains_files'] = true;
		}

		if ( ! isset( $snapshot_meta['contains_db'] ) ) {
			$snapshot_meta['contains_db'] = true;
		}

		$snapshot_meta['repository'] = $repository;

		return $snapshot_meta;
	}
}
