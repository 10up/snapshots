<?php
/**
 * Tests for MultisiteURLReplacer.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\WPCLICommands\Pull;

use PHPUnit\Framework\MockObject\MockObject;
use TenUp\Snapshots\Exceptions\SnapshotsInputValidationException;
use TenUp\Snapshots\Log\WPCLILogger;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\SnapshotsDirectory;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use TenUp\Snapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\Snapshots\WordPress\Database;
use TenUp\Snapshots\WPCLI\Prompt;
use TenUp\Snapshots\WPCLICommands\Pull\MultisiteURLReplacer;
use TenUp\Snapshots\WPCLICommands\Pull\URLReplacer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestMultisiteURLReplacer
 *
 * @package TenUp\Snapshots\Tests\WPCLICommands\Pull
 *
 * @coversDefaultClass \TenUp\Snapshots\WPCLICommands\Pull\MultisiteURLReplacer
 */
class TestMultisiteURLReplacer extends TestCase {

	use WPCLIMocking, PrivateAccess;

	/**
	 * URLReplacer instance.
	 *
	 * @var MultisiteURLReplacer
	 */
	protected $url_replacer;

	/**
	 * Whether the blogs table was created.
	 *
	 * @var bool
	 */
	protected $blogs_table_created = false;

	/**
	 * Set up.
	 */
	public function set_up() {
		global $wpdb;

		parent::set_up();

		$this->set_up_wp_cli_mock();

		require_once ABSPATH . 'wp-includes/ms-site.php';
		require_once ABSPATH . 'wp-includes/class-wp-site.php';

		$plugin = new Snapshots();
		$this->url_replacer = new MultisiteURLReplacer(
			$plugin->get_instance( Prompt::class ),
			$plugin->get_instance( SnapshotsDirectory::class ),
			$plugin->get_instance( Database ::class ),
			$plugin->get_instance( WPCLILogger::class ),
			[
				'sites' => [
					[
						'blog_id' => 1,
						'home_url'  => 'http://home-url.com',
						'site_url'  => 'http://site-url.com'
					],
				],
			],
			[],
			[],
			false,
			null
		);

		// Create $wpdb->blogs table if it doesn't exist.
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE 'wp_blogs'" ) ) {
			$wpdb->blogs = 'wp_blogs';
			$wpdb->query( "CREATE TABLE {$wpdb->blogs} (blog_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, site_id BIGINT(20) UNSIGNED NOT NULL DEFAULT '0', domain VARCHAR(200) NOT NULL DEFAULT '', path VARCHAR(100) NOT NULL DEFAULT '', registered DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', last_updated DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', public TINYINT(1) NOT NULL DEFAULT '1', archived TINYINT(1) NOT NULL DEFAULT '0', mature TINYINT(1) NOT NULL DEFAULT '0', spam TINYINT(1) NOT NULL DEFAULT '0', deleted TINYINT(1) NOT NULL DEFAULT '0', lang_id SMALLINT(4) NOT NULL DEFAULT '0', PRIMARY KEY  (blog_id), KEY domain (domain(50),path(5)), KEY lang_id (lang_id))" );
			$this->blogs_table_created = true;
		}
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();

