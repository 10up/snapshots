<?php
/**
 * Tests for the Trimmer class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\Snapshots;

use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\Snapshots\Trimmer;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use TenUp\WPSnapshots\Tests\Fixtures\WPCLIMocking;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Class TestTrimmer
 *
 * @package TenUp\WPSnapshots\Tests\Snapshots
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\Snapshots\Trimmer
 */
class TestTrimmer extends TestCase {

	use PrivateAccess, WPCLIMocking;

	/**
	 * Test instance
	 * 
	 * @var Trimmer
	 */
	private $trimmer;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->trimmer = ( new Plugin() )->get_instance( Trimmer::class );
		$this->set_up_wp_cli_mock();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
	}

	/**
	 * @covers ::__construct
	 */
	public function test_constructor() {
		$this->assertInstanceOf( Trimmer::class, $this->trimmer );

		$limits_filter = function() {
			return [
				'posts' => 10,
				'comments' => 10,
			];
		};
		
		add_filter( 'wpsnapshots_trimmer_limits', $limits_filter );

		$this->trimmer = ( new Plugin() )->get_instance( Trimmer::class );

		$this->assertEquals( 10, $this->get_private_property( $this->trimmer, 'limits' )['posts'] );
		$this->assertEquals( 10, $this->get_private_property( $this->trimmer, 'limits' )['comments'] );
	}

	/** @covers ::set_limits */
	public function test_set_limits() {
		$this->trimmer->set_limits( [
			'posts' => 10,
			'comments' => 10,
		] );

		$this->assertEquals( 10, $this->get_private_property( $this->trimmer, 'limits' )['posts'] );
		$this->assertEquals( 10, $this->get_private_property( $this->trimmer, 'limits' )['comments'] );
	}

	/**
	 * @covers ::set_limits
	 * @covers ::get_post_ids
	 * @covers ::trim
	 * @covers ::trim_posts
	 * @covers ::trim_comments
	 * @covers ::trim_terms
	 */
	public function test_trim() {
		global $wpdb;

		$this->trimmer->set_limits(
			[
				'posts' => 10,
				'comments' => 10,
			]
		);
		
		// Create a term and assign it to a post.
		$term = $this->factory()->term->create( [
			'taxonomy' => 'category',
		] );

		$post = $this->factory()->post->create_and_get( [
			'post_status' => 'publish',
			'post_type' => 'post',
			'post_title' => 'Test post',
		] );

		wp_set_post_terms( $post->ID, (int) $term, 'category' );

		// Create 20 posts.
		$posts = $this->factory()->post->create_many( 20, [
			'post_status' => 'publish',
			'post_type' => 'post',
			'post_title' => 'Test post',
		] );

		// Create 20 comments and assign them to the last post.
		$comments = $this->factory()->comment->create_many( 20, [
			'comment_post_ID' => $posts[19],
		] );

		$this->trimmer->trim();

		// Confirm the term is gone.
		$this->assertEmpty( get_term( $term ) );

		// Confirm the post is gone.
		$this->assertEmpty( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post->ID ) ) );

		// Confirm there are only 10 posts.
		$this->assertCount( 10, get_posts( [
			'post_type' => 'post',
			'posts_per_page' => -1,
		] ) );

		// Confirm there are only 10 comments.
		$this->assertCount( 10, get_comments( [
			'post_id' => $posts[19],
		] ) );

	}
}
