<?php
/**
 * Class handling Storage interactions.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use Aws\S3\S3Client;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\ProgressBar\ProgressBarInterface;
use TenUp\Snapshots\SnapshotsDirectory;
use Aws;

/**
 * Class S3StorageConnector
 *
 * @package TenUp\Snapshots
 */
class S3StorageConnector implements StorageConnectorInterface {

	/**
	 * Clients keyed by region.
	 *
	 * @var S3Client[]
	 */
	private $clients = [];

	/**
	 * SnapshotsDirectory instance.
	 *
	 * @var SnapshotsDirectory
	 */
	private $snapshots_file_system;

	/**
	 * Snapshot meta instance.
	 *
	 * @var SnapshotMetaInterface
	 */
	private $snapshot_meta;

	/**
	 * Progress Bar instance.
	 *
	 * @var ProgressBarInterface
	 */
	private $progress_bar;

	/**
	 * Class constructor.
	 *
	 * @param SnapshotsDirectory    $snapshots_file_system SnapshotsDirectory instance.
	 * @param SnapshotMetaInterface $snapshot_meta SnapshotMeta instance.
	 * @param ProgressBarInterface  $progress_bar Progress bar instance.
	 */
	public function __construct( SnapshotsDirectory $snapshots_file_system, SnapshotMetaInterface $snapshot_meta, ProgressBarInterface $progress_bar ) {
		$this->snapshots_file_system = $snapshots_file_system;
		$this->snapshot_meta         = $snapshot_meta;
		$this->progress_bar          = $progress_bar;
	}

