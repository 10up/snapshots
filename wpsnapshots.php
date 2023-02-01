<?php
/**
 * Plugin Name: WP Snapshots
 * Plugin URI: https://github.com/10up/snapshots-command
 * Description: A WordPress plugin to manage snapshots of your WordPress site.
 * Version: 0.1.0
 * Author: 10up
 * Author URI: https://get10up.com
 * License: MIT
 *
 * @package TenUp\WPSnapshots
 */

/**
 * Plugin entry file.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/utils.php';

if ( ! defined( 'WPSNAPSHOTS_DIR' ) ) {
	define( 'WPSNAPSHOTS_DIR', __DIR__ );
}

if ( ! defined( 'WPSNAPSHOTS_USE_FILE_SYSTEM' ) ) {
	define( 'WPSNAPSHOTS_USE_FILE_SYSTEM', true );
}

/**
 * Provides the Plugin instance.
 *
 * @return TenUp\WPSnapshots\Plugin
 */
function wpsnapshots() {
	static $plugin;

	if ( ! $plugin ) {
		$plugin = new TenUp\WPSnapshots\Plugin();
		$plugin->register();
	}

	return $plugin;
}

wpsnapshots();
