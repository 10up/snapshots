<?php
/**
 * Class handling reading and writing to the WP Snapshots directory.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Data;

use function TenUp\WPSnapshots\Utils\error;

/**
 * Class FromFileSystem
 *
 * @package TenUp\WPSnapshots
 */
class FromFileSystem implements DataHandler {

	/**
	 * Saves data as JSON.
	 *
	 * @param string $name Unique identifier for the JSON.
	 * @param mixed  $data Data to save.
	 */
	public function save_json( string $name, $data ) {
		$directory = $this->get_snapshots_directory();

		$file = $directory . '/' . $name . '.json';

		if ( false === file_put_contents( $file, wp_json_encode( $data ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			error( 'Unable to write to ' . $file );
		}
	}

	/**
	 * Loads JSON data.
	 *
	 * @param string $name Unique identifier for the JSON.
	 * @return mixed $data Loaded data.
	 */
	public function load_json( string $name ) {
		$directory = $this->get_snapshots_directory();

		$file = $directory . '/' . $name . '.json';

		if ( ! file_exists( $file ) ) {
			return null;
		}

		$data = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! is_array( $data ) ) {
			error( sprintf( 'File %s is not valid JSON.', $file ) );
		}

		return $data;
	}

	/**
	 * Gets the snapshots directory.
	 *
	 * @return string $file Snapshots directory.
	 */
	private function get_snapshots_directory() : string {

		/**
		 * Filters the configuration directory.
		 *
		 * @param string $file Snapshots directory.
		 */
		$directory = apply_filters(
			'wpsnapshots_config_directory',
			strlen( getenv( 'WPSNAPSHOTS_DIR' ) ) > 0 ? getenv( 'WPSNAPSHOTS_DIR' ) : $_SERVER['HOME'] . '/.wpsnapshots'
		);

		if ( ! is_dir( $directory ) ) {
			if ( ! mkdir( $directory, 0755, true ) ) {
				error( 'Unable to create ' . $directory );
			}
		}

		return $directory;
	}
}
