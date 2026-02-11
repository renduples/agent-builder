<?php
/**
 * Agentic Event Listeners Page
 *
 * Displays all agent event listeners – WordPress action hooks that trigger
 * agent behaviour automatically when events occur in the system.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      1.5.0
 *
 * @wordpress-plugin
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agent-builder' ) );
}

// Collect all event listeners from active agents.
$agentic_registry   = \Agentic_Agent_Registry::get_instance();
$agentic_instances  = $agentic_registry->get_all_instances();
$agentic_all_events = array();

foreach ( $agentic_instances as $agentic_agent ) {
	$agentic_listeners = $agentic_agent->get_event_listeners();
	foreach ( $agentic_listeners as $agentic_listener ) {
		$agentic_all_events[] = array(
			'agent_id'    => $agentic_agent->get_id(),
			'agent_name'  => $agentic_agent->get_name(),
			'agent_icon'  => $agentic_agent->get_icon(),
			'listener_id' => $agentic_listener['id'],
			'name'        => $agentic_listener['name'],
			'hook'        => $agentic_listener['hook'],
			'description' => $agentic_listener['description'] ?? '',
			'priority'    => $agentic_listener['priority'] ?? 10,
			'mode'        => ! empty( $agentic_listener['prompt'] ) ? 'autonomous' : 'direct',
		);
	}
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Event Listeners', 'agent-builder' ); ?></h1>
	<p><?php esc_html_e( 'WordPress action hooks that agents listen to. When a hook fires, the agent reacts automatically.', 'agent-builder' ); ?></p>

	<?php if ( empty( $agentic_all_events ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No event listeners found. Activate agents that define event listeners to see them here.', 'agent-builder' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped" style="margin-top: 20px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Agent', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Listener', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'WordPress Hook', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Priority', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Mode', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Status', 'agent-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $agentic_all_events as $agentic_event_row ) : ?>
				<tr>
					<td>
						<span style="font-size: 16px; vertical-align: -2px;"><?php echo esc_html( $agentic_event_row['agent_icon'] ); ?></span>
						<?php echo esc_html( $agentic_event_row['agent_name'] ); ?>
					</td>
					<td>
						<strong><?php echo esc_html( $agentic_event_row['name'] ); ?></strong>
						<?php if ( $agentic_event_row['description'] ) : ?>
							<br><small style="color: #646970;"><?php echo esc_html( $agentic_event_row['description'] ); ?></small>
						<?php endif; ?>
					</td>
					<td>
						<code><?php echo esc_html( $agentic_event_row['hook'] ); ?></code>
					</td>
					<td><?php echo esc_html( $agentic_event_row['priority'] ); ?></td>
					<td>
						<?php if ( 'autonomous' === $agentic_event_row['mode'] ) : ?>
							<span title="<?php esc_attr_e( 'Queues an async LLM task with event context when triggered', 'agent-builder' ); ?>" style="color: #2271b1;">🤖 <?php esc_html_e( 'AI (Async)', 'agent-builder' ); ?></span>
						<?php else : ?>
							<span title="<?php esc_attr_e( 'Calls the agent callback method directly (synchronous)', 'agent-builder' ); ?>" style="color: #646970;">⚙️ <?php esc_html_e( 'Direct', 'agent-builder' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<span style="color: #00a32a;">&#9679; <?php esc_html_e( 'Active', 'agent-builder' ); ?></span>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'How Event Listeners Work', 'agent-builder' ); ?></h3>
			<ul style="margin: 0; list-style: disc; padding-left: 20px;">
				<li><?php esc_html_e( 'Event listeners hook into WordPress actions (e.g., save_post, wp_login, user_register).', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'Direct mode listeners execute a PHP callback immediately when the hook fires.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'AI mode listeners queue an asynchronous LLM task so they don\'t block the current page load.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'WordPress hook arguments are automatically serialized into the LLM prompt as context.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'Listeners are bound when an agent is active. Deactivating the agent removes its listeners.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'Every execution is logged in the Audit Log with timing, mode, and outcome details.', 'agent-builder' ); ?></li>
			</ul>
		</div>
	<?php endif; ?>
</div>
