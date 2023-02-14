<?php
/**
 * Command entry point.
 *
 * @package TenUp\Snapshots
 */

use TenUp\Snapshots\WPCLI\WPCLICommand;

use function TenUp\Snapshots\Utils\tenup_snapshots;

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( ! defined( 'TENUP_SNAPSHOTS_DIR' ) ) {
	define( 'TENUP_SNAPSHOTS_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/utils.php';

if ( defined( 'ABSPATH' ) ) {
	WP_CLI::add_hook( 'after_wp_load', 'TenUp\\Snapshots\\Utils\\tenup_snapshots' );
	return;
}

/**
 * Outside a WP installation, still add commands to display info.
 */

/**
 * Shim apply filters because it's used when boostrapping commands.
 */
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Shim apply filters.
	 *
	 * @param string $tag The name of the filter hook.
	 * @param mixed  $value The value on which the filters hooked to `$tag` are applied on.
	 */
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

$tenup_snapshots = tenup_snapshots( false );

foreach ( $tenup_snapshots::get_commands() as $command_class ) {
	/**
	 * WPCLICommand instance.
	 *
	 * @var WPCLICommand $instance
	 */
	$command_instance = $tenup_snapshots->get_instance( $command_class );

	WP_CLI::add_command(
		'snapshots ' . $command_instance->get_command(),
		function() {},
		$command_instance->get_command_parameters()
	);
}
