<?php
/**
 * Interface for classes that persist configuration values.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\SnapshotsConfig;

use TenUp\Snapshots\Infrastructure\SharedService;

/**
 * Interface for classes that persist configuration values.
 *
 * @package TenUp\Snapshots\SnapshotsConfig
 */
interface SnapshotsConfigInterface extends SharedService {

	/**
	 * Gets the user name.
	 *
	 * @return ?string
	 */
	public function get_user_name() : ?string;

	/**
	 * Sets the user name.
	 *
	 * @param string $user_name User name.
	 */
	public function set_user_name( string $user_name );

	/**
	 * Gets the user email.
	 *
	 * @return ?string
	 */
	public function get_user_email() : ?string;

	/**
	 * Sets the user email.
	 *
	 * @param string $user_email User email.
	 */
	public function set_user_email( string $user_email );

	/**
	 * Gets the default repository name.
	 *
	 * @return ?string
	 */
	public function get_default_repository_name() : ?string;

	/**
	 * Gets repositories.
	 *
	 * @return array
	 */
	public function get_repositories() : array;

	/**
	 * Sets repositories.
	 *
	 * @param array $repositories Repositories.
	 */
	public function set_repositories( array $repositories );

	/**
	 * Gets settings for a repository.
	 *
	 * @param string $repository Repository name.
	 * @return ?array
	 */
	public function get_repository_settings( string $repository ) : ?array;

	/**
	 * Saves the configuration.
	 */
	public function save();
}
