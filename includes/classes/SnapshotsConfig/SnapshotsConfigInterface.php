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
	 * @param string $repository Repository name.
	 * @return ?string $user_name User name.
	 */
	public function get_user_name( string $repository = '' ) : ?string;

	/**
	 * Gets the user email.
	 *
	 * @param string $repository Repository name.
	 * @return ?string $user_email User email.
	 */
	public function get_user_email( string $repository = '' ) : ?string;

	/**
	 * Gets the default repository name.
	 *
	 * @return ?string
	 */
	public function get_default_repository_name() : ?string;

	/**
	 * Gets the region for a repository.
	 *
	 * @param string $repository Repository name.
	 *
	 * @return ?string
	 */
	public function get_repository_region( string $repository ) : ?string;

	/**
	 * Gets the profile property from a repository.
	 *
	 * @param string $repository Repository name.
	 * @return ?string $profile Profile name.
	 */
	public function get_repository_profile( string $repository = '' ) : ?string;

	/**
	 * Gets the roleArn property from a repository.
	 *
	 * @param string $repository Repository name.
	 * @return ?string $roleArn Role ARN string.
	 */
	public function get_repository_role_arn( string $repository = '' ) : ?string;

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
