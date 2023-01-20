<?php
/**
 * SingleSiteURLReplacer class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands\Pull;

/**
 * SingleSiteURLReplacer
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
final class SingleSiteURLReplacer extends URLReplacer {

	/**
	 * Replaces URLs.
	 *
	 * @return string The Home URL.
	 */
	public function replace_urls() : string {
		$this->log( 'Preparing to replace URLs...' );

		if ( empty( $this->skip_table_search_replace ) ) {
			$this->skip_table_search_replace = [
				'terms',
				'term_relationships',
				'term_taxonomy',
			];
		}

		$current_table_prefix = $this->wordpress_database->get_blog_prefix();

		if ( ! empty( $this->site_mapping ) ) {
			$new_home_url = $this->site_mapping[0]['home_url'];
			$new_site_url = $this->site_mapping[0]['site_url'];
		} else {
			$new_home_url = $this->prompt->readline(
				'Home URL (defaults to home URL in snapshot: ' . $this->meta['sites'][0]['home_url'] . '): ',
				$this->meta['sites'][0]['home_url'],
				[ $this, 'url_validator' ]
			);

			$new_site_url = $this->prompt->readline(
				'Site URL (defaults to site URL in snapshot: ' . $this->meta['sites'][0]['site_url'] . '): ',
				$this->meta['sites'][0]['site_url'],
				[ $this, 'url_validator' ]
			);
		}

		$home_url = $new_home_url;

		$this->log( 'Running replacement... This may take a while depending on the size of the database.' );

		$tables_to_update = [];

		foreach ( $this->wordpress_database->get_tables() as $table ) {
			$raw_table = str_replace( $current_table_prefix, '', $table );

			if ( ! in_array( $raw_table, $this->skip_table_search_replace, true ) ) {
				$tables_to_update[] = $table;
			}
		}

		$this->log( 'Search and replacing tables: ' . implode( ', ', $tables_to_update ), 1 );

		$this->run_search_and_replace( $this->meta['sites'][0]['home_url'], $new_home_url, $tables_to_update );

		if ( $this->meta['sites'][0]['home_url'] !== $this->meta['sites'][0]['site_url'] ) {
			$this->run_search_and_replace( $this->meta['sites'][0]['site_url'], $new_site_url, $tables_to_update );
		}

		$this->log( 'URLs replaced.', 'success' );

		return $home_url;
	}
}
