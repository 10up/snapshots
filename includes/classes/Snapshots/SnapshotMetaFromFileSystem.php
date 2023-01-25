<?php
/**
 * Snapshot meta class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Snapshots\DBConnectorInterface;
use TenUp\WPSnapshots\SnapshotsFiles;

/**
 * Snapshot meta wrapper with support for downloading remote meta
 *
 * @package TenUp\WPSnapshots\Snapshots
 */
class SnapshotMetaFromFileSystem extends SnapshotMeta {

	/**
	 * SnapshotsFiles instance.
	 *
	 * @var SnapshotsFiles
	 */
	private $snapshots_file_system;

	/**
	 * Meta constructor
	 *
	 * @param DBConnectorInterface $db Database connector instance.
	 * @param SnapshotsFiles  $snapshots_file_system SnapshotsFiles instance.
	 */
	public function __construct( DBConnectorInterface $db, SnapshotsFiles $snapshots_file_system ) {
		parent::__construct( $db );

		$this->snapshots_file_system = $snapshots_file_system;
	}

	/**
	 * Save snapshot meta locally
	 *
	 * @param string $id Snapshot ID.
	 * @param array  $meta Snapshot meta.
	 * @return int Number of bytes written
	 */
	public function save_local( string $id, array $meta ) {
		$this->snapshots_file_system->update_file_contents( 'meta.json', wp_json_encode( $meta ), false, $id );

		return $this->snapshots_file_system->get_file_size( 'meta.json', $id );
	}

	/**
	 * Get local snapshot meta
	 *
	 * @param  string $id Snapshot ID
	 * @param  string $repository Repository name
	 * @return mixed
	 *
	 * @throws WPSnapshotsException Snapshot meta invalid.
	 */
	public function get_local( string $id, string $repository ) {
		try {
			$meta_file_contents = $this->snapshots_file_system->get_file_contents( 'meta.json', $id );
		} catch ( WPSnapshotsException $e ) {
			return [];
		}

		$meta = json_decode( $meta_file_contents, true );

		if ( $repository !== $meta['repository'] ) {
			return false;
		}

		// Backwards compat since these previously were not set.
		if ( ! isset( $meta['contains_files'] ) && $this->snapshots_file_system->file_exists( 'files.tar.gz', $id ) ) {
			$meta['contains_files'] = true;
		} if ( ! isset( $meta['contains_db'] ) && $this->snapshots_file_system->file_exists( 'db.sql.gz', $id ) ) {
			$meta['contains_db'] = true;
		}

		if ( empty( $meta['contains_files'] ) && empty( $meta['contains_db'] ) ) {
			throw new WPSnapshotsException( 'Snapshot meta invalid.' );
		}

		return $meta;
	}
}
