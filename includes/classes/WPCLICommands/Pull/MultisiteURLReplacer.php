<?php
/**
 * MultisiteURLReplacer class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands\Pull;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsInputValidationException;

/**
 * MultisiteURLReplacer
 *
 * @package TenUp\WPSnapshots\WPCLI\Pull
 */
final class MultisiteURLReplacer extends URLReplacer {

	/**
	 * Used home URLs.
	 *
	 * @var array
	 */
	private $used_home_urls = [];

	/**
	 * Used site URLs.
	 *
	 * @var array
	 */
	private $used_site_urls = [];

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
				'site',
				'blogs',
			];
		}

		$main_blog_id         = defined( 'BLOG_ID_CURRENT_SITE' ) ? BLOG_ID_CURRENT_SITE : null;
		$current_table_prefix = $this->wordpress_database->get_blog_prefix();

		// Make WP realize we are in multisite now
		if ( ! defined( 'MULTISITE' ) ) {
			define( 'MULTISITE', true );
		}

		if ( empty( $this->meta['subdomain_install'] ) ) {
			$this->log( 'Multisite installation (path based install) detected.' );
		} else {
			$this->log( 'Multisite installation (subdomain based install) detected.' );
		}

		foreach ( $this->meta['sites'] as $site ) {
			$this->replace_urls_for_site( $site, $this->site_mapping, $this->skip_table_search_replace, $current_table_prefix );
		}

		$this->log( 'Updating site table...' );

		wp_update_site( $main_blog_id, [ 'domain' => $this->main_domain ] );

		$this->log( 'URLs replaced.' );

		if ( ! $this->update_multisite_constants ) {

			$this->log( 'The following code should be in your wp-config.php file:', 'warning' );
			$this->log(
				"define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', " . ( ( ! empty( $this->meta['subdomain_install'] ) ) ? 'true' : 'false' ) . ");
define('DOMAIN_CURRENT_SITE', '" . $this->main_domain . "');
define('PATH_CURRENT_SITE', '" . ( ( ! empty( $this->meta['path_current_site'] ) ) ? $this->meta['path_current_site'] : '/' ) . "');
define('SITE_ID_CURRENT_SITE', " . ( ( ! empty( $this->meta['site_id_current_site'] ) ) ? $this->meta['site_id_current_site'] : '1' ) . ");
define('BLOG_ID_CURRENT_SITE', " . ( ( ! empty( $this->meta['blog_id_current_site'] ) ) ? $this->meta['blog_id_current_site'] : '1' ) . ');',
				'success'
			);
		} else {
			$this->write_constants_to_wp_config(
				[
					'WP_ALLOW_MULTISITE'   => true,
					'MULTISITE'            => true,
					'SUBDOMAIN_INSTALL'    => ( ! empty( $this->meta['subdomain_install'] ) ) ? true : false,
					'DOMAIN_CURRENT_SITE'  => $this->main_domain,
					'PATH_CURRENT_SITE'    => ( ! empty( $this->meta['path_current_site'] ) ) ? $this->meta['path_current_site'] : '/',
					'SITE_ID_CURRENT_SITE' => ( ! empty( $this->meta['site_id_current_site'] ) ) ? $this->meta['site_id_current_site'] : 1,
					'BLOG_ID_CURRENT_SITE' => ( ! empty( $this->meta['blog_id_current_site'] ) ) ? $this->meta['blog_id_current_site'] : 1,
				]
			);

			$this->log( 'Multisite constants added to wp-config.php.' );
		}

		return $this->new_home_url ? $this->new_home_url : home_url();
	}

	/**
	 * Domain validator.
	 *
	 * @param string $domain The domain to validate.
	 *
	 * @return string
	 *
	 * @throws WPSnapshotsInputValidationException If the domain is not valid.
	 */
	protected function domain_validator( string $domain ) : string {
		if ( '' === trim( $domain ) || false !== strpos( $domain, ' ' ) || preg_match( '#https?:#i', $domain ) ) {
			throw new WPSnapshotsInputValidationException(
				'Domain not valid. The domain should be in the form of `google.com`, no https:// needed'
			);
		}

		return $domain;
	}

	/**
	 * Write constants to wp-config.php ensuring the same constants don't get written twice.
	 *
	 * @param  array   $constants       Constants array
	 * @param  ?string $wp_config_path Path to wp-config.php
	 */
	private function write_constants_to_wp_config( array $constants, ?string $wp_config_path = null ) {

		if ( empty( $wp_config_path ) ) {
			$wp_config_path = trailingslashit( ABSPATH ) . 'wp-config.php';
		}

		$wp_config_code     = $this->snapshots_filesystem->get_wp_filesystem()->get_contents_array( $wp_config_path );
		$new_wp_config_code = [];

		foreach ( $wp_config_code as $line ) {
			// We'll add this back later
			if ( preg_match( '#^<\?php.*#i', $line ) ) {
				continue;
			}

			// Don't readd lines that contain constants we are defining
			if ( preg_match( '#define\(.*?("|\')(.*?)("|\').*?\).*?;#', $line ) ) {
				$constant_name = preg_replace( '#^.*?define\(.*?("|\')(.*?)("|\').*$#', '$2', $line );

				if ( ! empty( $constants[ $constant_name ] ) ) {
					continue;
				}
			}

			$new_wp_config_code[] = $line;
		}

		foreach ( $constants as $constant_name => $constant_value ) {
			if ( false === $constant_value ) {
				$constant_value = 'false';
			} elseif ( true === $constant_value ) {
				$constant_value = 'true';
			} elseif ( is_string( $constant_value ) ) {
				$constant_value = addcslashes( $constant_value, "'" );

				$constant_value = "'$constant_value'";
			}

			array_unshift( $new_wp_config_code, 'define( "' . $constant_name . '", ' . $constant_value . ' ); // Auto added.' );
		}

		array_unshift( $new_wp_config_code, '<?php' );

		$this->snapshots_filesystem->get_wp_filesystem()->put_contents( $wp_config_path, implode( "\n", $new_wp_config_code ) );
	}

	/**
	 * Replace URLs for a site.
	 *
	 * @param array  $site Site array.
	 * @param array  $site_mapping Site mapping array.
	 * @param array  $skip_table_search_replace Skip table search replace array.
	 * @param string $current_table_prefix Current table prefix.
	 */
	private function replace_urls_for_site( array $site, array $site_mapping, array $skip_table_search_replace, string $current_table_prefix ) {
		$this->log( 'Replacing URLs for blog ' . $site['blog_id'] . '.' );

		if ( ! empty( $site_mapping[ (int) $site['blog_id'] ] ) ) {
			$new_home_url = $site_mapping[ (int) $site['blog_id'] ]['home_url'];
			$new_site_url = $site_mapping[ (int) $site['blog_id'] ]['site_url'];
		} else {
			$new_home_url = null;
			while ( ! $new_home_url ) {
				$new_home_url = $this->prompt->readline( 'Home URL (defaults home URL in snapshot: ' . $site['home_url'] . '): ', $site['home_url'], [ $this, 'url_validator' ] );

				if ( in_array( $new_home_url, $this->used_home_urls, true ) ) {
					$new_home_url = null;

					$this->log( 'Sorry, that home URL is already taken by another site.', 'error' );
				}
			}

			$new_site_url = null;
			while ( ! $new_site_url ) {
				$new_site_url = $this->prompt->readline( 'Site URL (defaults site URL in snapshot: ' . $site['site_url'] . '): ', $site['site_url'], [ $this, 'url_validator' ] );

				if ( in_array( $new_site_url, $this->used_site_urls, true ) ) {
					$new_site_url = null;

					$this->log( 'Sorry, that site URL is already taken by another site.', 'error' );
				}
			}
		}

		if ( empty( $this->new_home_url ) ) {
			$this->new_home_url = $new_home_url;
		}

		$this->used_home_urls[] = $new_home_url;
		$this->used_site_urls[] = $new_site_url;

		$this->log( 'Updating blogs table...' );

		$blog_path = trailingslashit( wp_parse_url( $new_home_url, PHP_URL_PATH ) );
		if ( empty( $blog_path ) ) {
			$blog_path = '/';
		}

		$blog_url = wp_parse_url( $new_home_url, PHP_URL_HOST );

		if ( ! empty( wp_parse_url( $new_home_url, PHP_URL_PORT ) ) ) {
			$blog_url .= ':' . wp_parse_url( $new_home_url, PHP_URL_PORT );
		}

		wp_update_site(
			(int) $site['blog_id'],
			[
				'domain' => $blog_url,
				'path'   => $blog_path,
			]
		);

		$tables_to_update = $this->get_tables_to_update( $site, $current_table_prefix, $skip_table_search_replace );

		if ( ! empty( $tables_to_update ) ) {
			$this->run_search_and_replace( $site['home_url'], $new_home_url, $tables_to_update, $skip_table_search_replace );

			if ( $site['home_url'] !== $site['site_url'] ) {
				$this->run_search_and_replace( $site['site_url'], $new_site_url, $tables_to_update, $skip_table_search_replace );
			}
		}
	}

	/**
	 * Gets tables to update.
	 *
	 * @param array  $site Site array.
	 * @param string $current_table_prefix Current table prefix.
	 * @param array  $skip_table_search_replace Skip table search replace array.
	 *
	 * @return array
	 */
	private function get_tables_to_update( array $site, string $current_table_prefix, array $skip_table_search_replace ) : array {
		/**
		 * Update all tables except wp_site and wp_blog since we handle that separately
		 */
		$tables_to_update = [];

		foreach ( $this->wordpress_database->get_tables() as $table ) {
			if ( 1 === (int) $site['blog_id'] ) {
				if ( preg_match( '#^' . $current_table_prefix . '#', $table ) && ! preg_match( '#^' . $current_table_prefix . '[0-9]+_#', $table ) ) {
					if ( ! in_array( str_replace( $current_table_prefix, '', $table ), $skip_table_search_replace, true ) ) {
						$tables_to_update[] = $table;
					}
				}
			} else {
				if ( preg_match( '#^' . $current_table_prefix . $site['blog_id'] . '_#', $table ) ) {
					$raw_table = str_replace( $current_table_prefix . $site['blog_id'] . '_', '', $table );

					if ( ! in_array( $raw_table, $skip_table_search_replace, true ) ) {
						$tables_to_update[] = $table;
					}
				}
			}
		}

		return $tables_to_update;
	}

}
