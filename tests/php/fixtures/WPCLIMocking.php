<?php
/**
 * Helper trait that allows mocking of WP_CLI.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Tests\Fixtures;

/**
 * Trait PrivateAccess
 *
 * @package TenUp\Snapshots\Tests\Fixtures
 */
trait WPCLIMocking {

	/**
	 * Mock object.
	 *
	 * @var ?WPCLIMock
	 */
	protected $wpcli_mock;

	/**
	 * Mock WP_CLI and keep track of method calls and arguments.
	 */
	public function set_up_wp_cli_mock() {
		$this->get_wp_cli_mock();

		add_filter( 'snapshots_wpcli', function() {
			return $this->get_wp_cli_mock();
		} );

		add_filter( 'snapshots_readline', function() {
			return [ $this->get_wp_cli_mock(), 'readline' ];
		} );

		add_filter( 'snapshots_get_flag_or_prompt_answers', function() {
			return null;
		} );
	}


	/**
	 * Tear down
	 */
	public function tear_down_wp_cli_mock() {
		remove_all_filters( 'snapshots_wpcli' );
		remove_all_filters( 'snapshots_readline' );
		remove_all_filters( 'snapshots_get_flag_or_prompt_answers' );

		$this->wpcli_mock->reset_wpcli_mock_calls();
		$this->wpcli_mock = null;
	}

	/**
	 * Get the mock object.
	 *
	 * @return WPCLIMock
	 */
	protected function get_wp_cli_mock() : WPCLIMock {
		if ( is_null( $this->wpcli_mock ) ) {
			$this->wpcli_mock = new WPCLIMock( $this );
		}

		return $this->wpcli_mock;
	}
}

