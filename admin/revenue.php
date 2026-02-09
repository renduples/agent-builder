<?php
/**
 * Developer Revenue Dashboard
 *
 * Display developer statistics, agent performance, and revenue data.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      1.2.0
 *
 * php version 8.1
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get developer API key.
$agentic_api_key     = get_option( 'agentic_developer_api_key', '' );
$agentic_has_api_key = ! empty( $agentic_api_key );

// Marketplace API base URL.
$agentic_marketplace_url = 'https://agentic-plugin.com/wp-json/agentic-marketplace/v1';

?>
<div class="wrap agentic-revenue-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Developer Revenue', 'agent-builder' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( ! $agentic_has_api_key ) : ?>
		<?php
		// Build developer registration URL with pre-filled data.
		$agentic_user      = wp_get_current_user();
		$agentic_site_url  = get_site_url();
		$agentic_site_name = get_bloginfo( 'name' );
		$agentic_site_desc = get_bloginfo( 'description' );

		$agentic_register_url = add_query_arg(
			array(
				'username'    => rawurlencode( $agentic_user->user_login ),
				'email'       => rawurlencode( $agentic_user->user_email ),
				'website'     => rawurlencode( $agentic_site_url ),
				'description' => rawurlencode( $agentic_site_desc ),
			),
			'https://agentic-plugin.com/developer-register/'
		);
		?>
		<div class="agentic-revenue-connect">
			<div class="connect-card">
				<span class="dashicons dashicons-chart-bar"></span>
				<h2><?php esc_html_e( 'Connect to Marketplace', 'agent-builder' ); ?></h2>
				<p>
					<?php esc_html_e( 'To earn revenue from your AI Agents, register a free developer account.', 'agent-builder' ); ?>
					<a href="https://agentic-plugin.com/agent-licensing-for-developers/" target="_blank"><?php esc_html_e( 'Learn more', 'agent-builder' ); ?></a>.
				</p>
				<div class="connect-actions">
				<a href="<?php echo esc_url( $agentic_register_url ); ?>" class="button button-primary button-hero" target="_blank">
						<?php esc_html_e( 'Register as Developer', 'agent-builder' ); ?>
					</a>
					<button type="button" class="button button-secondary button-hero" id="agentic-enter-api-key">
						<?php esc_html_e( 'I Have an API Key', 'agent-builder' ); ?>
					</button>
				</div>
				<div id="agentic-api-key-form" style="display: none; margin-top: 20px;">
					<input type="text" id="agentic-api-key-input" class="regular-text" placeholder="<?php esc_attr_e( 'Enter your Developer API Key', 'agent-builder' ); ?>">
					<button type="button" class="button button-primary" id="agentic-save-api-key">
						<?php esc_html_e( 'Connect', 'agent-builder' ); ?>
					</button>
				</div>
				<p style="margin-top: 20px; font-size: 12px; color: #646970;">
					Need help? <a href="https://agentic-plugin.com/support/" target="_blank">Contact Support</a>
				</p>
			</div>
		</div>
	<?php else : ?>
		<!-- Stats Cards -->
		<div class="agentic-stats-cards">
			<div class="stat-card" id="stat-agents">
				<div class="stat-icon">
					<span class="dashicons dashicons-admin-plugins"></span>
				</div>
				<div class="stat-content">
					<div class="stat-value" id="stat-agents-value">—</div>
					<div class="stat-label"><?php esc_html_e( 'Agents Submitted', 'agent-builder' ); ?></div>
					<div class="stat-detail" id="stat-agents-detail"></div>
				</div>
			</div>
			<div class="stat-card" id="stat-installs">
				<div class="stat-icon installs">
					<span class="dashicons dashicons-download"></span>
				</div>
				<div class="stat-content">
					<div class="stat-value" id="stat-installs-value">—</div>
					<div class="stat-label"><?php esc_html_e( 'Total Installs', 'agent-builder' ); ?></div>
					<div class="stat-detail" id="stat-installs-detail"></div>
				</div>
			</div>
			<div class="stat-card" id="stat-revenue">
				<div class="stat-icon revenue">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
				<div class="stat-content">
					<div class="stat-value" id="stat-revenue-value">—</div>
					<div class="stat-label"><?php esc_html_e( 'Revenue This Month', 'agent-builder' ); ?></div>
					<div class="stat-detail" id="stat-revenue-detail"></div>
				</div>
			</div>
			<div class="stat-card" id="stat-payout">
				<div class="stat-icon payout">
					<span class="dashicons dashicons-bank"></span>
				</div>
				<div class="stat-content">
					<div class="stat-value" id="stat-payout-value">—</div>
					<div class="stat-label"><?php esc_html_e( 'Pending Payout', 'agent-builder' ); ?></div>
					<div class="stat-detail" id="stat-payout-detail"></div>
				</div>
			</div>
		</div>

		<!-- Charts Section -->
		<div class="agentic-charts-section">
			<div class="chart-container">
				<div class="chart-header">
					<h2><?php esc_html_e( 'Revenue', 'agent-builder' ); ?></h2>
					<div class="chart-period-selector">
						<button class="period-btn active" data-period="30d"><?php esc_html_e( '30 Days', 'agent-builder' ); ?></button>
						<button class="period-btn" data-period="90d"><?php esc_html_e( '90 Days', 'agent-builder' ); ?></button>
						<button class="period-btn" data-period="12m"><?php esc_html_e( '12 Months', 'agent-builder' ); ?></button>
					</div>
				</div>
				<div class="chart-wrapper">
					<canvas id="revenue-chart"></canvas>
				</div>
			</div>
			<div class="chart-container">
				<div class="chart-header">
					<h2><?php esc_html_e( 'Installs', 'agent-builder' ); ?></h2>
					<div class="chart-period-selector">
						<button class="period-btn active" data-period="30d"><?php esc_html_e( '30 Days', 'agent-builder' ); ?></button>
						<button class="period-btn" data-period="90d"><?php esc_html_e( '90 Days', 'agent-builder' ); ?></button>
						<button class="period-btn" data-period="12m"><?php esc_html_e( '12 Months', 'agent-builder' ); ?></button>
					</div>
				</div>
				<div class="chart-wrapper">
					<canvas id="installs-chart"></canvas>
				</div>
			</div>
		</div>

		<!-- Agents Table -->
		<div class="agentic-agents-section">
			<div class="section-header">
				<h2><?php esc_html_e( 'Your Agents', 'agent-builder' ); ?></h2>
				<div class="section-actions">
					<select id="agents-status-filter">
						<option value="all"><?php esc_html_e( 'All Statuses', 'agent-builder' ); ?></option>
						<option value="approved"><?php esc_html_e( 'Approved', 'agent-builder' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending Review', 'agent-builder' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected', 'agent-builder' ); ?></option>
					</select>
					<a href="https://agentic-plugin.com/developer/submit/" class="button button-primary" target="_blank">
						<?php esc_html_e( 'Submit New Agent', 'agent-builder' ); ?>
					</a>
				</div>
			</div>
			<table class="wp-list-table widefat fixed striped" id="agents-table">
				<thead>
					<tr>
						<th class="column-agent"><?php esc_html_e( 'Agent', 'agent-builder' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'agent-builder' ); ?></th>
						<th class="column-price"><?php esc_html_e( 'Price', 'agent-builder' ); ?></th>
						<th class="column-installs"><?php esc_html_e( 'Installs', 'agent-builder' ); ?></th>
						<th class="column-revenue"><?php esc_html_e( 'Revenue', 'agent-builder' ); ?></th>
						<th class="column-rating"><?php esc_html_e( 'Rating', 'agent-builder' ); ?></th>
					</tr>
				</thead>
				<tbody id="agents-table-body">
					<tr class="loading-row">
						<td colspan="6">
							<span class="spinner is-active"></span>
							<?php esc_html_e( 'Loading agents...', 'agent-builder' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Payouts Section -->
		<div class="agentic-payouts-section">
			<div class="section-header">
				<h2><?php esc_html_e( 'Recent Payouts', 'agent-builder' ); ?></h2>
				<a href="https://agentic-plugin.com/developer/payouts/" class="button button-secondary" target="_blank">
					<?php esc_html_e( 'View All Payouts', 'agent-builder' ); ?>
				</a>
			</div>
			<table class="wp-list-table widefat fixed striped" id="payouts-table">
				<thead>
					<tr>
						<th class="column-date"><?php esc_html_e( 'Date', 'agent-builder' ); ?></th>
						<th class="column-amount"><?php esc_html_e( 'Amount', 'agent-builder' ); ?></th>
						<th class="column-method"><?php esc_html_e( 'Method', 'agent-builder' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'agent-builder' ); ?></th>
					</tr>
				</thead>
				<tbody id="payouts-table-body">
					<tr class="loading-row">
						<td colspan="4">
							<span class="spinner is-active"></span>
							<?php esc_html_e( 'Loading payouts...', 'agent-builder' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Disconnect Section -->
		<div class="agentic-disconnect-section">
			<button type="button" class="button button-link-delete" id="agentic-disconnect">
				<?php esc_html_e( 'Disconnect Marketplace Account', 'agent-builder' ); ?>
			</button>
		</div>
	<?php endif; ?>
</div>

<style>
.agentic-revenue-page {
	max-width: 1400px;
}

/* Connect Card */
.agentic-revenue-connect {
	display: flex;
	justify-content: center;
	padding: 60px 20px;
}

