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

WP_CLI::add_hook( 'after_wp_load', 'TenUp\\Snapshots\\Utils\\tenup_snapshots' );
