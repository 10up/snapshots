<?php
/**
 * Tests for the FileSystem class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests;

use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\FileSystem;
use TenUp\Snapshots\Snapshots;
use TenUp\Snapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\Snapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use ZipArchive;

/**
 * Class TestFileSystem
 *
 * @package TenUp\Snapshots\Tests
 *
 * @coversDefaultClass \TenUp\Snapshots\FileSystem
 */
class TestFileSystem extends TestCase {

    use PrivateAccess, DirectoryFiltering;

	/**
	 * SnapshotsDirectory instance.
	 *
	 * @var FileSystem
	 */
	private $filesystem;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->filesystem = ( new Snapshots() )->get_instance( FileSystem::class );

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
		$this->assertInstanceOf( FileSystem::class, $this->filesystem );
	}

	/** @covers ::delete_directory_contents */
	public function test_delete_directory_contents() {
		$directory = $this->get_directory_path() . '/test';

		// Create the directory.
		mkdir( $directory );

		for ( $i = 0; $i < 10; $i++ ) {
			$file = $directory . '/test-' . $i . '.txt';
			$contents = 'This is a test file. ' . $i;

			$this->filesystem->get_wp_filesystem()->put_contents( $file, $contents );

			$this->assertFileExists( $file, 'File ' . $file . ' does not exist.' );
		}

		// Create some files in a subdirectory.
		mkdir( $directory . '/subdirectory' );
		$this->filesystem->get_wp_filesystem()->put_contents( $directory . '/subdirectory/test-0.txt', 'This is a test file.' );
		$this->filesystem->get_wp_filesystem()->put_contents( $directory . '/subdirectory/test-1.txt', 'This is a test file.' );

		// Create some files in a sub-subdirectory.
		mkdir( $directory . '/subdirectory/sub-subdirectory' );
		$this->filesystem->get_wp_filesystem()->put_contents( $directory . '/subdirectory/sub-subdirectory/test-0.txt', 'This is a test file.' );
		$this->filesystem->get_wp_filesystem()->put_contents( $directory . '/subdirectory/sub-subdirectory/test-1.txt', 'This is a test file.' );

		$this->filesystem->delete_directory_contents(
			$directory,
			true,
			[
				$directory . '/test-8.txt',
				$directory . '/subdirectory/test-1.txt',
				'/subdirectory/sub-subdirectory',
				'test-6.txt'
			]
		);

		$this->assertFileDoesNotExist( $directory . '/test-0.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-1.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-2.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-3.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-4.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-5.txt' );
		$this->assertFileExists( $directory . '/test-6.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-7.txt' );
		$this->assertFileExists( $directory . '/test-8.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-9.txt' );
		$this->assertFileDoesNotExist( $directory . '/subdirectory/test-0.txt' );
		$this->assertFileExists( $directory . '/subdirectory/test-1.txt' );
		$this->assertFileExists( $directory . '/subdirectory/sub-subdirectory/' );
		$this->assertFileExists( $directory . '/subdirectory/sub-subdirectory/test-0.txt' );
		$this->assertFileExists( $directory . '/subdirectory/sub-subdirectory/test-1.txt' );


		// Confirm root still exists becuase it contains excluded files.
		$this->assertFileExists( $directory );

		$this->filesystem->get_wp_filesystem()->delete( $directory, true );

	}

	/** @covers ::delete_directory_contents */
	public function test_delete_directory_contents_can_delete_root_when_excludes_are_empty() {
		$directory = $this->get_directory_path() . '/test';

		// Create the directory.
		mkdir( $directory );

		for ( $i = 0; $i < 10; $i++ ) {
			$file = $directory . '/test-' . $i . '.txt';
			$contents = 'This is a test file. ' . $i;

			$this->filesystem->get_wp_filesystem()->put_contents( $file, $contents );

			$this->assertFileExists( $file, 'File ' . $file . ' does not exist.' );
		}

		$this->filesystem->delete_directory_contents( $directory, true );

		$this->assertFileDoesNotExist( $directory . '/test-0.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-1.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-2.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-3.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-4.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-5.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-6.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-7.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-8.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-9.txt' );

		// Confirm root no longer exists.
		$this->assertFileDoesNotExist( $directory );

		$this->filesystem->get_wp_filesystem()->delete( $directory, true );
	}

	/** @covers ::unzip_file */
	public function test_unzip_file() {
		$zip_file = $this->get_directory_path() . '/test.zip';
		$destination_directory = $this->get_directory_path() . '/destination';

		// Create the destination directory.
		mkdir( $destination_directory );

		// Create a zip file.
		$zip = new ZipArchive();
		$zip->open( $zip_file, ZipArchive::CREATE );

		for ( $i = 0; $i < 10; $i++ ) {
			$zip->addFromString( 'test-' . $i . '.txt', 'This is a test file. ' . $i );
		}

		$zip->close();

		$this->filesystem->unzip_file( $zip_file, $destination_directory );

		for ( $i = 0; $i < 10; $i++ ) {
			$destination = $destination_directory . '/test-' . $i . '.txt';

			$this->assertTrue( file_exists( $destination ) );
			$this->assertEquals( 'This is a test file. ' . $i, $this->filesystem->get_wp_filesystem()->get_contents( $destination ) );
		}

		$this->filesystem->get_wp_filesystem()->delete( $destination_directory, true );
	}

	/** @covers ::unzip_file */
	public function test_unzip_file_with_gzipped_sql_file() {
		// Create an sql file.
		$sql_file = $this->get_directory_path() . '/test.sql';
		$sql_handle = fopen( $sql_file, 'w' );
		fwrite( $sql_handle, 'This is a test file.' );
		fclose( $sql_handle );

		// Create a gzipped sql file.
		$gzipped_sql_file = $this->get_directory_path() . '/test.sql.gz';
		$gzipped_sql_handle = gzopen( $gzipped_sql_file, 'w' );

		// Add the sql file to the gzipped sql file.
		gzwrite( $gzipped_sql_handle, file_get_contents( $sql_file ) );
		gzclose( $gzipped_sql_handle );

		$destination = $this->get_directory_path() . '/destination';
		mkdir( $destination );

		$this->filesystem->unzip_file( $gzipped_sql_file, $destination );

		$this->assertTrue( file_exists( $destination . '/test.sql' ) );

		$this->filesystem->get_wp_filesystem()->delete( $destination, true );
	}

	/** @covers ::unzip_file */
	public function test_unzip_file_throws_when_invalid_file_is_passed() {
		$destination_directory = $this->get_directory_path() . '/destination';

		// Create the destination directory.
		mkdir( $destination_directory );

		$this->expectException( SnapshotsException::class );
		$this->expectExceptionMessage( 'Incompatible Archive' );

		$this->filesystem->unzip_file( 'nonexistent-file', $destination_directory, );

		$this->filesystem->get_wp_filesystem()->delete( $destination_directory, true );
	}
}
