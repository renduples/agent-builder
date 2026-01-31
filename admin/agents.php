<?php
/**
 * Installed Agents Admin Page
 *
 * Similar to WordPress Plugins page - lists all installed agents
 * with activate/deactivate functionality.
 *
 * @package Agentic_Plugin
 * @since 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle actions.

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agentic-plugin' ) );
}

// Get available updates.
$marketplace_client = new \Agentic\Marketplace_Client();
$available_updates  = $marketplace_client->get_available_updates();

$agent_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
$slug         = isset( $_GET['agent'] ) ? sanitize_text_field( wp_unslash( $_GET['agent'] ) ) : '';
$message      = '';
$agent_error  = '';

if ( $agent_action && $slug && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'agentic_agent_action' ) ) {
	$registry = Agentic_Agent_Registry::get_instance();

switch ( $agent_action ) {
	case 'activate':
		$result = $registry->activate_agent( $slug );
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			$agents_data = $registry->get_installed_agents( true );
			$agent_name  = $agents_data[ $slug ]['name'] ?? $slug;
			$chat_url    = admin_url( 'admin.php?page=agentic-chat&agent=' . $slug );
			/* translators: 1: Agent name, 2: Chat URL */

			$message = sprintf(
				__( \'%1$s activated. <a href="%2$s">Chat with this agent now →</a>', 'agentic-plugin' ),
				esc_html( $agent_name ),
				esc_url( $chat_url )
			);
		}
		break;

	case 'deactivate':
		$result = $registry->deactivate_agent( $slug );
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			$message = __( 'Agent deactivated.', 'agentic-plugin' );
		}
		break;

	case 'delete':
		$result = $registry->delete_agent( $slug );
		if ( is_wp_error( $result ) ) {
			$agent_error = $result->get_error_message();
		} else {
			// Deactivate license if agent had one.
			$marketplace            = new \Agentic\Marketplace_Client();
			$marketplace_reflection = new \ReflectionClass( $marketplace );
			$deactivate_method      = $marketplace_reflection->getMethod( 'deactivate_agent_license' );
			$deactivate_method->setAccessible( true );
			$deactivate_method->invoke( $marketplace, $slug );

			// Download and install the update.
			if ( isset( $available_updates[ $slug ] ) ) {
				$update_data = $available_updates[ $slug ];
				$was_active  = $registry->is_agent_active( $slug );

				// Deactivate if active.
				if ( $was_active ) {
					$registry->deactivate_agent( $slug );
				}

				// Install the update (replaces files).
				$result = $registry->install_agent( $slug );

				if ( is_wp_error( $result ) ) {
					$agent_error = $result->get_error_message();
				} else {
					// Reactivate if was active.
					if ( $was_active ) {
						$registry->activate_agent( $slug );
					}

					// Clear update cache to refresh.
					delete_transient( 'agentic_available_updates' );

					$message = sprintf(
						/* translators: 1: agent name, 2: new version */
						__( '%1$s updated to version %2$s successfully.', 'agentic-plugin' ),
						esc_html( $update_data['name'] ),
						esc_html( $update_data['latest'] )
					);
				}
			} else {
				$agent_error = __( 'No update available for this agent.', 'agentic-plugin' );
			}
			break;
		}
	}
}

$registry = Agentic_Agent_Registry::get_instance();
$agents   = $registry->get_installed_agents( true );

// Filter by status.
$filter = isset( $_GET['agent_status'] ) ? sanitize_text_field( wp_unslash( $_GET['agent_status'] ) ) : 'all';

$all_count      = count( $agents );
$active_count   = count( array_filter( $agents, fn( $a ) => $a['active'] ) );
$inactive_count = $all_count - $active_count;

if ( 'active' === $filter ) {
	$agents = array_filter( $agents, fn( $a ) => $a['active'] );
} elseif ( 'inactive' === $filter ) {
	$agents = array_filter( $agents, fn( $a ) => ! $a['active'] );
}

// Search.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
if ( $search ) {
	$agents = array_filter(
		$agents,
		function ( $a ) use ( $search ) {
			return stripos( $a['name'], $search ) !== false
			|| stripos( $a['description'], $search ) !== false;
		}
	);
}
?>

