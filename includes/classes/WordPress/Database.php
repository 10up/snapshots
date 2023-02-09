<?php
/**
 * WordPress database helpers.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WordPress;

use TenUp\Snapshots\Infrastructure\SharedService;
use wpdb;

/**
 * Database class.
 *
 * @package TenUp\Snapshots\WordPress
 */
class Database implements SharedService {

	/**
	 * Returns tables
	 *
	 * @param  bool $wp Whether to only return WP tables
	 * @return array
	 */
	public function get_tables( bool $wp = true ) {
		$wpdb = $this->get_wpdb();

		$tables = [];

		$results = $wpdb->get_results( 'SHOW TABLES', defined( 'ARRAY_A' ) ? ARRAY_A : 'ARRAY_A' );

		foreach ( $results as $table_info ) {
			$table_info = array_values( $table_info );
			$table      = $table_info[0];

			if ( $wp ) {
				if ( 0 === strpos( $table, $wpdb->base_prefix ) ) {
					$tables[] = $table;
				}
			} else {
				$tables[] = $table;
			}
		}

		return $tables;
	}

	/**
	 * Renames a table.
	 *
	 * @param  string $old_name Old table name.
	 * @param  string $new_name New table name.
	 */
	public function rename_table( string $old_name, string $new_name ) {
		$wpdb = $this->get_wpdb();

		$wpdb->query( $wpdb->prepare( 'RENAME TABLE ' . $old_name . ' TO ' . $new_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Provides the blog prefix.
	 *
	 * @param ?int $blog_id Blog ID.
	 *
	 * @return string
	 */
	public function get_blog_prefix( ?int $blog_id = null ) {
		return $this->get_wpdb()->get_blog_prefix( $blog_id );
	}


	/**
	 * Gets the WP database global.
	 */
	private function get_wpdb() : wpdb {
		global $wpdb;

		return $wpdb;
	}
}
