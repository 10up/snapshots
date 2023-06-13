<?php
/**
 * Search command class.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\WPCLICommands;

use Exception;
use TenUp\Snapshots\Exceptions\SnapshotsException;
use TenUp\Snapshots\WPCLI\WPCLICommand;
use jc21\CliTable;

use function TenUp\Snapshots\Utils\wp_cli;

/**
 * Search command
 *
 * @package TenUp\Snapshots\WPCLI
 */
final class Search extends WPCLICommand {

	/**
	 * Search for snapshots within a repository.
	 *
	 * @param array $args Arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function execute( array $args, array $assoc_args ) {
		try {
			$this->set_args( $args );
			$this->set_assoc_args( $assoc_args );
			$this->display_results( $this->search() );
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
			'when'      => 'before_wp_load',
		];
	}

	/**
	 * Gets the search string.
	 *
	 * @return string
	 *
	 * @throws SnapshotsException If no search string is provided.
	 */
	private function get_search_string() : string {
		$search_string = $this->get_args()[0] ?? '';

		if ( empty( $search_string ) ) {
			throw new SnapshotsException( 'Please provide a search string.' );
		}

		return $search_string;
	}

	/**
	 * Runs a search against the database.
	 *
	 * @return array
	 */
	private function search() {
		return $this->db_connector->search( $this->get_search_string(), $this->get_aws_config() );
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

		$output_format = $this->get_output_format();

		if ( 'table' === $output_format ) {
			$table = new CliTable();
			$table->setTableColor( 'blue' );
			$table->setHeaderColor( 'cyan' );

			$table->addField( 'ID', 'ID', false );
			$table->addField( 'Project', 'Project', false );
			$table->addField( 'Files', 'Contains files', false );
			$table->addField( 'DB', 'Contains database', false );
			$table->addField( 'Description', 'Description', false );
			$table->addField( 'Author', 'Author', false );
			$table->addField( 'Size', 'Size', false );
			$table->addField( 'Multisite', 'Multisite', false );
			$table->addField( 'Created', 'Created', false );

			// Max out the description at 40 chars and the project at 35, and remove the time from the created field.
			$rows = array_map(
				function( $row ) {
					if ( strlen( $row['Description'] ) > 40 ) {

						$row['Description'] = substr( $row['Description'], 0, 37 ) . '...';
					}

					if ( strlen( $row['Project'] ) > 30 ) {
						$row['Project'] = substr( $row['Project'], 0, 27 ) . '...';
					}

					// Remove everything from the second comma.
					$row['Created'] = substr( $row['Created'], 0, strpos( $row['Created'], ',', strpos( $row['Created'], ',' ) + 1 ) );
					return $row;
				},
				$rows
			);

			$table->injectData( $rows );

			$table->display();

		} else {
			wp_cli()::format_items(
				$output_format,
				$rows,
				array_keys( reset( $rows ) )
			);
		}
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
			'ID'                => ( ! empty( $instance['id'] ) ) ? $instance['id'] : '',
			'Project'           => ( ! empty( $instance['project'] ) ) ? $instance['project'] : '',
			'Contains files'    => $contains_files,
			'Contains database' => $contains_db,
			'Description'       => ( ! empty( $instance['description'] ) ) ? $instance['description'] : '',
			'Author'            => ( ! empty( $instance['author']['name'] ) ) ? $instance['author']['name'] : '',
			'Size'              => $size,
			'Multisite'         => ( ! empty( $instance['multisite'] ) ) ? 'Yes' : 'No',
			'Created'           => gmdate( $date_format, $instance['time'] ),
		];

		return $rows;
	}
}
