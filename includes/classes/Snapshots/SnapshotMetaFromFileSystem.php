<?php
/**
 * Snapshot meta class
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\SnapshotsDirectory;
use TenUp\Snapshots\WordPress\Database;

/**
 * Snapshot meta wrapper with support for downloading remote meta
 *
 * @package TenUp\Snapshots\Snapshots
 */
class SnapshotMetaFromFileSystem extends SnapshotMeta {

	/**
	 * SnapshotsDirectory instance.
	 *
	 * @var SnapshotsDirectory
	 */
	private $snapshot_files;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	private $wordpress_database;

	/**
	 * Meta constructor
	 *
	 * @param SnapshotsDirectory $snapshot_files SnapshotsDirectory instance.
	 * @param Database             $wordpress_database Database instance.
	 * @param array                ...$args Arguments.
	 */
	public function __construct( SnapshotsDirectory $snapshot_files, Database $wordpress_database, ...$args ) {
		parent::__construct( ...$args ); // @phpstan-ignore-line

		$this->snapshot_files     = $snapshot_files;
		$this->wordpress_database = $wordpress_database;
	}

	/**
	 * Save snapshot meta locally
	 *
	 * @param string $id Snapshot ID.
	 * @param array  $meta Snapshot meta.
	 * @return int Number of bytes written
	 */
	public function save_local( string $id, array $meta ) {
		$this->snapshot_files->update_file_contents( 'meta.json', json_encode( $meta ), false, $id ); // @phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		return $this->snapshot_files->get_file_size( 'meta.json', $id );
	}

	/**
	 * Get local snapshot meta
	 *
	 * @param  string $id Snapshot ID
	 * @param  string $repository Repository name
	 * @return mixed
	 *
	 * @throws SnapshotsException Snapshot meta invalid.
	 */
	public function get_local( string $id, string $repository ) {
		try {
			$meta_file_contents = $this->snapshot_files->get_file_contents( 'meta.json', $id );
		} catch ( SnapshotsException $e ) {
			return [];
		}

		$meta = json_decode( $meta_file_contents, true );

		if ( $repository !== $meta['repository'] ) {
			return false;
		}

		// Backwards compat since these previously were not set.
		if ( ! isset( $meta['contains_files'] ) && $this->snapshot_files->file_exists( 'files.tar.gz', $id ) ) {
			$meta['contains_files'] = true;
		} if ( ! isset( $meta['contains_db'] ) && $this->snapshot_files->file_exists( 'db.sql.gz', $id ) ) {
			$meta['contains_db'] = true;
		}

		if ( empty( $meta['contains_files'] ) && empty( $meta['contains_db'] ) ) {
			throw new SnapshotsException( 'Snapshot meta invalid.' );
		}

		return $meta;
	}

	/**
	 * Generate snapshot meta
	 *
	 * @param string $id Snapshot ID.
	 * @param array  $args Snapshot meta arguments.
	 * @param ?bool  $is_multisite Whether or not the snapshot is a multisite.
	 */
	public function generate( string $id, array $args, ?bool $is_multisite = null ) : void {
		global $wp_version;

		$meta = [
			'author'               => $args['author'],
			'repository'           => $args['repository'],
			'description'          => $args['description'],
			'project'              => $args['project'],
			'contains_files'       => $args['contains_files'],
			'contains_db'          => $args['contains_db'],
			'multisite'            => false,
			'subdomain_install'    => false,
			'domain_current_site'  => false,
			'path_current_site'    => false,
			'site_id_current_site' => false,
			'blog_id_current_site' => false,
		];

		$meta['wp_version'] = ! empty( $wp_version ) ? $wp_version : '';
		if ( ! empty( $args['wp_version'] ) ) {
			$meta['wp_version'] = $args['wp_version'];
		}

		$meta_sites = [];

		if ( is_null( $is_multisite ) ) {
			$is_multisite = is_multisite();
		}

		if ( $is_multisite ) {
			$meta['multisite'] = true;

			if ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) {
				$meta['subdomain_install'] = true;
			}

			if ( defined( 'DOMAIN_CURRENT_SITE' ) ) {
				$meta['domain_current_site'] = DOMAIN_CURRENT_SITE;
			}

			if ( defined( 'PATH_CURRENT_SITE' ) ) {
				$meta['path_current_site'] = PATH_CURRENT_SITE;
			}

			if ( defined( 'SITE_ID_CURRENT_SITE' ) ) {
				$meta['site_id_current_site'] = SITE_ID_CURRENT_SITE;
			}

			if ( defined( 'BLOG_ID_CURRENT_SITE' ) ) {
				$meta['blog_id_current_site'] = BLOG_ID_CURRENT_SITE;
			}

			if ( function_exists( 'get_sites' ) ) {
				$sites = get_sites( [ 'number' => 500 ] );
			} else {
				$sites = [];
			}

			foreach ( $sites as $site ) {
				$meta_sites[] = [
					'blog_id'  => $site->blog_id,
					'domain'   => $site->domain,
					'path'     => $site->path,
					'site_url' => get_blog_option( $site->blog_id, 'siteurl' ),
					'home_url' => get_blog_option( $site->blog_id, 'home' ),
					'blogname' => get_blog_option( $site->blog_id, 'blogname' ),
				];
			}
		} else {
			$meta_sites[] = [
				'site_url' => get_option( 'siteurl' ),
				'home_url' => get_option( 'home' ),
				'blogname' => get_option( 'blogname' ),
			];
		}

		$meta['sites'] = $meta_sites;

		$main_blog_id = ( defined( 'BLOG_ID_CURRENT_SITE' ) ) ? BLOG_ID_CURRENT_SITE : null;

		$meta['table_prefix'] = $this->wordpress_database->get_blog_prefix( $main_blog_id );

		if ( $args['contains_db'] ) {
			$meta['db_size'] = $this->snapshot_files->get_file_size( 'data.sql.gz', $id );
		}

		if ( $args['contains_files'] ) {
			$meta['files_size'] = $this->snapshot_files->get_file_size( 'files.zip', $id );
		}

		$this->save_local( $id, $meta );
	}
}
