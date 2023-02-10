<?php

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

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