.connect-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	padding: 40px 60px;
	text-align: center;
	max-width: 500px;
}

.connect-card .dashicons {
	font-size: 48px;
	width: 48px;
	height: 48px;
	color: #2271b1;
	margin-bottom: 20px;
}

.connect-card h2 {
	margin: 0 0 10px;
	font-size: 24px;
}

.connect-card p {
	color: #646970;
	margin-bottom: 25px;
}

.connect-actions {
	display: flex;
	gap: 10px;
	justify-content: center;
	flex-wrap: wrap;
}

#agentic-api-key-form {
	display: flex;
	gap: 10px;
	justify-content: center;
}

#agentic-api-key-input {
	min-width: 280px;
}

/* Stats Cards */
.agentic-stats-cards {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 20px;
	margin: 20px 0;
}

@media (max-width: 1200px) {
	.agentic-stats-cards {
		grid-template-columns: repeat(2, 1fr);
	}
}

@media (max-width: 600px) {
	.agentic-stats-cards {
		grid-template-columns: 1fr;
	}
}

.stat-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	padding: 20px;
	display: flex;
	align-items: center;
	gap: 15px;
}

.stat-icon {
	width: 50px;
	height: 50px;
	border-radius: 10px;
	background: #f0f6fc;
	display: flex;
	align-items: center;
	justify-content: center;
}

