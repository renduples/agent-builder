<?php
/**
 * Installed Agents Admin Page
 *
 * Similar to WordPress Plugins page - lists all installed agents
 * with activate/deactivate functionality.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      0.2.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle actions.

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agentbuilder' ) );
}

$agentic_agent_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
$agentic_slug         = isset( $_GET['agent'] ) ? sanitize_text_field( wp_unslash( $_GET['agent'] ) ) : '';
$agentic_message      = '';
$agentic_agent_error  = '';

if ( $agentic_agent_action && $agentic_slug && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'agentic_agent_action' ) ) {
	$agentic_registry = Agentic_Agent_Registry::get_instance();

	switch ( $agentic_agent_action ) {
		case 'activate':
			$agentic_result = $agentic_registry->activate_agent( $agentic_slug );
			if ( is_wp_error( $agentic_result ) ) {
					$agentic_agent_error = $agentic_result->get_error_message();
			} else {
				$agentic_agents_data = $agentic_registry->get_installed_agents( true );
				$agentic_agent_name  = $agentic_agents_data[ $agentic_slug ]['name'] ?? $agentic_slug;
				$agentic_page_slug   = 'agent-builder' === $agentic_slug ? 'agentbuilder' : 'agentic-chat';
				$agentic_chat_url    = admin_url( 'admin.php?page=' . $agentic_page_slug . '&agent=' . $agentic_slug );
				$agentic_message     = sprintf(
				/* translators: 1: agent name, 2: chat URL */
					__( '%1$s activated. <a href="%2$s">Chat with this agent now →</a>', 'agentbuilder' ),
					esc_html( $agentic_agent_name ),
					esc_url( $agentic_chat_url )
				);
			}
			break;

		case 'deactivate':
			$agentic_result = $agentic_registry->deactivate_agent( $agentic_slug );
			if ( is_wp_error( $agentic_result ) ) {
				$agentic_agent_error = $result->get_error_message();
			} else {
				$agentic_message = __( 'Agent deactivated.', 'agentbuilder' );
			}
			break;

		case 'delete':
			$agentic_result = $agentic_registry->delete_agent( $agentic_slug );
			if ( is_wp_error( $agentic_result ) ) {
				$agentic_agent_error = $agentic_result->get_error_message();
			} else {
				$agentic_message = __( 'Agent deleted.', 'agentbuilder' );
			}
			break;
	}
}

$agentic_registry = Agentic_Agent_Registry::get_instance();
$agentic_agents   = $agentic_registry->get_installed_agents( true );

// Filter by status.
$agentic_filter = isset( $_GET['agent_status'] ) ? sanitize_text_field( wp_unslash( $_GET['agent_status'] ) ) : 'all';

$agentic_all_count      = count( $agentic_agents );
$agentic_active_count   = count( array_filter( $agentic_agents, fn( $a ) => $a['active'] ) );
$agentic_inactive_count = $agentic_all_count - $agentic_active_count;

if ( 'active' === $agentic_filter ) {
	$agentic_agents = array_filter( $agentic_agents, fn( $a ) => $a['active'] );
} elseif ( 'inactive' === $agentic_filter ) {
	$agentic_agents = array_filter( $agentic_agents, fn( $a ) => ! $a['active'] );
}

?>

