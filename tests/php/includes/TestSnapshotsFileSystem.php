<?php
/**
 * Tests for the SnapshotFiles class.
 * 
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Tests;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Plugin;
use TenUp\WPSnapshots\SnapshotFiles;
use TenUp\WPSnapshots\Tests\Fixtures\DirectoryFiltering;
use TenUp\WPSnapshots\Tests\Fixtures\PrivateAccess;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use ZipArchive;

/**
 * Class TestSnapshotsFileSystem
 *
 * @package TenUp\WPSnapshots\Tests
 * 
 * @coversDefaultClass \TenUp\WPSnapshots\SnapshotFiles
 */
class TestSnapshotsFileSystem extends TestCase {
	
    use PrivateAccess, DirectoryFiltering;

	/**
	 * SnapshotFiles instance.
	 * 
	 * @var SnapshotFiles
	 */
	private $snapshots_fs;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->snapshots_fs = ( new Plugin() )->get_instance( SnapshotFiles::class );

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
		$this->assertInstanceOf( SnapshotFiles::class, $this->snapshots_fs );
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

			$this->assertFileExists( $destination );
			$this->assertEquals( 'This is a test file. ' . $i, $this->snapshots_fs->get_wp_filesystem()->get_contents( $destination ) );
		}
	}
}