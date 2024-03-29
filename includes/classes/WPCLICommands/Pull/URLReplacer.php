<?php
/**
 * URLReplacer class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLICommands\Pull;

use TenUp\Snapshots\Exceptions\SnapshotsInputValidationException;
use TenUp\Snapshots\Log\LoggerInterface;
use TenUp\Snapshots\Log\Logging;
use TenUp\Snapshots\SnapshotsDirectory;
use TenUp\Snapshots\WordPress\Database;
use TenUp\Snapshots\WPCLI\Prompt;

use function TenUp\Snapshots\Utils\wp_cli;

/**
 * URLReplacer
 *
 * @package TenUp\Snapshots\WPCLI
 */
abstract class URLReplacer {

	use Logging;

	/**
	 * New home URL.
	 *
	 * @var string
	 */
	protected $new_home_url;

	/**
	 * Prompt instance.
	 *
	 * @var Prompt
	 */
	protected $prompt;

	/**
	 * SnapshotsDirectory instance.
	 *
	 * @var SnapshotsDirectory
	 */
	protected $snapshots_filesystem;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	protected $wordpress_database;

	/**
	 * Snapshot metadata.
	 *
	 * @var array
	 */
	protected $meta;

	/**
	 * Site mapping.
	 *
	 * @var array
	 */
	protected $site_mapping;

	/**
	 * Skipped tables.
	 *
	 * @var array
	 */
	protected $skip_table_search_replace;

	/**
	 * Update multiste constants.
	 *
	 * @var bool
	 */
	protected $update_multisite_constants;

	/**
	 * Main domain.
	 *
	 * @var ?string
	 */
	protected $main_domain;

	/**
	 * Constructor.
	 *
	 * @param Prompt             $prompt Prompt instance.
	 * @param SnapshotsDirectory $snapshots_filesystem SnapshotsDirectory instance.
	 * @param Database           $wordpress_database Database instance.
	 * @param LoggerInterface    $logger WPCLILogger instance.
	 * @param array              $meta The snapshot meta.
	 * @param array              $site_mapping The site mapping.
	 * @param array              $skip_table_search_replace The tables to skip.
	 * @param bool               $update_multisite_constants Whether to update multisite constants.
	 * @param ?string            $main_domain The main domain.
	 */
	public function __construct(
		Prompt $prompt,
		SnapshotsDirectory $snapshots_filesystem,
		Database $wordpress_database,
		LoggerInterface $logger,
		array $meta,
		array $site_mapping,
		array $skip_table_search_replace = [],
		bool $update_multisite_constants = false,
		?string $main_domain = null
	) {
		$this->prompt                     = $prompt;
		$this->snapshots_filesystem       = $snapshots_filesystem;
		$this->wordpress_database         = $wordpress_database;
		$this->meta                       = $meta;
		$this->site_mapping               = $site_mapping;
		$this->skip_table_search_replace  = $skip_table_search_replace;
		$this->update_multisite_constants = $update_multisite_constants;
		$this->main_domain                = $main_domain;
		$this->set_logger( $logger );
	}

	/**
	 * Replaces URLs.
	 *
	 * @return string The Home URL.
	 */
	abstract public function replace_urls() : string;

	/**
	 * Run search and replace.
	 *
	 * @param string $search The search string.
	 * @param string $replace The replace string.
	 * @param array  $tables_to_update The tables to update.
	 * @param bool   $multisite Whether to run multisite search and replace.
	 */
	protected function run_search_and_replace( string $search, string $replace, array $tables_to_update, bool $multisite = false ) : void {
		$command = 'search-replace ' . $search . ' ' . $replace . ' ' . implode( ' ', $tables_to_update ) . ' --precise --skip-themes --skip-plugins --skip-packages';

		if ( $multisite ) {
			$command .= ' --network';
		}

		// Run search and replace.
		wp_cli()::runcommand(
			$command,
			[
				'launch'     => true,
				'exit_error' => false,
				'return'     => 'all',
			]
		);
	}

	/**
	 * URL validator.
	 *
	 * @param string $url The URL to validate.
	 *
	 * @return string
	 *
	 * @throws SnapshotsInputValidationException If the URL is not valid.
	 */
	protected function url_validator( string $url ) : string {
		if ( '' === trim( $url ) || false !== strpos( $url, ' ' ) || ! preg_match( '#https?://#i', $url ) ) {
			throw new SnapshotsInputValidationException(
				'URL not valid. The URL should be in the form of `https://google.com`, no trailing slash needed'
			);
		}

		return $url;
	}
}
