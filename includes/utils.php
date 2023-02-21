<?php
/**
 * Utility functions
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Utils;

use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\Snapshots;
use WP_CLI;

/**
 * Provides the Snapshots instance.
 *
 * @return Snapshots
 */
function snapshots() : Snapshots {
	static $snapshots;

	if ( ! $snapshots ) {
		$snapshots = new Snapshots();

		$snapshots->register();
	}

	return $snapshots;
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
	return snapshots_apply_filters(
		'snapshots_wpcli',
		new class() {

			/**
			 * Magic method to call WP_CLI\Utils and WP_CLI functions.
			 *
			 * @param string $name      Function name.
			 * @param array  $arguments Function arguments.
			 *
			 * @return mixed
			 *
			 * @throws SnapshotsException If the function does not exist.
			 */
			public static function __callStatic( string $name, array $arguments ) {
				$util = '\\WP_CLI\\Utils\\' . $name;

				if ( function_exists( $util ) ) {
					return call_user_func_array( $util, $arguments );
				}

				if ( method_exists( WP_CLI::class, $name ) ) {
					return call_user_func_array( [ WP_CLI::class, $name ], $arguments );
				}

				throw new SnapshotsException( sprintf( 'WP_CLI function %s does not exist.', $name ) );
			}
		}
	);
}

/**
 * Returns the path to the wp-content directory.
 *
 * @return string
 *
 * @throws SnapshotsException If WP_CONTENT_DIR is not defined.
 */
function snapshots_wp_content_dir() : string {
	if ( ! defined( 'WP_CONTENT_DIR' ) ) {
		throw new SnapshotsException( 'WP_CONTENT_DIR is not defined.' );
	}

	/**
	 * Filters the path to the wp-content directory.
	 *
	 * @param string $wp_content_dir Path to the wp-content directory.
	 */
	return apply_filters( 'snapshots_wp_content_dir', WP_CONTENT_DIR );
}

/**
 * Shims apply filters in contexts where it is not available.
 *
 * This mainly allows the command arguments to be displayed if WP is not bootstrapped.
 *
 * @param string $tag The name of the filter hook.
 * @param mixed  $value The value on which the filters hooked to `$tag` are applied on.
 *
 * @return mixed The filtered value after all hooked functions are applied to it.
 */
function snapshots_apply_filters( string $tag, $value ) {
	if ( function_exists( 'apply_filters' ) ) {
		return apply_filters( $tag, $value );
	}

	return $value;
}

/**
 * Removes the trailing slash from a string.
 *
 * @param string $string The string to remove the trailing slash from.
 *
 * @return string The string without the trailing slash.
 */
function snapshots_remove_trailing_slash( string $string ) : string {
	return rtrim( $string, '/' );
}

/**
 * Adds a trailing slash to a string.
 *
 * @param string $string The string to add the trailing slash to.
 *
 * @return string The string with the trailing slash.
 */
function snapshots_add_trailing_slash( string $string ) : string {
	return rtrim( $string, '/' ) . '/';
}