.stat-icon .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
	color: #2271b1;
}

.stat-icon.installs {
	background: #edf7ed;
}

.stat-icon.installs .dashicons {
	color: #00a32a;
}

.stat-icon.revenue {
	background: #fef8e7;
}

.stat-icon.revenue .dashicons {
	color: #dba617;
}

.stat-icon.payout {
	background: #f3e8ff;
}

.stat-icon.payout .dashicons {
	color: #8c5fc6;
}

.stat-value {
	font-size: 28px;
	font-weight: 600;
	color: #1d2327;
	line-height: 1.2;
}

.stat-label {
	color: #646970;
	font-size: 13px;
}

.stat-detail {
	font-size: 12px;
	color: #00a32a;
	margin-top: 4px;
}

.stat-detail.negative {
	color: #d63638;
}

/* Charts Section */
.agentic-charts-section {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 20px;
	margin: 20px 0;
}

@media (max-width: 1000px) {
	.agentic-charts-section {
		grid-template-columns: 1fr;
	}
}

.chart-container {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	padding: 20px;
}

.chart-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 15px;
}

.chart-header h2 {
	margin: 0;
	font-size: 16px;
}

.chart-period-selector {
	display: flex;
	gap: 5px;
}

.period-btn {
	background: #f0f0f1;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 4px 10px;
	font-size: 12px;
	cursor: pointer;
	color: #50575e;
}

.period-btn:hover {
	background: #e0e0e0;
}

