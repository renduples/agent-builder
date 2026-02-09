<?php
/**
 * Security Log Admin Page
 *
 * Display security events (blocked messages, rate limits, PII warnings).
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later
 * @link       https://agentic-plugin.com
 * @since      0.2.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check user capabilities.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'agent-builder' ) );
}

// Handle cleanup action.
if ( isset( $_POST['agentic_cleanup_security_log'] ) && check_admin_referer( 'agentic_cleanup_security_log' ) ) {
	$agentic_days    = isset( $_POST['cleanup_days'] ) ? absint( $_POST['cleanup_days'] ) : 30;
	$agentic_deleted = \Agentic\Security_Log::cleanup( $agentic_days );

	echo '<div class="notice notice-success"><p>';
	/* translators: %d: number of deleted log entries */
	printf( esc_html__( 'Deleted %d old security log entries.', 'agent-builder' ), (int) $agentic_deleted );
	echo '</p></div>';
}

// Get filter parameters.
$agentic_event_type = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : '';
$agentic_paged      = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$agentic_per_page   = 50;

// Build query args.
$agentic_query_args = array(
	'limit'  => $agentic_per_page,
	'offset' => ( $agentic_paged - 1 ) * $agentic_per_page,
);

if ( ! empty( $agentic_event_type ) ) {
	$agentic_query_args['event_type'] = $agentic_event_type;
}

