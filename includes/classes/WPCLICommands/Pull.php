<?php
/**
 * Pull command class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands;

use Exception;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\WPCLI\WPCLICommand;
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacerFactory;

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Pull command
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
final class Pull extends WPCLICommand {

	/**
	 * URLReplacer instance.
	 *
	 * @var URLReplacerFactory
	 */
	private $url_replacer_factory;

	/**
	 * The new home URL.
	 *
	 * @var ?string
	 */
	private $new_home_url;

	/**
	 * Meta data for the snapshot.
	 *
	 * @var ?array
	 */
	private $meta;

	/**
	 * Whether to download the snapshot.
	 *
	 * @var ?bool
	 */
	private $should_download;

	/**
	 * Main domain.
	 *
	 * @var ?string
	 */
	private $main_domain;

	/**
	 * Class constructor.
	 *
	 * @param URLReplacerFactory $url_replacer URLReplacer instance.
	 * @param array              ...$args Dependency injection arguments.
	 */
	public function __construct( URLReplacerFactory $url_replacer, ...$args ) {
		parent::__construct( ...$args );

		$this->url_replacer_factory = $url_replacer;
	}

	/**
	 * Callback for the command.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 *
	 * @throws WPSnapshotsException If there is an error.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );

			$this->log( 'Security Warning: WP Snapshots creates copies of your codebase and database. This could result in data retention policy issues, please exercise extreme caution when using production data.', 'warning' );

			$meta = $this->get_meta();

			$include_db    = $meta['contains_db'] && $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_db', 'Include database in snapshot?' );
			$include_files = $meta['contains_files'] && $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_files', 'Include files in snapshot?' );

			if ( ! $include_db && ! $include_files ) {
				throw new WPSnapshotsException( 'You must include either the DB, the files, or both.' );
			}

			if ( ! wp_cli()::get_flag_value( $this->get_assoc_args(), 'confirm' ) ) {
				wp_cli()::confirm( 'Are you sure you want to pull this snapshot? This is a potentially destructive operation. Please run a backup first.' );
			}

			foreach ( $this->get_pull_actions( $include_db, $include_files ) as $action ) {
				$action();
			}

			$this->new_home_url = $this->new_home_url ? $this->new_home_url : home_url();

			$this->log( 'Snapshot pulled successfully.', 'success' );
			$this->log( 'Visit in your browser: ' . $this->new_home_url, 'success' );

			if ( 'localhost' !== wp_parse_url( $this->new_home_url, PHP_URL_HOST ) ) {
				$this->log( 'Make sure the following entry is in your hosts file: "127.0.0.1 ' . wp_parse_url( $this->new_home_url, PHP_URL_HOST ) . '"', 'success' );
			}

			$this->log( 'Admin login: username - "wpsnapshots", password - "password"', 'success' );
		} catch ( Exception $e ) {
			wp_cli()::error( $e->getMessage() );
		}
	}

	/**
	 * Returns the command name.
	 *
	 * @inheritDoc
	 */
	public function get_command() : string {
		return 'pull';
	}

	/**
	 * Provides command parameters.
	 *
	 * @inheritDoc
	 */
	protected function get_command_parameters() : array {
		return [
			'shortdesc' => 'Pull a snapshot into a WordPress instance',
			'synopsis'  => [
				[
					'type'        => 'positional',
					'name'        => 'snapshot_id',
					'description' => 'Snapshot ID to pull.',
					'optional'    => false,
				],
				[
					'type'        => 'assoc',
					'name'        => 'repository',
					'description' => 'Repository to use. Defaults to 10up.',
					'optional'    => true,
				],
				[
					'type'        => 'assoc',
					'name'        => 'region',
					'description' => 'AWS region to use. Defaults to us-west-1.',
					'optional'    => true,
					'default'     => 'us-west-1',
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm',
					'description' => 'Confirm pull operation.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm_wp_download',
					'description' => 'Confirm WordPress download.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm_config_create',
					'description' => 'Confirm wp-config.php creation.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm_wp_version_change',
					'description' => 'Confirm WordPress version change.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'confirm_ms_constant_update',
					'description' => 'Confirm multisite constant update.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'suppress_instructions',
					'description' => 'Suppress instructions after successful installation.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'overwrite_local_copy',
					'description' => 'Overwrite local copy of snapshot.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'include_files',
					'description' => 'Include files in snapshot.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'include_db',
					'description' => 'Include database in snapshot.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'assoc',
					'name'        => 'skip_table_search_replace',
					'description' => 'Skip search and replacing specific tables. Enter a comma-separated list, leaving out the table prefix.',
					'optional'    => true,
					'default'     => '',
				],
				[
					'type'        => 'assoc',
					'name'        => 'site_mapping',
					'description' => 'JSON or path to site mapping file.',
					'optional'    => true,
				],
				[
					'type'        => 'assoc',
					'name'        => 'main_domain',
					'description' => 'Main domain for multisite snapshots.',
					'optional'    => true,
				],
			],
		];
	}

	/**
	 * Gets the snapshot meta.
	 *
	 * @return array
	 */
	private function get_meta() {
		if ( is_null( $this->meta ) ) {
			$this->set_up_meta();
		}

		return $this->meta;
	}

	/**
	 * Gets whether the snapshot should be downloaded.
	 *
	 * @return bool
	 */
	private function get_should_download() {
		if ( is_null( $this->should_download ) ) {
			$this->set_up_meta();
		}

		return $this->should_download;
	}

	/**
	 * Gets the snapshot meta.
	 *
	 * @throws WPSnapshotsException If the snapshot does not exist.
	 */
	private function set_up_meta() {
		if ( ! is_null( $this->meta ) && ! is_null( $this->should_download ) ) {
			return;
		}

		$id              = $this->get_id();
		$repository_name = $this->get_repository_name();

		$remote_meta = $this->snapshot_meta->get_remote( $id, $repository_name, $this->get_assoc_arg( 'region' ) );
		$local_meta  = $this->snapshot_meta->get_local( $id, $repository_name );

		switch ( true ) {
			case ! empty( $remote_meta ) && ! empty( $local_meta ):
				$this->should_download = $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'overwrite_local_copy', 'Snapshot already exists locally. Overwrite?', $this->get_default_arg_value( 'overwrite_local_copy' ) );
				$this->meta            = $this->should_download ? $remote_meta : $local_meta;
				break;
			case ! empty( $remote_meta ) && empty( $local_meta ):
				$this->meta            = $remote_meta;
				$this->should_download = true;
				break;
			case empty( $remote_meta ) && ! empty( $local_meta ):
				$this->meta            = $local_meta;
				$this->should_download = false;
				break;
			default:
				throw new WPSnapshotsException( 'Snapshot does not exist.' );
		}

		if ( empty( $this->meta ) || ( empty( $this->meta['contains_files'] ) && empty( $this->meta['contains_db'] ) ) ) {
			throw new WPSnapshotsException( 'Snapshot is not valid.' );
		}
	}

	/**
	 * Gets the actions required for the pull. These actions are collected ahead of time to ensure all checks are performed
	 * before any modifications to the file system or database which might cause side effects.
	 *
	 * @param bool $include_db    Whether to include the DB in the pull.
	 * @param bool $include_files Whether to include the files in the pull.
	 *
	 * @return callable[]
	 */
	private function get_pull_actions( bool $include_db, bool $include_files ) : array {
		$pull_actions = [];

		if ( $this->get_should_download() ) {
			$pull_actions[] = function() use ( $include_db, $include_files ) {
				$this->download_snapshot( $include_db, $include_files );
			};
		}

		if ( $include_files ) {
			$pull_actions[] = [ $this, 'pull_files' ];
		}

		if ( $include_db ) {
			$pull_actions[] = [ $this, 'rename_tables' ];
		}

		if ( $this->get_should_update_wp() ) {
			$pull_actions[] = function() {
				$this->update_wp( $this->get_meta()['wp_version'] );
			};
		}

		if ( $include_db ) {
			$pull_actions[] = [ $this, 'pull_db' ];
			$pull_actions[] = [ $this, 'replace_urls' ];

			$path_to_this_plugin_relative_to_plugins_directory = trailingslashit( basename( WPSNAPSHOTS_DIR ) ) . 'wpsnapshots.php';
			if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $path_to_this_plugin_relative_to_plugins_directory ) ) {
				$pull_actions[] = function() {
					$this->activate_this_plugin( true );
				};
			} elseif ( is_plugin_active( $path_to_this_plugin_relative_to_plugins_directory ) ) {
				$pull_actions[] = [ $this, 'activate_this_plugin' ];
			}

			$pull_actions[] = function() {
				$this->create_wpsnapshots_user( $this->get_meta()['multisite'] );
			};
		}

		return $pull_actions;
	}

	/**
	 * Returns whether to update WP to the version in the snapshot.
	 *
	 * @return bool
	 */
	private function get_should_update_wp() {
		global $wp_version;

		return $wp_version !== $this->get_meta()['wp_version'] && $this->prompt->get_flag_or_prompt(
			$this->get_assoc_args(),
			'update_wp',
			'This snapshot is running WordPress version ' . $this->get_meta()['wp_version'] . ', and you are running version ' . $wp_version . '. Do you want to change your version to match the snapshot?'
		);
	}

	/**
	 * Gets the snapshot ID.
	 *
	 * @return string
	 */
	private function get_id() : string {
		return $this->get_args()[0];
	}

	/**
	 * Gets the value of the skip_table_search_replace argument.
	 *
	 * @return array
	 */
	private function get_skip_table_search_replace() : array {
		$skip_table_search_replace = $this->get_assoc_arg( 'skip_table_search_replace' );

		if ( empty( $skip_table_search_replace ) ) {
			return [];
		}

		return explode( ',', $skip_table_search_replace );
	}


	/**
	 * Downloads the snapshot.
	 *
	 * @param bool $include_db    Whether to include the database in the snapshot.
	 * @param bool $include_files Whether to include the files in the snapshot.
	 *
	 * @throws WPSnapshotsException If the snapshot does not exist or is not valid.
	 */
	private function download_snapshot( bool $include_db = true, bool $include_files = true ) {
		$command = 'wpsnapshots download ' . $this->get_id() . ' --quiet --repository=' . $this->get_repository_name() . ' --region=' . $this->get_assoc_arg( 'region' );

		if ( $include_db ) {
			$command .= ' --include_db';
		}

		if ( $include_files ) {
			$command .= ' --include_files';
		}

		wp_cli()::runcommand( $command, [ 'launch' => false ] );

		$this->log( 'Snapshot downloaded.', 'success' );
	}


	/**
	 * Pulls the DB.
	 */
	private function pull_db() {
		$this->log( 'Importing database...' );

		// Unzip data.sql.gz
		$this->filesystem->unzip_file( $this->snapshots_filesystem->get_file_path( 'data.sql.gz', $this->get_id() ), $this->snapshots_filesystem->get_file_path( '', $this->get_id() ) );

		$command = 'db import ' . $this->snapshots_filesystem->get_file_path( 'data.sql', $this->get_id() ) . ' --quiet --skip-themes --skip-plugins --skip-packages';

		wp_cli()::runcommand( $command, [ 'launch' => false ] );

		$this->log( 'Database imported.', 'success' );

	}

	/**
	 * Renames the table prefixes.
	 */
	private function rename_tables() {
		$main_blog_id         = defined( 'BLOG_ID_CURRENT_SITE' ) ? BLOG_ID_CURRENT_SITE : null;
		$current_table_prefix = $this->wordpress_database->get_blog_prefix( $main_blog_id );

		$snapshot_table_prefix = $this->get_meta()['table_prefix'];

		/**
		 * Update table prefixes
		 */
		if ( ! empty( $snapshot_table_prefix ) && ! empty( $current_table_prefix ) && $snapshot_table_prefix !== $current_table_prefix ) {
			foreach ( $this->wordpress_database->get_tables( false ) as $table ) {
				if ( 0 === strpos( $table, $snapshot_table_prefix ) ) {
					/**
					 * Update this table to use the current config prefix
					 */
					$new_table = preg_replace( '/^' . $snapshot_table_prefix . '/', $current_table_prefix, $table, 1 );
					$this->wordpress_database->rename_table( $table, $new_table );
				}
			}
		}
	}

	/**
	 * Updates WP.
	 *
	 * @param string $wp_version The version of WP to update to.
	 */
	private function update_wp( string $wp_version ) {
		$this->log( 'Updating WordPress to version ' . $wp_version . '...' );

		$command = 'core update --version=' . $wp_version . ' --force --quiet --skip-themes --skip-plugins --skip-packages';

		wp_cli()::runcommand( $command, [ 'launch' => false ] );

		$this->log( 'WordPress updated.', 'success' );
	}

	/**
	 * Pulls files from the snapshot into the environment.
	 *
	 * @throws WPSnapshotsException If WP_CONTENT_DIR is not defined.
	 */
	private function pull_files() {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			throw new WPSnapshotsException( 'WP_CONTENT_DIR is not defined.' );
		}

		$this->log( 'Pulling files...' );

		$errors = $this->snapshots_filesystem->unzip_snapshot_files( $this->get_id(), WP_CONTENT_DIR );

		if ( ! empty( $errors ) ) {
			$this->log( 'There were errors pulling files:', 'error' );

			foreach ( $errors as $error ) {
				$this->log( $error, 'error' );
			}
		}

		$this->log( 'Files pulled.', 'success' );
	}

	/**
	 * Activates this plugin.
	 *
	 * @param bool $network_activate Whether to network activate the plugin.
	 */
	private function activate_this_plugin( bool $network_activate = false ) {
		$command = 'plugin activate snapshots-command --skip-themes --skip-plugins --skip-packages';

		if ( $network_activate ) {
			$command .= ' --network';
		}

		wp_cli()::runcommand(
			$command,
			[
				'launch' => true,
			]
		);
	}

	/**
	 * Replaces urls in the database.
	 */
	private function replace_urls() {
		$multisite = $this->get_meta()['multisite'];

		$this->new_home_url = $this->url_replacer_factory->get(
			$multisite ? 'multi' : 'single',
			$this->get_meta(),
			$this->get_site_mapping(),
			$this->get_skip_table_search_replace(),
			$multisite && $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'confirm_ms_constant_update', 'Constants need to be updated in your wp-config.php file. Want WP Snapshots to do this automatically?', true ),
			$multisite ? $this->get_main_domain() : null
		)->replace_urls();
	}

	/**
	 * Creates the wpsnapshots user.
	 *
	 * @param bool $multisite Whether this is a multisite install.
	 */
	private function create_wpsnapshots_user( bool $multisite ) : void {
		$this->log( 'Creating wpsnapshots user...' );

		$user = get_user_by( 'login', 'wpsnapshots' );

		$user_args = [
			'user_login' => 'wpsnapshots',
			'user_pass'  => 'password',
			'user_email' => 'wpsnapshots@wpsnapshots.test',
			'role'       => 'administrator',
		];

		if ( ! empty( $user ) ) {
			$user_args['ID']        = $user->ID;
			$user_args['user_pass'] = wp_hash_password( 'password' );
		}

		$user_id = wp_insert_user( $user_args );

		if ( is_wp_error( $user_id ) ) {
			$this->log( 'There was an error creating the wpsnapshots user.', 'error' );
			$this->log( $user_id->get_error_message(), 'error' );
		} else {
			if ( $multisite && function_exists( 'grant_super_admin' ) ) {
				grant_super_admin( $user_id );
			}

			$this->log( 'wpsnapshots user created.', 'success' );
		}
	}

	/**
	 * Gets the site mapping.
	 *
	 * @return ?array
	 */
	protected function get_site_mapping() : ?array {
		$site_mapping     = [];
		$site_mapping_raw = $this->get_assoc_arg( 'site_mapping' );

		if ( ! empty( $site_mapping_raw ) ) {
			if ( $this->snapshots_filesystem->get_wp_filesystem()->exists( $site_mapping_raw ) ) {
				$site_mapping_raw = $this->snapshots_filesystem->get_wp_filesystem()->get_contents( $site_mapping_raw );
			}

			$site_mapping_raw = json_decode( $site_mapping_raw, true );

			foreach ( $site_mapping_raw as $site ) {
				if ( ! empty( $site['blog_id'] ) ) {
					$site_mapping[ (int) $site['blog_id'] ] = $site;
				} else {
					$site_mapping[] = $site;
				}
			}

			if ( empty( $this->get_meta()['multisite'] ) ) {
				$site_mapping = array_values( $site_mapping );
			}
		}

		return $site_mapping;
	}

	/**
	 * Returns whether multisite constants need to be updated.
	 *
	 * @return bool
	 */
	protected function needs_multisite_constants_update() : bool {
		return ! defined( 'BLOG_ID_CURRENT_SITE' )
			|| ( ! empty( $this->meta['blog_id_current_site'] ) && BLOG_ID_CURRENT_SITE !== (int) $this->meta['blog_id_current_site'] )
			|| ! defined( 'SITE_ID_CURRENT_SITE' )
			|| ( ! empty( $this->meta['site_id_current_site'] ) && SITE_ID_CURRENT_SITE !== (int) $this->meta['site_id_current_site'] )
			|| ! defined( 'PATH_CURRENT_SITE' )
			|| ( ! empty( $this->meta['path_current_site'] ) && PATH_CURRENT_SITE !== $this->meta['path_current_site'] )
			|| ! defined( 'MULTISITE' )
			|| ! MULTISITE
			|| ! defined( 'DOMAIN_CURRENT_SITE' )
			|| DOMAIN_CURRENT_SITE !== $this->get_main_domain()
			|| ! defined( 'SUBDOMAIN_INSTALL' )
			|| SUBDOMAIN_INSTALL !== $this->meta['subdomain_install'];
	}

	/**
	 * Get the main domain from the snapshot.
	 *
	 * @return string
	 */
	private function get_main_domain() : string {
		if ( is_null( $this->main_domain ) ) {
			$this->main_domain = $this->get_assoc_arg( 'main_domain' );

			if ( empty( $this->main_domain ) ) {

				$snapshot_main_domain = ! empty( $this->get_meta()['domain_current_site'] ) ? $this->get_meta()['domain_current_site'] : '';

				if ( ! empty( $snapshot_main_domain ) ) {
					$this->main_domain = $this->prompt->readline( 'Main domain (defaults to main domain in the snapshot: ' . $snapshot_main_domain . '): ', $snapshot_main_domain, [ $this, 'domain_validator' ] );
				} else {
					$example_site = 'mysite.test';

					if ( ! empty( $this->meta['sites'][0]['home_url'] ) ) {
						$example_site = wp_parse_url( $this->meta['sites'][0]['home_url'], PHP_URL_HOST );
					}

					$this->main_domain = $this->prompt->readline( 'Main domain (' . $example_site . ' for example): ', '', [ $this, 'domain_validator' ] );
				}
			}
		}

		return $this->main_domain;
	}
}
