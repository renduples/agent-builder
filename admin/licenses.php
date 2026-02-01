<?php
/**
 * Agent Licenses Admin Page
 *
 * Display and manage per-agent licenses.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      1.0.0
 *
 * php version 8.1
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle deactivation action.
if ( isset( $_GET['action'] ) && 'deactivate' === $_GET['action'] && isset( $_GET['slug'] ) && check_admin_referer( 'agentic_deactivate_license_' . sanitize_key( wp_unslash( $_GET['slug'] ) ) ) ) {
	$slug     = sanitize_key( wp_unslash( $_GET['slug'] ) );
	$licenses = get_option( 'agentic_licenses', array() );

	if ( isset( $licenses[ $slug ] ) ) {
		$marketplace = new \Agentic\Marketplace_Client();

		// Call API to deactivate.
		$marketplace_reflection = new \ReflectionClass( $marketplace );
		$deactivate_method      = $marketplace_reflection->getMethod( 'deactivate_agent_license' );
		$deactivate_method->setAccessible( true );
		$deactivate_method->invoke( $marketplace, $slug );

		$message = __( 'License deactivated for this site.', 'agent-builder' );
	}
}

$licenses = get_option( 'agentic_licenses', array() );
$registry = \Agentic_Agent_Registry::get_instance();
$agents   = $registry->get_installed_agents( true );

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Agent Licenses', 'agent-builder' ); ?></h1>
	
	<?php if ( isset( $message ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
	<?php endif; ?>
	
	<p class="description">
		<?php esc_html_e( 'Manage licenses for your premium agents. Each premium agent requires its own license key.', 'agent-builder' ); ?>
	</p>
	
	<?php if ( empty( $licenses ) ) : ?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'You don\'t have any licensed agents yet.', 'agent-builder' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add' ) ); ?>">
					<?php esc_html_e( 'Browse premium agents →', 'agent-builder' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-name column-primary">
						<?php esc_html_e( 'Agent', 'agent-builder' ); ?>
					</th>
					<th scope="col" class="manage-column">
						<?php esc_html_e( 'License Key', 'agent-builder' ); ?>
					</th>
					<th scope="col" class="manage-column">
						<?php esc_html_e( 'Status', 'agent-builder' ); ?>
					</th>
					<th scope="col" class="manage-column">
						<?php esc_html_e( 'Expires', 'agent-builder' ); ?>
					</th>
					<th scope="col" class="manage-column">
						<?php esc_html_e( 'Activations', 'agent-builder' ); ?>
					</th>
					<th scope="col" class="manage-column">
						<?php esc_html_e( 'Actions', 'agent-builder' ); ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $licenses as $slug => $license ) : ?>
					<?php
					$agent_data     = $agents[ $slug ] ?? array();
					$agent_name     = $agent_data['name'] ?? ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
					$license_status = $license['status'] ?? 'unknown';
					$expires_at     = $license['expires_at'] ?? null;
					$is_expired     = false;
					$in_grace       = false;

					if ( $expires_at ) {
						$expires_timestamp = strtotime( $expires_at );
						$is_expired        = $expires_timestamp < time();

						// Check if in grace period.
						if ( $is_expired ) {
							$grace_end = $expires_timestamp + ( 7 * DAY_IN_SECONDS );
							$in_grace  = time() <= $grace_end;
						}
					}

					// Determine status display.
					if ( 'active' === $license_status && ! $is_expired ) {
						$status_class = 'success';
						$status_text  = __( 'Active', 'agent-builder' );
					} elseif ( $in_grace ) {
						$status_class = 'warning';
						$grace_days   = ceil( ( $grace_end - time() ) / DAY_IN_SECONDS );
						/* translators: %d: number of days remaining */
						$status_text = sprintf( __( 'Grace Period (%d days)', 'agent-builder' ), $grace_days );
					} elseif ( $is_expired ) {
						$status_class = 'error';
						$status_text  = __( 'Expired', 'agent-builder' );
					} else {
						$status_class = 'default';
						$status_text  = ucfirst( $license_status );
					}

					$activations_used = $license['activations_used'] ?? 1;
					$activation_limit = $license['activation_limit'] ?? 1;
					$activations_text = sprintf( '%d of %d', $activations_used, $activation_limit );

					if ( $activations_used >= $activation_limit ) {
						$activations_class = 'error';
					} elseif ( $activations_used > ( $activation_limit * 0.8 ) ) {
						$activations_class = 'warning';
					} else {
						$activations_class = 'success';
					}
					?>
					<tr>
						<td class="column-name column-primary" data-colname="<?php esc_attr_e( 'Agent', 'agent-builder' ); ?>">
							<strong><?php echo esc_html( $agent_name ); ?></strong>
							<div class="row-actions">
								<?php if ( isset( $agent_data['active'] ) && $agent_data['active'] ) : ?>
									<span class="active"><?php esc_html_e( 'Active', 'agent-builder' ); ?></span>
								<?php else : ?>
									<span class="inactive"><?php esc_html_e( 'Inactive', 'agent-builder' ); ?></span>
								<?php endif; ?>
							</div>
						</td>
						<td data-colname="<?php esc_attr_e( 'License Key', 'agent-builder' ); ?>">
							<code style="font-size: 11px;"><?php echo esc_html( $license['license_key'] ?? 'N/A' ); ?></code>
						</td>
						<td data-colname="<?php esc_attr_e( 'Status', 'agent-builder' ); ?>">
							<span class="agentic-license-status agentic-status-<?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( $status_text ); ?>
							</span>
						</td>
						<td data-colname="<?php esc_attr_e( 'Expires', 'agent-builder' ); ?>">
							<?php
							if ( $expires_at ) {
								$expires_date = date_i18n( get_option( 'date_format' ), strtotime( $expires_at ) );
								echo esc_html( $expires_date );

								if ( $is_expired && ! $in_grace ) {
									echo ' <span style="color: #d63638;">(⚠)</span>';
								}
							} else {
								echo '<span style="color: #646970;">—</span>';
							}
							?>
						</td>
						<td data-colname="<?php esc_attr_e( 'Activations', 'agent-builder' ); ?>">
							<span class="agentic-activations agentic-status-<?php echo esc_attr( $activations_class ); ?>">
								<?php echo esc_html( $activations_text ); ?>
							</span>
						</td>
						<td data-colname="<?php esc_attr_e( 'Actions', 'agent-builder' ); ?>">
							<?php
							$deactivate_url = wp_nonce_url(
								add_query_arg(
									array(
										'page'   => 'agentic-licenses',
										'action' => 'deactivate',
										'slug'   => $slug,
									),
									admin_url( 'admin.php' )
								),
								'agentic_deactivate_license_' . $slug
							);
							?>
							<a href="<?php echo esc_url( $deactivate_url ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to deactivate this license for this site? The license will remain valid for use on other sites.', 'agent-builder' ); ?>');">
								<?php esc_html_e( 'Deactivate This Site', 'agent-builder' ); ?>
							</a>
							
							<?php if ( $is_expired || 'active' !== $license_status ) : ?>
								<a href="https://agentic-plugin.com/renew?license=<?php echo esc_attr( $license['license_key'] ?? '' ); ?>" class="button button-primary button-small" target="_blank">
									<?php esc_html_e( 'Renew License →', 'agent-builder' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<style>
			.agentic-license-status,
			.agentic-activations {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 500;
			}
			
			.agentic-status-success {
				background: #d4f4dd;
				color: #00661b;
			}
			
			.agentic-status-warning {
				background: #fcf3cf;
				color: #8a6116;
			}
			
			.agentic-status-error {
				background: #fdd;
				color: #a00;
			}
			
			.agentic-status-default {
				background: #f0f0f1;
				color: #646970;
			}
			
			.column-name .row-actions {
				color: #646970;
			}
			
			.column-name .row-actions .active {
				color: #00a32a;
			}
			
			.column-name .row-actions .inactive {
				color: #646970;
			}
		</style>
		
		<div style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'License Information', 'agent-builder' ); ?></h3>
			<ul style="margin-bottom: 0;">
				<li><?php esc_html_e( 'Each premium agent requires its own license key.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'Licenses can be used on multiple sites up to your activation limit.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'Deactivating a license from this site frees up an activation slot.', 'agent-builder' ); ?></li>
				<li><?php esc_html_e( 'Expired licenses have a 7-day grace period before agents stop working.', 'agent-builder' ); ?></li>
				<li>
					<?php
					printf(
						/* translators: %s: marketplace URL */
						esc_html__( 'Manage all your licenses at %s', 'agent-builder' ),
						'<a href="https://agentic-plugin.com/account/licenses/" target="_blank">agentic-plugin.com/account/licenses/</a>'
					);
					?>
				</li>
			</ul>
		</div>
		<?php endif; ?>
</div>
