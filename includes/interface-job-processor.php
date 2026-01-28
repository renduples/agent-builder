<?php
/**
 * Job Processor Interface
 *
 * Interface that all job processors must implement
 *
 * @package Agentic_Plugin
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Agentic;

/**
 * Job Processor Interface
 */
interface Job_Processor_Interface {

	/**
	 * Execute the job
	 *
	 * @param array    $request_data   Job input data.
	 * @param callable $progress_callback Callback to update progress.
	 * @return array Job result data.
	 * @throws \Exception If job execution fails.
	 */
	public function execute( array $request_data, callable $progress_callback ): array;
}