// Get events and stats.
$agentic_events       = \Agentic\Security_Log::get_events( $agentic_query_args );
$agentic_total_items  = \Agentic\Security_Log::get_count( $agentic_query_args );
$agentic_total_pages  = ceil( $agentic_total_items / $agentic_per_page );
$agentic_stats        = \Agentic\Security_Log::get_stats( 7 );
$agentic_top_ips      = \Agentic\Security_Log::get_top_ips( 5, 7 );
$agentic_top_patterns = \Agentic\Security_Log::get_top_patterns( 5, 7 );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Security Log', 'agent-builder' ); ?></h1>

	<!-- Statistics Dashboard -->
	<div class="agentic-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
		<div class="agentic-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970;"><?php esc_html_e( 'Blocked Messages (7 days)', 'agent-builder' ); ?></h3>
			<p style="margin: 0; font-size: 32px; font-weight: 600;"><?php echo esc_html( number_format_i18n( (int) $agentic_stats['blocked_count'] ) ); ?></p>
		</div>

		<div class="agentic-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #f0b849; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970;"><?php esc_html_e( 'Rate Limited (7 days)', 'agent-builder' ); ?></h3>
			<p style="margin: 0; font-size: 32px; font-weight: 600;"><?php echo esc_html( number_format_i18n( (int) $agentic_stats['rate_limited_count'] ) ); ?></p>
		</div>

		<div class="agentic-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #72aee6; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970;"><?php esc_html_e( 'PII Warnings (7 days)', 'agent-builder' ); ?></h3>
			<p style="margin: 0; font-size: 32px; font-weight: 600;"><?php echo esc_html( number_format_i18n( (int) $agentic_stats['pii_warning_count'] ) ); ?></p>
		</div>

		<div class="agentic-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970;"><?php esc_html_e( 'Unique IPs (7 days)', 'agent-builder' ); ?></h3>
			<p style="margin: 0; font-size: 32px; font-weight: 600;"><?php echo esc_html( number_format_i18n( (int) $agentic_stats['unique_ips'] ) ); ?></p>
		</div>
	</div>

	<!-- Top Patterns and IPs -->
	<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
		<div class="card">
			<h2><?php esc_html_e( 'Top Blocked Patterns (7 days)', 'agent-builder' ); ?></h2>
			<?php if ( ! empty( $agentic_top_patterns ) ) : ?>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Pattern', 'agent-builder' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Count', 'agent-builder' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $agentic_top_patterns as $agentic_pattern ) : ?>
							<tr>
								<td><code><?php echo esc_html( substr( $agentic_pattern['pattern_matched'], 0, 50 ) ); ?></code></td>
								<td><?php echo esc_html( number_format_i18n( (int) $agentic_pattern['count'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No blocked patterns in the last 7 days.', 'agent-builder' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="card">
			<h2><?php esc_html_e( 'Top Offending IPs (7 days)', 'agent-builder' ); ?></h2>
			<?php if ( ! empty( $agentic_top_ips ) ) : ?>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'IP Address', 'agent-builder' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Events', 'agent-builder' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $agentic_top_ips as $agentic_ip_data ) : ?>
							<tr>
								<td><code><?php echo esc_html( $agentic_ip_data['ip_address'] ); ?></code></td>
								<td><?php echo esc_html( number_format_i18n( (int) $agentic_ip_data['event_count'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No security events in the last 7 days.', 'agent-builder' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Filters and Actions -->
	<div class="tablenav top">
		<div class="alignleft actions">
			<select name="event_type" id="event-type-filter" onchange="this.form.submit()">
				<option value=""><?php esc_html_e( 'All Events', 'agent-builder' ); ?></option>
				<option value="blocked" <?php selected( $agentic_event_type, 'blocked' ); ?>><?php esc_html_e( 'Blocked', 'agent-builder' ); ?></option>
				<option value="rate_limited" <?php selected( $agentic_event_type, 'rate_limited' ); ?>><?php esc_html_e( 'Rate Limited', 'agent-builder' ); ?></option>
				<option value="pii_warning" <?php selected( $agentic_event_type, 'pii_warning' ); ?>><?php esc_html_e( 'PII Warnings', 'agent-builder' ); ?></option>
			</select>
		</div>

		<div class="alignright">
			<button type="button" class="button" onclick="document.getElementById('cleanup-dialog').style.display='block'">
				<?php esc_html_e( 'Clean Up Old Logs', 'agent-builder' ); ?>
			</button>
		</div>
	</div>

	<!-- Events Table -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 150px;"><?php esc_html_e( 'Date/Time', 'agent-builder' ); ?></th>
				<th style="width: 100px;"><?php esc_html_e( 'Event Type', 'agent-builder' ); ?></th>
				<th style="width: 120px;"><?php esc_html_e( 'User', 'agent-builder' ); ?></th>
				<th style="width: 120px;"><?php esc_html_e( 'IP Address', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Message Preview', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Pattern/PII', 'agent-builder' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $agentic_events ) ) : ?>
				<?php foreach ( $agentic_events as $agentic_event ) : ?>
					<?php
					$agentic_event_class = '';
					if ( 'blocked' === $agentic_event['event_type'] ) {
						$agentic_event_class = 'error';
					} elseif ( 'rate_limited' === $agentic_event['event_type'] ) {
						$agentic_event_class = 'warning';
					}
					?>
					<tr class="<?php echo esc_attr( $agentic_event_class ); ?>">
						<td><?php echo esc_html( $agentic_event['created_at'] ); ?></td>
						<td>
							<?php
							$agentic_badge_color = match ( $agentic_event['event_type'] ) {
								'blocked' => '#d63638',
								'rate_limited' => '#f0b849',
								'pii_warning' => '#72aee6',
								default => '#646970',
							};
	?>
							<span style="display: inline-block; padding: 3px 8px; background: <?php echo esc_attr( $agentic_badge_color ); ?>; color: #fff; border-radius: 3px; font-size: 11px; font-weight: 600;">
								<?php echo esc_html( strtoupper( str_replace( '_', ' ', $agentic_event['event_type'] ) ) ); ?>
							</span>
						</td>
						<td>
							<?php
							if ( $agentic_event['user_id'] > 0 ) {
								$agentic_user = get_userdata( $agentic_event['user_id'] );
								echo esc_html( $agentic_user ? $agentic_user->user_login : 'User #' . $agentic_event['user_id'] );
							} else {
								esc_html_e( 'Anonymous', 'agent-builder' );
							}
							?>
						</td>
						<td><code><?php echo esc_html( $agentic_event['ip_address'] ); ?></code></td>
						<td>
							<details>
								<summary style="cursor: pointer;"><?php echo esc_html( substr( $agentic_event['message'], 0, 50 ) ); ?>...</summary>
								<div style="margin-top: 10px; padding: 10px; background: #f6f7f7; border-radius: 3px;">
									<code><?php echo esc_html( $agentic_event['message'] ); ?></code>
								</div>
							</details>
						</td>
						<td>
							<?php if ( ! empty( $agentic_event['pattern_matched'] ) ) : ?>
								<code><?php echo esc_html( substr( $agentic_event['pattern_matched'], 0, 40 ) ); ?></code>
							<?php endif; ?>
							<?php if ( ! empty( $agentic_event['pii_types'] ) ) : ?>
								<span style="color: #72aee6;"><?php echo esc_html( $agentic_event['pii_types'] ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="6" style="text-align: center; padding: 40px;">
						<?php esc_html_e( 'No security events found.', 'agent-builder' ); ?>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $agentic_total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $agentic_paged,
							'total'     => $agentic_total_pages,
							'prev_text' => __( '&laquo; Previous', 'agent-builder' ),
							'next_text' => __( 'Next &raquo;', 'agent-builder' ),
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Cleanup Dialog -->
<div id="cleanup-dialog" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; align-items: center; justify-content: center;">
	<div style="background: #fff; padding: 30px; border-radius: 5px; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
		<h2><?php esc_html_e( 'Clean Up Old Logs', 'agent-builder' ); ?></h2>
		<p><?php esc_html_e( 'Delete security log entries older than the specified number of days.', 'agent-builder' ); ?></p>
		
		<form method="post">
			<?php wp_nonce_field( 'agentic_cleanup_security_log' ); ?>
			<p>
				<label>
					<?php esc_html_e( 'Delete entries older than:', 'agent-builder' ); ?>
					<input type="number" name="cleanup_days" value="30" min="1" max="365" style="width: 80px;"> <?php esc_html_e( 'days', 'agent-builder' ); ?>
				</label>
			</p>
			<p>
				<button type="submit" name="agentic_cleanup_security_log" class="button button-primary">
					<?php esc_html_e( 'Clean Up Now', 'agent-builder' ); ?>
				</button>
				<button type="button" class="button" onclick="document.getElementById('cleanup-dialog').style.display='none'">
					<?php esc_html_e( 'Cancel', 'agent-builder' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<script>
// Show cleanup dialog as flex when opened
document.getElementById('cleanup-dialog').addEventListener('click', function(e) {
	if (e.target === this) {
		this.style.display = 'none';
	}
});
</script>
