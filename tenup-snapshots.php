<?php
/**
 * Plugin Name: 10up Snapshots
 * Plugin URI: https://github.com/10up/tenup-snapshots
 * Description: A WordPress plugin to manage snapshots of your WordPress site.
 * Version: 0.1.0
 * Author: 10up
 * Author URI: https://get10up.com
 * License: MIT
 *
 * @package TenUp\Snapshots
 */

/**
 * Plugin entry file.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/utils.php';

if ( ! defined( 'TENUP_SNAPSHOTS_DIR' ) ) {
	define( 'TENUP_SNAPSHOTS_DIR', __DIR__ );
}

/**
 * Provides the Plugin instance.
 *
 * @return TenUp\Snapshots\Plugin
 */
function tenup_snapshots() {
	static $plugin;

	if ( ! $plugin ) {
		$plugin = new TenUp\Snapshots\Plugin();
		$plugin->register();
	}

	return $plugin;
}

tenup_snapshots();
