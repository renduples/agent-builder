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
				$agentic_page_slug   = 'assistant-trainer' === $agentic_slug ? 'agentbuilder' : 'agentic-chat';
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

// Handle bulk actions.
if (
	isset( $_POST['bulk_action'], $_POST['checked'], $_POST['_wpnonce_bulk'] ) &&
	wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_bulk'] ) ), 'agentic_bulk_action' ) &&
	current_user_can( 'manage_options' )
) {
	$agentic_bulk_action  = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) );
	$agentic_bulk_slugs  = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['checked'] ) );
	$agentic_bulk_registry = Agentic_Agent_Registry::get_instance();
	$agentic_bulk_done   = 0;

	foreach ( $agentic_bulk_slugs as $agentic_bulk_slug ) {
		switch ( $agentic_bulk_action ) {
			case 'activate':
				if ( ! is_wp_error( $agentic_bulk_registry->activate_agent( $agentic_bulk_slug ) ) ) {
					++$agentic_bulk_done;
				}
				break;
			case 'deactivate':
				if ( ! is_wp_error( $agentic_bulk_registry->deactivate_agent( $agentic_bulk_slug ) ) ) {
					++$agentic_bulk_done;
				}
				break;
			case 'delete':
				if ( ! is_wp_error( $agentic_bulk_registry->delete_agent( $agentic_bulk_slug ) ) ) {
					++$agentic_bulk_done;
				}
				break;
		}
	}

	if ( $agentic_bulk_done ) {
		$agentic_message = sprintf(
			/* translators: 1: number of agents, 2: action label */
			_n( '%1$d agent %2$s.', '%1$d agents %2$s.', $agentic_bulk_done, 'agentbuilder' ),
			$agentic_bulk_done,
			$agentic_bulk_action . 'd'
		);
	}
}

// Handle agent zip upload.
$agentic_upload_message = '';
$agentic_upload_error   = '';

// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified immediately below inside the conditional.
if ( isset( $_FILES['agentzip'] ) && ! empty( $_FILES['agentzip']['name'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'agentic-agent-upload' ) ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		$agentic_upload_error = __( 'You do not have permission to upload agents.', 'agentbuilder' );
	} elseif ( ! str_ends_with( strtolower( sanitize_file_name( $_FILES['agentzip']['name'] ) ), '.zip' ) ) {
		$agentic_upload_error = __( 'The uploaded file is not a valid .zip archive.', 'agentbuilder' );
	} else {
		// Ensure filesystem functions are available.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$agentic_agents_dir = WP_CONTENT_DIR . '/agents';
		if ( ! is_dir( $agentic_agents_dir ) ) {
			wp_mkdir_p( $agentic_agents_dir );
		}

		$agentic_tmp_dir = $agentic_agents_dir . '/__upload_tmp_' . wp_generate_password( 8, false );
		wp_mkdir_p( $agentic_tmp_dir );

		// Validate and sanitize the uploaded file.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- is_uploaded_file() validates the file path directly from $_FILES, sanitization not required for validation.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- is_uploaded_file() validates the file, sanitization not required.
		if ( ! isset( $_FILES['agentzip']['tmp_name'] ) || ! is_uploaded_file( $_FILES['agentzip']['tmp_name'] ) ) {
			$agentic_upload_error = __( 'Invalid file upload.', 'agentbuilder' );
		} else {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- File path already validated by is_uploaded_file, do not sanitize as it can corrupt the path.
			$agentic_unzip = unzip_file( $_FILES['agentzip']['tmp_name'], $agentic_tmp_dir );

			if ( is_wp_error( $agentic_unzip ) ) {
				$agentic_upload_error = $agentic_unzip->get_error_message();
			} else {
				// Find agent.php — could be at root or inside a subfolder.
				$agentic_agent_file = null;
				$agentic_agent_root = null;

				if ( file_exists( $agentic_tmp_dir . '/agent.php' ) ) {
					$agentic_agent_file = $agentic_tmp_dir . '/agent.php';
					$agentic_agent_root = $agentic_tmp_dir;
				} else {
					// Check one level deep (zip contains a folder).
					$agentic_subdirs = glob( $agentic_tmp_dir . '/*', GLOB_ONLYDIR );
					foreach ( $agentic_subdirs as $agentic_subdir ) {
						if ( file_exists( $agentic_subdir . '/agent.php' ) ) {
							$agentic_agent_file = $agentic_subdir . '/agent.php';
							$agentic_agent_root = $agentic_subdir;
							break;
						}
					}
				}

				if ( ! $agentic_agent_file ) {
					$agentic_upload_error = __( 'The uploaded zip does not contain a valid agent. An agent.php file is required.', 'agentbuilder' );
				} else {
					// Read agent headers.
					$agentic_headers = array(
						'name'        => 'Agent Name',
						'version'     => 'Version',
						'description' => 'Description',
						'author'      => 'Author',
					);
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$agentic_agent_contents = file_get_contents( $agentic_agent_file );
					$agentic_parsed_headers = array();
					foreach ( $agentic_headers as $agentic_hkey => $agentic_hlabel ) {
						if ( preg_match( '/^\s*\*?\s*' . preg_quote( $agentic_hlabel, '/' ) . ':\s*(.+)$/mi', $agentic_agent_contents, $agentic_hmatch ) ) {
							$agentic_parsed_headers[ $agentic_hkey ] = trim( $agentic_hmatch[1] );
						}
					}

					if ( empty( $agentic_parsed_headers['name'] ) ) {
						$agentic_upload_error = __( 'The agent.php file is missing a required "Agent Name" header.', 'agentbuilder' );
					} else {
						// Derive slug from folder name or sanitize the agent name.
						$agentic_upload_slug = sanitize_title( basename( $agentic_agent_root ) );
						if ( '__upload_tmp_' === substr( $agentic_upload_slug, 0, 13 ) ) {
							$agentic_upload_slug = sanitize_title( $agentic_parsed_headers['name'] );
						}

						$agentic_dest = $agentic_agents_dir . '/' . $agentic_upload_slug;

						if ( is_dir( $agentic_dest ) ) {
							// Remove existing version for update.
							global $wp_filesystem;
							$wp_filesystem->delete( $agentic_dest, true );
						}

						rename( $agentic_agent_root, $agentic_dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Moving extracted directory; WP_Filesystem::move() does not support directory moves.

						// Stamp as uploaded (not user-created) so license gating can distinguish.
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
						file_put_contents( $agentic_dest . '/.uploaded', gmdate( 'c' ) );

						// --- Premium agent activation via .license file ---
						$agentic_license_file = $agentic_dest . '/.license';
						$agentic_activation_ok = true; // default: free agent, no activation needed

						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						if ( file_exists( $agentic_license_file ) ) {
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
							$agentic_lic_data = json_decode( file_get_contents( $agentic_license_file ), true );

							if ( ! empty( $agentic_lic_data['token'] ) && ! empty( $agentic_lic_data['agent'] ) ) {
								$agentic_activate_response = wp_remote_post(
									'https://agentic-plugin.com/wp-json/agentic-marketplace/v1/agents/activate-token',
									array(
										'body'      => array(
											'token'      => $agentic_lic_data['token'],
											'agent_slug' => $agentic_lic_data['agent'],
											'site_url'   => home_url(),
										),
										'timeout'   => 15,
										'sslverify' => true,
									)
								);

								if ( is_wp_error( $agentic_activate_response ) ) {
									$agentic_activation_ok = false;
									$agentic_upload_error  = sprintf(
										/* translators: %s: error message */
										__( 'Assistant installed but license activation failed: %s. Please re-install or contact support.', 'agentbuilder' ),
										$agentic_activate_response->get_error_message()
									);
								} else {
									$agentic_activate_http = wp_remote_retrieve_response_code( $agentic_activate_response );
									$agentic_activate_body = json_decode( wp_remote_retrieve_body( $agentic_activate_response ), true );

									if ( 200 === $agentic_activate_http && ! empty( $agentic_activate_body['success'] ) && ! empty( $agentic_activate_body['activation_hash'] ) ) {
										// Write .activation hash — domain-binds this install
										// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
										file_put_contents( $agentic_dest . '/.activation', $agentic_activate_body['activation_hash'] );
									} else {
										$agentic_activation_ok = false;
										$agentic_err_code      = $agentic_activate_body['code'] ?? 'unknown';
										$agentic_err_msgs      = array(
											'token_invalid' => __( 'License token is invalid. Please re-download from your agentic-plugin.com account.', 'agentbuilder' ),
											'token_expired' => __( 'Your agent license has expired. Please renew at agentic-plugin.com.', 'agentbuilder' ),
											'token_used'    => __( 'This license has already been activated on a different domain. Use your account to transfer it.', 'agentbuilder' ),
											'agent_mismatch' => __( 'The license token is for a different assistant.', 'agentbuilder' ),
										);
										$agentic_upload_error = $agentic_err_msgs[ $agentic_err_code ] ?? sprintf(
											/* translators: %s: error code */
											__( 'Activation error: %s. Please contact support.', 'agentbuilder' ),
											esc_html( $agentic_err_code )
										);
									}
								}
							} else {
								// Malformed .license file
								$agentic_activation_ok = false;
								$agentic_upload_error  = __( 'The .license file in this archive is malformed. Please re-download from your account.', 'agentbuilder' );
							}

							// If activation failed, remove the installed directory so a broken agent is not left behind.
							if ( ! $agentic_activation_ok ) {
								global $wp_filesystem;
								$wp_filesystem->delete( $agentic_dest, true );
							}
						}

						if ( $agentic_activation_ok ) {
							$agentic_upload_message = sprintf(
								/* translators: %s: Agent name */
								__( 'Agent "%s" has been installed successfully. You can now activate it below.', 'agentbuilder' ),
								$agentic_parsed_headers['name']
							);
						}
					}
				}
			}
		}
	}

	// Cleanup temp directory if it still exists.
	if ( is_dir( $agentic_tmp_dir ) ) {
		global $wp_filesystem;
		$wp_filesystem->delete( $agentic_tmp_dir, true );
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
	<a href="#" class="upload-view-toggle page-title-action" id="agentic-upload-toggle"><?php esc_html_e( 'Upload Assistant', 'agentbuilder' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( $agentic_upload_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $agentic_upload_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $agentic_upload_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $agentic_upload_error ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Upload Agent Form (hidden by default) -->
	<div class="upload-agent-wrap" id="agentic-upload-wrap" style="display: none;">
		<div class="upload-agent">
			<?php if ( \Agentic\License_Client::get_instance()->is_premium() ) : ?>
				<p class="install-help"><?php esc_html_e( 'If you have an agent in a .zip format, you may install it by uploading it here.', 'agentbuilder' ); ?></p>
				<form method="post" enctype="multipart/form-data" class="wp-upload-form" action="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents' ) ); ?>">
					<?php wp_nonce_field( 'agentic-agent-upload' ); ?>
					<label class="screen-reader-text" for="agentzip">
						<?php esc_html_e( 'Agent zip file', 'agentbuilder' ); ?>
					</label>
					<input type="file" id="agentzip" name="agentzip" accept=".zip" />
					<?php submit_button( __( 'Install Now', 'agentbuilder' ), 'primary', 'install-agent-submit', false ); ?>
				</form>
			<?php else : ?>
				<p class="install-help"><?php esc_html_e( 'A license is required to upload agents.', 'agentbuilder' ); ?></p>
				<p style="text-align: center; margin-top: 20px;">
					<a href="https://agentic-plugin.com/pricing/" target="_blank" class="button button-primary button-hero">
						<?php esc_html_e( 'Get a License', 'agentbuilder' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</div>

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
			</a> |
		</li>
		<li class="available">
			<a href="https://agentic-plugin.com/marketplace/" target="_blank">
				<?php
				$agentic_marketplace_count = get_transient( 'agentic_marketplace_agent_count' );
				if ( false === $agentic_marketplace_count ) {
					$agentic_marketplace_response = wp_remote_get( 'https://agentic-plugin.com/wp-json/agentic-marketplace/v1/agents?per_page=1', array( 'timeout' => 3, 'sslverify' => true ) );
					if ( ! is_wp_error( $agentic_marketplace_response ) ) {
						$agentic_marketplace_body = json_decode( wp_remote_retrieve_body( $agentic_marketplace_response ), true );
						$agentic_marketplace_count = isset( $agentic_marketplace_body['total'] ) ? (int) $agentic_marketplace_body['total'] : 0;
					}
					set_transient( 'agentic_marketplace_agent_count', $agentic_marketplace_count ?: 9, HOUR_IN_SECONDS );
				}
				?>
				<?php esc_html_e( 'Available', 'agentbuilder' ); ?>
				<span class="count">(<?php echo esc_html( $agentic_marketplace_count ?: 9 ); ?>)</span>
			</a>
		</li>
	</ul>

	<form method="post" id="bulk-action-form">
	<?php wp_nonce_field( 'agentic_bulk_action', '_wpnonce_bulk' ); ?>

	<!-- Tablenav Top -->
	<div class="tablenav top">
		<div class="alignleft actions bulkactions">
			<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'agentbuilder' ); ?></label>
			<select name="bulk_action" id="bulk-action-selector-top">
				<option value="-1"><?php esc_html_e( 'Bulk actions', 'agentbuilder' ); ?></option>
				<option value="activate"><?php esc_html_e( 'Activate', 'agentbuilder' ); ?></option>
				<option value="deactivate"><?php esc_html_e( 'Deactivate', 'agentbuilder' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Delete', 'agentbuilder' ); ?></option>
			</select>
			<input type="submit" id="doaction" class="button action" value="<?php esc_attr_e( 'Apply', 'agentbuilder' ); ?>" onclick="return agentic_confirm_bulk();">
		</div>
		<br class="clear">
	</div>

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
									$agentic_page_slug = 'assistant-trainer' === $agentic_slug ? 'agentbuilder' : 'agentic-chat';
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
									printf( esc_html__( 'Version %s', 'agentbuilder' ), esc_html( $agentic_agent['version'] ) );
									?>
									</span>
									<span class="separator">|</span>
								<?php endif; ?>

								<?php if ( ! empty( $agentic_agent['author'] ) ) : ?>
									<span class="agent-author">
										<?php esc_html_e( 'By', 'agentbuilder' ); ?>
										<?php if ( ! empty( $agentic_agent['author_uri'] ) ) : ?>
											<a href="<?php echo esc_url( $agentic_agent['author_uri'] ); ?>" target="_blank">
												<?php echo esc_html( $agentic_agent['author'] ); ?>
											</a>
										<?php else : ?>
											<?php echo esc_html( $agentic_agent['author'] ); ?>
										<?php endif; ?>
									</span>
									<span class="separator">|</span>
								<?php endif; ?>

								<?php if ( ! empty( $agentic_agent['category'] ) ) : ?>
									<span class="agent-category">
										<?php echo esc_html( $agentic_agent['category'] ); ?>
									</span>
									<span class="separator">|</span>
								<?php endif; ?>

								<?php if ( ! empty( $agentic_agent['capabilities'] ) ) : ?>
									<span class="agent-capabilities">
										<?php
										/* translators: %s: comma-separated list of agent capabilities */                                       printf(
											esc_html__( 'Capabilities: %s', 'agentbuilder' ),
											esc_html( implode( ', ', $agentic_agent['capabilities'] ) )
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

	<!-- Tablenav Bottom -->
	<div class="tablenav bottom">
		<div class="alignleft actions bulkactions">
			<label for="bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'agentbuilder' ); ?></label>
			<select name="bulk_action" id="bulk-action-selector-bottom">
				<option value="-1"><?php esc_html_e( 'Bulk actions', 'agentbuilder' ); ?></option>
				<option value="activate"><?php esc_html_e( 'Activate', 'agentbuilder' ); ?></option>
				<option value="deactivate"><?php esc_html_e( 'Deactivate', 'agentbuilder' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Delete', 'agentbuilder' ); ?></option>
			</select>
			<input type="submit" id="doaction2" class="button action" value="<?php esc_attr_e( 'Apply', 'agentbuilder' ); ?>" onclick="return agentic_confirm_bulk();">
		</div>
		<br class="clear">
	</div>

	</form><!-- end bulk-action-form -->
</div>

<script>
(function () {
	var toggle = document.getElementById( 'agentic-upload-toggle' );
	var wrap   = document.getElementById( 'agentic-upload-wrap' );

	if ( toggle && wrap ) {
		toggle.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var isVisible = 'none' !== wrap.style.display;
			wrap.style.display = isVisible ? 'none' : 'block';
		} );

		<?php if ( $agentic_upload_error ) : ?>
		// Show upload form when there was an upload error.
		wrap.style.display = 'block';
		<?php endif; ?>
	}

	// Keep top/bottom bulk selects in sync.
	var selTop    = document.getElementById( 'bulk-action-selector-top' );
	var selBottom = document.getElementById( 'bulk-action-selector-bottom' );
	if ( selTop && selBottom ) {
		selTop.addEventListener( 'change', function () { selBottom.value = selTop.value; } );
		selBottom.addEventListener( 'change', function () { selTop.value = selBottom.value; } );
	}

	// Keep top/bottom checkboxes in sync.
	var cbAll1 = document.getElementById( 'cb-select-all-1' );
	var cbAll2 = document.getElementById( 'cb-select-all-2' );
	function syncCheckAll( from, to ) {
		if ( from && to ) {
			from.addEventListener( 'change', function () {
				to.checked = from.checked;
				document.querySelectorAll( '#the-list input[type="checkbox"]' ).forEach( function ( cb ) {
					cb.checked = from.checked;
				} );
			} );
		}
	}
	syncCheckAll( cbAll1, cbAll2 );
	syncCheckAll( cbAll2, cbAll1 );
})();

function agentic_confirm_bulk() {
	var sel = document.getElementById( 'bulk-action-selector-top' );
	if ( ! sel || sel.value === '-1' ) {
		alert( '<?php echo esc_js( __( 'Please select a bulk action.', 'agentbuilder' ) ); ?>' );
		return false;
	}
	var checked = document.querySelectorAll( '#the-list input[type="checkbox"]:checked' );
	if ( ! checked.length ) {
		alert( '<?php echo esc_js( __( 'Please select at least one agent.', 'agentbuilder' ) ); ?>' );
		return false;
	}
	if ( sel.value === 'delete' ) {
		return confirm( '<?php echo esc_js( __( 'Are you sure you want to delete the selected agents?', 'agentbuilder' ) ); ?>' );
	}
	return true;
}
</script>

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

.agentic-agents-page .upload-agent-wrap {
	overflow: hidden;
}

.agentic-agents-page .upload-agent {
	box-sizing: border-box;
	display: block;
	margin: 0;
	padding: 50px 0;
	width: 100%;
	overflow: hidden;
	position: relative;
	text-align: center;
}

.agentic-agents-page .upload-agent .install-help {
	color: #50575e;
	font-size: 18px;
	font-style: normal;
	margin: 0;
	padding: 0;
	text-align: center;
}

.agentic-agents-page .upload-agent .wp-upload-form {
	background: #f6f7f7;
	border: 1px solid #c3c4c7;
	padding: 30px;
	margin: 30px auto;
	display: inline-flex;
	justify-content: space-between;
	align-items: center;
}

.agentic-agents-page .upload-agent .wp-upload-form input[type="file"] {
	margin-right: 10px;
}
</style>
