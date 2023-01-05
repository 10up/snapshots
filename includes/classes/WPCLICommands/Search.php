<?php
/**
 * Search command class.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\WPCLICommands;

use TenUp\WPSnapshots\Exceptions\WPSnapshotsException;
use TenUp\WPSnapshots\WPCLI\WPCLICommand;

use function TenUp\WPSnapshots\Utils\wp_cli;

/**
 * Search command
 *
 * @package TenUp\WPSnapshots\WPCLI
 */
final class Search extends WPCLICommand {

	/**
	 * Search for snapshots within a repository.
	 *
	 * ## ARGUMENTS
	 *
	 * <search_text>
	 * : Text to search against snapshots. If multiple queries are used, they must match exactly to project names or snapshot ids.
	 *
	 * ## OPTIONS
	 *
	 * [--repository=<repository>]
	 * : The repository to search in. Defaults to the first repository set in the config file.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format. Available options: table and json. Defaults to table.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );
			$this->display_results( $this->search() );
		} catch ( WPSnapshotsException $e ) {
			wp_cli()::error( $e->getMessage() );
		}
	}

	/**
	 * Returns the command name.
	 *
	 * @inheritDoc
	 */
	public function get_command() : string {
		return 'search';
	}

	/**
	 * Provides command parameters.
	 *
	 * @inheritDoc
	 */
	protected function get_command_parameters() : array {
		return [
			'shortdesc' => 'Search for snapshots within a repository.',
			'synopsis'  => [
				[
					'type'        => 'positional',
					'name'        => 'search_text',
					'description' => 'Text to search against snapshots. If multiple queries are used, they must match exactly to project names or snapshot ids.',
					'optional'    => false,
				],
				[
					'type'        => 'assoc',
					'name'        => 'repository',
					'description' => 'The repository to search in. Defaults to the first repository set in the config file.',
					'optional'    => true,
				],
				[
					'type'        => 'assoc',
					'name'        => 'format',
					'description' => 'Render output in a particular format. Available options: table and json. Defaults to table.',
					'optional'    => true,
				],
			],
		];
	}

	/**
	 * Gets the search string.
	 *
	 * @return string
	 *
	 * @throws WPSnapshotsException If no search string is provided.
	 */
	private function get_search_string() : string {
		$search_string = $this->get_args()[0] ?? '';

		if ( empty( $search_string ) ) {
			throw new WPSnapshotsException( 'Please provide a search string.' );
		}

		return $search_string;
	}

	/**
	 * Gets the repository name.
	 *
	 * @return string
	 */
	private function get_repository_name() : string {
		return $this->get_assoc_arg( 'repository' ) ?? '';
	}

	/**
	 * Runs a search against the database.
	 *
	 * @return array
	 */
	private function search() {
		$repository_name = $this->get_repository_name();

		$repo_info = $this->config->get_repository_settings( $repository_name );
		$s3_config = $this->aws_authentication_factory->get(
			[
				'key'        => $repo_info['access_key_id'],
				'secret'     => $repo_info['secret_access_key'],
				'region'     => $repo_info['region'],
				'repository' => $repo_info['repository'],
			]
		);
		$this->db_connector->set_configuration( $s3_config );

		return $this->db_connector->search( $this->get_search_string() );
	}

	/**
	 * Displays search results.
	 *
	 * @param array $results Search results.
	 */
	private function display_results( array $results ) {
		if ( empty( $results ) ) {
			$this->log( 'No snapshots found.' );
			return;
		}

		$rows = array_reduce( $results, [ $this, 'format_row' ], [] );

		ksort( $rows );

		wp_cli()::format_items(
			$this->get_output_format(),
			$rows,
			[
				'id',
				'project',
				'contains_files',
				'contains_db',
				'description',
				'author',
				'size',
				'multisite',
				'created',
			]
		);
	}


	/**
	 * Format bytes to pretty file size
	 *
	 * @param  int $size     Number of bytes
	 * @param  int $precision Decimal precision
	 * @return string
	 */
	private function format_bytes( $size, $precision = 2 ) {
		$base     = log( $size, 1024 );
		$suffixes = [ '', 'KB', 'MB', 'GB', 'TB' ];

		return round( pow( 1024, $base - floor( $base ) ), $precision ) . ' ' . $suffixes[ floor( $base ) ];
	}

	/**
	 * Gets output format.
	 *
	 * @return string
	 */
	private function get_output_format() {
		return $this->get_assoc_arg( 'format' ) ?? 'table';
	}

	/**
	 * Formats a row for output.
	 *
	 * @param array $rows Formatted rows.
	 * @param array $instance Row data
	 * @return array
	 */
	private function format_row( array $rows, array $instance ) : array {
		if ( empty( $instance['time'] ) ) {
			$instance['time'] = time();
		}

		// Defaults to yes for backwards compat since old snapshots dont have this meta.
		$contains_files = 'Yes';
		$contains_db    = 'Yes';

		if ( isset( $instance['contains_files'] ) ) {
			$contains_files = $instance['contains_files'] ? 'Yes' : 'No';
		}

		if ( isset( $instance['contains_db'] ) ) {
			$contains_db = $instance['contains_db'] ? 'Yes' : 'No';
		}

		$size = '-';

		if ( empty( $instance['files_size'] ) && empty( $instance['db_size'] ) ) {
			// This is for backwards compat with old snapshots
			if ( ! empty( $instance['size'] ) ) {
				$size = $this->format_bytes( (int) $instance['size'] );
			}
		} else {
			$size = 0;

			if ( ! empty( $instance['files_size'] ) ) {
				$size += (int) $instance['files_size'];
			} if ( ! empty( $instance['db_size'] ) ) {
				$size += (int) $instance['db_size'];
			}

			$size = $this->format_bytes( $size );
		}

		$date_format = 'F j, Y, g:i a';
		if ( 'json' === $this->get_output_format() ) {
			$date_format = 'U';
		}

		$rows[ $instance['time'] ] = [
			'id'             => ( ! empty( $instance['id'] ) ) ? $instance['id'] : '',
			'project'        => ( ! empty( $instance['project'] ) ) ? $instance['project'] : '',
			'contains_files' => $contains_files,
			'contains_db'    => $contains_db,
			'description'    => ( ! empty( $instance['description'] ) ) ? $instance['description'] : '',
			'author'         => ( ! empty( $instance['author']['name'] ) ) ? $instance['author']['name'] : '',
			'size'           => $size,
			'multisite'      => ( ! empty( $instance['multisite'] ) ) ? 'Yes' : 'No',
			'created'        => gmdate( $date_format, $instance['time'] ),
		];

		return $rows;
	}
}
