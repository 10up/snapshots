<?php
/**
 * WP_CLI-based Progress Bar.
 *
 * @package TenUp\Snapshots
 */
namespace TenUp\Snapshots\ProgressBar;

/**
 * WP_CLI-based logger.
 *
 * @package TenUp\Snapshots\ProgressBar
 */
class WPCLIProgressBar implements ProgressBarInterface {

	/**
	 * The Progress Bar Instances.
	 *
	 * @var array
	 */
	protected $progress_bars = [];

	/**
	 * Create a progress bar.
	 *
	 * @param string $key Key to store the progress bar under.
	 * @param string $message Message to display.
	 * @param float  $size Size of the progress bar.
	 *
	 * @return \cli\progress\Bar
	 */
	public function create_progress_bar( string $key, string $message, float $size ) {
		$this->progress_bars[ $key ]['bar'] = \WP_CLI\Utils\make_progress_bar(
			$message,
			$size
		);

		$this->progress_bars[ $key ]['progress'] = 0;

		return $this->progress_bars[ $key ]['bar'];
	}

	/**
	 * Advance the progress bar.
	 *
	 * @param string $key Key of the progress bar to progress.
	 * @param int $amount Amount to advance the progress bar.
	 *
	 * @return void
	 */
	public function advance_progress_bar( string $key, $amount = 1 ) {
		$this->progress_bars[ $key ]['bar']->tick( $amount - $this->progress_bars[ $key ]['progress'] );
		$this->progress_bars[ $key ]['progress'] = $amount;
	}

	/**
	 * Finish the progress bar.
	 *
	 * @param string $key Key of the progress bar to finish.
	 *
	 * @return void
	 */
	public function finish_progress_bar( string $key ) {
		$this->progress_bars[ $key ]['bar']->finish();
		unset( $this->progress_bars[ $key ] );
	}
}
