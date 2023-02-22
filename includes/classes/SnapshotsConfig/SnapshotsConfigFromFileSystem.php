<?php
/**
 * Handle getting and setting of configuration values.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\SnapshotsConfig;

use Exception;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\SnapshotsDirectory;

/**
 * Handle getting and setting of configuration values.
 *
 * @package TenUp\Snapshots\SnapshotsConfig
 */
class SnapshotsConfigFromFileSystem implements SnapshotsConfigInterface {

	/**
	 * Data instance.
	 *
	 * @var SnapshotsDirectory
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
	 * @param SnapshotsDirectory $snapshots_file_system SnapshotsDirectory instance.
	 */
	public function __construct( SnapshotsDirectory $snapshots_file_system ) {
		$this->snapshots_file_system = $snapshots_file_system;
	}

	/**
	 * Gets the user name.
	 *
	 * @param string $repository Repository name.
	 * @return ?string $user_name User name.
	 */
	public function get_user_name( string $repository = '' ) : ?string {
		try {
			return $this->get_repository_settings( $repository )['user_name'];
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Gets the user email.
	 *
	 * @param string $repository Repository name.
	 * @return ?string $user_email User email.
	 */
	public function get_user_email( string $repository = '' ) : ?string {
		try {
			return $this->get_repository_settings( $repository )['user_email'];
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Sets the profile.
	 */

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
	 * @return ?array $settings Repository settings.
	 *
	 * @throws SnapshotsException If the repository does not exist.
	 */
	public function get_repository_settings( string $repository = '' ) : ?array {
		$repositories = $this->get_repositories();

		if ( empty( $repository ) && ! empty( $repositories ) ) {
			$repository = array_keys( $repositories )[0];
		}

		if ( ! isset( $repositories[ $repository ] ) ) {
			return null;
		}

		return $repositories[ $repository ];
	}

	/**
	 * Gets the profile property from a repository.
	 *
	 * @param string $repository Repository name.
	 * @return ?string $profile Profile name.
	 */
	public function get_repository_profile( string $repository = '' ) : ?string {
		$settings = $this->get_repository_settings( $repository );

		if ( is_array( $settings ) ) {
			return $settings['profile'] ?? null;
		}

		return null;
	}

	/**
	 * Gets the default repository name.
	 *
	 * @return ?string $repository Default repository name.
	 */
	public function get_default_repository_name() : ?string {
		$repository = $this->get_repository_settings();

		if ( is_array( $repository ) ) {
			return $repository['repository'];
		}

		return null;
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
			'repositories' => [],
		];
	}

	/**
	 * Loads configuration from a file.
	 */
	private function load() {
		try {
			$config = json_decode( $this->snapshots_file_system->get_file_contents( 'config.json' ), true );
		} catch ( SnapshotsException $e ) {
			$config = null;
		}

		$defaults = $this->get_defaults();

		if ( ! is_array( $config ) ) {
			$config = $defaults;
			$this->save();
		}

		$this->config = array_merge( $defaults, $config );
	}

	/**
	 * Saves the configuration to a file.
	 */
	public function save() {
		$this->snapshots_file_system->update_file_contents( 'config.json', json_encode( $this->config ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}
