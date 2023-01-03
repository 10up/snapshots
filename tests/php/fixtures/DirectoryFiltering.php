<?php
/**
 * Trait for filtering the directory data is saved to.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Fixtures;

/**
 * Trait DirectoryFiltering
 *
 * @package TenUp\WPSnapshots\Fixtures
 */
trait DirectoryFiltering {
	
	/**
	 * Filters the directory data is saved to.
	 */
	public function set_up_directory_filtering() {
		$filter_directory = function() {
			return $this->get_directory_path();
		};

		// Create the directory.
		exec( 'mkdir ' . $this->get_directory_path() );

		add_filter( 'wpsnapshots_directory', $filter_directory );
	}

	/**
	 * Removes the directory filter.
	 */
	public function tear_down_directory_filtering() {
		remove_all_filters( 'wpsnapshots_directory' );

		exec( 'rm -rf ' . $this->get_directory_path() );
	}

	/**
	 * Gets the directory path.
	 * 
	 * @return string
	 */
	public function get_directory_path() {
		return '/wpsnapshots-tmp';
	}
}