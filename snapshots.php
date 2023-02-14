<?php
/**
 * Plugin Name: 10up Snapshots
 * Plugin URI: https://github.com/10up/snapshots
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

if ( ! defined( 'TENUP_SNAPSHOTS_DIR' ) ) {
	require __DIR__ . '/command.php';
}
