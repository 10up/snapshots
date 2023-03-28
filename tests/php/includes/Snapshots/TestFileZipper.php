<?php
/**
 * Tests for the FileZipper class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Snapshots;

use Phar;
use PharData;
use TenUp\Snapshots\FileSystem;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Snapshots\FileZipper;
use TenUp\Snapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class FileZipper
 *
 * @package TenUp\Snapshots\Tests\Snapshots
 *
 * @coversDefaultClass \TenUp\Snapshots\Snapshots\FileZipper
 */
class TestFileZipper extends TestCase {

	use PrivateAccess, DirectoryFiltering;

	/**
	 * FileSystem instance.
	 *
	 * @var FileSystem
	 */
	private $file_system;

	/**
	 * FileZipper instance.
	 *
	 * @var FileZipper
	 */
	private $file_zipper;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->file_system = ( new Snapshots() )->get_instance( FileSystem::class );
		$this->file_zipper = ( new Snapshots() )->get_instance( FileZipper::class );

		$this->set_up_directory_filtering();

		$this->file_system->get_wp_filesystem()->delete( '/tmp/files', true );

		// Recreate /tmp/files.
		$this->file_system->get_wp_filesystem()->mkdir( '/tmp/files' );
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_directory_filtering();

		$this->file_system->get_wp_filesystem()->delete( '/tenup-snapshots-tmp', true );
		$this->file_system->get_wp_filesystem()->delete( '/tmp/files', true );
		$this->file_system->get_wp_filesystem()->delete( '/tmp/wp-content', true );
	}

	public function test_constructor() {
		$this->assertInstanceOf( FileZipper::class, $this->file_zipper );
	}

	/**
	 * @covers ::zip_files
	 * @covers ::get_build_from_iterator_iterator
	 * @covers ::build_file_list_recursively
	 */
	public function test_zip_files() {
		add_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		$id = 'test-id';
		$args = [
			'excludes' => [],
			'exclude_uploads' => false,
			'include_node_modules' => false,
			'exclude_vendor' => false,
		];

		$this->file_zipper->zip_files( $id, $args );

		$this->assertFileExists( '/tenup-snapshots-tmp/test-id/files.tar.gz' );

		remove_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		// Unzip with exec and tar instead. The above will be deleted.
		exec( 'tar -xzf /tenup-snapshots-tmp/test-id/files.tar.gz -C /tmp/files');		

		$this->assertFileExists( '/tmp/files/uploads/' );
		$this->assertFileExists( '/tmp/files/uploads/test-file.txt' );
		$this->assertFileExists( '/tmp/files/plugins/test-file.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-file.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/test-file.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/test-file-2.txt' );
	}

	/**
	 * @covers ::zip_files
	 * @covers ::get_build_from_iterator_iterator
	 * @covers ::build_file_list_recursively
	 */
	public function test_zip_files_with_excludes() {
		add_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		$id = 'test-id-2';
		$args = [
			'excludes' => [
				'plugins/test-file.txt',
				'themes/test-theme',
			],
			'exclude_uploads' => true,
			'include_node_modules' => false,
			'exclude_vendor' => false,
		];

		$this->file_zipper->zip_files( $id, $args );

		$this->assertFileExists( '/tenup-snapshots-tmp/test-id-2/files.tar.gz' );

		remove_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		// Unzip the file and check the contents.
		exec( 'tar -xzf /tenup-snapshots-tmp/test-id-2/files.tar.gz -C /tmp/files');

		$this->assertFileDoesNotExist( '/tmp/files/uploads/' );
		$this->assertFileDoesNotExist( '/tmp/files/uploads/test-file.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/plugins/test-file.txt' );
		$this->assertFileExists( '/tmp/files/plugins/test-plugin/test-file.txt' );
		$this->assertFileExists( '/tmp/files/plugins/test-plugin/test-file-2.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/test-file.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/test-file-2.txt' );
		$this->assertFileExists( '/tmp/files/themes/' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme' );
	}

	/**
	 * @covers ::zip_files
	 * @covers ::get_build_from_iterator_iterator
	 * @covers ::build_file_list_recursively
	 * 
	 * @group failing
	 */
	public function test_zip_files_with_exclude_node_modules() {
		add_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		$id = 'test-id-3';
		$args = [
			'excludes' => [],
			'exclude_uploads' => true,
			'include_node_modules' => false,
			'exclude_vendor' => false,
		];

		$this->file_zipper->zip_files( $id, $args );

		$this->assertFileExists( '/tenup-snapshots-tmp/test-id-3/files.tar.gz' );

		remove_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		// Unzip the file and check the contents.
		exec( 'tar -xzf /tenup-snapshots-tmp/test-id-3/files.tar.gz -C /tmp/files');

		$this->assertFileDoesNotExist( '/tmp/files/plugins/test-plugin/node_modules' );
		$this->assertFileDoesNotExist( '/tmp/files/plugins/test-plugin/node_modules/test-file.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/plugins/test-plugin/node_modules/test-file-2.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/node_modules' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/node_modules/test-file.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/node_modules/test-file-2.txt' );
	}

	/**
	 * @covers ::zip_files
	 * @covers ::get_build_from_iterator_iterator
	 * @covers ::build_file_list_recursively
	 */
	public function test_zip_files_with_include_node_modules() {
		add_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		$id = 'test-id-4';
		$args = [
			'excludes' => [],
			'exclude_uploads' => true,
			'include_node_modules' => true,
			'exclude_vendor' => false,
		];

		$this->file_zipper->zip_files( $id, $args );

		$this->assertFileExists( '/tenup-snapshots-tmp/test-id-4/files.tar.gz' );

		remove_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		// Unzip the file and check the contents.
		exec( 'tar -xzf /tenup-snapshots-tmp/test-id-4/files.tar.gz -C /tmp/files');

		$this->assertFileExists( '/tmp/files/plugins/test-plugin/node_modules' );
		$this->assertFileExists( '/tmp/files/plugins/test-plugin/node_modules/test-file.txt' );
		$this->assertFileExists( '/tmp/files/plugins/test-plugin/node_modules/test-file-2.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/node_modules' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/node_modules/test-file.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/node_modules/test-file-2.txt' );
	}

	/**
	 * @covers ::zip_files
	 * @covers ::get_build_from_iterator_iterator
	 * @covers ::build_file_list_recursively
	 */
	public function test_zip_files_with_include_vendor() {
		add_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		$id = 'test-id-5';
		$args = [
			'excludes' => [],
			'exclude_uploads' => true,
			'include_node_modules' => false,
			'exclude_vendor' => false,
		];

		$this->file_zipper->zip_files( $id, $args );

		$this->assertFileExists( '/tenup-snapshots-tmp/test-id-5/files.tar.gz' );

		remove_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		// Unzip the file and check the contents.
		exec( 'tar -xzf /tenup-snapshots-tmp/test-id-5/files.tar.gz -C /tmp/files');

		$this->assertFileExists( '/tmp/files/plugins/test-plugin/vendor' );
		$this->assertFileExists( '/tmp/files/plugins/test-plugin/vendor/test-file.txt' );
		$this->assertFileExists( '/tmp/files/plugins/test-plugin/vendor/test-file-2.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/vendor' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/vendor/test-file.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/vendor/test-file-2.txt' );
	}

	/**
	 * @covers ::zip_files
	 * @covers ::get_build_from_iterator_iterator
	 * @covers ::build_file_list_recursively
	 */
	public function test_zip_files_with_exclude_vendor() {
		add_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		$id = 'test-id-6';
		$args = [
			'excludes' => [],
			'exclude_uploads' => true,
			'include_node_modules' => false,
			'exclude_vendor' => true,
		];

		$this->file_zipper->zip_files( $id, $args );

		$this->assertFileExists( '/tenup-snapshots-tmp/test-id-6/files.tar.gz' );

		remove_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		// Unzip the file and check the contents.
		exec( 'tar -xzf /tenup-snapshots-tmp/test-id-6/files.tar.gz -C /tmp/files');

		$this->assertFileDoesNotExist( '/tmp/files/plugins/test-plugin/vendor' );
		$this->assertFileDoesNotExist( '/tmp/files/plugins/test-plugin/vendor/test-file.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/plugins/test-plugin/vendor/test-file-2.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/vendor' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/vendor/test-file.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/vendor/test-file-2.txt' );
	}

	/**
	 * @covers ::zip_files
	 */
	public function test_zip_files_with_very_long_file_names() {
		add_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		$this->filter_wp_content();

		$id = 'test-id-7';
		$args = [
			'excludes' => [],
			'exclude_uploads' => false,
			'include_node_modules' => false,
			'exclude_vendor' => true,
		];

		$this->file_zipper->zip_files( $id, $args );

		$this->assertFileExists( '/tenup-snapshots-tmp/test-id-7/files.tar.gz' );

		remove_filter( 'snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		// Unzip the file and check the contents.
		exec( 'tar -xzf /tenup-snapshots-tmp/test-id-7/files.tar.gz -C /tmp/files');

		// Check another file first.
		$this->assertFileExists( '/tmp/files/uploads/test-file.txt' );

		// Check the long file name.
		$this->assertFileExists( '/tmp/files/uploads/' . $this->get_100_character_file_name() );

		// Check the long file name.
		$this->assertFileExists( '/tmp/files/uploads/' . $this->get_101_character_file_name() );
	}

	/**
	 * Provides a file name that is 100 characters.
	 * 
	 * @return string
	 */
	public function get_100_character_file_name() {
		return 'this-is-a-very-long-file-name-that-is-100-characters-long-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.txt';
	}

	/**
	 * Provides a file name that is 101 characters.
	 * 
	 * @return string
	 */
	public function get_101_character_file_name() {
		return 'this-is-a-very-long-file-name-that-is-101-characters-long-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.txt';
	}

	public function filter_wp_content() {
		$path = '/tmp/wp-content';

		if ( ! file_exists( $path ) ) {
			mkdir( $path );
		}

		$uploads = $path . '/uploads';
		if ( ! file_exists( $uploads ) ) {
			mkdir( $uploads );
		}

		$uploads_file = $uploads . '/test-file.txt';

		file_put_contents( $uploads_file, 'test' );

		$plugins = $path . '/plugins';

		if ( ! file_exists( $plugins ) ) {
			mkdir( $plugins );
		}

		$plugins_file = $plugins . '/test-file.txt';

		file_put_contents( $plugins_file, 'test' );

		// Create a plugin with two files.
		$plugin = $plugins . '/test-plugin';

		if ( ! file_exists( $plugin ) ) {
			mkdir( $plugin );
		}

		$plugin_file = $plugin . '/test-file.txt';

		file_put_contents( $plugin_file, 'test' );

		// create another plugin file
		$plugin_file = $plugin . '/test-file-2.txt';

		file_put_contents( $plugin_file, 'test' );

		$themes = $path . '/themes';

		if ( ! file_exists( $themes ) ) {
			mkdir( $themes );
		}

		$themes_file = $themes . '/test-file.txt';

		file_put_contents( $themes_file, 'test' );

		// create a theme.
		$theme = $themes . '/test-theme';

		if ( ! file_exists( $theme ) ) {
			mkdir( $theme );
		}

		$theme_file = $theme . '/test-file.txt';

		file_put_contents( $theme_file, 'test' );

		// create another theme file
		$theme_file = $theme . '/test-file-2.txt';

		file_put_contents( $theme_file, 'test' );

		// create a vendor directory.
		$vendor = $theme . '/vendor';

		if ( ! file_exists( $vendor ) ) {
			mkdir( $vendor );
		}

		$vendor_file = $vendor . '/test-file.txt';

		file_put_contents( $vendor_file, 'test' );

		// create another vendor file
		$vendor_file = $vendor . '/test-file-2.txt';

		file_put_contents( $vendor_file, 'test' );

		// Do the same in a plugin.
		$vendor = $plugin . '/vendor';

		if ( ! file_exists( $vendor ) ) {
			mkdir( $vendor );
		}

		$vendor_file = $vendor . '/test-file.txt';

		file_put_contents( $vendor_file, 'test' );

		// create another vendor file
		$vendor_file = $vendor . '/test-file-2.txt';

		file_put_contents( $vendor_file, 'test' );

		// create files in the plugin node_modules directory.

		$node_modules = $plugin . '/node_modules';

		if ( ! file_exists( $node_modules ) ) {
			mkdir( $node_modules );
		}

		$this->assertFileExists( $node_modules );

		$node_modules_file = $node_modules . '/test-file.txt';

		file_put_contents( $node_modules_file, 'test' );

		// create another node_modules file
		$node_modules_file = $node_modules . '/test-file-2.txt';

		file_put_contents( $node_modules_file, 'test' );

		// Create node_modules in the theme.
		$node_modules = $theme . '/node_modules';

		if ( ! file_exists( $node_modules ) ) {
			mkdir( $node_modules );
		}

		$node_modules_file = $node_modules . '/test-file.txt';

		file_put_contents( $node_modules_file, 'test' );

		// create another node_modules file

		$node_modules_file = $node_modules . '/test-file-2.txt';

		file_put_contents( $node_modules_file, 'test' );

		// Add a file with a 100-character file name.
		$file_name = $this->get_100_character_file_name();

		$long_file = $uploads . '/' . $file_name;

		file_put_contents( $long_file, 'test' );

		// Add a file with a 101-character file name.
		$file_name = $this->get_101_character_file_name();

		$long_file = $uploads . '/' . $file_name;

		file_put_contents( $long_file, 'test' );

		return $path;
	}


}
