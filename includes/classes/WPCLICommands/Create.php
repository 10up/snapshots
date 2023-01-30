<?php
/**
 * Create command class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands;

use Exception;
use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\Infrastructure\Service;
use TenUp\WPSnapshots\Snapshots\SnapshotCreator;
use TenUp\WPSnapshots\WPCLI\WPCLICommand;

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Create command
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
final class Create extends WPCLICommand {

	/**
	 * SnapshotCreator instance.
	 *
	 * @var SnapshotCreator
	 */
	protected $snapshot_creator;

	/**
	 * Create constructor.
	 *
	 * @param SnapshotCreator $snapshot_creator SnapshotCreator instance.
	 * @param array<Service>  ...$args             Additional dependencies to pass to the parent.
	 */
	public function __construct( SnapshotCreator $snapshot_creator, ...$args ) {
		parent::__construct( ...$args ); // @phpstan-ignore-line

		$this->snapshot_creator = $snapshot_creator;
	}


	/**
	 * Search for snapshots within a repository.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 *
	 * @throws WPSnapshotsException If the snapshot cannot be created.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );

			$contains_db    = $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_db', 'Include database in snapshot?', true );
			$contains_files = $this->prompt->get_flag_or_prompt( $this->get_assoc_args(), 'include_files', 'Include files in snapshot?', true );

			if ( ! $contains_db && ! $contains_files ) {
				throw new WPSnapshotsException( 'You must include either the database or files in the snapshot.' );
			}

			$id = $this->snapshot_creator->create(
				[
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
					'region'          => $this->get_assoc_arg( 'region' ),
					'repository'      => $this->get_assoc_arg(
						'repository',
						[
							'key'               => 'repository',
							'prompt'            => 'Repository Slug (letters, numbers, _, and - only)',
							'sanitize_callback' => 'strtolower',
							'validate_callback' => [ $this, 'validate_slug' ],
						]
					),
					'small'           => $this->get_assoc_arg( 'small' ),
					'wp_version'      => $this->get_assoc_arg( 'wp_version' ),
				]
			);

			wp_cli()::success( sprintf( 'Snapshot %s created.', $id ) );

		} catch ( Exception $e ) {
			wp_cli()::error( $e->getMessage() );
		}
	}

	/**
	 * Returns the command name.
	 *
	 * @inheritDoc
	 */
	public function get_command() : string {
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
					'description' => 'Repository to use. Defaults to 10up.',
					'optional'    => true,
					'default'     => '10up',
				],
				[
					'type'        => 'assoc',
					'name'        => 'region',
					'description' => 'The AWS region to use. Defaults to us-west-1.',
					'optional'    => true,
					'default'     => 'us-west-1',
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
		];
	}

	/**
	 * Validates a slug.
	 *
	 * @param string $slug The slug to validate.
	 *
	 * @return bool
	 *
	 * @throws WPSnapshotsException If the slug is invalid.
	 */
	protected function validate_slug( string $slug ) : bool {
		if ( ! preg_match( '/^[a-z0-9_-]+$/', $slug ) ) {
			throw new WPSnapshotsException( 'Input must be letters, numbers, _, and - only.' );
		}

		return true;
	}
}
