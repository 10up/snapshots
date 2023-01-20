<?php
/**
 * Tests for SingleSiteURLReplacer.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests\WPCLICommands\Pull;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsInputValidationException;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\SnapshotsFileSystem;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use TenUp\WPSnapshots\Tests\Fixtures\WPCLIMocking;
use TenUp\WPSnapshots\WordPress\Database;
use TenUp\WPSnapshots\WPCLI\Prompt;
use TenUp\WPSnapshots\WPCLICommands\Pull\SingleSiteURLReplacer;
use TenUp\WPSnapshots\WPCLICommands\Pull\URLReplacer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class TestSingleSiteURLReplacer
 * 
 * @package TenUp\WPSnapshots\Tests\WPCLICommands\Pull
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\WPCLICommands\Pull\SingleSiteURLReplacer
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

		$plugin = new Plugin();
		$this->url_replacer = new SingleSiteURLReplacer(
			$plugin->get_instance( Prompt::class ),
			$plugin->get_instance( SnapshotsFileSystem::class ),
			$plugin->get_instance( Database ::class ),
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
					'search-replace http://search.com http://replace.com table1 table2 --quiet --skip-columns=guid --precise --skip-themes --skip-plugins --skip-packages --report=false',
					[ 'launch' => false ]
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
					'search-replace http://home-url.com Y wp_commentmeta wp_comments wp_links wp_options wp_postmeta wp_posts wp_termmeta wp_usermeta wp_users --quiet --skip-columns=guid --precise --skip-themes --skip-plugins --skip-packages --report=false',
					[ 'launch' => false ]
				],
				[
					'search-replace http://site-url.com Y wp_commentmeta wp_comments wp_links wp_options wp_postmeta wp_posts wp_termmeta wp_usermeta wp_users --quiet --skip-columns=guid --precise --skip-themes --skip-plugins --skip-packages --report=false',
					[ 'launch' => false ]
				],
			]
		);

	}
}
