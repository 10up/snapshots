<?php
/**
 * Snapshot meta abstract class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\{Service, Shared};
use TenUp\WPSnapshots\Snapshots\AWSAuthentication;
use TenUp\WPSnapshots\Snapshots\DBConnectorInterface;

/**
 * Snapshot meta wrapper with support for downloading remote meta
 *
 * @package TenUp\WPSnapshots\Snapshots
 */
abstract class SnapshotMeta implements Shared, Service, SnapshotMetaInterface {

	/**
	 * Database connector instance.
	 *
	 * @var DBConnectorInterface
	 */
	protected $db;

	/**
	 * Snapshot meta data
	 *
	 * @var array
	 */
	protected $meta = [];

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
	 * @throws WPSnapshotsException Snapshot meta invalid.
	 */
	abstract public function get_local( string $id, string $repository );

	/**
	 * Download meta from remote DB
	 *
	 * @param string            $id Snapshot ID
	 * @param AWSAuthentication $aws_authentication AWS authentication instance.
	 * @return array
	 */
	public function get_remote_meta( string $id, AWSAuthentication $aws_authentication ) {
		$snapshot_meta = $this->db->get_snapshot( $id, $aws_authentication );

		// Backwards compat since these previously were not set.
		if ( ! isset( $snapshot_meta['contains_files'] ) ) {
			$snapshot_meta['contains_files'] = true;
		} if ( ! isset( $snapshot_meta['contains_db'] ) ) {
			$snapshot_meta['contains_db'] = true;
		}

		$snapshot_meta['repository'] = $aws_authentication->get_repository();

		return $snapshot_meta;
	}
}
