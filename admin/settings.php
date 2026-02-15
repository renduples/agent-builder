<?php
/**
 * Agentic Settings Page
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

// Handle form submission.

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agentbuilder' ) );
}

if ( isset( $_POST['agentic_save_settings'] ) && check_admin_referer( 'agentic_settings_nonce' ) ) {
	$agentic_save_tab = sanitize_text_field( wp_unslash( $_POST['tab'] ?? 'general' ) );

	// Only save settings for the active tab to avoid overwriting other tabs with defaults.
	if ( 'general' === $agentic_save_tab ) {
		$agentic_new_provider = sanitize_text_field( wp_unslash( $_POST['agentic_llm_provider'] ?? 'openai' ) );
		$agentic_new_model    = sanitize_text_field( wp_unslash( $_POST['agentic_model'] ?? '' ) );

		// If model is empty or doesn't belong to the selected provider, use the provider's default.
		if ( empty( $agentic_new_model ) ) {
			$agentic_provider_defaults = array(
				'openai'    => 'gpt-4o',
				'anthropic' => 'claude-3-5-sonnet-20241022',
				'xai'       => 'grok-3',
				'google'    => 'gemini-2.0-flash-exp',
				'mistral'   => 'mistral-large-latest',
			);
			$agentic_new_model         = $agentic_provider_defaults[ $agentic_new_provider ] ?? 'gpt-4o';
		}

		update_option( 'agentic_llm_provider', $agentic_new_provider );
		update_option( 'agentic_llm_api_key', sanitize_text_field( wp_unslash( $_POST['agentic_llm_api_key'] ?? '' ) ) );
		update_option( 'agentic_model', $agentic_new_model );
		update_option( 'agentic_agent_mode', sanitize_text_field( wp_unslash( $_POST['agentic_agent_mode'] ?? 'supervised' ) ) );
	}

	if ( 'cache' === $agentic_save_tab ) {
		update_option( 'agentic_response_cache_enabled', isset( $_POST['agentic_response_cache_enabled'] ) );
		update_option( 'agentic_response_cache_ttl', absint( $_POST['agentic_response_cache_ttl'] ?? 3600 ) );

		// Handle cache clear.
		if ( isset( $_POST['agentic_clear_cache'] ) ) {
			$agentic_cleared = \Agentic\Response_Cache::clear_all();
			echo '<div class="notice notice-info"><p>Cleared ' . esc_html( $agentic_cleared ) . ' cached responses.</p></div>';
		}
	}

	if ( 'security' === $agentic_save_tab ) {
		update_option( 'agentic_security_enabled', isset( $_POST['agentic_security_enabled'] ) );
		update_option( 'agentic_rate_limit_authenticated', absint( $_POST['agentic_rate_limit_authenticated'] ?? 30 ) );
		update_option( 'agentic_rate_limit_anonymous', absint( $_POST['agentic_rate_limit_anonymous'] ?? 10 ) );
		update_option( 'agentic_allow_anonymous_chat', isset( $_POST['agentic_allow_anonymous_chat'] ) );
	}

	if ( 'permissions' === $agentic_save_tab ) {
		$agentic_perm_scopes = \Agentic\Agent_Permissions::get_scopes();
		$agentic_perm_values = array();
		foreach ( array_keys( $agentic_perm_scopes ) as $agentic_scope_key ) {
			$agentic_perm_values[ $agentic_scope_key ] = isset( $_POST[ 'agentic_perm_' . $agentic_scope_key ] );
		}
		$agentic_confirm_mode = sanitize_text_field( wp_unslash( $_POST['agentic_confirmation_mode'] ?? 'confirm' ) );
		\Agentic\Agent_Permissions::save_settings( $agentic_perm_values, $agentic_confirm_mode );
	}

	// Handle system check completion flag.
	if ( isset( $_POST['agentic_system_check_done'] ) ) {
		update_option( 'agentic_system_check_done', true );
	}

	echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
}

// Get current values.
$agentic_llm_provider_val = get_option( 'agentic_llm_provider', 'openai' );
$agentic_api_key_val      = get_option( 'agentic_llm_api_key', '' );
$agentic_model_val        = get_option( 'agentic_model', 'gpt-4o' );
$agentic_agent_mode_val   = get_option( 'agentic_agent_mode', 'supervised' );

// Cache settings.
$agentic_cache_enabled = get_option( 'agentic_response_cache_enabled', true );
$agentic_cache_ttl     = get_option( 'agentic_response_cache_ttl', 3600 );
$agentic_cache_stats   = \Agentic\Response_Cache::get_stats();

// Security settings.
$agentic_security_enabled = get_option( 'agentic_security_enabled', true );
$agentic_rate_limit_auth  = get_option( 'agentic_rate_limit_authenticated', 30 );
$agentic_rate_limit_anon  = get_option( 'agentic_rate_limit_anonymous', 10 );
$agentic_allow_anon_chat  = get_option( 'agentic_allow_anonymous_chat', false );
?>
<div class="wrap">
	<h1>Agentic Settings</h1>
	<p style="margin-bottom: 20px;">
		Need help? Visit our <a href="https://agentic-plugin.com/support/" target="_blank">Support Center</a> | <a href="https://agentic-plugin.com/documentation/" target="_blank">Documentation</a>
	</p>

	<?php
	$agentic_active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
	?>

	<h2 class="nav-tab-wrapper">
		<a href="?page=agentic-settings&tab=general" class="nav-tab <?php echo 'general' === $agentic_active_tab ? 'nav-tab-active' : ''; ?>">General</a>
		<a href="?page=agentic-settings&tab=license" class="nav-tab <?php echo 'license' === $agentic_active_tab ? 'nav-tab-active' : ''; ?>">License</a>
		<a href="?page=agentic-settings&tab=cache" class="nav-tab <?php echo 'cache' === $agentic_active_tab ? 'nav-tab-active' : ''; ?>">Cache</a>
		<a href="?page=agentic-settings&tab=security" class="nav-tab <?php echo 'security' === $agentic_active_tab ? 'nav-tab-active' : ''; ?>">Security</a>
		<a href="?page=agentic-settings&tab=permissions" class="nav-tab <?php echo 'permissions' === $agentic_active_tab ? 'nav-tab-active' : ''; ?>">Permissions</a>
	</h2>

	<form method="post" action="">
		<?php wp_nonce_field( 'agentic_settings_nonce' ); ?>
		<input type="hidden" name="tab" value="<?php echo esc_attr( $agentic_active_tab ); ?>" />

		<?php if ( 'license' === $agentic_active_tab ) : ?>
			<?php
			$agentic_lc = \Agentic\License_Client::get_instance();
			$agentic_ls = $agentic_lc->get_status();
			$agentic_lk = get_option( \Agentic\License_Client::OPTION_LICENSE_KEY, '' );
			?>
		<h2>License</h2>
		<p>Enter your license key to unlock premium marketplace features and automatic updates.</p>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="agentic_license_key_input">License Key</label></th>
				<td>
					<input type="text" name="agentic_license_key_input" id="agentic_license_key_input"
						value="<?php echo esc_attr( $agentic_lk ); ?>"
						class="regular-text" placeholder="AGNT-XXXX-XXXX-XXXX-XXXX"
						style="font-family: monospace;" />
					<?php if ( ! empty( $agentic_lk ) ) : ?>
						<button type="button" id="agentic-deactivate-license" class="button" style="margin-left: 8px; color: #b91c1c;">Deactivate</button>
					<?php endif; ?>
					<p class="description">Purchase a license at <a href="https://agentic-plugin.com/pricing/" target="_blank">agentic-plugin.com/pricing</a></p>
				</td>
			</tr>
			<tr>
				<th scope="row">Status</th>
				<td>
					<?php
					$agentic_status_label = $agentic_ls['status'] ?? 'free';
					$agentic_status_color = '#646970';
					$agentic_status_icon  = '○';

					switch ( $agentic_status_label ) {
						case 'active':
							$agentic_status_color = '#00a32a';
							$agentic_status_icon  = '●';
							break;
						case 'grace_period':
							$agentic_status_color = '#dba617';
							$agentic_status_icon  = '⚠';
							break;
						case 'expired':
						case 'license_expired':
						case 'revoked':
						case 'license_revoked':
						case 'invalid':
						case 'invalid_key':
							$agentic_status_color = '#d63638';
							$agentic_status_icon  = '✕';
							break;
					}
					?>
					<span style="color: <?php echo esc_attr( $agentic_status_color ); ?>; font-weight: 600; font-size: 14px;">
						<?php echo esc_html( $agentic_status_icon . ' ' . ucfirst( str_replace( '_', ' ', $agentic_status_label ) ) ); ?>
					</span>
					<?php if ( ! empty( $agentic_ls['type'] ) && 'free' !== $agentic_ls['type'] ) : ?>
						<span style="margin-left: 8px; color: #646970;">(<?php echo esc_html( ucfirst( $agentic_ls['type'] ) ); ?> License)</span>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( ! empty( $agentic_ls['expires_at'] ) ) : ?>
			<tr>
				<th scope="row">Expires</th>
				<td><?php echo esc_html( gmdate( 'F j, Y', strtotime( $agentic_ls['expires_at'] ) ) ); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ( isset( $agentic_ls['activations_used'] ) ) : ?>
			<tr>
				<th scope="row">Activations</th>
				<td><?php echo esc_html( $agentic_ls['activations_used'] . ' / ' . $agentic_ls['activations_limit'] ); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ( ! empty( $agentic_ls['validated_at'] ) ) : ?>
			<tr>
				<th scope="row">Last Validated</th>
				<td><?php echo esc_html( $agentic_ls['validated_at'] ); ?></td>
			</tr>
			<?php endif; ?>
		</table>

		<h3>What You Get With a License</h3>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><strong>Automatic updates</strong> — receive new features and security patches</li>
			<li><strong>Premium agents</strong> — install and run premium marketplace agents</li>
			<li><strong>Priority support</strong> — get help from the development team</li>
		</ul>
		<p class="description" style="margin-top: 16px;">Without a license, the core plugin and all bundled agents work normally. Only marketplace premium agents and auto-updates require a license.</p>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const input = document.getElementById('agentic_license_key_input');
			const deactivateBtn = document.getElementById('agentic-deactivate-license');

			// Activate on form submit.
			const form = input.closest('form');
			const submitBtn = form.querySelector('input[type="submit"], .button-primary[type="submit"]');
			if (submitBtn) {
				submitBtn.addEventListener('click', function(e) {
					// Only intercept when on the License tab.
					const tabInput = form.querySelector('input[name="tab"]');
					if (!tabInput || tabInput.value !== 'license') return;

					e.preventDefault();
					const key = input.value.trim();
					if (!key) { alert('Please enter a license key.'); return; }

					submitBtn.disabled = true;
					submitBtn.value = 'Activating...';

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({
							action: 'agentic_activate_plugin_license',
							_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'agentic_license_nonce' ) ); ?>',
							license_key: key
						})
					})
					.then(r => r.json())
					.then(data => {
						if (data.success) {
							location.reload();
						} else {
							alert(data.data || 'Activation failed.');
							submitBtn.disabled = false;
							submitBtn.value = 'Save Settings';
						}
					})
					.catch(() => {
						alert('Connection error. Please try again.');
						submitBtn.disabled = false;
						submitBtn.value = 'Save Settings';
					});
				});
			}

			// Deactivate button.
			if (deactivateBtn) {
				deactivateBtn.addEventListener('click', function() {
					if (!confirm('Deactivate your license from this site?')) return;
					deactivateBtn.disabled = true;
					deactivateBtn.textContent = 'Deactivating...';

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({
							action: 'agentic_deactivate_plugin_license',
							_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'agentic_license_nonce' ) ); ?>'
						})
					})
					.then(r => r.json())
					.then(() => location.reload())
					.catch(() => location.reload());
				});
			}
		});
		</script>

		<?php elseif ( 'general' === $agentic_active_tab ) : ?>
		<h2>API Configuration</h2>
		<p>Configure your AI provider and model settings.</p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="agentic_llm_provider">LLM Provider</label>
				</th>
				<td>
					<select name="agentic_llm_provider" id="agentic_llm_provider">
						<option value="openai" <?php selected( $agentic_llm_provider_val, 'openai' ); ?>>OpenAI</option>
						<option value="anthropic" <?php selected( $agentic_llm_provider_val, 'anthropic' ); ?>>Anthropic (Claude)</option>
						<option value="xai" <?php selected( $agentic_llm_provider_val, 'xai' ); ?>>xAI (Grok)</option>
						<option value="google" <?php selected( $agentic_llm_provider_val, 'google' ); ?>>Google (Gemini)</option>
						<option value="mistral" <?php selected( $agentic_llm_provider_val, 'mistral' ); ?>>Mistral AI</option>
					</select>
					<a href="#" id="agentic-get-api-key" class="button" target="_blank" style="margin-left: 8px;">
						<span class="dashicons dashicons-external" style="margin-right: 4px; vertical-align: -2px;"></span>Get API Key
					</a>
					<p class="description">
						Choose your preferred AI provider for the agent builder.
					</p>
					<div id="agentic-api-key-instructions" style="margin-top: 12px; padding: 12px; background: #f0f6fc; border-left: 4px solid #0073aa; display: none;">
						<p style="margin: 0 0 8px 0; font-weight: 600;">How to get your API key:</p>
						<ol style="margin: 0; padding-left: 20px;" id="agentic-api-steps">
							<!-- Steps populated dynamically -->
						</ol>
					</div>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="agentic_llm_api_key">API Key</label>
				</th>
				<td>
					<input 
						type="password" 
						name="agentic_llm_api_key" 
						id="agentic_llm_api_key" 
						value="<?php echo esc_attr( $agentic_api_key_val ); ?>" 
						class="regular-text"
					/>
					<button type="button" id="agentic-test-api" class="button" style="margin-left: 8px;">Test</button>
					<p class="description" id="agentic-api-key-help">
						<!-- Updated dynamically based on provider -->
					</p>
					<div id="agentic-test-result" style="margin-top: 8px;"></div>
					<?php if ( ! empty( $agentic_api_key_val ) ) : ?>
						<p><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> API key is set</p>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="agentic_model">Model</label>
				</th>
				<td>
					<select name="agentic_model" id="agentic_model" data-current-model="<?php echo esc_attr( $agentic_model_val ); ?>">
						<!-- Options populated dynamically based on provider -->
					</select>
					<p class="description" id="agentic-model-help">
						<!-- Updated dynamically based on provider -->
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="agentic_agent_mode">Agent Mode</label>
				</th>
				<td>
					<select name="agentic_agent_mode" id="agentic_agent_mode">
						<option value="disabled" <?php selected( $agentic_agent_mode_val, 'disabled' ); ?>>Disabled</option>
						<option value="supervised" <?php selected( $agentic_agent_mode_val, 'supervised' ); ?>>Supervised (Recommended)</option>
						<option value="autonomous" <?php selected( $agentic_agent_mode_val, 'autonomous' ); ?>>Autonomous</option>
					</select>
					<p class="description" id="agentic-agent-mode-help">
						<!-- Help text updated dynamically -->
					</p>
				</td>
			</tr>
		</table>

		<?php elseif ( 'cache' === $agentic_active_tab ) : ?>
		<h2>Response Caching</h2>
		<p>Cache identical queries to save tokens and reduce latency.</p>
		
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="agentic_response_cache_enabled">Enable Response Cache</label>
				</th>
				<td>
					<label>
						<input 
							type="checkbox" 
							name="agentic_response_cache_enabled" 
							id="agentic_response_cache_enabled" 
							value="1"
							<?php checked( $agentic_cache_enabled ); ?>
						/>
						Cache identical messages to avoid repeated LLM calls
					</label>
					<p class="description">
						When enabled, exact-match queries return cached responses. Saves tokens and improves response time.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="agentic_response_cache_ttl">Cache TTL</label>
				</th>
				<td>
					<select name="agentic_response_cache_ttl" id="agentic_response_cache_ttl">
						<option value="900" <?php selected( $agentic_cache_ttl, 900 ); ?>>15 minutes</option>
						<option value="1800" <?php selected( $agentic_cache_ttl, 1800 ); ?>>30 minutes</option>
						<option value="3600" <?php selected( $agentic_cache_ttl, 3600 ); ?>>1 hour (Recommended)</option>
						<option value="7200" <?php selected( $agentic_cache_ttl, 7200 ); ?>>2 hours</option>
						<option value="21600" <?php selected( $agentic_cache_ttl, 21600 ); ?>>6 hours</option>
						<option value="86400" <?php selected( $agentic_cache_ttl, 86400 ); ?>>24 hours</option>
					</select>
					<p class="description">
						How long to keep cached responses before they expire.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">Cache Statistics</th>
				<td>
					<p>
						<strong>Cached entries:</strong> <?php echo esc_html( $agentic_cache_stats['entry_count'] ); ?><br>
						<strong>Status:</strong> <?php echo $agentic_cache_stats['enabled'] ? '<span style="color: #22c55e;">Active</span>' : '<span style="color: #b91c1c;">Disabled</span>'; ?>
					</p>
					<label>
						<input type="checkbox" name="agentic_clear_cache" value="1" />
						Clear all cached responses on save
					</label>
				</td>
			</tr>
		</table>

		<?php elseif ( 'security' === $agentic_active_tab ) : ?>
		<h2>Security Settings</h2>
		<p>Protect against prompt injection and abuse.</p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="agentic_security_enabled">Enable Security Filter</label>
				</th>
				<td>
					<label>
						<input 
							type="checkbox" 
							name="agentic_security_enabled" 
							id="agentic_security_enabled" 
							value="1"
							<?php checked( $agentic_security_enabled ); ?>
						/>
						Scan messages for prompt injection and malicious content
					</label>
					<p class="description">
						Blocks common injection patterns, rate limits requests, and flags PII. Adds &lt;1ms overhead.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="agentic_rate_limit_authenticated">Rate Limit (Authenticated)</label>
				</th>
				<td>
					<input 
						type="number" 
						name="agentic_rate_limit_authenticated" 
						id="agentic_rate_limit_authenticated" 
						value="<?php echo esc_attr( $agentic_rate_limit_auth ); ?>" 
						min="5"
						max="100"
						class="small-text"
					/> requests per minute
					<p class="description">
						Maximum chat requests per minute for logged-in users.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="agentic_rate_limit_anonymous">Rate Limit (Anonymous)</label>
				</th>
				<td>
					<input 
						type="number" 
						name="agentic_rate_limit_anonymous" 
						id="agentic_rate_limit_anonymous" 
						value="<?php echo esc_attr( $agentic_rate_limit_anon ); ?>" 
						min="1"
						max="30"
						class="small-text"
					/> requests per minute
					<p class="description">
						Maximum chat requests per minute for anonymous visitors (by IP).
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="agentic_allow_anonymous_chat">Allow Anonymous Chat</label>
				</th>
				<td>
					<label>
						<input 
							type="checkbox" 
							name="agentic_allow_anonymous_chat" 
							id="agentic_allow_anonymous_chat" 
							value="1"
							<?php checked( $agentic_allow_anon_chat ); ?>
						/>
						Allow non-logged-in users to chat via frontend shortcodes
					</label>
					<p class="description">
						When disabled, users must log in to use [agentic_chat] on the frontend.
					</p>
				</td>
			</tr>
		</table>

		<?php elseif ( 'permissions' === $agentic_active_tab ) : ?>
			<?php
			$agentic_perm_settings = \Agentic\Agent_Permissions::get_settings();
			$agentic_perm_scopes   = \Agentic\Agent_Permissions::get_scopes();
			?>
		<h2>Agent Permissions</h2>
		<p>Control what user-space write operations agents can perform. All changes are logged in the Audit Log.</p>

		<div style="padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; margin: 15px 0;">
			<p style="margin: 0;"><strong>Two Zones:</strong></p>
			<ul style="margin: 8px 0 0 20px;">
				<li><strong>Plugin/Repo Code</strong> — Changes go through <code>request_code_change</code> (git branch → human review). Always available.</li>
				<li><strong>User Space</strong> — Active theme files and custom plugins. Controlled by the permissions below.</li>
			</ul>
		</div>

		<h3>Confirmation Mode</h3>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="agentic_confirmation_mode">When agents make changes</label></th>
				<td>
					<select name="agentic_confirmation_mode" id="agentic_confirmation_mode">
						<option value="confirm" <?php selected( $agentic_perm_settings['confirmation_mode'], 'confirm' ); ?>>Always Confirm (Recommended)</option>
						<option value="auto" <?php selected( $agentic_perm_settings['confirmation_mode'], 'auto' ); ?>>Auto-Approve</option>
					</select>
					<p class="description">
						<strong>Always Confirm:</strong> Agent proposes the change → you see a diff in chat → click Approve or Reject.<br>
						<strong>Auto-Approve:</strong> Agent executes immediately (audit-logged, backup created). Use with caution.
					</p>
				</td>
			</tr>
		</table>

		<h3>User-Space Permissions</h3>
		<p>Enable specific capabilities that agents can use on your site. All are <strong>disabled by default</strong>.</p>

		<table class="widefat" style="max-width: 700px;">
			<thead>
				<tr>
					<th style="width: 40px;">Enabled</th>
					<th>Permission</th>
					<th>Description</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $agentic_perm_scopes as $agentic_scope_key => $agentic_scope_info ) : ?>
				<tr>
					<td style="text-align: center;">
						<input
							type="checkbox"
							name="agentic_perm_<?php echo esc_attr( $agentic_scope_key ); ?>"
							id="agentic_perm_<?php echo esc_attr( $agentic_scope_key ); ?>"
							value="1"
							<?php checked( $agentic_perm_settings['permissions'][ $agentic_scope_key ] ?? false ); ?>
						/>
					</td>
					<td><label for="agentic_perm_<?php echo esc_attr( $agentic_scope_key ); ?>"><strong><?php echo esc_html( $agentic_scope_info['label'] ); ?></strong></label></td>
					<td><?php echo esc_html( $agentic_scope_info['description'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h3 style="margin-top: 30px;">Read-Only Capabilities (Always Allowed)</h3>
		<table class="widefat" style="max-width: 700px;">
			<tbody>
				<tr>
					<td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Read files from plugins/ and themes/</td>
				</tr>
				<tr>
					<td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Search code</td>
				</tr>
				<tr>
					<td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Query database (SELECT only)</td>
				</tr>
				<tr>
					<td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Read error log, site health, options, users</td>
				</tr>
				<tr>
					<td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> View WordPress posts, comments, cron events</td>
				</tr>
				<tr>
					<td><span class="dashicons dashicons-warning" style="color: #f59e0b;"></span> Modify plugin/repo code → Requires approval via <code>request_code_change</code></td>
				</tr>
			</tbody>
		</table>

		<?php endif; ?>

		<p class="submit">
			<input type="submit" name="agentic_save_settings" class="button-primary" value="Save Settings" />
		</p>
	</form>

	<script>
	document.getElementById('agentic-test-api').addEventListener('click', async function(e) {
		e.preventDefault();
		const result = document.getElementById('agentic-test-result');
		const btn = this;
		const provider = document.getElementById('agentic_llm_provider').value;
		const apiKey = document.getElementById('agentic_llm_api_key').value;
		const model = document.getElementById('agentic_model').value;
		
		if (!apiKey) {
			result.innerHTML = '<p style="color: #b91c1c; margin: 0;"><span class="dashicons dashicons-no-alt" style="vertical-align: -2px;"></span> ✗ Please enter an API key first</p>';
			return;
		}
		
		btn.disabled = true;
		const originalText = btn.innerHTML;
		btn.innerHTML = '<span class="spinner" style="float: none; vertical-align: -2px; margin-right: 4px;"></span>Testing...';
		
		try {
			const response = await fetch('<?php echo esc_js( rest_url( 'agentic/v1/test-api' ) ); ?>', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
				},
				body: JSON.stringify({
					provider: provider,
					api_key: apiKey
				})
			});
			const data = await response.json();
			
			if (data.success) {
				result.innerHTML = '<p style="color: #22c55e; margin: 0;"><span class="dashicons dashicons-yes-alt" style="vertical-align: -2px;"></span> ✓ ' + data.message + ' Saving...</p>';
				
				// Save via AJAX without page refresh.
				const saveResponse = await fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						'agentic_save_settings': '1',
						'agentic_llm_provider': provider,
						'agentic_llm_api_key': apiKey,
						'agentic_model': model,
						'agentic_agent_mode': document.getElementById('agentic_agent_mode').value,
						'_wpnonce': '<?php echo esc_js( wp_create_nonce( 'agentic_settings_nonce' ) ); ?>',
					'_wp_http_referer': '<?php echo esc_js( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ); ?>'
					})
				});
				
				if (saveResponse.ok) {
					result.innerHTML = '<p style="color: #22c55e; margin: 0;"><span class="dashicons dashicons-yes-alt" style="vertical-align: -2px;"></span> ✓ ' + data.message + ' Settings saved!</p>';
					// Update the "API key is set" indicator.
					const setIndicator = btn.parentElement.querySelector('p:last-child');
					if (!setIndicator || !setIndicator.querySelector('.dashicons-yes-alt')) {
						const indicator = document.createElement('p');
						indicator.innerHTML = '<span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> API key is set';
						btn.parentElement.appendChild(indicator);
					}
				} else {
					result.innerHTML = '<p style="color: #b91c1c; margin: 0;"><span class="dashicons dashicons-warning" style="vertical-align: -2px;"></span> API key valid but save failed. Please use Save Settings button.</p>';
				}
				btn.disabled = false;
				btn.innerHTML = originalText;
			} else {
				result.innerHTML = '<p style="color: #b91c1c; margin: 0;"><span class="dashicons dashicons-no-alt" style="vertical-align: -2px;"></span> ✗ ' + (data.message || 'API test failed') + '</p>';
				btn.disabled = false;
				btn.innerHTML = originalText;
			}
		} catch (error) {
			result.innerHTML = '<p style="color: #b91c1c; margin: 0;"><span class="dashicons dashicons-no-alt" style="vertical-align: -2px;"></span> ✗ Error: ' + error.message + '</p>';
			btn.disabled = false;
			btn.innerHTML = originalText;
		}
	});
	</script>
</div>
