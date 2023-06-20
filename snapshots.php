<?php
/**
 * Plugin Name: 10up Snapshots
 * Plugin URI: https://github.com/10up/snapshots
 * Description: A WordPress plugin to manage snapshots of your WordPress site.
 * Version: 1.1.0
 * Author: 10up
 * Author URI: https://10up.com
 * License: MIT
 *
 * @package TenUp\Snapshots
 */

/**
 * Snapshots entry file.
 */

if ( ! defined( 'TENUP_SNAPSHOTS_DIR' ) ) {
	require __DIR__ . '/command.php';
}
