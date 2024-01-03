<?php
/**
 * Progress Bar interface.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\ProgressBar;

use TenUp\Snapshots\Infrastructure\SharedService;

/**
 * Progress Bar  interface.
 *
 * @package TenUp\Snapshots\ProgressBar
 */
interface ProgressBarInterface extends SharedService {

	/**
	 * Create a progress bar.
	 *
	 * @param string $key Key to store the progress bar under.
	 * @param string $message Message to display.
	 * @param float  $size Size of the progress bar.
	 *
	 * @return mixed
	 */
	public function create_progress_bar( string $key, string $message, float $size );

	/**
	 * Advance the progress bar.
	 *
	 * @param string $key Key of the progress bar to progress.
	 * @param int    $amount Amount to advance the progress bar.
	 *
	 * @return void
	 */
	public function advance_progress_bar( string $key, int $amount = 1 );

	/**
	 * Finish the progress bar.
	 *
	 * @param string $key Key of the progress bar to finish.
	 *
	 * @return void
	 */
	public function finish_progress_bar( string $key );
}