<div class="wrap agentic-agents-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Agents', 'agentbuilder' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( $agentic_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo wp_kses( $agentic_message, array( 'a' => array( 'href' => array() ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $agentic_agent_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $agentic_agent_error ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Filter Links -->
	<ul class="subsubsub">
		<li class="all">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents' ) ); ?>"
		class="<?php echo 'all' === $agentic_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'All', 'agentbuilder' ); ?>
				<span class="count">(<?php echo esc_html( $agentic_all_count ); ?>)</span>
			</a> |
		</li>
		<li class="active">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&agent_status=active' ) ); ?>"
				class="<?php echo 'active' === $agentic_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'Active', 'agentbuilder' ); ?>
				<span class="count">(<?php echo esc_html( $agentic_active_count ); ?>)</span>
			</a> |
		</li>
		<li class="inactive">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&agent_status=inactive' ) ); ?>"
				class="<?php echo 'inactive' === $agentic_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'Inactive', 'agentbuilder' ); ?>
				<span class="count">(<?php echo esc_html( $agentic_inactive_count ); ?>)</span>
			</a>
		</li>
	</ul>

	<!-- Agents Table -->
	<table class="wp-list-table widefat plugins">
		<thead>
			<tr>
				<td id="cb" class="manage-column column-cb check-column">
					<input type="checkbox" id="cb-select-all-1">
				</td>
				<th scope="col" class="manage-column column-name column-primary">
					<?php esc_html_e( 'Agent', 'agentbuilder' ); ?>
				</th>
				<th scope="col" class="manage-column column-description">
					<?php esc_html_e( 'Description', 'agentbuilder' ); ?>
				</th>
			</tr>
		</thead>
		<tbody id="the-list">
			<?php if ( empty( $agentic_agents ) ) : ?>
				<tr class="no-items">
					<td class="colspanchange" colspan="3">
						<?php esc_html_e( 'No agents installed yet.', 'agentbuilder' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $agentic_agents as $agentic_slug => $agentic_agent ) : ?>
					<?php
					$agentic_row_class = $agentic_agent['active'] ? 'active' : 'inactive';
					$agentic_nonce     = wp_create_nonce( 'agentic_agent_action' );
					?>
					<tr class="<?php echo esc_attr( $agentic_row_class ); ?>" data-slug="<?php echo esc_attr( $agentic_slug ); ?>">
						<th scope="row" class="check-column">
							<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $agentic_slug ); ?>">
						</th>
						<td class="plugin-title column-primary">
							<strong><?php echo esc_html( $agentic_agent['name'] ); ?></strong>

							<div class="row-actions visible">

								<?php if ( $agentic_agent['active'] ) : ?>
									<?php
									$agentic_page_slug = 'agent-builder' === $agentic_slug ? 'agentbuilder' : 'agentic-chat';
									?>
						<span class="chat">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $agentic_page_slug . '&agent=' . $agentic_slug ) ); ?>" style="font-weight: 600;">
											<?php esc_html_e( 'Chat', 'agentbuilder' ); ?>
										</a> |
									</span>
									<span class="deactivate">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&action=deactivate&agent=' . $agentic_slug . '&_wpnonce=' . $agentic_nonce ) ); ?>">
											<?php esc_html_e( 'Deactivate', 'agentbuilder' ); ?>
										</a>
									</span>
								<?php else : ?>
									<span class="activate">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&action=activate&agent=' . $agentic_slug . '&_wpnonce=' . $agentic_nonce ) ); ?>">
											<?php esc_html_e( 'Activate', 'agentbuilder' ); ?>
										</a> |
									</span>
									<span class="delete">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&action=delete&agent=' . $agentic_slug . '&_wpnonce=' . $agentic_nonce ) ); ?>"
											class="delete"
											onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this agent?', 'agentbuilder' ); ?>');">
											<?php esc_html_e( 'Delete', 'agentbuilder' ); ?>
										</a>
									</span>
								<?php endif; ?>
							</div>
						</td>
						<td class="column-description desc">
							<div class="plugin-description">
								<p><?php echo esc_html( $agentic_agent['description'] ); ?></p>
							</div>
							<div class="plugin-meta">
								<?php if ( ! empty( $agentic_agent['version'] ) ) : ?>
									<span class="agent-version">
									<?php
									/* translators: %s: agent version number */
									printf( esc_html__( 'Version %s', 'agentbuilder' ), esc_html( $agent['version'] ) );
									?>
									</span>
									<span class="separator">|</span>
								<?php endif; ?>

								<?php if ( ! empty( $agent['author'] ) ) : ?>
									<span class="agent-author">
										<?php esc_html_e( 'By', 'agentbuilder' ); ?>
										<?php if ( ! empty( $agent['author_uri'] ) ) : ?>
											<a href="<?php echo esc_url( $agent['author_uri'] ); ?>" target="_blank">
												<?php echo esc_html( $agent['author'] ); ?>
											</a>
										<?php else : ?>
											<?php echo esc_html( $agent['author'] ); ?>
										<?php endif; ?>
									</span>
									<span class="separator">|</span>
								<?php endif; ?>

								<?php if ( ! empty( $agent['category'] ) ) : ?>
									<span class="agent-category">
										<?php echo esc_html( $agent['category'] ); ?>
									</span>
									<span class="separator">|</span>
								<?php endif; ?>

								<?php if ( ! empty( $agent['capabilities'] ) ) : ?>
									<span class="agent-capabilities">
										<?php
										/* translators: %s: comma-separated list of agent capabilities */                                       printf(
											esc_html__( 'Capabilities: %s', 'agentbuilder' ),
											esc_html( implode( ', ', $agent['capabilities'] ) )
										);
										?>
									</span>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<td class="manage-column column-cb check-column">
					<input type="checkbox" id="cb-select-all-2">
				</td>
				<th scope="col" class="manage-column column-name column-primary">
					<?php esc_html_e( 'Agent', 'agentbuilder' ); ?>
				</th>
				<th scope="col" class="manage-column column-description">
					<?php esc_html_e( 'Description', 'agentbuilder' ); ?>
				</th>
			</tr>
		</tfoot>
	</table>
</div>

<style>
.agentic-agents-page .plugins tr.active th,
.agentic-agents-page .plugins tr.active td {
	background-color: #e7f4e7;
}

.agentic-agents-page .plugins tr.inactive th,
.agentic-agents-page .plugins tr.inactive td {
	background-color: #f9f9f9;
}

.agentic-agents-page .plugin-meta {
	margin-top: 8px;
	font-size: 12px;
	color: #666;
}

.agentic-agents-page .plugin-meta .separator {
	margin: 0 5px;
	color: #ccc;
}

.agentic-agents-page .search-form {
	float: right;
	margin: 0;
}

.agentic-agents-page .subsubsub {
	margin-bottom: 10px;
}

.agentic-agents-page .wp-list-table {
	margin-top: 10px;
}

.agentic-agents-page .column-name {
	width: 25%;
}
</style>