	/**
	 * Download a snapshot given an id. Must specify where to download files/data
	 *
	 * @param string $id Snapshot ID
	 * @param array  $config Config array
	 * @param array  $snapshot_meta Snapshot meta.
	 */
	public function download_snapshot( string $id, array $config, array $snapshot_meta ) {
		$this->snapshots_file_system->create_directory( $id );

		if ( $snapshot_meta['contains_db'] ) {
			$this->progress_bar->create_progress_bar(
				'db_dl',
				sprintf( 'Downloading Database (%s)', $this->format_bytes( $snapshot_meta['db_size'] ) ),
				$snapshot_meta['db_size']
			);

			$this->get_client( $config )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $config['repository'] ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/data.sql.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ),
					'@http' => [
						'progress' => function ( $expected_dl, $dl ) {
							$this->progress_bar->advance_progress_bar( 'db_dl', (int) $dl );
						},
					],
				]
			);

			$this->progress_bar->finish_progress_bar( 'db_dl' );
		}

		if ( $snapshot_meta['contains_files'] ) {
			$this->progress_bar->create_progress_bar(
				'files_dl',
				sprintf( 'Downloading Files (%s)', $this->format_bytes( $snapshot_meta['files_size'] ) ),
				$snapshot_meta['files_size']
			);

			$this->get_client( $config )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $config['repository'] ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/files.tar.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ),
					'@http' => [
						'progress' => function ( $expected_dl, $dl ) {
							$this->progress_bar->advance_progress_bar( 'files_dl', (int) $dl );
						},
					],
				]
			);

			$this->progress_bar->finish_progress_bar( 'files_dl' );
		}
	}

	/**
	 * Create Snapshots S3 bucket
	 *
	 * @param array $config AWS config
	 *
	 * @throws SnapshotsException If bucket already exists.
	 */
	public function create_bucket( array $config ) {
		$client              = $this->get_client( $config );
		$list_buckets_result = $client->listBuckets();
		$bucket_name         = $this->get_bucket_name( $config['repository'] );

		foreach ( $list_buckets_result['Buckets'] as $bucket ) {
			if ( $bucket_name === $bucket['Name'] ) {
				throw new SnapshotsException( $this->get_bucket_already_exists_message() );
			}
		}

		$client->createBucket(
			[
				'Bucket'             => $bucket_name,
				'LocationConstraint' => $config['region'],
			]
		);
	}

	/**
	 * Gets the bucket already exists error message.
	 *
	 * @return string
	 */
	public function get_bucket_already_exists_message() : string {
		return 'S3 bucket already exists.';
	}

	/**
	 * Upload a snapshot to S3
	 *
	 * @param  string $id Snapshot ID
	 * @param array  $config AWS config
	 */
	public function put_snapshot( string $id, array $config ) : void {
		$meta   = $this->snapshot_meta->get_local( $id, $config['repository'] );
		$client = $this->get_client( $config );

		if ( $meta['contains_db'] && file_exists( $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ) ) ) {
			$filesize = filesize( $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ) );
			$this->progress_bar->create_progress_bar(
				'db_ul',
				sprintf( 'Uploading Database (%s)', $this->format_bytes( $filesize ) ),
				$filesize
			);

			$client->putObject(
				[
					'Bucket'     => $this->get_bucket_name( $config['repository'] ),
					'Key'        => $meta['project'] . '/' . $id . '/data.sql.gz',
					'SourceFile' => realpath( $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ) ),
					'ContentMD5' => base64_encode( md5_file( $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ), true ) ), // phpcs:ignore
					'@http' => [
						'progress' => function ( $expected_dl, $dl, $expected_ul, $ul ) {
							$this->progress_bar->advance_progress_bar( 'db_ul', (int) $ul );
						},
					],
				]
			);

			$this->progress_bar->finish_progress_bar( 'db_ul' );
		}

		if ( $meta['contains_files'] && file_exists( $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ) ) ) {
			$filesize = filesize( $this->snapshots_file_system->get_file_path( 'files.sql.gz', $id ) );
			$this->progress_bar->create_progress_bar(
				'files_ul',
				sprintf( 'Uploading Files (%s)', $this->format_bytes( $filesize ) ),
				$filesize
			);

			$client->putObject(
				[
					'Bucket'     => $this->get_bucket_name( $config['repository'] ),
					'Key'        => $meta['project'] . '/' . $id . '/files.tar.gz',
					'SourceFile' => realpath( $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ) ),
					'ContentMD5' => base64_encode( md5_file( $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ), true ) ), // phpcs:ignore
					'@http' => [
						'progress' => function ( $expected_dl, $dl, $expected_ul, $ul ) {
							$this->progress_bar->advance_progress_bar( 'files_ul', (int) $ul );
						},
					],
				]
			);

			$this->progress_bar->finish_progress_bar( 'files_ul' );
		}

		/**
		 * Wait for files first since that will probably take longer
		 */
		if ( $meta['contains_files'] ) {
			$client->waitUntil(
				'ObjectExists',
				[
					'Bucket' => $this->get_bucket_name( $config['repository'] ),
					'Key'    => $meta['project'] . '/' . $id . '/files.tar.gz',
				]
			);
		}

		if ( $meta['contains_db'] ) {
			$client->waitUntil(
				'ObjectExists',
				[
					'Bucket' => $this->get_bucket_name( $config['repository'] ),
					'Key'    => $meta['project'] . '/' . $id . '/data.sql.gz',
				]
			);
		}
	}

	/**
	 * Delete a snapshot given an id
	 *
	 * @param  string $id Snapshot id
	 * @param  string $project Project name
	 * @param array  $config AWS config
	 */
	public function delete_snapshot( string $id, string $project, array $config ) : void {
		$this->get_client( $config )->deleteObjects(
			[
				'Bucket' => $this->get_bucket_name( $config['repository'] ),
				'Delete' => [
					'Objects' => [
						[
							'Key' => $project . '/' . $id . '/files.tar.gz',
						],
						[
							'Key' => $project . '/' . $id . '/data.sql',
						],
						[
							'Key' => $project . '/' . $id . '/data.sql.gz',
						],
					],
				],
			]
		);
	}

	/**
	 * Tests the user's AWS credentials.
	 *
	 * @param array $config AWS config
	 */
	public function test( array $config ) {
		$client = $this->get_client( $config );

		$bucket_name = $this->get_bucket_name( $config['repository'] );

		$client->listObjects( [ 'Bucket' => $bucket_name ] );
	}

	/**
	 * Configures the client.
	 *
	 * @param array $config AWS config
	 * @return S3Client
	 * @throws SnapshotsException Failed to assume ARN role
	 */
	private function get_client( array $config ) : S3Client {
		$client_key = $config['profile'] . '_' . $config['region'];

		$args = [
			'region'    => $config['region'],
			'signature' => 'v4',
			'version'   => '2006-03-01',
			'csm'       => false,
		];

		// if role_arn has a value use STS to assume the role
		// and pass the credential info to S3Client later on
		if ( ! empty( $config['role_arn'] ) ) {
			$args['roleArn'] = $config['role_arn'];

			$temp_creds = $this->assume_role( $args );

			if ( ! is_array( $temp_creds ) ) {
				throw new SnapshotsException( sprintf( "Failed to assume role '%s'.", $args['roleArn'] ) );
			}

			$args['credentials'] = [
				'key'    => $temp_creds['AccessKeyId'],
				'secret' => $temp_creds['SecretAccessKey'],
				'token'  => $temp_creds['SessionToken'],
			];
		} elseif ( ! empty( $config['profile'] ) ) {
			$args['profile'] = $config['profile'];
		}

		if ( ! isset( $this->clients[ $client_key ] ) ) {
			$this->clients[ $client_key ] = new S3Client( $args );
		}

		return $this->clients[ $client_key ];
	}

	/**
	 * Performs STS
	 *
	 * @param array $connection_parameters Parameters for connection
	 * @return mixed
	 */
	private function assume_role( $connection_parameters ) {
		$sts_client = new Aws\Sts\StsClient(
			[
				'region'  => 'us-east-1',
				'version' => '2011-06-15',
			]
		);

		$result = $sts_client->assumeRole(
			[
				'RoleArn'         => $connection_parameters['roleArn'],
				'RoleSessionName' => 'wpsnapshots',
			]
		);

		return $result['Credentials'];
	}

	/**
	 * Get bucket name
	 *
	 * @param string $repository Repository name.
	 * @return string
	 */
	private function get_bucket_name( string $repository ) : string {
		return 'wpsnapshots-' . $repository;
	}

	/**
	 * Formats bytes to human-readable format.
	 *
	 * @param int $size Size in bytes.
	 * @param int $precision Precision.
	 * @return string
	 */
	private function format_bytes( $size, $precision = 2 ) {
		$base     = log( $size, 1024 );
		$suffixes = array( '', 'kb', 'mb', 'g', 't' );
		return round( pow( 1024, $base - floor( $base ) ), $precision ) . $suffixes[ floor( $base ) ];
	}
}
