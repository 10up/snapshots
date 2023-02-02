<?php
/**
 * SnapshotCreator class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\SharedService;
use TenUp\WPSnapshots\Log\{LoggerInterface, Logging};

/**
 * Creates snapshots.
 *
 * @package TenUp\WPSnapshots\Snapshots
 */
class SnapshotCreator implements SharedService {

	use Logging;

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
	 * @param DumperInterface       $dumper DumperInterface instance.
	 * @param FileZipper            $file_zipper FileZipper instance.
	 * @param LoggerInterface       $logger LoggerInterface instance.
	 */
	public function __construct( SnapshotMetaInterface $meta, DumperInterface $dumper, FileZipper $file_zipper, LoggerInterface $logger ) {
		$this->meta        = $meta;
		$this->dumper      = $dumper;
		$this->file_zipper = $file_zipper;
		$this->set_logger( $logger );
		$this->file_zipper->set_logger( $logger );
	}

	/**
	 * Create a snapshot.
	 *
	 * @param array   $args List of arguments
	 * @param ?string $id A snapshot ID to use. If not provided, a random one will be generated.
	 *
	 * @return string Snapshot ID
	 *
	 * @throws WPSnapshotsException Throw exception if snapshot can't be created.
	 */
	public function create( array $args, ?string $id = null ) : string {
		if ( empty( $args['contains_db'] ) && empty( $args['contains_files'] ) ) {
			throw new WPSnapshotsException( 'Snapshot must contain either database or files.' );
		}

		/**
		 * Define snapshot ID
		 */
		if ( ! $id ) {
			$id = md5( time() . wp_rand() );
		}

		if ( $args['contains_db'] ) {
			$this->log( 'Saving database...' );

			$args['db_size'] = $this->dumper->dump( $id, $args );
		}

		if ( $args['contains_files'] ) {
			$this->log( 'Saving files...' );

			$args['files_size'] = $this->file_zipper->zip_files( $id, $args );
		}

		/**
		 * Finally save snapshot meta to meta.json
		 */
		$this->meta->generate( $id, $args );

		return $id;
	}
}
