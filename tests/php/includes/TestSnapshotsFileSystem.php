<?php
/**
 * Tests for the SnapshotsFileSystem class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\SnapshotsFileSystem;
use TenUp\WPSnapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use ZipArchive;

/**
 * Class TestSnapshotsFileSystem
 *
 * @package TenUp\WPSnapshots\Tests
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\SnapshotsFileSystem
 */
class TestSnapshotsFileSystem extends TestCase {
	
    use PrivateAccess, DirectoryFiltering;

	/**
	 * SnapshotsFileSystem instance.
	 * 
	 * @var SnapshotsFileSystem
	 */
	private $snapshots_fs;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->snapshots_fs = ( new Plugin() )->get_instance( SnapshotsFileSystem::class );

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
		$this->assertInstanceOf( SnapshotsFileSystem::class, $this->snapshots_fs );
	}

	/** @covers ::get_directory */
	public function test_get_snapshots_directory_with_filter() {
		$filter = static function() {
			return '/tmp';
		};

		add_filter( 'wpsnapshots_directory',  $filter );

		$this->assertEquals( '/tmp', $this->call_private_method( $this->snapshots_fs, 'get_directory' ) );

		remove_filter( 'wpsnapshots_directory', $filter );
	}

	/** @covers ::get_directory */
	public function test_get_snapshots_directory_with_filter_and_trailing_slash() {
		$filter = static function() {
			return '/tmp';
		};

		add_filter( 'wpsnapshots_directory', $filter );

		$this->assertEquals( '/tmp', $this->call_private_method( $this->snapshots_fs, 'get_directory' ) );

		remove_filter( 'wpsnapshots_directory', $filter );
	}

	/**
	 * @covers ::update_file_contents
	 * @covers ::delete_file
	 * @covers ::get_file_contents
	 * @covers ::get_file_size
	 * @covers ::get_file_path
	 * @covers ::file_exists
	 */
	public function test_delete_file() {
		$filename = 'test.txt';
		$contents = 'This is a test file.';
		$directory = $this->call_private_method( $this->snapshots_fs, 'get_directory' );

		$this->snapshots_fs->update_file_contents( $filename, $contents );

		$this->assertFileExists( $directory . '/' . $filename );
		$this->assertEquals( $contents, $this->snapshots_fs->get_file_contents( $filename ) );

		$this->assertTrue( 0 < $this->snapshots_fs->get_file_size( $filename ) );

		$this->snapshots_fs->delete_file( $filename );
		$this->assertFalse( file_exists( $directory . '/' . $filename ) );
	}

	/**
	 * @covers ::get_file_contents
	 */
	public function test_get_file_contents_throws_when_nonexistent_path() {
		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Unable to read file: /wpsnapshots-tmp/nonexistent.txt' );

		$this->snapshots_fs->get_file_contents( 'nonexistent.txt' );
	}

	/**
	 * @covers ::update_file_contents
	 */
	function test_update_file_contents_when_appending() {
		$filename = 'test.txt';
		$contents = 'This is a test file.';
		$directory = $this->call_private_method( $this->snapshots_fs, 'get_directory' );

		$this->snapshots_fs->update_file_contents( $filename, $contents );
		$this->assertFileExists( $directory . '/' . $filename );
		$this->assertEquals( $contents, $this->snapshots_fs->get_file_contents( $filename ) );

		$this->snapshots_fs->update_file_contents( $filename, $contents, true );
		$this->assertEquals( $contents . $contents, $this->snapshots_fs->get_file_contents( $filename ) );
	}

	/** @covers ::file_exists */
	public function test_file_exists() {
		$filename = 'test.txt';
		$contents = 'This is a test file.';
		$directory = $this->call_private_method( $this->snapshots_fs, 'get_directory' );

		$this->snapshots_fs->update_file_contents( $filename, $contents );

		$this->assertFileExists( $directory . '/' . $filename );
		$this->assertTrue( $this->snapshots_fs->file_exists( $filename ) );
	}

	/**
	 * @covers ::create_directory
	 */
	public function test_create_directory() {
		// Delete the directory if it exists.
		exec( 'rm -rf ' . $this->get_directory_path() );

		$this->assertFalse( file_exists( $this->get_directory_path() ) );

		$this->snapshots_fs->create_directory();

		$this->assertTrue( file_exists( $this->get_directory_path() ) );
	}

	/**
	 * @covers ::create_directory
	 * @covers ::directory_exists
	 * @covers ::get_file_path
	 */
	public function test_create_snapshot_directory() {
		$snapshot_id = 'test-snapshot-id';

		$this->assertFalse( $this->snapshots_fs->directory_exists( $snapshot_id ) );

		$this->snapshots_fs->create_directory( $snapshot_id );

		$this->assertTrue( $this->snapshots_fs->directory_exists( $snapshot_id ) );
	}

	/**
	 * @covers ::get_file_lines
	 * @covers ::get_wp_filesystem
	 */
	public function test_get_file_lines() {
		$filename = 'test.txt';

		// Content with multiple lines.
		$contents = "This is a test file. \nThis is a second line. \nThis is a third line.";

		$this->snapshots_fs->update_file_contents( $filename, $contents );

		$this->assertEquals(
			[
				"This is a test file. \n",
				"This is a second line. \n",
				"This is a third line.",
			],
			$this->snapshots_fs->get_file_lines( $filename )
		);
	}

	/** @covers ::get_file_lines */
	public function test_get_file_lines_throws_when_nonexistent_path() {
		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Unable to read file: /wpsnapshots-tmp/nonexistent.txt' );

		$this->snapshots_fs->get_file_lines( 'nonexistent.txt' );
	}

	/** @covers ::sync_files */
	public function test_sync_files() {
		$source_directory = $this->get_directory_path() . '/source';
		$destination_directory = $this->get_directory_path() . '/destination';

		// Create the source directory.
		mkdir( $source_directory );

		// Create the destination directory.
		mkdir( $destination_directory );

		for ( $i = 0; $i < 10; $i++ ) {
			$source = $source_directory . '/test-' . $i . '.txt';
			$contents = 'This is a test file. ' . $i;

			$this->snapshots_fs->get_wp_filesystem()->put_contents( $source, $contents );
		}

		$this->snapshots_fs->sync_files( $source_directory, $destination_directory, true );

		for ( $i = 0; $i < 10; $i++ ) {
			$destination = $destination_directory . '/test-' . $i . '.txt';

			$this->assertTrue( file_exists( $destination ) );
			$this->assertEquals( 'This is a test file. ' . $i, $this->snapshots_fs->get_wp_filesystem()->get_contents( $destination ) );
		}

		// Confirm the source directory was deleted.
		$this->assertFalse( file_exists( $source_directory ) );
	}

	/** @covers ::sync_files */
	public function test_sync_files_with_multiple_layers_of_subdirectories() {
		$source_directory = $this->get_directory_path() . '/source';
		$destination_directory = $this->get_directory_path() . '/destination';

		// Create the source directory.
		mkdir( $source_directory );

		// Create the destination directory.
		mkdir( $destination_directory );

		for ( $i = 0; $i < 10; $i++ ) {
			$source = $source_directory . '/test-' . $i . '.txt';
			$contents = 'This is a test file. ' . $i;

			$this->snapshots_fs->get_wp_filesystem()->put_contents( $source, $contents );
		}

		// Create a subdirectory in the source directory.
		mkdir( $source_directory . '/subdirectory' );

		for ( $i = 0; $i < 10; $i++ ) {
			$source = $source_directory . '/subdirectory/test-' . $i . '.txt';
			$contents = 'This is a test file. ' . $i;

			if ( ! $this->snapshots_fs->get_wp_filesystem()->put_contents( $source, $contents ) ) {
				$this->fail( 'Unable to create file ' . $source );
			}
		}

		$this->snapshots_fs->sync_files( $source_directory, $destination_directory, false );

		for ( $i = 0; $i < 10; $i++ ) {
			$destination = $destination_directory . '/test-' . $i . '.txt';

			$this->assertTrue( file_exists( $destination ) );
			$this->assertEquals( 'This is a test file. ' . $i, $this->snapshots_fs->get_wp_filesystem()->get_contents( $destination ) );
		}

		for ( $i = 0; $i < 10; $i++ ) {
			$destination = $destination_directory . '/subdirectory/test-' . $i . '.txt';

			$this->assertTrue( file_exists( $destination ), 'File ' . $destination . ' does not exist.' );
			$this->assertEquals( 'This is a test file. ' . $i, $this->snapshots_fs->get_wp_filesystem()->get_contents( $destination ) );
		}
	}

	/** @covers ::unzip_snapshot_files */
	public function test_unzip_snapshot_files() {
		$snapshot_id = 'test-snapshot-id';
		$destination_directory = $this->get_directory_path() . '/destination';

		// Create the destination directory.
		mkdir( $destination_directory );

		// Create a zip file.
		$zip_file = $this->snapshots_fs->get_file_path( 'files.tar.gz', $snapshot_id );
		$this->snapshots_fs->create_directory( $snapshot_id );

		$zip = new ZipArchive();
		$zip->open( $zip_file, ZipArchive::CREATE );

		for ( $i = 0; $i < 10; $i++ ) {
			$zip->addFromString( 'test-' . $i . '.txt', 'This is a test file. ' . $i );
		}

		$zip->close();

		$this->snapshots_fs->unzip_snapshot_files( $snapshot_id, $destination_directory );

		for ( $i = 0; $i < 10; $i++ ) {
			$destination = $destination_directory . '/test-' . $i . '.txt';

			$this->assertTrue( file_exists( $destination ) );
			$this->assertEquals( 'This is a test file. ' . $i, $this->snapshots_fs->get_wp_filesystem()->get_contents( $destination ) );
		}
	}

	/** @covers ::delete_directory_contents */
	public function test_delete_directory_contents() {
		$directory = $this->get_directory_path() . '/test';

		// Create the directory.
		mkdir( $directory );

		for ( $i = 0; $i < 10; $i++ ) {
			$file = $directory . '/test-' . $i . '.txt';
			$contents = 'This is a test file. ' . $i;

			$this->snapshots_fs->get_wp_filesystem()->put_contents( $file, $contents );

			$this->assertFileExists( $file, 'File ' . $file . ' does not exist.' );
		}

		// Create some files in a subdirectory.
		mkdir( $directory . '/subdirectory' );
		$this->snapshots_fs->get_wp_filesystem()->put_contents( $directory . '/subdirectory/test-0.txt', 'This is a test file.' );
		$this->snapshots_fs->get_wp_filesystem()->put_contents( $directory . '/subdirectory/test-1.txt', 'This is a test file.' );

		// Create some files in a sub-subdirectory.
		mkdir( $directory . '/subdirectory/sub-subdirectory' );
		$this->snapshots_fs->get_wp_filesystem()->put_contents( $directory . '/subdirectory/sub-subdirectory/test-0.txt', 'This is a test file.' );
		$this->snapshots_fs->get_wp_filesystem()->put_contents( $directory . '/subdirectory/sub-subdirectory/test-1.txt', 'This is a test file.' );


		// Expect WPSnapshotsException to be thrown.
		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Cannot delete root directory because files were excluded from deletion: ' . $directory );

		$this->snapshots_fs->delete_directory_contents( $directory, true, [ $directory . '/test-8.txt', $directory . '/subdirectory/test-1.txt', '/subdirectory/sub-subdirectory' ] );

		$this->assertFileDoesNotExist( $directory . '/test-0.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-1.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-2.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-3.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-4.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-5.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-6.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-7.txt' );
		$this->assertFileExists( $directory . '/test-8.txt' );
		$this->assertFileDoesNotExist( $directory . '/test-9.txt' );
		$this->assertFileDoesNotExist( $directory . '/subdirectory/test-0.txt' );
		$this->assertFileExists( $directory . '/subdirectory/test-1.txt' );
		$this->assertFileExists( $directory . '/subdirectory/sub-subdirectory/' );
		$this->assertFileExists( $directory . '/subdirectory/sub-subdirectory/test-0.txt' );
		$this->assertFileExists( $directory . '/subdirectory/sub-subdirectory/test-1.txt' );


		// Confirm root no longer exists.
		$this->assertFileDoesNotExist( $directory );
	}

	/** @covers ::delete_directory_contents */
	public function test_delete_directory_contents_can_delete_root_when_excludes_are_empty() {
		$directory = $this->get_directory_path() . '/test';

		// Create the directory.
		mkdir( $directory );

		for ( $i = 0; $i < 10; $i++ ) {
			$file = $directory . '/test-' . $i . '.txt';
			$contents = 'This is a test file. ' . $i;

			$this->snapshots_fs->get_wp_filesystem()->put_contents( $file, $contents );

			$this->assertFileExists( $file, 'File ' . $file . ' does not exist.' );
		}

		$this->snapshots_fs->delete_directory_contents( $directory, true );

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

		$this->snapshots_fs->unzip_file( $zip_file, $destination_directory );

		for ( $i = 0; $i < 10; $i++ ) {
			$destination = $destination_directory . '/test-' . $i . '.txt';

			$this->assertTrue( file_exists( $destination ) );
			$this->assertEquals( 'This is a test file. ' . $i, $this->snapshots_fs->get_wp_filesystem()->get_contents( $destination ) );
		}
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

		$this->snapshots_fs->unzip_file( $gzipped_sql_file, $destination );

		$this->assertTrue( file_exists( $destination . '/test.sql' ) );
	}

	/** @covers ::unzip_file */
	public function test_unzip_file_throws_when_invalid_file_is_passed() {
		$destination_directory = $this->get_directory_path() . '/destination';

		// Create the destination directory.
		mkdir( $destination_directory );

		$this->expectException( WPSnapshotsException::class );
		$this->expectExceptionMessage( 'Incompatible Archive' );

		$this->snapshots_fs->unzip_file( 'nonexistent-file', $destination_directory, );
	}
}