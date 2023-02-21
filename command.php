<?php
/**
 * Command entry point.
 *
 * @package TenUp\Snapshots
 */

use function TenUp\Snapshots\Utils\snapshots;

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( ! defined( 'TENUP_SNAPSHOTS_DIR' ) ) {
	define( 'TENUP_SNAPSHOTS_DIR', __DIR__ );
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/utils.php';

snapshots();