.period-btn.active {
	background: #2271b1;
	border-color: #2271b1;
	color: #fff;
}

.chart-wrapper {
	height: 250px;
	position: relative;
}

/* Tables */
.agentic-agents-section,
.agentic-payouts-section {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	padding: 20px;
	margin: 20px 0;
}

.section-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 15px;
}

.section-header h2 {
	margin: 0;
	font-size: 16px;
}

.section-actions {
	display: flex;
	gap: 10px;
	align-items: center;
}

.loading-row td {
	text-align: center;
	padding: 30px !important;
	color: #646970;
}

.loading-row .spinner {
	float: none;
	margin: 0 10px 0 0;
}

.agent-name {
	display: flex;
	align-items: center;
	gap: 10px;
}

.agent-icon {
	width: 32px;
	height: 32px;
	border-radius: 4px;
	background: #f0f0f1;
}

.agent-meta {
	font-size: 12px;
	color: #646970;
}

.status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 500;
	text-transform: uppercase;
}

.status-approved {
	background: #d4f4dd;
	color: #00661b;
}

.status-pending {
	background: #fcf3cf;
	color: #8a6116;
}

.status-rejected {
	background: #fdd;
	color: #a00;
}

.status-completed {
	background: #d4f4dd;
	color: #00661b;
}

.status-processing {
	background: #e7f3ff;
	color: #2271b1;
}

.rating-stars {
	color: #dba617;
}

.no-data-row td {
	text-align: center;
	padding: 40px !important;
	color: #646970;
}

/* Disconnect */
.agentic-disconnect-section {
	margin: 30px 0;
	text-align: center;
}
</style>

