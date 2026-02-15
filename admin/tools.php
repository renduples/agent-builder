<?php
/**
 * Agentic Tools Administration Page
 *
 * Displays all available tools across all agents: core tools and
 * agent-specific tools. Provides visibility into what each agent can do.
 * Administrators can enable or disable individual tools via toggle switches.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      1.4.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agent-builder' ) );
}

// Collect core tool definitions (unfiltered — we need all tools for the admin UI).
$agentic_core_tools = new \Agentic\Agent_Tools();
$agentic_core_defs  = $agentic_core_tools->get_all_tool_definitions();

// Get currently disabled tools.
$agentic_disabled_tools = get_option( 'agentic_disabled_tools', array() );
if ( ! is_array( $agentic_disabled_tools ) ) {
	$agentic_disabled_tools = array();
}

// Known core tool names (tools defined in Agent_Tools, not from agents).
$agentic_core_names = array(
	'read_file',
	'list_directory',
	'search_code',
	'get_posts',
	'get_comments',
	'create_comment',
	'update_documentation',
	'request_code_change',
	'manage_schedules',
	'query_database',
	'get_error_log',
	'get_site_health',
	'manage_wp_cron',
	'get_users',
	'get_option',
	'write_file',
	'modify_option',
	'manage_transients',
	'modify_postmeta',
);

// Collect agent instances and their tools.
$agentic_registry  = \Agentic_Agent_Registry::get_instance();
$agentic_instances = $agentic_registry->get_all_instances();

// Build unified tool list.
$agentic_all_tools = array();

// First: core tools from Agent_Tools.
foreach ( $agentic_core_defs as $agentic_def ) {
	$agentic_fname   = $agentic_def['function']['name'] ?? '';
	$agentic_is_core = in_array( $agentic_fname, $agentic_core_names, true );

	if ( ! $agentic_is_core ) {
		// This is an agent-contributed tool merged by get_tool_definitions; skip here.
		continue;
	}

	$agentic_params     = $agentic_def['function']['parameters']['properties'] ?? array();
	$agentic_param_list = array();
	foreach ( $agentic_params as $agentic_pname => $agentic_pdef ) {
		if ( is_array( $agentic_pdef ) ) {
			$agentic_param_list[] = $agentic_pname;
		}
	}

	$agentic_all_tools[ $agentic_fname ] = array(
		'name'        => $agentic_fname,
		'description' => $agentic_def['function']['description'] ?? '',
		'type'        => 'Core',
		'agents'      => array(),
		'params'      => $agentic_param_list,
		'enabled'     => ! in_array( $agentic_fname, $agentic_disabled_tools, true ),
	);
}

// Second: agent-specific tools.
foreach ( $agentic_instances as $agentic_agent ) {
	$agentic_agent_tools = $agentic_agent->get_tools();
	foreach ( $agentic_agent_tools as $agentic_tdef ) {
		$agentic_fname = $agentic_tdef['function']['name'] ?? $agentic_tdef['name'] ?? '';

		if ( isset( $agentic_all_tools[ $agentic_fname ] ) ) {
			$agentic_all_tools[ $agentic_fname ]['agents'][] = $agentic_agent->get_name();
		} else {
			$agentic_params     = $agentic_tdef['function']['parameters']['properties'] ?? array();
			$agentic_param_list = array();
			if ( is_array( $agentic_params ) ) {
				foreach ( $agentic_params as $agentic_pname => $agentic_pdef ) {
					if ( is_array( $agentic_pdef ) ) {
						$agentic_param_list[] = $agentic_pname;
					}
				}
			}

			$agentic_all_tools[ $agentic_fname ] = array(
				'name'        => $agentic_fname,
				'description' => $agentic_tdef['function']['description'] ?? '',
				'type'        => 'Agent',
				'agents'      => array( $agentic_agent->get_name() ),
				'params'      => $agentic_param_list,
				'enabled'     => ! in_array( $agentic_fname, $agentic_disabled_tools, true ),
			);
		}
	}
}

// Filter by type if requested.
$agentic_filter_type = sanitize_text_field( wp_unslash( $_GET['tool_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter, not a form submission.
if ( $agentic_filter_type ) {
	$agentic_all_tools = array_filter(
		$agentic_all_tools,
		fn( $t ) => strtolower( $t['type'] ) === strtolower( $agentic_filter_type )
	);
}

// Query tool usage counts from the audit log.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table aggregate.
$agentic_usage_rows   = $wpdb->get_results(
	"SELECT target_type AS tool_name, COUNT(*) AS call_count FROM {$wpdb->prefix}agentic_audit_log WHERE action = 'tool_call' GROUP BY target_type",
	ARRAY_A
);
$agentic_usage_counts = array();
if ( is_array( $agentic_usage_rows ) ) {
	foreach ( $agentic_usage_rows as $agentic_row ) {
		$agentic_usage_counts[ $agentic_row['tool_name'] ] = (int) $agentic_row['call_count'];
	}
}

// Count totals.
$agentic_total_tools = count( $agentic_all_tools );
$agentic_core_count  = count( array_filter( $agentic_all_tools, fn( $t ) => 'Core' === $t['type'] ) );
$agentic_agent_count = $agentic_total_tools - $agentic_core_count;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Agent Tools', 'agent-builder' ); ?></h1>
	<p><?php esc_html_e( 'All tools available to agents. Core tools are shared across all agents; agent tools are specific to individual agents.', 'agent-builder' ); ?></p>

	<!-- Filter links -->
	<ul class="subsubsub" style="margin-bottom: 10px;">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-tools' ) ); ?>"
				<?php echo empty( $agentic_filter_type ) ? 'class="current"' : ''; ?>>
				<?php
				/* translators: %d: number of tools */
				printf( esc_html__( 'All (%d)', 'agent-builder' ), (int) $agentic_total_tools );
				?>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-tools&tool_type=core' ) ); ?>"
				<?php echo 'core' === $agentic_filter_type ? 'class="current"' : ''; ?>>
				<?php
				/* translators: %d: number of core tools */
				printf( esc_html__( 'Core (%d)', 'agent-builder' ), (int) $agentic_core_count );
				?>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-tools&tool_type=agent' ) ); ?>"
				<?php echo 'agent' === $agentic_filter_type ? 'class="current"' : ''; ?>>
				<?php
				/* translators: %d: number of agent tools */
				printf( esc_html__( 'Agent (%d)', 'agent-builder' ), (int) $agentic_agent_count );
				?>
			</a>
		</li>
	</ul>

	<?php if ( empty( $agentic_all_tools ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No tools found. Activate agents to see their available tools.', 'agent-builder' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped" style="margin-top: 10px;">
			<thead>
				<tr>
					<th style="width: 80px;"><?php esc_html_e( 'Enabled', 'agent-builder' ); ?></th>
					<th style="width: 200px;"><?php esc_html_e( 'Tool Name', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Description', 'agent-builder' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Type', 'agent-builder' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Usage', 'agent-builder' ); ?></th>
					<th style="width: 200px;"><?php esc_html_e( 'Parameters', 'agent-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $agentic_all_tools as $agentic_tool ) : ?>
				<tr<?php echo $agentic_tool['enabled'] ? '' : ' style="opacity: 0.6;"'; ?> data-tool="<?php echo esc_attr( $agentic_tool['name'] ); ?>">
					<td style="text-align: center;">
						<label class="agentic-toggle" title="<?php echo $agentic_tool['enabled'] ? esc_attr__( 'Click to disable this tool', 'agent-builder' ) : esc_attr__( 'Click to enable this tool', 'agent-builder' ); ?>">
							<input type="checkbox" class="agentic-tool-toggle"
								data-tool="<?php echo esc_attr( $agentic_tool['name'] ); ?>"
								<?php checked( $agentic_tool['enabled'] ); ?> />
							<span class="agentic-toggle-slider"></span>
						</label>
					</td>
					<td>
						<strong><code><?php echo esc_html( $agentic_tool['name'] ); ?></code></strong>
					</td>
					<td>
						<?php echo esc_html( $agentic_tool['description'] ); ?>
					</td>
					<td>
						<?php if ( 'Core' === $agentic_tool['type'] ) : ?>
							<span style="background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 3px; font-size: 12px;">
								<?php esc_html_e( 'Core', 'agent-builder' ); ?>
							</span>
						<?php else : ?>
							<span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 3px; font-size: 12px;">
								<?php esc_html_e( 'Agent', 'agent-builder' ); ?>
							</span>
						<?php endif; ?>
					</td>
					<td>
						<?php
						$agentic_tool_uses = $agentic_usage_counts[ $agentic_tool['name'] ] ?? 0;
						if ( $agentic_tool_uses > 0 ) {
							printf(
								'<span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">%s</span>',
								/* translators: %s: number of times a tool was called */
								esc_html( sprintf( _n( '%s call', '%s calls', $agentic_tool_uses, 'agent-builder' ), number_format_i18n( $agentic_tool_uses ) ) )
							);
						} else {
							echo '<span style="color: #9ca3af; font-size: 12px;">' . esc_html__( 'No calls yet', 'agent-builder' ) . '</span>';
						}
						?>
					</td>
					<td>
						<?php
						if ( ! empty( $agentic_tool['params'] ) ) {
							echo '<code style="font-size: 11px;">' . esc_html( implode( ', ', $agentic_tool['params'] ) ) . '</code>';
						} else {
							echo '<span style="color: #646970;">' . esc_html__( 'None', 'agent-builder' ) . '</span>';
						}
						?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Tool Permission System', 'agent-builder' ); ?></h3>
		<p style="margin-bottom: 8px;"><?php esc_html_e( 'Use the toggle switches above to control which tools agents can access. When a tool is disabled:', 'agent-builder' ); ?></p>
		<ul style="margin: 0 0 10px; list-style: disc; padding-left: 20px;">
			<li><?php esc_html_e( 'The tool is hidden from the AI model — agents will not know it exists and cannot request it.', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'Even if an agent attempts to call a disabled tool directly, execution is blocked and an error is returned.', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'Disabled tool calls are logged in the Audit Log for security visibility.', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'Changes take effect immediately — no restart or reload required.', 'agent-builder' ); ?></li>
		</ul>
		<p style="margin: 0; font-size: 12px; color: #856404;">
			<strong><?php esc_html_e( 'Tip:', 'agent-builder' ); ?></strong>
			<?php esc_html_e( 'Disable write tools (request_code_change, update_documentation, write_file) for a read-only agent experience. Core read tools like read_file and list_directory are needed by most agents.', 'agent-builder' ); ?>
		</p>
	</div>

	<div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'About Tools', 'agent-builder' ); ?></h3>
		<ul style="margin: 0; list-style: disc; padding-left: 20px;">
			<li><?php esc_html_e( 'Core tools are available to all agents and provide file operations, WordPress data access, and schedule management.', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'Agent tools are defined by individual agents for their specific functionality (e.g., security scans, content analysis).', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'When chatting with an agent, the LLM decides which tools to call based on the conversation context.', 'agent-builder' ); ?></li>
			<li><?php esc_html_e( 'All tool executions are logged in the Audit Log for transparency and debugging.', 'agent-builder' ); ?></li>
		</ul>
	</div>
</div>

<!-- Toggle switch CSS -->
<style>
	.agentic-toggle {
		position: relative;
		display: inline-block;
		width: 40px;
		height: 22px;
		cursor: pointer;
	}
	.agentic-toggle input {
		opacity: 0;
		width: 0;
		height: 0;
	}
	.agentic-toggle-slider {
		position: absolute;
		top: 0; left: 0; right: 0; bottom: 0;
		background-color: #ccc;
		border-radius: 22px;
		transition: background-color 0.2s;
	}
	.agentic-toggle-slider::before {
		content: "";
		position: absolute;
		height: 16px;
		width: 16px;
		left: 3px;
		bottom: 3px;
		background-color: #fff;
		border-radius: 50%;
		transition: transform 0.2s;
	}
	.agentic-toggle input:checked + .agentic-toggle-slider {
		background-color: #2271b1;
	}
	.agentic-toggle input:checked + .agentic-toggle-slider::before {
		transform: translateX(18px);
	}
	.agentic-toggle input:focus + .agentic-toggle-slider {
		box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.4);
	}
	.agentic-toggle.is-saving .agentic-toggle-slider {
		opacity: 0.5;
	}
</style>

<!-- Toggle AJAX handler -->
<script>
(function() {
	'use strict';
	document.querySelectorAll('.agentic-tool-toggle').forEach(function(toggle) {
		toggle.addEventListener('change', function() {
			var toolName = this.dataset.tool;
			var enabled  = this.checked;
			var row      = this.closest('tr');
			var label    = this.closest('.agentic-toggle');

			label.classList.add('is-saving');

			var data = new FormData();
			data.append('action', 'agentic_toggle_tool');
			data.append('tool', toolName);
			data.append('enabled', enabled ? '1' : '0');
			data.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'agentic_toggle_tool' ) ); ?>');

			fetch(ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			})
			.then(function(response) { return response.json(); })
			.then(function(result) {
				label.classList.remove('is-saving');
				if (result.success) {
					row.style.opacity = enabled ? '1' : '0.6';
					label.title = enabled
						? '<?php echo esc_js( __( 'Click to disable this tool', 'agent-builder' ) ); ?>'
						: '<?php echo esc_js( __( 'Click to enable this tool', 'agent-builder' ) ); ?>';
				} else {
					// Revert on failure.
					toggle.checked = !enabled;
					alert(result.data || 'Failed to update tool status.');
				}
			})
			.catch(function() {
				label.classList.remove('is-saving');
				toggle.checked = !enabled;
			});
		});
	});
})();
</script>
