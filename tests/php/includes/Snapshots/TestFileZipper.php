<?php
/**
 * Tests for the FileZipper class.
 * 
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Phar;
use PharData;
use PHPUnit\Framework\MockObject\MockObject;
use TenUp\Snapshots\FileSystem;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Snapshots\DynamoDBConnector;
use TenUp\Snapshots\Snapshots\FileZipper;
use TenUp\Snapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use ZipArchive;

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
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->tear_down_directory_filtering();
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
		add_filter( 'tenup_snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		$id = 'test-id';
		$args = [
			'excludes' => [],
			'exclude_uploads' => false,
		];

		$this->file_zipper->zip_files( $id, $args );

		$this->assertFileExists( '/tenup-snapshots-tmp/test-id/files.tar.gz' );

		remove_filter( 'tenup_snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		// Unzip the file and check the contents.
		$phar = new PharData( '/tenup-snapshots-tmp/test-id/files.tar.gz' );
		$phar->decompress();
		$phar->extractTo( '/tmp/files' );

		unset( $phar );
		Phar::unlinkArchive( '/tenup-snapshots-tmp/test-id/files.tar.gz' );

		$this->assertFileExists( '/tmp/files/uploads/' );
		$this->assertFileExists( '/tmp/files/uploads/test-file.txt' );
		$this->assertFileExists( '/tmp/files/plugins/test-file.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-file.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/test-file.txt' );
		$this->assertFileExists( '/tmp/files/themes/test-theme/test-file-2.txt' );

		$this->file_system->get_wp_filesystem()->delete( '/tenup-snapshots-tmp', true );
		$this->file_system->get_wp_filesystem()->delete( '/tmp/files', true );
		$this->file_system->get_wp_filesystem()->delete( '/tmp/wp-content', true );


	}

	/**
	 * @covers ::zip_files
	 * @covers ::get_build_from_iterator_iterator
	 * @covers ::build_file_list_recursively
	 */
	public function test_zip_files_with_excludes() {
		add_filter( 'tenup_snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		$id = 'test-id-2';
		$args = [
			'excludes' => [
				'plugins/test-file.txt',
				'themes/test-theme',
			],
			'exclude_uploads' => true,
		];

		$this->file_zipper->zip_files( $id, $args );

		$this->assertFileExists( '/tenup-snapshots-tmp/test-id-2/files.tar.gz' );

		remove_filter( 'tenup_snapshots_wp_content_dir', [ $this, 'filter_wp_content' ] );

		// Unzip the file and check the contents.
		$phar = new PharData( '/tenup-snapshots-tmp/test-id-2/files.tar.gz' );
		$phar->decompress();
		$phar->extractTo( '/tmp/files' );

		unset( $phar );
		Phar::unlinkArchive( '/tenup-snapshots-tmp/test-id-2/files.tar.gz' );

		$this->assertFileDoesNotExist( '/tmp/files/uploads/' );
		$this->assertFileDoesNotExist( '/tmp/files/uploads/test-file.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/plugins/test-file.txt' );
		$this->assertFileExists( '/tmp/files/plugins/test-plugin/test-file.txt' );
		$this->assertFileExists( '/tmp/files/plugins/test-plugin/test-file-2.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/test-file.txt' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme/test-file-2.txt' );
		$this->assertFileExists( '/tmp/files/themes/' );
		$this->assertFileDoesNotExist( '/tmp/files/themes/test-theme' );

		$this->file_system->get_wp_filesystem()->delete( '/tenup-snapshots-tmp', true );
		$this->file_system->get_wp_filesystem()->delete( '/tmp/files', true );
		$this->file_system->get_wp_filesystem()->delete( '/tmp/wp-content', true );
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

		return $path;
	}
}
