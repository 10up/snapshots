<?php
/**
 * Tests for SingleSiteURLReplacer.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\WPCLICommands\Pull;

use TenUp\Snapshots\Exceptions\WPSnapshotsInputValidationException;
use TenUp\Snapshots\Log\WPCLILogger;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\WPSnapshotsDirectory;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use TenUp\Snapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\Snapshots\WordPress\Database;
use TenUp\Snapshots\WPCLI\Prompt;
use TenUp\Snapshots\WPCLICommands\Pull\SingleSiteURLReplacer;
use TenUp\Snapshots\WPCLICommands\Pull\URLReplacer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestSingleSiteURLReplacer
 * 
 * @package TenUp\Snapshots\Tests\WPCLICommands\Pull
 * 
 * @coversDefaultClass \TenUp\Snapshots\WPCLICommands\Pull\SingleSiteURLReplacer
 */
class TestSingleSiteURLReplacer extends TestCase {

	use WPCLIMocking, PrivateAccess;

	/**
	 * URLReplacer instance.
	 * 
	 * @var SingleSiteURLReplacer
	 */
	protected $url_replacer;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_up_wp_cli_mock();

		$plugin = new Snapshots();
		$this->url_replacer = new SingleSiteURLReplacer(
			$plugin->get_instance( Prompt::class ),
			$plugin->get_instance( WPSnapshotsDirectory::class ),
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

		// Drop wp_blogs if it exists.
		global $wpdb;
		if ( $wpdb->get_var( "SHOW TABLES LIKE 'wp_blogs'" ) === 'wp_blogs' ) {
			$wpdb->query( "DROP TABLE wp_blogs" );
		}
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_wp_cli_mock();
	}

	/** @covers ::__construct */
	public function test_constructor() {
		$this->assertInstanceOf( SingleSiteURLReplacer::class, $this->url_replacer );
		$this->assertInstanceOf( URLReplacer::class, $this->url_replacer );
	}

	/** @covers ::run_search_and_replace */
	public function test_run_search_and_replace() {
		$this->call_private_method( $this->url_replacer, 'run_search_and_replace', [ 'http://search.com', 'http://replace.com', [ 'table1', 'table2' ] ] );

		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			1,
			[
				[
					'search-replace http://search.com http://replace.com table1 table2 --skip-columns=guid --precise --skip-themes --skip-plugins --skip-packages',
					[
						'launch' => true,
						'exit_error' => false,
						'return' => 'all',
					],
				],
			]
		);
	}

	/** @covers ::url_validator */
	public function test_url_validator_with_valid_urls() {
		$this->assertEquals( 'http://example.com', $this->call_private_method( $this->url_replacer, 'url_validator', [ 'http://example.com' ] ) );
		$this->assertEquals( 'https://example.com', $this->call_private_method( $this->url_replacer, 'url_validator', [ 'https://example.com' ] ) );
	}

	/** @covers ::url_validator */
	public function test_url_validator_with_invalid_urls() {
		$this->expectException( WPSnapshotsInputValidationException::class );
		$this->expectExceptionMessage( 'URL not valid. The URL should be in the form of `https://google.com`, no trailing slash needed' );

		$this->call_private_method( $this->url_replacer, 'url_validator', [ 'example.com' ] );
	}

	/** @covers ::replace_urls */
	public function test_replace_urls() {
		$this->url_replacer->replace_urls();

		$this->get_wp_cli_mock()->assertMethodCalled(
			'runcommand',
			2,
			[
				[
					'search-replace http://home-url.com readline2 wp_commentmeta wp_comments wp_links wp_options wp_postmeta wp_posts wp_termmeta wp_usermeta wp_users --skip-columns=guid --precise --skip-themes --skip-plugins --skip-packages',
					[
						'launch' => true,
						'exit_error' => false,
						'return' => 'all',
					],
				],
				[
					'search-replace http://site-url.com readline3 wp_commentmeta wp_comments wp_links wp_options wp_postmeta wp_posts wp_termmeta wp_usermeta wp_users --skip-columns=guid --precise --skip-themes --skip-plugins --skip-packages',
					[
						'launch' => true,
						'exit_error' => false,
						'return' => 'all',
					],
				],
			]
		);

	}
}
