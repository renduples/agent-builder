<?php
/**
 * Agentic Admin Dashboard
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      0.1.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Agentic\Audit_Log;
use Agentic\LLM_Client;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agentbuilder' ) );
}

$agentic_audit         = new Audit_Log();
$agentic_stats         = $agentic_audit->get_stats( 'week' );
$agentic_llm           = new LLM_Client();
$agentic_is_configured = $agentic_llm->is_configured();
$agentic_provider      = $agentic_llm->get_provider();
$agentic_model         = $agentic_llm->get_model();

// Get actual count of activated agents.
$agentic_active_agents = get_option( 'agentic_active_agents', array() );
$agentic_active_count  = is_array( $agentic_active_agents ) ? count( $agentic_active_agents ) : 0;

// Check plugin license status.
$agentic_plugin_license_key = get_option( 'agentic_plugin_license_key', '' );
$agentic_license_status     = 'free'; // Default to free tier.
$agentic_license_display    = 'Free';

if ( class_exists( '\Agentic\License_Client' ) ) {
	$agentic_lc_instance = \Agentic\License_Client::get_instance();
	$agentic_lc_status   = $agentic_lc_instance->get_status();

	$agentic_license_status = $agentic_lc_status['status'] ?? 'free';
	$agentic_tier           = $agentic_lc_status['type'] ?? 'free';

	switch ( $agentic_license_status ) {
		case 'active':
			$agentic_license_display = '<span style="color: #00a32a; font-weight: 600;">● ' . ucfirst( $agentic_tier ) . '</span>';
			break;
		case 'grace_period':
			$agentic_license_display = '<span style="color: #dba617; font-weight: 600;">⚠ ' . ucfirst( $agentic_tier ) . ' (Expiring)</span>';
			break;
		case 'expired':
		case 'license_expired':
		case 'revoked':
		case 'license_revoked':
		case 'invalid':
		case 'invalid_key':
			$agentic_license_display = '<span style="color: #d63638;">✕ ' . ucfirst( str_replace( '_', ' ', $agentic_license_status ) ) . '</span> <a href="https://agentic-plugin.com/pricing/" target="_blank">Renew</a>';
			break;
		default:
			$agentic_license_display = 'Free <a href="https://agentic-plugin.com/pricing/" target="_blank" style="font-size: 12px;">(Upgrade)</a>';
			break;
	}
} elseif ( ! empty( $agentic_plugin_license_key ) ) {
	// Fallback: direct API call if License_Client not loaded.
	$agentic_response = wp_remote_get(
		'https://agentic-plugin.com/wp-json/agentic-marketplace/v1/licenses/status',
		array(
			'timeout' => 5,
			'headers' => array(
				'Authorization' => 'Bearer ' . $agentic_plugin_license_key,
			),
		)
	);

	if ( ! is_wp_error( $agentic_response ) && 200 === wp_remote_retrieve_response_code( $agentic_response ) ) {
		$agentic_data = json_decode( wp_remote_retrieve_body( $agentic_response ), true );
		if ( isset( $agentic_data['success'] ) && $agentic_data['success'] && isset( $agentic_data['license']['status'] ) ) {
			$agentic_license_status = $agentic_data['license']['status'];
			$agentic_tier           = $agentic_data['license']['tier'] ?? 'pro';

			if ( 'active' === $agentic_license_status ) {
				$agentic_license_display = '<span style="color: #00a32a; font-weight: 600;">● ' . ucfirst( $agentic_tier ) . '</span>';
			} elseif ( 'grace_period' === $agentic_license_status ) {
				$agentic_license_display = '<span style="color: #dba617; font-weight: 600;">⚠ ' . ucfirst( $agentic_tier ) . ' (Expiring)</span>';
			} else {
				$agentic_license_display = '<span style="color: #d63638;">✕ Expired</span> <a href="https://agentic-plugin.com/pricing/" target="_blank">Renew</a>';
			}
		}
	}
} else {
	$agentic_license_display = 'Free <a href="https://agentic-plugin.com/pricing/" target="_blank" style="font-size: 12px;">(Upgrade)</a>';
}
?>
<div class="wrap agentic-admin">
	<h1>
		<span class="dashicons dashicons-superhero" style="font-size: 30px; margin-right: 10px;"></span>
		Agent Builder
	</h1>
	<p style="margin-bottom: 20px;">
		Need help? Visit our <a href="https://agentic-plugin.com/support/" target="_blank">Support Center</a> | <a href="https://agentic-plugin.com/documentation/" target="_blank">Documentation</a>
	</p>

	<div class="agentic-dashboard-grid">
		<div class="agentic-card">
			<h2>Status</h2>
			<table class="widefat">
				<tr>
					<td><strong>License</strong></td>
					<td><?php echo wp_kses_post( $agentic_license_display ); ?></td>
				</tr>
				<tr>
					<td><strong>AI Provider</strong></td>
					<td><?php echo $agentic_is_configured ? esc_html( strtoupper( $agentic_provider ) ) . ' (Connected)' : '<a href="' . esc_url( admin_url( 'admin.php?page=agentic-settings' ) ) . '">Configure now</a>'; ?></td>
				</tr>
				<tr>
					<td><strong>Model</strong></td>
					<td>
						<?php
						if ( $agentic_is_configured ) {
							$agentic_mode = ucfirst( get_option( 'agentic_agent_mode', 'supervised' ) );
							echo esc_html( $agentic_model ) . ' <span style="color: #646970;">(' . esc_html( $agentic_mode ) . ')</span>';
						} else {
							echo 'None';
						}
						?>
					</td>
				</tr>
			</table>
		</div>

		<div class="agentic-card">
			<h2>Weekly Statistics</h2>
			<table class="widefat">
				<tr>
					<td><strong>Total Actions</strong></td>
					<td><?php echo esc_html( number_format( (int) ( $agentic_stats['total_actions'] ?? 0 ) ) ); ?></td>
				</tr>
				<tr>
					<td><strong>Tokens Used</strong></td>
					<td><?php echo esc_html( number_format( (int) ( $agentic_stats['total_tokens'] ?? 0 ) ) ); ?></td>
				</tr>
				<tr>
					<td><strong>Estimated Cost</strong></td>
					<td>$<?php echo esc_html( number_format( (float) ( $agentic_stats['total_cost'] ?? 0 ), 4 ) ); ?></td>
				</tr>
				<tr>
					<td><strong>Active Agents</strong></td>
					<td><?php echo esc_html( $agentic_active_count ); ?></td>
				</tr>
			</table>
		</div>

		<div class="agentic-card">
			<h2>Marketplace</h2>
			<?php
			// Fetch marketplace stats — derive from /agents endpoint for reliability.
			$agentic_marketplace_stats = array(
				'latest_agent'  => array(
					'name' => 'N/A',
					'url'  => '',
				),
				'popular_agent' => array(
					'name' => 'N/A',
					'url'  => '',
				),
				'user_sales'    => 0,
				'user_revenue'  => 0.00,
			);

			// Get developer API key for user-specific stats (sales/revenue).
			$agentic_dev_api_key = get_option( 'agentic_developer_api_key', '' );

			// Fetch user sales/revenue from stats endpoint if dev key exists.
			if ( ! empty( $agentic_dev_api_key ) ) {
				$agentic_stats_response = wp_remote_get(
					'https://agentic-plugin.com/wp-json/agentic-marketplace/v1/stats/dashboard',
					array(
						'timeout' => 5,
						'headers' => array(
							'Authorization' => 'Bearer ' . $agentic_dev_api_key,
						),
					)
				);

				if ( ! is_wp_error( $agentic_stats_response ) && 200 === wp_remote_retrieve_response_code( $agentic_stats_response ) ) {
					$agentic_stats_data = json_decode( wp_remote_retrieve_body( $agentic_stats_response ), true );
					if ( isset( $agentic_stats_data['success'] ) && $agentic_stats_data['success'] && isset( $agentic_stats_data['stats'] ) ) {
						$agentic_marketplace_stats['user_sales']   = (int) ( $agentic_stats_data['stats']['user_sales'] ?? 0 );
						$agentic_marketplace_stats['user_revenue'] = (float) ( $agentic_stats_data['stats']['user_revenue'] ?? 0 );
					}
				}
			}

			// Fetch agents list to derive latest and most popular — more reliable than stats endpoint.
			$agentic_agents_response = wp_remote_get(
				'https://agentic-plugin.com/wp-json/agentic-marketplace/v1/agents',
				array( 'timeout' => 5 )
			);

			if ( ! is_wp_error( $agentic_agents_response ) && 200 === wp_remote_retrieve_response_code( $agentic_agents_response ) ) {
				$agentic_agents_data = json_decode( wp_remote_retrieve_body( $agentic_agents_response ), true );
				$agentic_agents_list = $agentic_agents_data['agents'] ?? array();

				if ( ! empty( $agentic_agents_list ) ) {
					// Latest agent = most recently updated.
					$agentic_latest = $agentic_agents_list[0];
					foreach ( $agentic_agents_list as $agentic_agent ) {
						if ( ( $agentic_agent['last_updated'] ?? '' ) > ( $agentic_latest['last_updated'] ?? '' ) ) {
							$agentic_latest = $agentic_agent;
						}
					}
					$agentic_marketplace_stats['latest_agent'] = array(
						'name' => $agentic_latest['name'] ?? 'N/A',
						'url'  => $agentic_latest['url'] ?? '',
					);

					// Popular agent = most downloads, then highest rating as tiebreaker.
					$agentic_popular = $agentic_agents_list[0];
					foreach ( $agentic_agents_list as $agentic_agent ) {
						$agentic_agent_downloads   = (int) ( $agentic_agent['downloads'] ?? 0 );
						$agentic_popular_downloads = (int) ( $agentic_popular['downloads'] ?? 0 );
						if ( $agentic_agent_downloads > $agentic_popular_downloads
							|| ( $agentic_agent_downloads === $agentic_popular_downloads
								&& (float) ( $agentic_agent['rating'] ?? 0 ) > (float) ( $agentic_popular['rating'] ?? 0 ) )
						) {
							$agentic_popular = $agentic_agent;
						}
					}
					$agentic_marketplace_stats['popular_agent'] = array(
						'name' => $agentic_popular['name'] ?? 'N/A',
						'url'  => $agentic_popular['url'] ?? '',
					);
				}
			}
			?>
			<table class="widefat">
				<tr>
					<td><strong>New Agent</strong></td>
					<td>
						<?php
						if ( ! empty( $agentic_marketplace_stats['latest_agent']['url'] ) ) {
							echo '<a href="' . esc_url( $agentic_marketplace_stats['latest_agent']['url'] ) . '" target="_blank">' . esc_html( $agentic_marketplace_stats['latest_agent']['name'] ) . '</a>';
						} else {
							echo esc_html( $agentic_marketplace_stats['latest_agent']['name'] );
						}
						?>
					</td>
				</tr>
				<tr>
					<td><strong>Popular Agent</strong></td>
					<td>
						<?php
						if ( ! empty( $agentic_marketplace_stats['popular_agent']['url'] ) ) {
							echo '<a href="' . esc_url( $agentic_marketplace_stats['popular_agent']['url'] ) . '" target="_blank">' . esc_html( $agentic_marketplace_stats['popular_agent']['name'] ) . '</a>';
						} else {
							echo esc_html( $agentic_marketplace_stats['popular_agent']['name'] );
						}
						?>
					</td>
				</tr>
				<tr>
					<td><strong>Agent Sales</strong></td>
					<td><?php echo esc_html( number_format( (int) $agentic_marketplace_stats['user_sales'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong>Total Revenue</strong></td>
					<td>$<?php echo esc_html( number_format( (float) $agentic_marketplace_stats['user_revenue'], 2 ) ); ?></td>
				</tr>
			</table>
			<p style="margin-top: 15px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-revenue' ) ); ?>" class="button button-primary">
					<?php echo ( $agentic_marketplace_stats['user_revenue'] > 0 ) ? 'View Revenue' : 'Earn Revenue'; ?>
				</a>
			</p>
		</div>

		<div class="agentic-card">
			<h2>Quick Actions</h2>
			<?php if ( ! $agentic_is_configured ) : ?>
				<p>
					<span class="dashicons dashicons-no-alt" style="color: #b91c1c; vertical-align: -2px;" title="Set up an AI provider and API key to enable the chatbot."></span>
					Chatbot offline
					<span class="dashicons dashicons-editor-help" title="Go to Settings to configure your AI provider and API key." style="vertical-align: -2px; margin-left: 6px;"></span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-settings' ) ); ?>" style="margin-left: 6px;">Configure now</a>
				</p>
			<?php endif; ?>
			<p>
				<?php if ( $agentic_is_configured ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-chat' ) ); ?>" class="button button-primary">
						Open Chat Interface
					</a>
				<?php endif; ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-approvals' ) ); ?>" class="button">
					View Approval Queue
				</a>
			</p>
		</div>

		<div class="agentic-card agentic-card-wide">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
				<h2 style="margin: 0;">Recent Activity</h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-audit' ) ); ?>" class="button">
					View Audit Log
				</a>
			</div>
			<?php
			$agentic_recent = $agentic_audit->get_recent( 10 );
			if ( empty( $agentic_recent ) ) :
				?>
				<p>No agent activity recorded yet.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Time</th>
							<th>Agent</th>
							<th>Action</th>
							<th>Target</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $agentic_recent as $agentic_entry ) : ?>
						<tr>
							<td>
								<?php
								$agentic_timestamp = strtotime( $agentic_entry['created_at'] );
								echo esc_html( wp_date( 'M j, Y g:i a', $agentic_timestamp ) );
								?>
							</td>
							<td><?php echo esc_html( $agentic_entry['agent_id'] ); ?></td>
							<td><?php echo esc_html( $agentic_entry['action'] ); ?></td>
							<td><?php echo esc_html( $agentic_entry['target_type'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
.agentic-dashboard-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.agentic-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
}

.agentic-card h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}

.agentic-card-wide {
	grid-column: 1 / -1;
}

.agentic-card .button {
	margin-bottom: 5px;
}
</style>
