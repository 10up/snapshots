<?php
/**
 * Trimmer class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Infrastructure\SharedService;
use TenUp\WPSnapshots\Log\{LoggerInterface, Logging};
use TenUp\WPSnapshots\WordPress\Database;
use WP_Site;

/**
 * Class Trimmer
 *
 * @package TenUp\WPSnapshots
 */
class Trimmer implements SharedService {

	use Logging;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	private $wordpress_database;

	/**
	 * Limits on the number of posts and comments to keep.
	 *
	 * @var array
	 */
	private $limits;

	/**
	 * Class constructor.
	 *
	 * @param Database        $wordpress_database Database instance.
	 * @param LoggerInterface $logger LoggerInterface instance.
	 */
	public function __construct( Database $wordpress_database, LoggerInterface $logger ) {
		$this->wordpress_database = $wordpress_database;
		$this->set_logger( $logger );

		/**
		 * Filters the number of posts and comments to keep.
		 *
		 * @param array $limits {
		 *    @type int $posts Number of posts to keep.
		 *    @type int $comments Number of comments to keep.
		 * }
		 */
		$this->limits = apply_filters(
			'wpsnapshots_trimmer_limits',
			[
				'posts'    => 300,
				'comments' => 500,
			]
		);
	}

	/**
	 * Sets limits.
	 *
	 * @param array $limits Config.
	 */
	public function set_limits( array $limits ) {
		$this->limits = $limits;
	}

	/**
	 * Makes the database small.
	 *
	 * @param ?WP_Site[] $sites Sites to trim.
	 */
	public function trim( ?array $sites = null ) {
		global $wpdb;

		$original_prefix = $wpdb->prefix;

		if ( is_array( $sites ) ) {
			if ( empty( $sites ) ) {
				return;
			}

			$site = array_shift( $sites );

			$this->log( 'Trimming snapshot data and files blog ' . $site->blog_id . '...' );

			switch_to_blog( (int) $site->blog_id );

			$wpdb->prefix = $this->wordpress_database->get_blog_prefix( (int) $site->blog_id );
		} else {
			$this->log( 'Trimming snapshot data and files...' );
		}

		$post_ids = $this->get_post_ids();
		$this->trim_posts( $post_ids );
		$this->trim_comments();
		$this->trim_terms( $post_ids );

		if ( is_array( $sites ) ) {
			restore_current_blog();
			$wpdb->prefix = $original_prefix;
			$this->trim( $sites );
		}
	}

	/**
	 * Get post IDs.
	 *
	 * @return array
	 */
	private function get_post_ids() {
		// Trim posts
		$post_types = get_post_types( [], 'names' );

		if ( empty( $post_types ) ) {
			return [];
		}

		$this->log( 'Trimming posts...' );

		$post_ids       = [];
		$posts_per_page = 100;

		foreach ( $post_types as $post_type ) {
			// Get the limit number of posts for the post type.
			$paged               = 1;
			$limit               = $this->limits['posts'];
			$posts_for_post_type = [];

			do {
				$posts = get_posts(
					[
						'post_type'      => $post_type,
						'posts_per_page' => $posts_per_page,
						'paged'          => $paged,
						'fields'         => 'ids',
						'orderby'        => 'ID',
						'order'          => 'DESC',
						'no_found_rows'  => true,
					]
				);

				if ( empty( $posts ) ) {
					break;
				}

				$posts_for_post_type = array_merge( $posts_for_post_type, $posts );

				if ( count( $posts_for_post_type ) >= $limit ) {
					$posts_for_post_type = array_slice( $posts_for_post_type, 0, $limit );
					break;
				}

				$paged++;
			} while ( $paged * $posts_per_page < $limit );

			$post_ids = array_merge( $post_ids, $posts_for_post_type );
		}

		return $post_ids;
	}

	/**
	 * Trims posts.
	 *
	 * @param array $post_ids Post IDs.
	 */
	private function trim_posts( array $post_ids ) {
		global $wpdb;

		if ( ! empty( $post_ids ) ) {
			// Delete other posts.
			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}posts WHERE ID NOT IN (" . implode( ',', $post_ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);

			// Delete orphan comments.
			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}comments WHERE comment_post_ID NOT IN (" . implode( ',', $post_ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);

			// Delete orphan meta
			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}postmeta WHERE post_id NOT IN (" . implode( ',', $post_ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		}
	}

	/**
	 * Trims comments.
	 */
	private function trim_comments() {
		global $wpdb;

		$this->log( 'Trimming comments...' );

		$comments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->prefix}comments ORDER BY comment_ID DESC LIMIT %d",
				$this->limits['comments']
			),
			'ARRAY_A'
		);

		// Delete comments
		if ( ! empty( $comments ) ) {
			$comment_ids = [];

			foreach ( $comments as $comment ) {
				$comment_ids[] = (int) $comment['comment_ID'];
			}

			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}comments WHERE comment_ID NOT IN (" . implode( ',', $comment_ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);

			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}commentmeta WHERE comment_id NOT IN (" . implode( ',', $comment_ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		}
	}

	/**
	 * Trims terms.
	 *
	 * @param array $post_ids Post IDs.
	 */
	private function trim_terms( array $post_ids ) {
		global $wpdb;

		// Terms
		$this->log( 'Trimming terms...' );

		if ( ! empty( $post_ids ) ) {
			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id NOT IN (" . implode( ',', $post_ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		}

		$term_relationships = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}term_relationships ORDER BY term_taxonomy_id DESC", 'ARRAY_A' );

		if ( ! empty( $term_relationships ) ) {
			$term_taxonomy_ids = [];

			foreach ( $term_relationships as $term_relationship ) {
				$term_taxonomy_ids[] = (int) $term_relationship['term_taxonomy_id'];
			}

			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}term_taxonomy WHERE term_taxonomy_id NOT IN (" . implode( ',', $term_taxonomy_ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);

		}

		$term_taxonomy = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}term_taxonomy ORDER BY term_taxonomy_id DESC", 'ARRAY_A' );

		if ( ! empty( $term_taxonomy ) ) {
			$term_ids = [];

			foreach ( $term_taxonomy as $term_taxonomy_row ) {
				$term_ids[] = (int) $term_taxonomy_row['term_id'];
			}

			// Delete excess terms
			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}terms WHERE term_id NOT IN (" . implode( ',', $term_ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);

			// Delete excess term meta
			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}termmeta WHERE term_id NOT IN (" . implode( ',', $term_ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		}
	}
}
