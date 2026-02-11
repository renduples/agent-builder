<?php
/**
 * Agentic Tools Administration Page
 *
 * Displays all available tools across all agents: core tools and
 * agent-specific tools. Provides visibility into what each agent can do.
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

// Collect core tool definitions.
$agentic_core_tools = new \Agentic\Agent_Tools();
$agentic_core_defs  = $agentic_core_tools->get_tool_definitions();

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
);

// Collect agent instances and their tools.
$agentic_registry  = \Agentic_Agent_Registry::get_instance();
$agentic_instances = $agentic_registry->get_all_instances();

// Build unified tool list.
$agentic_all_tools = array();

// First: core tools from Agent_Tools.
foreach ( $agentic_core_defs as $agentic_def ) {
	$agentic_fname = $agentic_def['function']['name'] ?? '';
	$agentic_is_core = in_array( $agentic_fname, $agentic_core_names, true );

	if ( ! $agentic_is_core ) {
		// This is an agent-contributed tool merged by get_tool_definitions; skip here.
		continue;
	}

	$agentic_params = $agentic_def['function']['parameters']['properties'] ?? array();
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
			$agentic_params = $agentic_tdef['function']['parameters']['properties'] ?? array();
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
			);
		}
	}
}

// Filter by type if requested.
$agentic_filter_type = sanitize_text_field( wp_unslash( $_GET['tool_type'] ?? '' ) );
if ( $agentic_filter_type ) {
	$agentic_all_tools = array_filter(
		$agentic_all_tools,
		fn( $t ) => strtolower( $t['type'] ) === strtolower( $agentic_filter_type )
	);
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
				printf( esc_html__( 'All (%d)', 'agent-builder' ), $agentic_total_tools );
				?>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-tools&tool_type=core' ) ); ?>"
				<?php echo 'core' === $agentic_filter_type ? 'class="current"' : ''; ?>>
				<?php
				/* translators: %d: number of core tools */
				printf( esc_html__( 'Core (%d)', 'agent-builder' ), $agentic_core_count );
				?>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-tools&tool_type=agent' ) ); ?>"
				<?php echo 'agent' === $agentic_filter_type ? 'class="current"' : ''; ?>>
				<?php
				/* translators: %d: number of agent tools */
				printf( esc_html__( 'Agent (%d)', 'agent-builder' ), $agentic_agent_count );
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
					<th style="width: 200px;"><?php esc_html_e( 'Tool Name', 'agent-builder' ); ?></th>
					<th><?php esc_html_e( 'Description', 'agent-builder' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Type', 'agent-builder' ); ?></th>
					<th style="width: 200px;"><?php esc_html_e( 'Used By', 'agent-builder' ); ?></th>
					<th style="width: 200px;"><?php esc_html_e( 'Parameters', 'agent-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $agentic_all_tools as $agentic_tool ) : ?>
				<tr>
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
						if ( empty( $agentic_tool['agents'] ) ) {
							echo '<span style="color: #646970;">' . esc_html__( 'All agents (core)', 'agent-builder' ) . '</span>';
						} else {
							echo esc_html( implode( ', ', $agentic_tool['agents'] ) );
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
