<?php
/**
 * Deployment Tab: Scheduled Tasks
 *
 * Displays all agent scheduled tasks, their status, and next run time.
 * Included by admin/deployment.php — do not load directly.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @since      1.7.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Collect all scheduled tasks from active agents.
$agentic_registry  = \Agentic_Agent_Registry::get_instance();
$agentic_instances = $agentic_registry->get_all_instances();
$agentic_all_tasks = array();

foreach ( $agentic_instances as $agentic_agent ) {
	$agentic_tasks = $agentic_agent->get_scheduled_tasks();
	foreach ( $agentic_tasks as $agentic_task ) {
		$agentic_hook      = $agentic_agent->get_cron_hook( $agentic_task['id'] );
		$agentic_next_run  = wp_next_scheduled( $agentic_hook );
		$agentic_schedules = wp_get_schedules();

		$agentic_all_tasks[] = array(
			'agent_id'         => $agentic_agent->get_id(),
			'agent_name'       => $agentic_agent->get_name(),
			'agent_icon'       => $agentic_agent->get_icon(),
			'task_id'          => $agentic_task['id'],
			'task_name'        => $agentic_task['name'],
			'description'      => $agentic_task['description'] ?? '',
			'schedule'         => $agentic_task['schedule'],
			'schedule_display' => $agentic_schedules[ $agentic_task['schedule'] ]['display'] ?? ucfirst( $agentic_task['schedule'] ),
			'hook'             => $agentic_hook,
			'next_run'         => $agentic_next_run,
			'registered'       => false !== $agentic_next_run,
			'mode'             => ! empty( $agentic_task['prompt'] ) ? 'autonomous' : 'direct',
		);
	}
}

?>
<?php if ( empty( $agentic_all_tasks ) ) : ?>
	<div class="notice notice-info">
		<p><?php esc_html_e( 'No scheduled tasks found. Activate agents that define scheduled tasks to see them here.', 'agent-builder' ); ?></p>
	</div>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Agent', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Task', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Schedule', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Mode', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Status', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Next Run', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'agent-builder' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $agentic_all_tasks as $agentic_task_row ) : ?>
			<tr>
				<td>
					<span style="font-size: 16px; vertical-align: -2px;"><?php echo esc_html( $agentic_task_row['agent_icon'] ); ?></span>
					<?php echo esc_html( $agentic_task_row['agent_name'] ); ?>
				</td>
				<td>
					<strong><?php echo esc_html( $agentic_task_row['task_name'] ); ?></strong>
					<?php if ( $agentic_task_row['description'] ) : ?>
						<br><small style="color: #646970;"><?php echo esc_html( $agentic_task_row['description'] ); ?></small>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $agentic_task_row['schedule_display'] ); ?></td>
				<td>
					<?php if ( 'autonomous' === $agentic_task_row['mode'] ) : ?>
						<span title="<?php esc_attr_e( 'Runs through the LLM with AI reasoning and tool calls', 'agent-builder' ); ?>" style="color: #2271b1;">🤖 <?php esc_html_e( 'AI', 'agent-builder' ); ?></span>
					<?php else : ?>
						<span title="<?php esc_attr_e( 'Runs the callback method directly without LLM', 'agent-builder' ); ?>" style="color: #646970;">⚙️ <?php esc_html_e( 'Direct', 'agent-builder' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $agentic_task_row['registered'] ) : ?>
						<span style="color: #00a32a;">&#9679; <?php esc_html_e( 'Active', 'agent-builder' ); ?></span>
					<?php else : ?>
						<span style="color: #b91c1c;">&#9679; <?php esc_html_e( 'Not Scheduled', 'agent-builder' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $agentic_task_row['next_run'] ) : ?>
						<?php echo esc_html( wp_date( 'Y-m-d H:i', $agentic_task_row['next_run'] ) ); ?>
						<br><small style="color: #646970;">
							<?php
							$agentic_diff = $agentic_task_row['next_run'] - time();
							if ( $agentic_diff > 0 ) {
								/* translators: %s: human-readable time difference */
								printf( esc_html__( 'in %s', 'agent-builder' ), esc_html( human_time_diff( time(), $agentic_task_row['next_run'] ) ) );
							} else {
								esc_html_e( 'overdue', 'agent-builder' );
							}
							?>
						</small>
					<?php else : ?>
						&mdash;
					<?php endif; ?>
				</td>
				<td>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=agentic-run-task&agent=' . rawurlencode( $agentic_task_row['agent_id'] ) . '&task=' . rawurlencode( $agentic_task_row['task_id'] ) ), 'agentic_run_task', '_wpnonce' ) ); ?>" class="button button-small">
						<?php esc_html_e( 'Run Now', 'agent-builder' ); ?>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'How Scheduled Tasks Work', 'agent-builder' ); ?></h3>
		<ul style="margin: 0; list-style: disc; padding-left: 20px;">
			<li><?php esc_html_e( 'Tasks use WordPress cron (WP-Cron) which runs when someone visits your site.', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'For reliable scheduling on low-traffic sites, set up a real cron job to hit wp-cron.php.', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'Tasks are automatically registered when an agent is activated and removed when deactivated.', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'AI mode tasks run through the LLM with full tool access. Direct mode tasks call a PHP method. If the LLM is not configured, AI tasks gracefully fall back to direct mode.', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'Every execution is logged in the Audit Log with timing, mode, and outcome details.', 'agent-builder' ); ?></li>
		</ul>
	</div>
<?php endif; ?>
