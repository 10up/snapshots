<?php
/**
 * URLReplacer class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands\Pull;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\{Factory, Service, Shared};
use TenUp\WPSnapshots\Log\{LoggerInterface, Logging};
use TenUp\WPSnapshots\SnapshotsFileSystem;
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\WPCLI\Prompt;

/**
 * URLReplacerFactory
 *
 * @package TenUp\WPSnapshots\WPCLI\Pull
 */
final class URLReplacerFactory implements Factory, Shared, Service {

	use Logging;

	/**
	 * Prompt instance.
	 *
	 * @var Prompt
	 */
	protected $prompt;

	/**
	 * SnapshotsFileSystem instance.
	 *
	 * @var SnapshotsFileSystem
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
	 * @param Prompt              $prompt Prompt instance.
	 * @param SnapshotsFileSystem $snapshots_filesystem SnapshotsFileSystem instance.
	 * @param Database            $wordpress_database Database instance.
	 * @param LoggerInterface     $logger WPCLILogger instance.
	 */
	public function __construct( Prompt $prompt, SnapshotsFileSystem $snapshots_filesystem, Database $wordpress_database, LoggerInterface $logger ) {
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
			$instance = new SingleSiteURLReplacer( $this->prompt, $this->snapshots_filesystem, $this->wordpress_database, ...$args );
		} else {
			$instance = new MultisiteURLReplacer( $this->prompt, $this->snapshots_filesystem, $this->wordpress_database, ...$args );
		}

		$instance->set_logger( $this->get_logger() );

		return $instance;
	}
}
