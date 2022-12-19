<?php
/**
 * Handle getting and setting of configuration values.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use WP_CLI;

/**
 * Handle getting and setting of configuration values.
 *
 * @package TenUp\WPSnapshots
 */
final class Config {

	/**
	 * Configuration values.
	 *
	 * @var ?array
	 */
	private $config;

	/**
	 * Gets a configuration value.
	 *
	 * @param string $key Key to get.
	 * @param mixed  $default Default value to return if key is not set.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$config = $this->get_config();

		if ( ! isset( $config[ $key ] ) ) {
			return $default;
		}

		return $config[ $key ];
	}

	/**
	 * Provides a single default.
	 *
	 * @param string $key Key to get.
	 * @return mixed $value Value of the key.
	 */
	public function get_default( string $key ) {
		$defaults = $this->get_defaults();

		if ( ! isset( $defaults[ $key ] ) ) {
			return null;
		}

		return $defaults[ $key ];
	}

	/**
	 * Sets a configuration value.
	 *
	 * @param string $key Key to set.
	 * @param mixed  $value Value to set.
	 */
	public function set( string $key, $value ) {
		$config = $this->get_config();

		if ( ! isset( $config[ $key ] ) || $value !== $config[ $key ] ) {

			$config[ $key ] = $value;

			$this->config = $config;

			$this->save();
		}
	}

	/**
	 * Saves the configuration to a file.
	 */
	public function save() {
		$directory = $this->get_config_directory();
		$file      = $this->get_config_file();

		if ( ! file_exists( $directory ) ) {
			if ( ! is_writable( dirname( $directory ) ) ) {
				WP_CLI::error( sprintf( 'Unable to create directory %s', dirname( $directory ) ) );
			}

			$wrote_directory = mkdir( $directory, 0755, true ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.mkdir_mkdir

			if ( ! $wrote_directory ) {
				WP_CLI::error( sprintf( 'Unable to create directory %s', dirname( $file ) ) );
			}
		}

		$config = $this->config;

		file_put_contents( $file, wp_json_encode( $config ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

		WP_CLI::debug( sprintf( 'Configuration saved to %s', $file ), 'wpsnapshots' );
	}

	/**
	 * Loads configuration from a file.
	 */
	private function load() {
		$file = $this->get_config_file();

		if ( ! file_exists( $file ) ) {
			// Create a new config file.
			$this->config = $this->get_defaults();
			$this->save();
			return;
		}

		$config = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! is_array( $config ) ) {
			WP_CLI::error( sprintf( 'Configuration file %s is not valid JSON.', $file ) );
		}

		$this->config = wp_parse_args( $config, $this->get_defaults() );

		WP_CLI::debug( sprintf( 'Configuration loaded from %s', $file ), 'wpsnapshots' );
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
			'region' => 'us-west-1',
		];
	}

	/**
	 * Gets the configuration file.
	 *
	 * @return string $file Configuration file.
	 */
	private function get_config_directory() : string {

		/**
		 * Filters the configuration file.
		 *
		 * @param string $file Configuration file.
		 */
		return apply_filters( 'wpsnapshots_config_directory', strlen( getenv( 'WPSNAPSHOTS_DIR' ) ) > 0 ? getenv( 'WPSNAPSHOTS_DIR' ) : $_SERVER['HOME'] . '/.wpsnapshots' );
	}

	/**
	 * Gets the configuration file path.
	 *
	 * @return string $file Configuration file path.
	 */
	private function get_config_file() : string {

		/**
		 * Filters the configuration file.
		 *
		 * @param string $file Configuration file.
		 */
		return apply_filters( 'wpsnapshots_config_file', $this->get_config_directory() . '/config.json' );
	}
}
