<?php
/**
 * Agentic Audit Log Page
 *
 * @package Agentic_Plugin
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Audit_Log;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agentic-plugin' ) );
}


$audit = new Audit_Log();

// Filter parameters
$agent_filter  = sanitize_text_field( $_GET['agent'] ?? '' );
$action_filter = sanitize_text_field( $_GET['action'] ?? '' );

$logs  = $audit->get_recent( 100, $agent_filter ?: null, $action_filter ?: null );
$stats = $audit->get_stats( 'month' );
?>
<div class="wrap">
	<h1>Agent Audit Log</h1>
	<p>Complete history of all agent actions for transparency and debugging.</p>

	<div class="agentic-stats-bar" style="display: flex; gap: 20px; margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 4px;">
		<div>
			<strong>Actions (30 days):</strong> <?php echo esc_html( number_format( (int) ( $stats['total_actions'] ?? 0 ) ) ); ?>
		</div>
		<div>
			<strong>Tokens Used:</strong> <?php echo esc_html( number_format( (int) ( $stats['total_tokens'] ?? 0 ) ) ); ?>
		</div>
		<div>
			<strong>Estimated Cost:</strong> $<?php echo esc_html( number_format( (float) ( $stats['total_cost'] ?? 0 ), 4 ) ); ?>
		</div>
	</div>

	<form method="get" action="" style="margin-bottom: 20px;">
		<input type="hidden" name="page" value="agentic-audit">
		
		<select name="agent">
			<option value="">All Agents</option>
			<option value="developer_agent" <?php selected( $agent_filter, 'developer_agent' ); ?>>Developer Agent</option>
			<option value="human" <?php selected( $agent_filter, 'human' ); ?>>Human Actions</option>
		</select>

		<select name="action">
			<option value="">All Actions</option>
			<option value="chat_start" <?php selected( $action_filter, 'chat_start' ); ?>>Chat Start</option>
			<option value="chat_complete" <?php selected( $action_filter, 'chat_complete' ); ?>>Chat Complete</option>
			<option value="tool_call" <?php selected( $action_filter, 'tool_call' ); ?>>Tool Call</option>
			<option value="create_comment" <?php selected( $action_filter, 'create_comment' ); ?>>Create Comment</option>
			<option value="update_documentation" <?php selected( $action_filter, 'update_documentation' ); ?>>Update Documentation</option>
			<option value="request_code_change" <?php selected( $action_filter, 'request_code_change' ); ?>>Request Code Change</option>
		</select>

		<button type="submit" class="button">Filter</button>
		<?php if ( $agent_filter || $action_filter ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-audit' ) ); ?>" class="button">Clear</a>
		<?php endif; ?>
	</form>

	<?php if ( empty( $logs ) ) : ?>
		<div class="notice notice-info">
			<p>No audit log entries found.</p>
		</div>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>Time</th>
					<th>Agent</th>
					<th>Action</th>
					<th>Target</th>
					<th>Tokens</th>
					<th>Cost</th>
					<th>User</th>
					<th>Details</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $entry ) : ?>
				<tr>
					<td>
						<span title="<?php echo esc_attr( $entry['created_at'] ); ?>">
							<?php echo esc_html( human_time_diff( strtotime( $entry['created_at'] ) ) ); ?> ago
						</span>
					</td>
					<td><?php echo esc_html( $entry['agent_id'] ); ?></td>
					<td><code><?php echo esc_html( $entry['action'] ); ?></code></td>
					<td>
						<?php if ( $entry['target_type'] ) : ?>
							<?php echo esc_html( $entry['target_type'] ); ?>
							<?php if ( $entry['target_id'] ) : ?>
								<small>(<?php echo esc_html( $entry['target_id'] ); ?>)</small>
							<?php endif; ?>
						<?php else : ?>
							-
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( number_format( (int) ( $entry['tokens_used'] ?? 0 ) ) ); ?></td>
					<td>$<?php echo esc_html( number_format( (float) ( $entry['cost'] ?? 0 ), 6 ) ); ?></td>
					<td>
						<?php
						if ( $entry['user_id'] ) {
							$user = get_user_by( 'id', $entry['user_id'] );
							echo esc_html( $user ? $user->display_name : 'User #' . $entry['user_id'] );
						} else {
							echo '-';
						}
						?>
					</td>
					<td>
						<?php if ( $entry['details'] ) : ?>
							<details>
								<summary>View</summary>
								<pre style="max-width: 400px; overflow: auto; font-size: 11px; background: #f5f5f5; padding: 5px;"><?php echo esc_html( $entry['details'] ); ?></pre>
							</details>
						<?php else : ?>
							-
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<hr>

	<h2>Data Retention</h2>
	<p>
		Audit logs are retained for 90 days by default. Older entries are automatically cleaned up.
	</p>
</div>
