<?php
/**
 * FileZipper class
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\Infrastructure\SharedService;
use TenUp\Snapshots\SnapshotsDirectory;

use function TenUp\Snapshots\Utils\snapshots_wp_content_dir;

/**
 * Class FileZipper
 *
 * @package TenUp\Snapshots
 */
class FileZipper implements SharedService {

	/**
	 * SnapshotsDirectory instance.
	 *
	 * @var SnapshotsDirectory
	 */
	private $snapshot_files;

	/**
	 * Class constructor.
	 *
	 * @param SnapshotsDirectory $snapshot_files SnapshotsDirectory instance.
	 */
	public function __construct( SnapshotsDirectory $snapshot_files ) {
		$this->snapshot_files = $snapshot_files;
	}

	/**
	 * Zips up the files in the wp-content directory.
	 *
	 * @param string $id  Snapshot ID.
	 * @param array  $args Snapshot arguments.
	 *
	 * @return int The size of the created file.
	 *
	 * @throws SnapshotsException If could not create zip.
	 */
	public function zip_files( string $id, array $args ) : int {
		$this->snapshot_files->create_directory( $id );

		$excludes = [];

		$command = 'cd ' . escapeshellarg( snapshots_wp_content_dir() ) . '/ && tar ';

		if ( ! empty( $args['exclude_uploads'] ) ) {
			$excludes[] = './uploads';
		}

		if ( ! empty( $args['excludes'] ) ) {
			foreach ( $args['excludes'] as $exclude ) {
				$exclude = trim( $exclude );

				if ( ! preg_match( '#^\./.*#', $exclude ) ) {
					$exclude = './' . $exclude;
				}

				$excludes[] = $exclude;
			}
		}

		if ( true !== $args['include_node_modules'] ) {
			// Exclude all node_modules directories as a pattern, including the root one.
			$excludes[] = './node_modules';
			$excludes[] = './**/node_modules';
		}

		if ( ! empty( $args['exclude_vendor'] ) ) {
			// Exclude all vendor directories.
			$excludes[] = './vendor';
			$excludes[] = './**/vendor';
		}

		if ( ! empty( $excludes ) ) {
			$command .= '--exclude=' . implode( ' --exclude=', array_map( 'escapeshellarg', $excludes ) ) . ' ';
		}

		$command .= '-czf ' . escapeshellarg( $this->snapshot_files->get_file_path( 'files.tar.gz', $id ) ) . ' .';

		$exit_code = 0;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( $command, $output, $exit_code );

		if ( 0 !== $exit_code ) {
			throw new SnapshotsException( 'Could not create zip file.' );
		}

		return $this->snapshot_files->get_file_size( 'files.tar.gz', $id );
	}
}
