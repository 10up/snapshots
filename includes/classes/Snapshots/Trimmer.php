<?php
/**
 * Trimmer class
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Infrastructure\SharedService;
use TenUp\WPSnapshots\Log\{LoggerInterface, Logging};
use WP_Site;

/**
 * Class Trimmer
 *
 * @package TenUp\WPSnapshots
 */
class Trimmer implements SharedService {

	use Logging;

	/**
	 * Class constructor.
	 *
	 * @param LoggerInterface $logger LoggerInterface instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->set_logger( $logger );
	}

	/**
	 * Trims the database
	 */
	public function trim() {
		$this->trim_database( is_multisite() ? get_sites() : null );
	}

	/**
	 * Makes the database small.
	 *
	 * @param ?WP_Site[] $sites Sites to trim.
	 */
	private function trim_database( ?array $sites = null ) {
		global $wpdb;

		if ( is_array( $sites ) ) {
			if ( empty( $sites ) ) {
				return;
			}

			$site = array_shift( $sites );

			$this->log( 'Trimming snapshot data and files blog ' . $site->blog_id . '...' );

			switch_to_blog( (int) $site->blog_id );

			$wpdb->prefix = $wpdb->get_blog_prefix( $site->blog_id );
		} else {
			$this->log( 'Trimming snapshot data and files...' );
		}

		$post_ids = $this->get_post_ids();
		$this->trim_posts( $post_ids );
		$this->trim_comments();
		$this->trim_terms( $post_ids );

		if ( is_array( $sites ) ) {
			restore_current_blog();
			$this->trim_database( $sites );
		}
	}

	/**
	 * Get post IDs.
	 *
	 * @return array
	 */
	private function get_post_ids() {
		// Trim posts
		$post_ids   = [];
		$post_types = get_post_types( [], 'names' );

		if ( empty( $post_types ) ) {
			return [];
		}

		$this->log( 'Trimming posts...' );

		foreach ( $post_types as $post_type ) {
			for ( $i = 1; $i <= 3; $i++ ) {
				$next_post_ids = get_posts(
					[
						'posts_per_page' => 100,
						'paged'          => $i,
						'post_type'      => $post_type,
						'fields'         => 'ids',
						'orderby'        => 'ID',
						'order'          => 'DESC',
					]
				);

				if ( ! empty( $next_post_ids ) ) {
					$post_ids = array_merge( $post_ids, $next_post_ids );
				}

				if ( count( $next_post_ids ) < 100 ) {
					break;
				}
			}
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
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}posts WHERE ID NOT IN (%s)",
					implode( ',', $post_ids )
				)
			);

			// Delete orphan comments.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}comments WHERE comment_post_ID NOT IN (%s)",
					implode( ',', $post_ids )
				)
			);

			// Delete orphan meta
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}postmeta WHERE post_id NOT IN (%s)",
					implode( ',', $post_ids )
				)
			);
		}
	}

	/**
	 * Trims comments.
	 */
	private function trim_comments() {
		global $wpdb;

		$this->log( 'Trimming comments...' );

		$comments = $wpdb->get_results( "SELECT comment_ID FROM {$wpdb->prefix}comments ORDER BY comment_ID DESC LIMIT 500", defined( ARRAY_A ) ? ARRAY_A : 'ARRAY_A' );

		// Delete comments
		if ( ! empty( $comments ) ) {
			$comment_ids = [];

			foreach ( $comments as $comment ) {
				$comment_ids[] = (int) $comment['ID'];
			}

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}comments WHERE comment_ID NOT IN (%s)",
					implode( ',', $comment_ids )
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}commentmeta WHERE comment_id NOT IN (%s)",
					implode( ',', $comment_ids )
				)
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

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id NOT IN (%s)",
				implode( ',', array_unique( $post_ids ) )
			)
		);

		$term_relationships = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}term_relationships ORDER BY term_taxonomy_id DESC", defined( ARRAY_A ) ? ARRAY_A : 'ARRAY_A' );

		if ( ! empty( $term_relationships ) ) {
			$term_taxonomy_ids = [];

			foreach ( $term_relationships as $term_relationship ) {
				$term_taxonomy_ids[] = (int) $term_relationship['term_taxonomy_id'];
			}

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}term_taxonomy WHERE term_taxonomy_id NOT IN (%s)",
					implode( ',', array_unique( $term_taxonomy_ids ) )
				)
			);

		}

		$term_taxonomy = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}term_taxonomy ORDER BY term_taxonomy_id DESC", defined( ARRAY_A ) ? ARRAY_A : 'ARRAY_A' );

		if ( ! empty( $term_taxonomy ) ) {
			$term_ids = [];

			foreach ( $term_taxonomy as $term_taxonomy_row ) {
				$term_ids[] = (int) $term_taxonomy_row['term_id'];
			}

			// Delete excess terms
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}terms WHERE term_id NOT IN (%s)",
					implode( ',', array_unique( $term_ids ) )
				)
			);

			// Delete excess term meta
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}termmeta WHERE term_id NOT IN (%s)",
					implode( ',', array_unique( $term_ids ) )
				)
			);
		}
	}
}
