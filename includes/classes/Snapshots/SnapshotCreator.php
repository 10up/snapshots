<?php
/**
 * SnapshotCreator class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\SharedService;
use TenUp\WPSnapshots\Log\LoggerInterface;
use TenUp\WPSnapshots\Log\Logging;
use TenUp\WPSnapshots\SnapshotFiles;

/**
 * Creates snapshots.
 *
 * @package TenUp\WPSnapshots\Snapshots
 */
class SnapshotCreator implements SharedService {

	use Logging;

	/**
	 * SnapshotsFileSystem instance.
	 *
	 * @var SnapshotFiles
	 */
	private $snapshot_files;

	/**
	 * SnapshotMetaInterface instance
	 *
	 * @var SnapshotMetaInterface
	 */
	private $meta;

	/**
	 * DumperInterface instance.
	 *
	 * @var DumperInterface
	 */
	private $dumper;

	/**
	 * FileZipper instance.
	 *
	 * @var FileZipper
	 */
	private $file_zipper;

	/**
	 * Class constructor.
	 *
	 * @param SnapshotMetaInterface $meta Meta instance.
	 * @param SnapshotFiles         $snapshot_files SnapshotFiles instance.
	 * @param DumperInterface       $dumper DumperInterface instance.
	 * @param FileZipper            $file_zipper FileZipper instance.
	 * @param LoggerInterface       $logger LoggerInterface instance.
	 */
	public function __construct( SnapshotMetaInterface $meta, SnapshotFiles $snapshot_files, DumperInterface $dumper, FileZipper $file_zipper, LoggerInterface $logger ) {
		$this->meta           = $meta;
		$this->snapshot_files = $snapshot_files;
		$this->dumper         = $dumper;
		$this->file_zipper    = $file_zipper;
		$this->set_logger( $logger );
	}

	/**
	 * Create a snapshot.
	 *
	 * @param array $args List of arguments
	 *
	 * @throws WPSnapshotsException Throw exception if snapshot can't be created.
	 */
	public function create( array $args ) {
		if ( ! $args['contains_db'] && ! $args['contains_files'] ) {
			throw new WPSnapshotsException( 'Snapshot must contain either database or files.' );
		}

		/**
		 * Define snapshot ID
		 */
		$id = md5( time() . wp_rand() );

		$this->snapshot_files->create_directory( $id );

		if ( $args['contains_db'] ) {
	//		$this->dumper->dump( $id, $args );
	//		$args['db_size'] = $this->snapshot_files->get_file_size( 'data.sql.zip' );
		}

		if ( $args['contains_files'] ) {
			$this->log( 'Saving files...' );

			$this->file_zipper->zip_files( $id, $args );
			$args['files_size'] = $this->snapshot_files->get_file_size( 'files.tar.gz', $id );
		}

		/**
		 * Finally save snapshot meta to meta.json
		 */
		$this->meta->generate( $id, $args );
	}
}
