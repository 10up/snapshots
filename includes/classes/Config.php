<?php
/**
 * Handle getting and setting of configuration values.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots;

use TenUp\WPSnapshots\Data\DataHandlerInterface;

/**
 * Handle getting and setting of configuration values.
 *
 * @package TenUp\WPSnapshots
 */
final class Config {

	/**
	 * Data instance.
	 *
	 * @var DataHandlerInterface
	 */
	private $data_handler;

	/**
	 * Configuration values.
	 *
	 * @var ?array
	 */
	private $config;

	/**
	 * Config constructor.
	 *
	 * @param DataHandlerInterface $data_handler Data instance.
	 */
	public function __construct( DataHandlerInterface $data_handler ) {
		$this->data_handler = $data_handler;
	}

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
		$config   = $this->data_handler->load_json( 'config' );
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
	private function save() {
		$this->data_handler->save_json( 'config', $this->config );
	}
}
