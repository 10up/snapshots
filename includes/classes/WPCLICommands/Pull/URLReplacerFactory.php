<?php
/**
 * URLReplacer class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLICommands\Pull;

use TenUp\Snapshots\Exceptions\WPSnapshotsException;
use TenUp\Snapshots\Infrastructure\Factory;
use TenUp\Snapshots\Log\{LoggerInterface, Logging};
use TenUp\Snapshots\WPSnapshotsDirectory;
use TenUp\Snapshots\WordPress\Database;
use TenUp\Snapshots\WPCLI\Prompt;

/**
 * URLReplacerFactory
 *
 * @package TenUp\Snapshots\WPCLI\Pull
 */
class URLReplacerFactory implements Factory {

	use Logging;

	/**
	 * Prompt instance.
	 *
	 * @var Prompt
	 */
	protected $prompt;

	/**
	 * WPSnapshotsDirectory instance.
	 *
	 * @var WPSnapshotsDirectory
	 */
	protected $snapshots_filesystem;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	protected $wordpress_database;

	/**
	 * Constructor.
	 *
	 * @param Prompt               $prompt Prompt instance.
	 * @param WPSnapshotsDirectory $snapshots_filesystem WPSnapshotsDirectory instance.
	 * @param Database             $wordpress_database Database instance.
	 * @param LoggerInterface      $logger WPCLILogger instance.
	 */
	public function __construct( Prompt $prompt, WPSnapshotsDirectory $snapshots_filesystem, Database $wordpress_database, LoggerInterface $logger ) {
		$this->prompt               = $prompt;
		$this->snapshots_filesystem = $snapshots_filesystem;
		$this->wordpress_database   = $wordpress_database;
		$this->set_logger( $logger );
	}

	/**
	 * Creates a new instance of the class.
	 *
	 * @param array ...$args The arguments.
	 *
	 * @return object
	 *
	 * @throws WPSnapshotsException If the first argument is not 'single' or 'multi'.
	 */
	public function get( ...$args ) : object {
		/**
		 * Whether to create a single-site or multisite URL replacer.
		 *
		 * @var string $single_or_multi
		 */
		$single_or_multi = array_shift( $args );

		if ( ! in_array( $single_or_multi, [ 'single', 'multi' ], true ) ) {
			throw new WPSnapshotsException( 'Invalid argument passed to URLReplacerFactory::get()' );
		}

		if ( 'single' === $single_or_multi ) {
			$instance = new SingleSiteURLReplacer( $this->prompt, $this->snapshots_filesystem, $this->wordpress_database, $this->get_logger(), ...$args );
		} else {
			require_once ABSPATH . 'wp-includes/ms-site.php';
			require_once ABSPATH . 'wp-includes/class-wp-site.php';
			$instance = new MultisiteURLReplacer( $this->prompt, $this->snapshots_filesystem, $this->wordpress_database, $this->get_logger(), ...$args );
		}

		return $instance;
	}
}
