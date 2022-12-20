<?php
/**
 * Interface for classes that implement data persistence.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Data;

/**
 * Interface DataHandlerInterface
 *
 * @package TenUp\WPSnapshots
 */
interface DataHandlerInterface {

	/**
	 * Saves data as JSON.
	 *
	 * @param string $name Unique identifier for the JSON.
	 * @param mixed  $data Data to save.
	 */
	public function save_json( string $name, $data );

	/**
	 * Loads JSON data.
	 *
	 * @param string $name Unique identifier for the JSON.
	 * @return mixed $data Loaded data.
	 */
	public function load_json( string $name );

}
