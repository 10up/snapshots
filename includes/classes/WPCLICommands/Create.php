<?php
/**
 * Create command class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLICommands;

use Exception;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\Infrastructure\Service;
use TenUp\Snapshots\Snapshots\DBExportInterface;
use TenUp\Snapshots\Snapshots\FileZipper;
use TenUp\Snapshots\WPCLI\WPCLICommand;

use function TenUp\Snapshots\Utils\wp_cli;

/**
 * Create command
 *
 * @package TenUp\Snapshots\WPCLI
 */
class Create extends WPCLICommand {

	/**
	 * DBExportInterface instance.
	 *
	 * @var DBExportInterface
	 */
	private $dumper;

	/**
	 * FileZipper instance.
	 *
	 * @var FileZipper
	 */
	private $file_zipper;

	/**
	 * Create constructor.
	 *
	 * @param DBExportInterface $dumper           DBExportInterface instance.
	 * @param FileZipper        $file_zipper      FileZipper instance.
	 * @param array<Service>    ...$args             Additional dependencies to pass to the parent.
	 */
	public function __construct( DBExportInterface $dumper, FileZipper $file_zipper, ...$args ) {
		parent::__construct( ...$args ); // @phpstan-ignore-line

		$this->dumper      = $dumper;
		$this->file_zipper = $file_zipper;
	}


	/**
	 * Search for snapshots within a repository.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 *
	 * @throws SnapshotsException If the snapshot cannot be created.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );

			$contains_db    = $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_db', 'Include database in snapshot?', true );
			$contains_files = $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_files', 'Include files in snapshot?', true );

			if ( ! $contains_db && ! $contains_files ) {
				throw new SnapshotsException( 'You must include either the database or files in the snapshot.' );
			}

			$id = $this->run( $contains_db, $contains_files );

			wp_cli()::success( $this->get_success_message( $id ) );
		} catch ( Exception $e ) {
			wp_cli()::error( $e->getMessage() );
		}
	}

	/**
	 * Returns the command name.
	 *
	 * @inheritDoc
	 */
	protected function get_command() : string {
		return 'create';
	}

