<?php
/**
 * Command entry point.
 *
 * @package TenUp\Snapshots
 */

use function TenUp\Snapshots\Utils\tenup_snapshots;

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( ! defined( 'TENUP_SNAPSHOTS_DIR' ) ) {
	define( 'TENUP_SNAPSHOTS_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/utils.php';

tenup_snapshots();
