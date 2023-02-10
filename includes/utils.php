<?php
/**
 * Utility functions
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Utils;

use TenUp\Snapshots\Exceptions\WPSnapshotsException;
use TenUp\Snapshots\Plugin;
use WP_CLI;

/**
 * Provides the Plugin instance.
 *
 * @return TenUp\Snapshots\Plugin
 */
function tenup_snapshots() {
	static $plugin;

	if ( ! $plugin ) {
		$plugin = new Plugin();
		$plugin->register();
	}

	return $plugin;
}

/**
 * Provides an object wrapping WP_CLI and WP_CLI\Utils functions.
 *
 * @see https://github.com/wp-cli/wp-cli/blob/main/php/class-wp-cli.php
 * @see https://github.com/wp-cli/wp-cli/blob/main/php/utils.php
 *
 * @return object
 */
function wp_cli() : object {

	/**
	 * Filters the WP_CLI wrapper object.
	 *
	 * @param object $wp_cli Class that wraps WP_CLI and WP_CLI\Utils functions as static methods.
	 */
	return apply_filters(
		'tenup_snapshots_wpcli',
		new class() {

			/**
			 * Magic method to call WP_CLI\Utils and WP_CLI functions.
			 *
			 * @param string $name      Function name.
			 * @param array  $arguments Function arguments.
			 *
			 * @return mixed
			 *
			 * @throws WPSnapshotsException If the function does not exist.
			 */
			public static function __callStatic( string $name, array $arguments ) {
				$util = '\\WP_CLI\\Utils\\' . $name;

				if ( function_exists( $util ) ) {
					return call_user_func_array( $util, $arguments );
				}

				if ( method_exists( WP_CLI::class, $name ) ) {
					return call_user_func_array( [ WP_CLI::class, $name ], $arguments );
				}

				throw new WPSnapshotsException( sprintf( 'WP_CLI function %s does not exist.', $name ) );
			}
		}
	);
}

/**
 * Returns the path to the wp-content directory.
 *
 * @return string
 *
 * @throws WPSnapshotsException If WP_CONTENT_DIR is not defined.
 */
function tenup_snapshots_wp_content_dir() : string {
	if ( ! defined( 'WP_CONTENT_DIR' ) ) {
		throw new WPSnapshotsException( 'WP_CONTENT_DIR is not defined.' );
	}

	/**
	 * Filters the path to the wp-content directory.
	 *
	 * @param string $wp_content_dir Path to the wp-content directory.
	 */
	return apply_filters( 'tenup_snapshots_wp_content_dir', WP_CONTENT_DIR );
}