<div class="wrap agentic-agents-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Agents', 'agentic-plugin' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New Agent', 'agentic-plugin' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $agent_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $agent_error ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Filter Links -->
	<ul class="subsubsub">
		<li class="all">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents' ) ); ?>"
				class="<?php echo $filter === 'all' ? 'current' : ''; ?>">
				<?php esc_html_e( 'All', 'agentic-plugin' ); ?>
				<span class="count">(<?php echo esc_html( $all_count ); ?>)</span>
			</a> |
		</li>
		<li class="active">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&agent_status=active' ) ); ?>"
				class="<?php echo $filter === 'active' ? 'current' : ''; ?>">
				<?php esc_html_e( 'Active', 'agentic-plugin' ); ?>
				<span class="count">(<?php echo esc_html( $active_count ); ?>)</span>
			</a> |
		</li>
		<li class="inactive">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&agent_status=inactive' ) ); ?>"
				class="<?php echo $filter === 'inactive' ? 'current' : ''; ?>">
				<?php esc_html_e( 'Inactive', 'agentic-plugin' ); ?>
				<span class="count">(<?php echo esc_html( $inactive_count ); ?>)</span>
			</a>
		</li>
	</ul>

	<!-- Search Box -->
	<form method="get" class="search-form">
		<input type="hidden" name="page" value="agentic-agents">
		<p class="search-box">
			<label class="screen-reader-text" for="agent-search-input">
				<?php esc_html_e( 'Search Agents', 'agentic-plugin' ); ?>
			</label>
			<input type="search" id="agent-search-input" name="s"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php esc_attr_e( 'Search installed agents...', 'agentic-plugin' ); ?>">
			<input type="submit" id="search-submit" class="button"
					value="<?php esc_attr_e( 'Search Agents', 'agentic-plugin' ); ?>">
		</p>
	</form>

	<!-- Agents Table -->
	<table class="wp-list-table widefat plugins">
		<thead>
			<tr>
				<td id="cb" class="manage-column column-cb check-column">
					<input type="checkbox" id="cb-select-all-1">
				</td>
				<th scope="col" class="manage-column column-name column-primary">
					<?php esc_html_e( 'Agent', 'agentic-plugin' ); ?>
				</th>
				<th scope="col" class="manage-column column-description">
					<?php esc_html_e( 'Description', 'agentic-plugin' ); ?>
				</th>
			</tr>
		</thead>
		<tbody id="the-list">
			<?php if ( empty( $agents ) ) : ?>
				<tr class="no-items">
					<td class="colspanchange" colspan="3">
						<?php if ( $search ) : ?>
							<?php esc_html_e( 'No agents found matching your search.', 'agentic-plugin' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'No agents installed yet.', 'agentic-plugin' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add' ) ); ?>">
								<?php esc_html_e( 'Add your first agent', 'agentic-plugin' ); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $agents as $slug => $agent ) : ?>
					<?php
					$row_class    = $agent['active'] ? 'active' : 'inactive';
					$nonce        = wp_create_nonce( 'agentic_agent_action' );
					$has_update   = isset( $available_updates[ $slug ] );
					$update_class = $has_update ? 'update-available' : '';
					?>
					<tr class="<?php echo esc_attr( $row_class . ' ' . $update_class ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
						<th scope="row" class="check-column">
							<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $slug ); ?>">
						</th>
						<td class="plugin-title column-primary">
							<strong><?php echo esc_html( $agent['name'] ); ?></strong>

							<?php if ( $has_update ) : ?>
								<div class="notice notice-warning inline" style="margin: 5px 0; padding: 5px 10px;">
									<p style="margin: 0;">
										<?php
										printf(
											/* translators: 1: current version, 2: new version */
											esc_html__( 'Update available: %1$s → %2$s', 'agentic-plugin' ),
											'<strong>' . esc_html( $available_updates[ $slug ]['current'] ) . '</strong>',
											'<strong>' . esc_html( $available_updates[ $slug ]['latest'] ) . '</strong>'
										);
										?>
									</p>
								</div>
							<?php endif; ?>

							<div class="row-actions visible">
								<?php if ( $has_update && ! empty( $agent['bundled'] ) === false ) : ?>
									<span class="update">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&action=update&agent=' . $slug . '&_wpnonce=' . $nonce ) ); ?>" style="color: #d63638; font-weight: 600;">
											<?php esc_html_e( 'Update Now', 'agentic-plugin' ); ?>
										</a> |
									</span>
								<?php endif; ?>

								<?php if ( $agent['active'] ) : ?>
									<span class="chat">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-chat&agent=' . $slug ) ); ?>" style="font-weight: 600;">
											<?php esc_html_e( 'Chat', 'agentic-plugin' ); ?>
										</a> |
									</span>
									<span class="deactivate">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&action=deactivate&agent=' . $slug . '&_wpnonce=' . $nonce ) ); ?>">
											<?php esc_html_e( 'Deactivate', 'agentic-plugin' ); ?>
										</a>
									</span>
								<?php else : ?>
									<span class="activate">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&action=activate&agent=' . $slug . '&_wpnonce=' . $nonce ) ); ?>">
											<?php esc_html_e( 'Activate', 'agentic-plugin' ); ?>
										</a> |
									</span>
									<span class="delete">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents&action=delete&agent=' . $slug . '&_wpnonce=' . $nonce ) ); ?>"
											class="delete"
											onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this agent?', 'agentic-plugin' ); ?>');">
											<?php esc_html_e( 'Delete', 'agentic-plugin' ); ?>
										</a>
									</span>
								<?php endif; ?>
							</div>
						</td>
						<td class="column-description desc">
							<div class="plugin-description">
								<p><?php echo esc_html( $agent['description'] ); ?></p>
							</div>
							<div class="plugin-meta">
								<?php if ( ! empty( $agent['version'] ) ) : ?>
									<span class="agent-version">
										<?php printf( esc_html__( 'Version %s', 'agentic-plugin' ), esc_html( $agent['version'] ) ); ?>
									</span>
									<span class="separator">|</span>
								<?php endif; ?>

								<?php if ( ! empty( $agent['author'] ) ) : ?>
									<span class="agent-author">
										<?php esc_html_e( 'By', 'agentic-plugin' ); ?>
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
										printf(
											esc_html__( 'Capabilities: %s', 'agentic-plugin' ),
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
					<?php esc_html_e( 'Agent', 'agentic-plugin' ); ?>
				</th>
				<th scope="col" class="manage-column column-description">
					<?php esc_html_e( 'Description', 'agentic-plugin' ); ?>
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
