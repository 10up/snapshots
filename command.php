<?php
/**
 * Command entry point.
 *
 * @package TenUp\Snapshots
 */

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( ! defined( 'TENUP_SNAPSHOTS_DIR' ) ) {
	define( 'TENUP_SNAPSHOTS_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/utils.php';

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
