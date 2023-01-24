<?php
/**
 * Handle getting and setting of configuration values.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPSnapshotsConfig;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\SnapshotFiles;

/**
 * Handle getting and setting of configuration values.
 *
 * @package TenUp\WPSnapshots\WPSnapshotsConfig
 */
class WPSnapshotsConfigFromFileSystem implements WPSnapshotsConfigInterface {

	/**
	 * Data instance.
	 *
	 * @var SnapshotFiles
	 */
	private $snapshots_file_system;

	/**
	 * Configuration values.
	 *
	 * @var ?array
	 */
	private $config;

	/**
	 * Config constructor.
	 *
	 * @param SnapshotFiles $snapshots_file_system SnapshotFiles instance.
	 */
	public function __construct( SnapshotFiles $snapshots_file_system ) {
		$this->snapshots_file_system = $snapshots_file_system;
	}

	/**
	 * Gets the user name.
	 *
	 * @return string $user_name User name.
	 */
	public function get_user_name() : string {
		return $this->get_config()['user_name'];
	}

	/**
	 * Sets the user name.
	 *
	 * @param string $user_name User name.
	 * @param bool   $save      Whether to save the configuration.
	 */
	public function set_user_name( string $user_name, bool $save = true ) {
		$config              = $this->get_config();
		$config['user_name'] = $user_name;
		$this->config        = $config;

		if ( $save ) {
			$this->save();
		}
	}

	/**
	 * Gets the user email.
	 *
	 * @return string $user_email User email.
	 */
	public function get_user_email() : string {
		return $this->get_config()['user_email'];
	}

	/**
	 * Sets the user email.
	 *
	 * @param string $user_email User email.
	 * @param bool   $save       Whether to save the configuration.
	 */
	public function set_user_email( string $user_email, bool $save = true ) {
		$config               = $this->get_config();
		$config['user_email'] = $user_email;
		$this->config         = $config;

		if ( $save ) {
			$this->save();
		}
	}

	/**
	 * Gets the repositories.
	 *
	 * @return array $repositories Repositories.
	 */
	public function get_repositories() : array {
		return $this->get_config()['repositories'];
	}

	/**
	 * Sets the repositories.
	 *
	 * @param array $repositories Repositories.
	 * @param bool  $save         Whether to save the configuration.
	 */
	public function set_repositories( array $repositories, bool $save = true ) {
		$config                 = $this->get_config();
		$config['repositories'] = $repositories;
		$this->config           = $config;

		if ( $save ) {
			$this->save();
		}
	}

	/**
	 * Gets settings for a repository.
	 *
	 * @param string $repository Repository name.
	 * @return array $settings Repository settings.
	 *
	 * @throws WPSnapshotsException If the repository does not exist.
	 */
	public function get_repository_settings( string $repository = '' ) : ?array {
		$repositories = $this->get_repositories();

		if ( empty( $repository ) && ! empty( $repositories ) ) {
			$repository = array_keys( $repositories )[0];
		}

		if ( ! isset( $repositories[ $repository ] ) ) {
			throw new WPSnapshotsException( sprintf( 'Repository %s does not exist.', $repository ) );
		}

		return $repositories[ $repository ];
	}

	/**
	 * Gets the configuration.
	 *
	 * @return array $config Configuration.
	 */
	private function get_config() : array {
		if ( is_null( $this->config ) ) {
			$this->load();
		}

		return $this->config;
	}

	/**
	 * Provides defaults.
	 *
	 * @return array $config Configuration defaults.
	 */
	private function get_defaults() : array {
		return [
			'user_name'    => '',
			'user_email'   => '',
			'repositories' => [],
		];
	}

	/**
	 * Loads configuration from a file.
	 */
	private function load() {
		try {
			$config = json_decode( $this->snapshots_file_system->get_file_contents( 'config.json' ), true );
		} catch ( WPSnapshotsException $e ) {
			$config = null;
		}

		$defaults = $this->get_defaults();

		if ( ! is_array( $config ) ) {
			$config = $defaults;
			$this->save();
		}

		$this->config = wp_parse_args( $config, $defaults );
	}

	/**
	 * Saves the configuration to a file.
	 */
	public function save() {
		$this->snapshots_file_system->update_file_contents( 'config.json', wp_json_encode( $this->config ) );
	}
}
