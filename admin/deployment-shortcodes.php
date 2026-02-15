<?php
/**
 * Deployment Tab: Shortcodes
 *
 * Manage agent shortcode deployments. Administrators can create shortcodes
 * to embed agents on pages, posts, and sidebars. Shows existing deployments
 * and provides a builder for creating new shortcode snippets.
 *
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

// Handle delete action.
if ( isset( $_POST['agentic_delete_shortcode'], $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'agentic_manage_shortcodes' ) ) {
	$agentic_delete_id    = (int) ( $_POST['agentic_shortcode_id'] ?? 0 );
	$agentic_deployments  = get_option( 'agentic_shortcode_deployments', array() );
	$agentic_deployments  = array_filter( $agentic_deployments, fn( $d ) => $d['id'] !== $agentic_delete_id );
	update_option( 'agentic_shortcode_deployments', array_values( $agentic_deployments ) );
	$agentic_notice = __( 'Shortcode deployment removed.', 'agent-builder' );
}

// Handle create action.
if ( isset( $_POST['agentic_create_shortcode'], $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'agentic_manage_shortcodes' ) ) {
	$agentic_new = array(
		'id'          => time(),
		'label'       => sanitize_text_field( wp_unslash( $_POST['agentic_sc_label'] ?? '' ) ),
		'agent'       => sanitize_text_field( wp_unslash( $_POST['agentic_sc_agent'] ?? '' ) ),
		'style'       => sanitize_text_field( wp_unslash( $_POST['agentic_sc_style'] ?? 'inline' ) ),
		'height'      => sanitize_text_field( wp_unslash( $_POST['agentic_sc_height'] ?? '500px' ) ),
		'placeholder' => sanitize_text_field( wp_unslash( $_POST['agentic_sc_placeholder'] ?? '' ) ),
		'show_header' => ! empty( $_POST['agentic_sc_show_header'] ) ? 'true' : 'false',
		'created_at'  => current_time( 'mysql' ),
	);

	if ( empty( $agentic_new['label'] ) || empty( $agentic_new['agent'] ) ) {
		$agentic_error = __( 'Label and Agent are required.', 'agent-builder' );
	} else {
		$agentic_deployments   = get_option( 'agentic_shortcode_deployments', array() );
		$agentic_deployments[] = $agentic_new;
		update_option( 'agentic_shortcode_deployments', $agentic_deployments );
		$agentic_notice = sprintf(
			/* translators: %s: shortcode string */
			__( 'Shortcode created. Copy and paste it into any page, post, or widget: %s', 'agent-builder' ),
			'[agentic_chat agent="' . $agentic_new['agent'] . '"]'
		);
	}
}

// Load data.
$agentic_registry    = \Agentic_Agent_Registry::get_instance();
$agentic_all_agents  = $agentic_registry->get_all_instances();
$agentic_deployments = get_option( 'agentic_shortcode_deployments', array() );
if ( ! is_array( $agentic_deployments ) ) {
	$agentic_deployments = array();
}

