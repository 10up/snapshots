<?php
/**
 * Class handling Storage interactions.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Snapshots;

use Aws\S3\S3Client;
use TenUp\Snapshots\Exceptions\SnapshotsException;
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
	 * Class constructor.
	 *
	 * @param SnapshotsDirectory    $snapshots_file_system SnapshotsDirectory instance.
	 * @param SnapshotMetaInterface $snapshot_meta SnapshotMeta instance.
	 */
	public function __construct( SnapshotsDirectory $snapshots_file_system, SnapshotMetaInterface $snapshot_meta ) {
		$this->snapshots_file_system = $snapshots_file_system;
		$this->snapshot_meta         = $snapshot_meta;
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
			$this->get_client( $config )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $config['repository'] ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/data.sql.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ),
				]
			);
		}

		if ( $snapshot_meta['contains_files'] ) {
			$this->get_client( $config )->getObject(
				[
					'Bucket' => $this->get_bucket_name( $config['repository'] ),
					'Key'    => $snapshot_meta['project'] . '/' . $id . '/files.tar.gz',
					'SaveAs' => $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ),
				]
			);
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
			$client->putObject(
				[
					'Bucket'     => $this->get_bucket_name( $config['repository'] ),
					'Key'        => $meta['project'] . '/' . $id . '/data.sql.gz',
					'SourceFile' => realpath( $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ) ),
					'ContentMD5' => base64_encode( md5_file( $this->snapshots_file_system->get_file_path( 'data.sql.gz', $id ), true ) ), // phpcs:ignore
				]
			);
		}

		if ( $meta['contains_files'] && file_exists( $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ) ) ) {
			$client->putObject(
				[
					'Bucket'     => $this->get_bucket_name( $config['repository'] ),
					'Key'        => $meta['project'] . '/' . $id . '/files.tar.gz',
					'SourceFile' => realpath( $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ) ),
					'ContentMD5' => base64_encode( md5_file( $this->snapshots_file_system->get_file_path( 'files.tar.gz', $id ), true ) ), // phpcs:ignore
				]
			);
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
	 * @return array
	 */
	private function assume_role( $connection_parameters ) : array {
		$sts_client = new Aws\Sts\StsClient(
			[
				'region'  => 'us-east-1',
				'version' => '2011-06-15',
			]
		);

		$result = $sts_client->AssumeRole(
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

}