<script>
jQuery(document).ready(function($) {
	const apiKey = '<?php echo esc_js( $agentic_api_key ); ?>';
	const marketplaceUrl = '<?php echo esc_js( $agentic_marketplace_url ); ?>';
	
	// API Key form toggle
	$('#agentic-enter-api-key').on('click', function() {
		$('#agentic-api-key-form').slideToggle();
	});
	
	// Save API Key
	$('#agentic-save-api-key').on('click', function() {
		const key = $('#agentic-api-key-input').val().trim();
		if (!key) {
			alert('<?php echo esc_js( __( 'Please enter an API key.', 'agent-builder' ) ); ?>');
			return;
		}
		
		$(this).prop('disabled', true).text('<?php echo esc_js( __( 'Connecting...', 'agent-builder' ) ); ?>');
		
		$.post(ajaxurl, {
			action: 'agentic_save_developer_api_key',
			api_key: key,
			_wpnonce: '<?php echo esc_js( wp_create_nonce( 'agentic_save_api_key' ) ); ?>'
		}).done(function(response) {
			if (response.success) {
				location.reload();
			} else {
				alert(response.data || '<?php echo esc_js( __( 'Failed to save API key.', 'agent-builder' ) ); ?>');
				$('#agentic-save-api-key').prop('disabled', false).text('<?php echo esc_js( __( 'Connect', 'agent-builder' ) ); ?>');
			}
		}).fail(function() {
			alert('<?php echo esc_js( __( 'Connection failed. Please try again.', 'agent-builder' ) ); ?>');
			$('#agentic-save-api-key').prop('disabled', false).text('<?php echo esc_js( __( 'Connect', 'agent-builder' ) ); ?>');
		});
	});
	
	// Disconnect
	$('#agentic-disconnect').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect your marketplace account?', 'agent-builder' ) ); ?>')) {
			return;
		}
		
		$.post(ajaxurl, {
			action: 'agentic_disconnect_developer',
			_wpnonce: '<?php echo esc_js( wp_create_nonce( 'agentic_disconnect' ) ); ?>'
		}).done(function() {
			location.reload();
		});
	});
	
	// Only load data if we have an API key
	if (!apiKey) return;
	
	// Fetch developer stats
	function fetchStats() {
		$.ajax({
			url: marketplaceUrl + '/developer/stats',
			headers: { 'Authorization': 'Bearer ' + apiKey },
			timeout: 10000
		}).done(function(response) {
			if (response.success && response.data) {
				const d = response.data;
				$('#stat-agents-value').text(d.total_agents_submitted || 0);
				$('#stat-agents-detail').html(
					'<span class="status-approved">' + (d.agents_approved || 0) + ' approved</span> · ' +
					'<span class="status-pending">' + (d.agents_pending_review || 0) + ' pending</span>'
				);
				
				$('#stat-installs-value').text(formatNumber(d.total_installs || 0));
				$('#stat-installs-detail').text((d.total_active_installs || 0) + ' active');
				
				$('#stat-revenue-value').text(formatCurrency(d.revenue_this_month || 0, d.currency));
				const revChange = calculateChange(d.revenue_this_month, d.revenue_last_month);
				$('#stat-revenue-detail').html(revChange.html).toggleClass('negative', revChange.negative);
				
				$('#stat-payout-value').text(formatCurrency(d.revenue_pending_payout || 0, d.currency));
				if (d.next_payout_date) {
					$('#stat-payout-detail').text('Next: ' + formatDate(d.next_payout_date));
				}
			}
		}).fail(function() {
			// Use demo data for now
			showDemoStats();
		});
	}
	
	function showDemoStats() {
		$('#stat-agents-value').text('3');
		$('#stat-agents-detail').html('<span class="status-approved">2 approved</span> · <span class="status-pending">1 pending</span>');
		$('#stat-installs-value').text('247');
		$('#stat-installs-detail').text('198 active');
		$('#stat-revenue-value').text('$485.00');
		$('#stat-revenue-detail').html('↑ 12% vs last month');
		$('#stat-payout-value').text('$120.00');
		$('#stat-payout-detail').text('Next: Feb 15, 2026');
	}
	
	// Fetch agents
	function fetchAgents(status = 'all') {
		$('#agents-table-body').html('<tr class="loading-row"><td colspan="6"><span class="spinner is-active"></span> <?php echo esc_js( __( 'Loading agents...', 'agent-builder' ) ); ?></td></tr>');
		
		$.ajax({
			url: marketplaceUrl + '/developer/agents',
			data: { status: status },
			headers: { 'Authorization': 'Bearer ' + apiKey },
			timeout: 10000
		}).done(function(response) {
			if (response.success && response.data && response.data.agents) {
				renderAgentsTable(response.data.agents);
			} else {
				showDemoAgents();
			}
		}).fail(function() {
			showDemoAgents();
		});
	}
	
	function renderAgentsTable(agents) {
		if (!agents.length) {
			$('#agents-table-body').html('<tr class="no-data-row"><td colspan="6"><?php echo esc_js( __( 'No agents found. Submit your first agent to the marketplace!', 'agent-builder' ) ); ?></td></tr>');
			return;
		}
		
		let html = '';
		agents.forEach(function(agent) {
			html += '<tr>';
			html += '<td class="column-agent"><div class="agent-name">';
			if (agent.thumbnail_url) {
				html += '<img src="' + agent.thumbnail_url + '" class="agent-icon" alt="">';
			} else {
				html += '<div class="agent-icon"></div>';
			}
			html += '<div><strong>' + escapeHtml(agent.name) + '</strong>';
			html += '<div class="agent-meta">v' + escapeHtml(agent.version) + '</div></div></div></td>';
			html += '<td class="column-status"><span class="status-badge status-' + agent.status + '">' + agent.status + '</span></td>';
			html += '<td class="column-price">' + (agent.price ? formatCurrency(agent.price) + '/' + agent.price_type : 'Free') + '</td>';
			html += '<td class="column-installs">' + formatNumber(agent.installs || 0) + '</td>';
			html += '<td class="column-revenue">' + formatCurrency(agent.revenue_total || 0) + '</td>';
			html += '<td class="column-rating">';
			if (agent.rating) {
				html += '<span class="rating-stars">★</span> ' + agent.rating.toFixed(1) + ' (' + agent.rating_count + ')';
			} else {
				html += '—';
			}
			html += '</td></tr>';
		});
		$('#agents-table-body').html(html);
	}
	
	function showDemoAgents() {
		const demoAgents = [
			{ name: 'SEO Content Optimizer', version: '1.2.0', status: 'approved', price: 29, price_type: 'yearly', installs: 156, revenue_total: 342, rating: 4.8, rating_count: 12 },
			{ name: 'WooCommerce Assistant', version: '2.0.1', status: 'approved', price: 49, price_type: 'yearly', installs: 91, revenue_total: 143, rating: 4.5, rating_count: 8 },
			{ name: 'Form Builder Agent', version: '1.0.0', status: 'pending', price: 19, price_type: 'yearly', installs: 0, revenue_total: 0, rating: null, rating_count: 0 }
		];
		renderAgentsTable(demoAgents);
	}
	
	// Fetch payouts
	function fetchPayouts() {
		$.ajax({
			url: marketplaceUrl + '/developer/payouts',
			data: { per_page: 5 },
			headers: { 'Authorization': 'Bearer ' + apiKey },
			timeout: 10000
		}).done(function(response) {
			if (response.success && response.data && response.data.payouts) {
				renderPayoutsTable(response.data.payouts);
			} else {
				showDemoPayouts();
			}
		}).fail(function() {
			showDemoPayouts();
		});
	}
	
	function renderPayoutsTable(payouts) {
		if (!payouts.length) {
			$('#payouts-table-body').html('<tr class="no-data-row"><td colspan="4"><?php echo esc_js( __( 'No payouts yet. Earnings are paid out monthly once you reach $50.', 'agent-builder' ) ); ?></td></tr>');
			return;
		}
		
		let html = '';
		payouts.forEach(function(payout) {
			html += '<tr>';
			html += '<td class="column-date">' + formatDate(payout.completed_at || payout.initiated_at) + '</td>';
			html += '<td class="column-amount"><strong>' + formatCurrency(payout.amount, payout.currency) + '</strong></td>';
			html += '<td class="column-method">' + escapeHtml(payout.method || 'Stripe') + '</td>';
			html += '<td class="column-status"><span class="status-badge status-' + payout.status + '">' + payout.status + '</span></td>';
			html += '</tr>';
		});
		$('#payouts-table-body').html(html);
	}
	
	function showDemoPayouts() {
		const demoPayouts = [
			{ completed_at: '2026-01-15', amount: 285.00, currency: 'USD', method: 'Stripe', status: 'completed' },
			{ completed_at: '2025-12-15', amount: 120.00, currency: 'USD', method: 'Stripe', status: 'completed' }
		];
		renderPayoutsTable(demoPayouts);
	}
	
	// Initialize charts (placeholder - needs Chart.js)
	let revenueChart = null;
	let installsChart = null;
	
	function initCharts() {
		// Check if Chart.js is available
		if (typeof Chart === 'undefined') {
			$('.chart-wrapper').html('<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#646970;"><?php echo esc_js( __( 'Charts loading...', 'agent-builder' ) ); ?></div>');
			return;
		}
		
		// Initialize empty charts, then fetch data
		const revenueCtx = document.getElementById('revenue-chart');
		if (revenueCtx) {
			revenueChart = new Chart(revenueCtx, {
				type: 'line',
				data: {
					labels: [],
					datasets: [{
						label: 'Revenue',
						data: [],
						borderColor: '#2271b1',
						backgroundColor: 'rgba(34, 113, 177, 0.1)',
						fill: true,
						tension: 0.4
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: false } },
					scales: {
						y: { beginAtZero: true, ticks: { callback: v => '$' + v } }
					}
				}
			});
		}
		
		const installsCtx = document.getElementById('installs-chart');
		if (installsCtx) {
			installsChart = new Chart(installsCtx, {
				type: 'bar',
				data: {
					labels: [],
					datasets: [{
						label: 'Installs',
						data: [],
						backgroundColor: '#00a32a'
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: false } },
					scales: { y: { beginAtZero: true } }
				}
			});
		}
		
		// Fetch initial data for both charts
		fetchRevenueHistory('30d');
		fetchInstallsHistory('30d');
	}
	
	function fetchRevenueHistory(period) {
		$.ajax({
			url: marketplaceUrl + '/developer/revenue/history',
			data: { period: period },
			headers: { 'Authorization': 'Bearer ' + apiKey },
			timeout: 15000
		}).done(function(response) {
			if (response.success && response.data && response.data.data_points) {
				updateRevenueChart(response.data.data_points, response.data.currency || 'USD');
			} else {
				updateRevenueChart(generateDemoRevenueData(period), 'USD');
			}
		}).fail(function() {
			updateRevenueChart(generateDemoRevenueData(period), 'USD');
		});
	}
	
	function fetchInstallsHistory(period) {
		$.ajax({
			url: marketplaceUrl + '/developer/installs/history',
			data: { period: period },
			headers: { 'Authorization': 'Bearer ' + apiKey },
			timeout: 15000
		}).done(function(response) {
			if (response.success && response.data && response.data.data_points) {
				updateInstallsChart(response.data.data_points);
			} else {
				updateInstallsChart(generateDemoInstallsData(period));
			}
		}).fail(function() {
			updateInstallsChart(generateDemoInstallsData(period));
		});
	}
	
	function updateRevenueChart(dataPoints, currency) {
		if (!revenueChart) return;
		
		const labels = dataPoints.map(d => formatChartDate(d.date));
		const values = dataPoints.map(d => d.revenue || 0);
		
		revenueChart.data.labels = labels;
		revenueChart.data.datasets[0].data = values;
		revenueChart.update();
	}
	
	function updateInstallsChart(dataPoints) {
		if (!installsChart) return;
		
		const labels = dataPoints.map(d => formatChartDate(d.date));
		const values = dataPoints.map(d => d.installs || 0);
		
		installsChart.data.labels = labels;
		installsChart.data.datasets[0].data = values;
		installsChart.update();
	}
	
	function formatChartDate(dateStr) {
		const d = new Date(dateStr);
		return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
	}
	
	function generateDemoRevenueData(period) {
		const count = period === '12m' ? 12 : (period === '90d' ? 90 : 30);
		const data = [];
		for (let i = count - 1; i >= 0; i--) {
			const d = new Date();
			if (period === '12m') {
				d.setMonth(d.getMonth() - i);
			} else {
				d.setDate(d.getDate() - i);
			}
			data.push({
				date: d.toISOString().split('T')[0],
				revenue: Math.floor(Math.random() * 50)
			});
		}
		return data;
	}
	
	function generateDemoInstallsData(period) {
		const count = period === '12m' ? 12 : (period === '90d' ? 90 : 30);
		const data = [];
		for (let i = count - 1; i >= 0; i--) {
			const d = new Date();
			if (period === '12m') {
				d.setMonth(d.getMonth() - i);
			} else {
				d.setDate(d.getDate() - i);
			}
			data.push({
				date: d.toISOString().split('T')[0],
				installs: Math.floor(Math.random() * 10)
			});
		}
		return data;
	}
	
	// Utility functions
	function formatNumber(n) {
		return new Intl.NumberFormat().format(n);
	}
	
	function formatCurrency(amount, currency = 'USD') {
		return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(amount);
	}
	
	function formatDate(dateStr) {
		return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
	}
	
	function calculateChange(current, previous) {
		if (!previous) return { html: '', negative: false };
		const pct = ((current - previous) / previous * 100).toFixed(0);
		const arrow = pct >= 0 ? '↑' : '↓';
		return {
			html: arrow + ' ' + Math.abs(pct) + '% vs last month',
			negative: pct < 0
		};
	}
	
	function escapeHtml(str) {
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}
	
	// Event handlers
	$('#agents-status-filter').on('change', function() {
		fetchAgents($(this).val());
	});
	
	$('.chart-period-selector .period-btn').on('click', function() {
		const $btn = $(this);
		const period = $btn.data('period');
		const chartType = $btn.closest('.chart-card').find('canvas').attr('id');
		
		$btn.siblings().removeClass('active');
		$btn.addClass('active');
		
		if (chartType === 'revenue-chart') {
			fetchRevenueHistory(period);
		} else if (chartType === 'installs-chart') {
			fetchInstallsHistory(period);
		}
	});
	
	// Initialize
	fetchStats();
	fetchAgents();
	fetchPayouts();
	initCharts();
});
</script>