if ( ! empty( $agentic_notice ) ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php echo esc_html( $agentic_notice ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! empty( $agentic_error ) ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( $agentic_error ); ?></p>
	</div>
<?php endif; ?>

<!-- Existing Deployments -->
<h2><?php esc_html_e( 'Active Shortcode Deployments', 'agent-builder' ); ?></h2>

<?php if ( empty( $agentic_deployments ) ) : ?>
	<div class="notice notice-info" style="margin-top: 0;">
		<p><?php esc_html_e( 'No shortcode deployments yet. Use the form below to create one.', 'agent-builder' ); ?></p>
	</div>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Label', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Agent', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Style', 'agent-builder' ); ?></th>
				<th style="width: 350px;"><?php esc_html_e( 'Shortcode', 'agent-builder' ); ?></th>
				<th><?php esc_html_e( 'Created', 'agent-builder' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Actions', 'agent-builder' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $agentic_deployments as $agentic_dep ) : ?>
				<?php
				// Build the shortcode string.
				$agentic_sc_parts = array( 'agent="' . esc_attr( $agentic_dep['agent'] ) . '"' );
				if ( ! empty( $agentic_dep['style'] ) && 'inline' !== $agentic_dep['style'] ) {
					$agentic_sc_parts[] = 'style="' . esc_attr( $agentic_dep['style'] ) . '"';
				}
				if ( ! empty( $agentic_dep['height'] ) && '500px' !== $agentic_dep['height'] ) {
					$agentic_sc_parts[] = 'height="' . esc_attr( $agentic_dep['height'] ) . '"';
				}
				if ( ! empty( $agentic_dep['placeholder'] ) ) {
					$agentic_sc_parts[] = 'placeholder="' . esc_attr( $agentic_dep['placeholder'] ) . '"';
				}
				if ( isset( $agentic_dep['show_header'] ) && 'false' === $agentic_dep['show_header'] ) {
					$agentic_sc_parts[] = 'show_header="false"';
				}
				$agentic_sc_string = '[agentic_chat ' . implode( ' ', $agentic_sc_parts ) . ']';

				// Look up agent name.
				$agentic_dep_agent_name = $agentic_dep['agent'];
				if ( isset( $agentic_all_agents[ $agentic_dep['agent'] ] ) ) {
					$agentic_dep_agent_obj  = $agentic_all_agents[ $agentic_dep['agent'] ];
					$agentic_dep_agent_name = $agentic_dep_agent_obj->get_icon() . ' ' . $agentic_dep_agent_obj->get_name();
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( $agentic_dep['label'] ); ?></strong></td>
					<td><?php echo esc_html( $agentic_dep_agent_name ); ?></td>
					<td>
						<?php
						$agentic_style_labels = array(
							'inline'  => __( 'Inline', 'agent-builder' ),
							'popup'   => __( 'Popup', 'agent-builder' ),
							'sidebar' => __( 'Sidebar', 'agent-builder' ),
						);
						echo esc_html( $agentic_style_labels[ $agentic_dep['style'] ] ?? ucfirst( $agentic_dep['style'] ) );
						?>
					</td>
					<td>
						<code class="agentic-sc-copyable" style="cursor: pointer; font-size: 12px; padding: 4px 8px; background: #f0f0f1; display: inline-block; max-width: 100%; overflow-x: auto;"
							title="<?php esc_attr_e( 'Click to copy', 'agent-builder' ); ?>"
							data-shortcode="<?php echo esc_attr( $agentic_sc_string ); ?>">
							<?php echo esc_html( $agentic_sc_string ); ?>
						</code>
					</td>
					<td>
						<small><?php echo esc_html( $agentic_dep['created_at'] ?? '' ); ?></small>
					</td>
					<td>
						<form method="post" style="display: inline;">
							<?php wp_nonce_field( 'agentic_manage_shortcodes' ); ?>
							<input type="hidden" name="agentic_shortcode_id" value="<?php echo esc_attr( $agentic_dep['id'] ); ?>">
							<button type="submit" name="agentic_delete_shortcode" value="1" class="button button-small button-link-delete"
								onclick="return confirm('<?php esc_attr_e( 'Remove this shortcode deployment?', 'agent-builder' ); ?>');">
								<?php esc_html_e( 'Delete', 'agent-builder' ); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<!-- Create New Deployment -->
<h2 style="margin-top: 30px;"><?php esc_html_e( 'Create New Deployment', 'agent-builder' ); ?></h2>

<?php if ( empty( $agentic_all_agents ) ) : ?>
	<div class="notice notice-warning">
		<p><?php esc_html_e( 'No active agents. Activate at least one agent before creating a shortcode deployment.', 'agent-builder' ); ?></p>
	</div>
<?php else : ?>
	<form method="post">
		<?php wp_nonce_field( 'agentic_manage_shortcodes' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="agentic_sc_label"><?php esc_html_e( 'Label', 'agent-builder' ); ?></label>
				</th>
				<td>
					<input type="text" id="agentic_sc_label" name="agentic_sc_label" class="regular-text" required
						placeholder="<?php esc_attr_e( 'e.g., Homepage Support Chat', 'agent-builder' ); ?>">
					<p class="description"><?php esc_html_e( 'A name to identify this deployment (not shown to visitors).', 'agent-builder' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="agentic_sc_agent"><?php esc_html_e( 'Agent', 'agent-builder' ); ?></label>
				</th>
				<td>
					<select id="agentic_sc_agent" name="agentic_sc_agent" required>
						<option value=""><?php esc_html_e( '— Select Agent —', 'agent-builder' ); ?></option>
						<?php foreach ( $agentic_all_agents as $agentic_a ) : ?>
							<option value="<?php echo esc_attr( $agentic_a->get_id() ); ?>">
								<?php echo esc_html( $agentic_a->get_icon() . ' ' . $agentic_a->get_name() ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The agent visitors will chat with.', 'agent-builder' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="agentic_sc_style"><?php esc_html_e( 'Display Style', 'agent-builder' ); ?></label>
				</th>
				<td>
					<select id="agentic_sc_style" name="agentic_sc_style">
						<option value="inline"><?php esc_html_e( 'Inline — embedded in page content', 'agent-builder' ); ?></option>
						<option value="popup"><?php esc_html_e( 'Popup — floating button that opens a chat window', 'agent-builder' ); ?></option>
						<option value="sidebar"><?php esc_html_e( 'Sidebar — compact widget for sidebars', 'agent-builder' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="agentic_sc_height"><?php esc_html_e( 'Chat Height', 'agent-builder' ); ?></label>
				</th>
				<td>
					<input type="text" id="agentic_sc_height" name="agentic_sc_height" value="500px" class="small-text" style="width: 100px;">
					<p class="description"><?php esc_html_e( 'CSS height (e.g., 500px, 60vh). Only affects inline and sidebar styles.', 'agent-builder' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="agentic_sc_placeholder"><?php esc_html_e( 'Placeholder Text', 'agent-builder' ); ?></label>
				</th>
				<td>
					<input type="text" id="agentic_sc_placeholder" name="agentic_sc_placeholder" class="regular-text"
						placeholder="<?php esc_attr_e( 'Type your message...', 'agent-builder' ); ?>">
					<p class="description"><?php esc_html_e( 'Custom placeholder text in the input field. Leave empty for default.', 'agent-builder' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'agent-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="agentic_sc_show_header" value="1" checked>
						<?php esc_html_e( 'Show agent name and icon header', 'agent-builder' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<!-- Live Preview -->
		<div id="agentic-sc-preview" style="margin: 10px 0 20px; padding: 12px 16px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px;">
			<strong><?php esc_html_e( 'Preview:', 'agent-builder' ); ?></strong>
			<code id="agentic-sc-preview-code" style="display: inline-block; margin-left: 8px; font-size: 13px;">[agentic_chat]</code>
		</div>

		<?php submit_button( __( 'Create Shortcode', 'agent-builder' ), 'primary', 'agentic_create_shortcode' ); ?>
	</form>
<?php endif; ?>

<div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
	<h3 style="margin-top: 0;"><?php esc_html_e( 'How Shortcodes Work', 'agent-builder' ); ?></h3>
	<ul style="margin: 0; list-style: disc; padding-left: 20px;">
		<li><?php esc_html_e( 'Paste the shortcode into any page, post, or text widget to embed an agent chat interface.', 'agent-builder' ); ?></li>
		<li>
			<?php esc_html_e( 'Two shortcodes are available:', 'agent-builder' ); ?>
			<ul style="list-style: circle; padding-left: 20px; margin: 4px 0;">
				<li><code>[agentic_chat]</code> — <?php esc_html_e( 'Full interactive chat with conversation history', 'agent-builder' ); ?></li>
				<li><code>[agentic_ask]</code> — <?php esc_html_e( 'One-shot query that displays a single response inline', 'agent-builder' ); ?></li>
			</ul>
		</li>
		<li><?php esc_html_e( 'Users must be logged in to chat unless anonymous access is enabled in Settings.', 'agent-builder' ); ?></li>
		<li><?php esc_html_e( 'Multiple shortcodes (even different agents) can be placed on the same page.', 'agent-builder' ); ?></li>
		<li><?php esc_html_e( 'All agent interactions via shortcodes are logged in the Audit Log.', 'agent-builder' ); ?></li>
	</ul>
</div>

<!-- Shortcode preview builder + copy-to-clipboard -->
<script>
(function() {
	'use strict';

	// Live shortcode preview.
	var agent = document.getElementById('agentic_sc_agent');
	var style = document.getElementById('agentic_sc_style');
	var height = document.getElementById('agentic_sc_height');
	var placeholder = document.getElementById('agentic_sc_placeholder');
	var showHeader = document.querySelector('input[name="agentic_sc_show_header"]');
	var preview = document.getElementById('agentic-sc-preview-code');

	function updatePreview() {
		if (!agent || !preview) return;
		var parts = [];
		if (agent.value) parts.push('agent="' + agent.value + '"');
		if (style.value && style.value !== 'inline') parts.push('style="' + style.value + '"');
		if (height.value && height.value !== '500px') parts.push('height="' + height.value + '"');
		if (placeholder.value) parts.push('placeholder="' + placeholder.value + '"');
		if (showHeader && !showHeader.checked) parts.push('show_header="false"');
		preview.textContent = '[agentic_chat' + (parts.length ? ' ' + parts.join(' ') : '') + ']';
	}

	if (agent) {
		[agent, style, height, placeholder].forEach(function(el) { el.addEventListener('input', updatePreview); el.addEventListener('change', updatePreview); });
		if (showHeader) showHeader.addEventListener('change', updatePreview);
		updatePreview();
	}

	// Copy-to-clipboard for existing shortcodes.
	document.querySelectorAll('.agentic-sc-copyable').forEach(function(el) {
		el.addEventListener('click', function() {
			var sc = this.dataset.shortcode;
			if (navigator.clipboard) {
				navigator.clipboard.writeText(sc).then(function() {
					el.style.background = '#d1fae5';
					setTimeout(function() { el.style.background = ''; }, 1000);
				});
			} else {
				// Fallback.
				var ta = document.createElement('textarea');
				ta.value = sc;
				document.body.appendChild(ta);
				ta.select();
				document.execCommand('copy');
				document.body.removeChild(ta);
				el.style.background = '#d1fae5';
				setTimeout(function() { el.style.background = ''; }, 1000);
			}
		});
	});
})();
</script>
