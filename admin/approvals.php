<?php
/**
 * Agentic Approval Queue
 *
 * @package Agentic_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Core\Approval_Queue;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agentic-core' ) );
}

$queue           = new Approval_Queue();
$pending         = $queue->get_pending();
$agent_mode      = get_option( 'agentic_agent_mode', 'supervised' );
$show_empty_note = empty( $pending );
?>
<div class="wrap agentic-admin">
	<h1>
		<span class="dashicons dashicons-yes-alt" style="font-size: 30px; margin-right: 10px;"></span>
		Approval Queue
	</h1>

	<p class="description">
		Review and approve actions requested by AI agents before they are executed.
		<?php if ( $agent_mode === 'supervised' ) : ?>
			<strong>Mode:</strong> Supervised - Code changes require approval, documentation updates are automatic.
		<?php elseif ( $agent_mode === 'autonomous' ) : ?>
			<strong>Mode:</strong> Autonomous - All actions execute automatically (approval queue bypassed).
		<?php else : ?>
			<strong>Mode:</strong> Disabled - Agents cannot make file changes.
		<?php endif; ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-settings' ) ); ?>">Change mode</a>
	</p>

	<?php if ( $agent_mode === 'autonomous' ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong>Warning:</strong> Autonomous mode is enabled. Actions are executed immediately without requiring approval.
				The queue below will remain empty.
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $show_empty_note ) : ?>
		<div class="agentic-card" style="margin-top: 20px; padding: 30px; text-align: center;">
			<span class="dashicons dashicons-thumbs-up" style="font-size: 48px; color: #22c55e; margin-bottom: 10px;"></span>
			<h2 style="margin: 10px 0;">All caught up!</h2>
			<p style="color: #666;">No pending approvals at this time.</p>
		</div>
	<?php else : ?>
		<div style="margin-top: 20px;">
			<?php foreach ( $pending as $item ) : ?>
				<?php
				$action_type  = esc_html( $item['action'] );
				$action_label = str_replace( '_', ' ', ucwords( $action_type, '_' ) );
				$params       = $item['params'];
				$created      = human_time_diff( strtotime( $item['created_at'] ) );
				$expires      = human_time_diff( strtotime( $item['expires_at'] ) );
				?>
				<div class="agentic-approval-item" data-id="<?php echo esc_attr( $item['id'] ); ?>" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 15px;">
					<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
						<div>
							<h3 style="margin: 0 0 5px 0;">
								<?php echo esc_html( $action_label ); ?>
							</h3>
							<p style="margin: 0; color: #666; font-size: 13px;">
								<strong>Agent:</strong> <?php echo esc_html( $item['agent_id'] ); ?> &nbsp;•&nbsp; 
								<strong>Requested:</strong> <?php echo esc_html( $created ); ?> ago &nbsp;•&nbsp; 
								<strong>Expires:</strong> in <?php echo esc_html( $expires ); ?>
							</p>
						</div>
						<div>
							<button class="button button-primary agentic-approve-btn" data-id="<?php echo esc_attr( $item['id'] ); ?>">
								<span class="dashicons dashicons-yes" style="vertical-align: -2px;"></span> Approve
							</button>
							<button class="button agentic-reject-btn" data-id="<?php echo esc_attr( $item['id'] ); ?>" style="margin-left: 8px;">
								<span class="dashicons dashicons-no" style="vertical-align: -2px;"></span> Reject
							</button>
						</div>
					</div>

					<?php if ( ! empty( $item['reasoning'] ) ) : ?>
						<div style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 12px; margin-bottom: 15px;">
							<p style="margin: 0; font-weight: 600; font-size: 13px;">Agent's Reasoning:</p>
							<p style="margin: 8px 0 0 0; font-size: 13px;"><?php echo esc_html( $item['reasoning'] ); ?></p>
						</div>
					<?php endif; ?>

					<details style="margin-top: 10px;">
						<summary style="cursor: pointer; font-weight: 600; color: #2271b1;">View Details</summary>
						<div style="margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px; overflow-x: auto;">
							<pre style="margin: 0; font-size: 12px; white-space: pre-wrap;"><?php echo esc_html( wp_json_encode( $params, JSON_PRETTY_PRINT ) ); ?></pre>
						</div>
					</details>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<style>
.agentic-approval-item button:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}
.agentic-approval-item.processing {
	opacity: 0.6;
	pointer-events: none;
}
.agentic-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Handle approve
	$('.agentic-approve-btn').on('click', function() {
		const btn = $(this);
		const id = btn.data('id');
		const item = btn.closest('.agentic-approval-item');
		
		if (!confirm('Are you sure you want to approve this action?')) {
			return;
		}
		
		item.addClass('processing');
		btn.prop('disabled', true).text('Approving...');
		
		$.ajax({
			url: '<?php echo esc_url( rest_url( 'agentic/v1/approvals/' ) ); ?>' + id,
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
			},
			data: {
				action: 'approve'
			},
			success: function(response) {
				item.fadeOut(400, function() {
					$(this).remove();
					checkIfEmpty();
				});
			},
			error: function(xhr) {
				const error = xhr.responseJSON?.error || 'Failed to approve action';
				alert('Error: ' + error);
				item.removeClass('processing');
				btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align: -2px;"></span> Approve');
			}
		});
	});
	
	// Handle reject
	$('.agentic-reject-btn').on('click', function() {
		const btn = $(this);
		const id = btn.data('id');
		const item = btn.closest('.agentic-approval-item');
		
		if (!confirm('Are you sure you want to reject this action?')) {
			return;
		}
		
		item.addClass('processing');
		btn.prop('disabled', true).text('Rejecting...');
		
		$.ajax({
			url: '<?php echo esc_url( rest_url( 'agentic/v1/approvals/' ) ); ?>' + id,
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
			},
			data: {
				action: 'reject'
			},
			success: function(response) {
				item.fadeOut(400, function() {
					$(this).remove();
					checkIfEmpty();
				});
			},
			error: function(xhr) {
				const error = xhr.responseJSON?.error || 'Failed to reject action';
				alert('Error: ' + error);
				item.removeClass('processing');
				btn.prop('disabled', false).html('<span class="dashicons dashicons-no" style="vertical-align: -2px;"></span> Reject');
			}
		});
	});
	
	function checkIfEmpty() {
		if ($('.agentic-approval-item').length === 0) {
			location.reload();
		}
	}
});
</script>