	/**
	 * Provides command parameters.
	 *
	 * @inheritDoc
	 */
	protected function get_command_parameters() : array {
		return [
			'shortdesc' => 'Create a snapshot locally.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'repository',
					'description' => 'Repository to use.',
					'optional'    => true,
					'default'     => '10up',
				],
				[
					'type'        => 'assoc',
					'name'        => 'exclude',
					'description' => 'Exclude a file or directory from the snapshot. Enter a comma-separated list of files or directories to exclude, relative to the WP content directory.',
					'optional'    => true,
					'default'     => '',
				],
				[
					'type'        => 'flag',
					'name'        => 'exclude_uploads',
					'description' => 'Exclude uploads from pushed snapshot.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'small',
					'description' => 'Trim data and files to create a small snapshot. Note that this action will modify your local.',
					'optional'    => true,
					'default'     => false,
				],
				[
					'type'        => 'flag',
					'name'        => 'include_files',
					'description' => 'Include files in snapshot.',
					'optional'    => true,
					'default'     => true,
				],
				[
					'type'        => 'flag',
					'name'        => 'include_db',
					'description' => 'Include database in snapshot.',
					'optional'    => true,
					'default'     => true,
				],
				[
					'type'        => 'assoc',
					'name'        => 'slug',
					'description' => 'Project slug for snapshot.',
					'optional'    => true,
					'default'     => '',
				],
				[
					'type'        => 'assoc',
					'name'        => 'description',
					'description' => 'Description of snapshot.',
					'optional'    => true,
					'default'     => '',
				],
				[
					'type'        => 'assoc',
					'name'        => 'wp_version',
					'description' => 'Override the WordPress version.',
					'optional'    => true,
					'default'     => '',
				],
				[
					'type'        => 'assoc',
					'name'        => 'author_name',
					'description' => 'Snapshot creator name.',
					'optional'    => true,
					'default'     => '',
				],
				[
					'type'        => 'assoc',
					'name'        => 'author_email',
					'description' => 'Snapshot creator email.',
					'optional'    => true,
					'default'     => '',
				],
			],
			'when'      => 'after_wp_load',
		];
	}

	/**
	 * Runs the command.
	 *
	 * @param bool $contains_db Whether the snapshot contains a database.
	 * @param bool $contains_files Whether the snapshot contains files.
	 *
	 * @return string
	 *
	 * @throws SnapshotsException If the snapshot cannot be created.
	 */
	public function run( bool $contains_db, bool $contains_files ) : string {
		$id = md5( time() . wp_rand() );
		$this->snapshots_filesystem->create_directory( $id );
		return $this->create( $this->get_create_args( $contains_db, $contains_files ), $id );
	}

	/**
	 * Create a snapshot.
	 *
	 * @param array   $args List of arguments
	 * @param ?string $id A snapshot ID to use. If not provided, a random one will be generated.
	 *
	 * @return string Snapshot ID
	 *
	 * @throws SnapshotsException Throw exception if snapshot can't be created.
	 */
	public function create( array $args, ?string $id = null ) : string {
		if ( empty( $args['contains_db'] ) && empty( $args['contains_files'] ) ) {
			throw new SnapshotsException( 'Snapshot must contain either database or files.' );
		}

		/**
		 * Define snapshot ID
		 */
		if ( ! $id ) {
			$id = md5( time() . wp_rand() );
		}

		if ( $args['contains_db'] ) {
			$this->log( 'Saving database...' );

			$args['db_size'] = $this->dumper->dump( $id, $args );
		}

		if ( $args['contains_files'] ) {
			$this->log( 'Saving files...' );

			$args['files_size'] = $this->file_zipper->zip_files( $id, $args );
		}

		/**
		 * Finally save snapshot meta to meta.json
		 */
		$this->snapshot_meta->generate( $id, $args );

		return $id;
	}

	/**
	 * Returns the arguments for creating a snapshot.
	 *
	 * @param bool $contains_db Whether the snapshot contains a database.
	 * @param bool $contains_files Whether the snapshot contains files.
	 *
	 * @return array
	 *
	 * @throws SnapshotsException If the snapshot cannot be created.
	 */
	protected function get_create_args( bool $contains_db, bool $contains_files ) : array {
		$repository = $this->get_assoc_arg(
			'repository',
			[
				'key'               => 'repository',
				'prompt'            => 'Repository Slug (letters, numbers, _, and - only)',
				'sanitize_callback' => 'strtolower',
				'validate_callback' => [ $this, 'validate_slug' ],
			]
		);

		return [
			'author'          => [
				'name'  => $this->config->get_user_name() ?? $this->get_assoc_arg(
					'author_name',
					[
						'key'    => 'author_name',
						'prompt' => 'Your name',
					]
				),
				'email' => $this->config->get_user_email() ?? $this->get_assoc_arg(
					'author_email',
					[
						'key'    => 'author_email',
						'prompt' => 'Your email',
					]
				),
			],
			'contains_db'     => $contains_db,
			'contains_files'  => $contains_files,
			'description'     => $this->get_assoc_arg(
				'description',
				[
					'key'    => 'description',
					'prompt' => 'Snapshot Description (e.g. Local environment)',
				]
			),
			'exclude_uploads' => ! ! wp_cli()::get_flag_value( $this->get_assoc_args(), 'exclude_uploads' ),
			'excludes'        => array_filter( array_map( 'trim', explode( ',', $this->get_assoc_arg( 'exclude' ) ) ) ),
			'project'         => $this->get_assoc_arg(
				'slug',
				[
					'key'               => 'project',
					'prompt'            => 'Project Slug (letters, numbers, _, and - only)',
					'sanitize_callback' => 'strtolower',
					'validate_callback' => [ $this, 'validate_slug' ],
				]
			),
			'region'          => $this->get_region( $repository ),
			'repository'      => $repository,
			'small'           => $this->get_assoc_arg( 'small' ),
			'wp_version'      => $this->get_assoc_arg( 'wp_version' ),
		];
	}


	/**
	 * Validates a slug.
	 *
	 * @param string $slug The slug to validate.
	 *
	 * @return bool
	 *
	 * @throws SnapshotsException If the slug is invalid.
	 */
	protected function validate_slug( string $slug ) : bool {
		if ( ! preg_match( '/^[a-z0-9_-]+$/', $slug ) ) {
			throw new SnapshotsException( 'Input must be letters, numbers, _, and - only.' );
		}

		return true;
	}

	/**
	 * Returns the success message.
	 *
	 * @param string $id The snapshot ID.
	 */
	protected function get_success_message( string $id ) : string {
		return sprintf( 'Snapshot %s created.', $id );
	}
}