		if ( $this->blogs_table_created ) {
			global $wpdb;
			$wpdb->query( "DROP TABLE {$wpdb->blogs}" );
		}
	}

	/** @covers ::__construct */
	public function test_constructor() {
		$this->assertInstanceOf( MultisiteURLReplacer::class, $this->url_replacer );
		$this->assertInstanceOf( URLReplacer::class, $this->url_replacer );
	}

	/**
	 * @covers ::replace_urls
	 * @covers ::replace_urls_for_site
	 */
	public function test_replace_urls() : void {
		global $wpdb;

		// Drop tables if they exist.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->usermeta}_temp" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->users}_temp" );

		$meta = [
			'sites' => [
				[
					'blog_id' => 1,
					'home_url'  => 'http://home-url.com',
					'site_url'  => 'http://site-url.com'
				],
			],
		];

		$this->set_private_property( $this->url_replacer, 'meta', $meta );

		$new_url = $this->url_replacer->replace_urls();
		$this->assertEquals( 'readline0', $new_url );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			2,
			[
				[
					'search-replace //home-url.com readline0 wp_commentmeta wp_comments wp_links wp_options wp_postmeta wp_posts wp_termmeta wp_usermeta wp_users --precise --skip-themes --skip-plugins --skip-packages --network',
					[
						'launch' => true,
						'exit_error' => false,
						'return' => 'all',
					],
				],
				[
					'search-replace //site-url.com readline1 wp_commentmeta wp_comments wp_links wp_options wp_postmeta wp_posts wp_termmeta wp_usermeta wp_users --precise --skip-themes --skip-plugins --skip-packages --network',
					[
						'launch' => true,
						'exit_error' => false,
						'return' => 'all',
					],
				],
			]
		);

		$this->get_wp_cli_mock()->assertMethodCalled(
			'readline',
			2,
			[
				[
					'Home URL (defaults home URL in snapshot: http://home-url.com): '
				],
				[
					'Site URL (defaults site URL in snapshot: http://site-url.com): '
				],
			]
		);
	}

	/** @covers ::domain_validator */
	public function test_domain_validator() {
		$invalid_domain = 'http://example.com';
		$valid_domain = 'example.com';

		$this->assertEquals( $valid_domain, $this->call_private_method( $this->url_replacer, 'domain_validator', [ $valid_domain ] ) );

		$this->expectException( SnapshotsInputValidationException::class );
		$this->call_private_method( $this->url_replacer, 'domain_validator', [ $invalid_domain ] );
	}

	/** @covers ::write_constants_to_wp_config */
	public function test_write_constants_to_wp_config() {
		// Create temporary wp-config.php file.
		$wp_config_file = '/tmp/wp-config.php';
		file_put_contents( $wp_config_file, "<?php
define( 'WP_HOME', 'previousurl.com' );
define( 'SOME_OTHER_CONSTANT', 'somevalue' );
" );

		$this->call_private_method( $this->url_replacer, 'write_constants_to_wp_config', [
			[
				'WP_SITEURL' => 'site1.com',
				'WP_HOME' => 'site1.com',
			],
			$wp_config_file,
		] );

		$this->assertEquals(
			"<?php
if ( ! defined( \"WP_HOME\" ) ) {
	define( \"WP_HOME\", 'site1.com' ); // Auto added.
}
if ( ! defined( \"WP_SITEURL\" ) ) {
	define( \"WP_SITEURL\", 'site1.com' ); // Auto added.
}
define( 'SOME_OTHER_CONSTANT', 'somevalue' );",
			file_get_contents( $wp_config_file )
		);
	}

	/** @covers ::get_tables_to_update */
	public function test_get_tables_to_update() {
		$this->assertEquals(
			[
				'wp_blogs',
				'wp_commentmeta',
				'wp_comments',
				'wp_links',
				'wp_options',
				'wp_postmeta',
				'wp_posts',
				'wp_term_relationships',
				'wp_term_taxonomy',
				'wp_termmeta',
				'wp_terms',
				'wp_usermeta',
				'wp_users',
			],
			$this->call_private_method( $this->url_replacer, 'get_tables_to_update', [ [ 'blog_id' => 1 ], 'wp_', [] ] )
		);
	}

	/** @covers ::get_tables_to_update */
	public function test_get_tables_to_update_with_excluded_tables() {
		$this->assertEquals(
			[
				'wp_blogs',
				'wp_commentmeta',
				'wp_comments',
				'wp_links',
				'wp_options',
				'wp_postmeta',
				'wp_posts',
				'wp_term_relationships',
				'wp_term_taxonomy',
				'wp_termmeta',
				'wp_terms',
				'wp_usermeta',
				'wp_users',
			],
			$this->call_private_method( $this->url_replacer, 'get_tables_to_update', [ [ 'blog_id' => 1 ], 'wp_', [ 'wp_options' ] ] )
		);
	}

	/** @covers ::get_tables_to_update */
	public function test_get_tables_to_update_with_excluded_tables_and_prefix() {
		/**
		 * Mock Database class.
		 *
		 * @var MockObject|Database $database
		 */
		$database = $this->createMock( Database::class );
		$database->method( 'get_tables' )->willReturn(
				[
					'wp_2_posts',
					'wp_2_commentmeta',
					'wp_2_comments',
					'wp_3_posts',
					'wp_5_posts',
				]
			);

		$this->set_private_property( $this->url_replacer, 'wordpress_database', $database );

		$this->assertEquals(
			[
				'wp_2_posts',
				'wp_2_commentmeta',
				'wp_2_comments',
			],
			$this->call_private_method( $this->url_replacer, 'get_tables_to_update', [ [ 'blog_id' => 2 ], 'wp_', [ 'options' ] ] )
		);
	}
}
