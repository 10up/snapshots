

<?php
/**
 * PHPUnit bootstrap file
 *
 */

define( 'TESTS_PLUGIN_DIR', dirname( dirname( __DIR__ ) ) );

// When run in wp-env context, set the test config file path.
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) && false !== getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
    define( 'WP_TESTS_CONFIG_FILE_PATH', getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) );
}

require_once TESTS_PLUGIN_DIR . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';
$_tests_dir = getenv( 'WP_TESTS_DIR' );
require_once $_tests_dir . '/includes/functions.php'; 

if ( ! function_exists( 'tests_add_filter' ) ) {
	function tests_add_filter( ...$args ) {}

	throw new Exception( 'Unable to load the WP test suite.' );
}

if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true );
}

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require_once dirname( __DIR__, 2 ) . '/wpsnapshots.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/*
 * Load WP CLI. Its test bootstrap file can't be required as it will load
 * duplicate class names which are already in use.
 */
define( 'WP_CLI_ROOT', TESTS_PLUGIN_DIR . '/vendor/wp-cli/wp-cli' );
define( 'WP_CLI_VENDOR_DIR', TESTS_PLUGIN_DIR . '/vendor' );

if ( file_exists( WP_CLI_ROOT . '/php/utils.php' ) ) {
	require_once WP_CLI_ROOT . '/php/utils.php';

	$logger = new WP_CLI\Loggers\Regular( true );
	WP_CLI::set_logger( $logger );
}

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Require all files in the fixtures directory.
foreach ( glob( __DIR__ . '/fixtures/*.php' ) as $file ) {
	require_once $file;
}
