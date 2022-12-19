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

use TenUp\WPSnapshots\Commands\{Configure, Create, CreateRepository, Delete, Download, Pull, Push, Search};
use TenUp\WPSnapshots\Config;

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

$wpsnapshots_config = new Config();

WP_CLI::add_command( 'wpsnapshots configure', [ new Configure( $wpsnapshots_config ), 'execute' ] );
WP_CLI::add_command( 'wpsnapshots create', [ new Create(), 'execute' ] );
WP_CLI::add_command( 'wpsnapshots create-repository', [ new CreateRepository(), 'execute' ] );
WP_CLI::add_command( 'wpsnapshots delete', [ new Delete(), 'execute' ] );
WP_CLI::add_command( 'wpsnapshots download', [ new Download(), 'execute' ] );
WP_CLI::add_command( 'wpsnapshots pull', [ new Pull(), 'execute' ] );
WP_CLI::add_command( 'wpsnapshots push', [ new Push(), 'execute' ] );
WP_CLI::add_command( 'wpsnapshots search', [ new Search(), 'execute' ] );

// FOR DEVELOPMENT ONLY. Writes the config file to this plugin's directory.
add_filter(
	'wpsnapshots_config_directory',
	function() {
		return __DIR__ . '/.wpsnapshots';
	}
);
