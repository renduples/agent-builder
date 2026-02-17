<?php
/**
 * Add New Agent Admin Page
 *
 * Similar to WordPress Add New Plugin page - browse and install agents
 * from the library.
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

// Handle install action.

if ( ! current_user_can( 'read' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agentbuilder' ) );
}

$agentic_agent_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
$agentic_slug         = isset( $_GET['agent'] ) ? sanitize_text_field( wp_unslash( $_GET['agent'] ) ) : '';
$agentic_message      = '';
$agentic_agent_error  = '';

if ( 'install' === $agentic_agent_action && $agentic_slug && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'agentic_install_agent' ) ) {
	$agentic_registry = Agentic_Agent_Registry::get_instance();
	$agentic_result   = $agentic_registry->install_agent( $agentic_slug );

	if ( is_wp_error( $agentic_result ) ) {
		$agentic_agent_error = $agentic_result->get_error_message();
	} else {
		$agentic_message = __( 'Agent installed successfully.', 'agentbuilder' );
	}
}

// Handle agent zip upload.
$agentic_upload_message = '';
$agentic_upload_error   = '';

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
						$agentic_upload_message = sprintf(
						/* translators: %s: Agent name */
							__( 'Agent "%s" has been installed successfully.', 'agentbuilder' ),
							$agentic_parsed_headers['name']
						);

						// Clear registry cache by forcing a refresh.
						$agentic_registry_inst = Agentic_Agent_Registry::get_instance();
						$agentic_registry_inst->get_installed_agents( true );
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

$agentic_registry   = Agentic_Agent_Registry::get_instance();
$agentic_categories = $agentic_registry->get_agent_categories();

// Get search/filter params.
$agentic_search_term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$agentic_category    = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
$agentic_current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

// Fetch library agents.
$agentic_library = $agentic_registry->get_library_agents(
	array(
		'search'   => $agentic_search_term,
		'category' => $agentic_category,
	)
);
?>

<div class="wrap agentic-add-agents-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Add Agents', 'agentbuilder' ); ?></h1>

	<a href="#" class="upload-view-toggle page-title-action" id="agentic-upload-toggle">
		<span class="upload"><?php esc_html_e( 'Upload Agent', 'agentbuilder' ); ?></span>
		<span class="browse"><?php esc_html_e( 'Browse Agents', 'agentbuilder' ); ?></span>
	</a>

	<hr class="wp-header-end">

		<p class="agentic-add-agents-description">
		<?php
		echo wp_kses(
			__( 'AI Agents extend and expand the functionality of WordPress. You may install AI Agents from the library right on this page, or upload an Agent in .zip format by clicking the button above.', 'agentbuilder' ),
			array( 'a' => array( 'href' => array() ) )
		);
		?>
	</p>

	<!-- Upload Agent Form (hidden by default) -->
	<div class="upload-agent-wrap" style="display: none;">
		<div class="upload-agent">
			<p class="install-help"><?php esc_html_e( 'If you have an agent in a .zip format, you may install it by uploading it here.', 'agentbuilder' ); ?></p>
			<form method="post" enctype="multipart/form-data" class="wp-upload-form" action="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add' ) ); ?>">
				<?php wp_nonce_field( 'agentic-agent-upload' ); ?>
				<label class="screen-reader-text" for="agentzip">
					<?php esc_html_e( 'Agent zip file', 'agentbuilder' ); ?>
				</label>
				<input type="file" id="agentzip" name="agentzip" accept=".zip" />
				<?php submit_button( __( 'Install Now', 'agentbuilder' ), 'primary', 'install-agent-submit', false ); ?>
			</form>
		</div>
	</div>

	<?php if ( $agentic_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php echo esc_html( $agentic_message ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents' ) ); ?>">
					<?php esc_html_e( 'Go to Installed Agents', 'agentbuilder' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $agentic_agent_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $agentic_agent_error ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $agentic_upload_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php echo esc_html( $agentic_upload_message ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents' ) ); ?>">
					<?php esc_html_e( 'Go to Installed Agents', 'agentbuilder' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $agentic_upload_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $agentic_upload_error ); ?></p>
		</div>
	<?php endif; ?>

	<div class="agentic-browse-content">
	<!-- Navigation Tabs -->
	<ul class="filter-links">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add&tab=featured' ) ); ?>"
				class="<?php echo ( '' === $agentic_current_tab && empty( $agentic_category ) ) ? 'current' : ''; ?>">
				<?php esc_html_e( 'Featured', 'agentbuilder' ); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add&tab=popular' ) ); ?>"
				class="<?php echo 'popular' === $agentic_current_tab ? 'current' : ''; ?>">
				<?php esc_html_e( 'Popular', 'agentbuilder' ); ?>
			</a>
		</li>
		<?php foreach ( $agentic_categories as $agentic_cat_name => $agentic_count ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add&category=' . rawurlencode( $agentic_cat_name ) ) ); ?>"
					class="<?php echo $agentic_category === $agentic_cat_name ? 'current' : ''; ?>">
					<?php echo esc_html( $agentic_cat_name ); ?>
					<span class="count">(<?php echo esc_html( $agentic_count ); ?>)</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<!-- Search Box -->
	<form method="get" class="search-form search-plugins">
		<input type="hidden" name="page" value="agentic-agents-add">
		<p class="search-box">
			<label class="screen-reader-text" for="agent-search-input">
				<?php esc_html_e( 'Search Agents', 'agentbuilder' ); ?>
			</label>
			<input type="search" id="agent-search-input" name="s"
					value="<?php echo esc_attr( $agentic_search_term ); ?>"
					placeholder="<?php esc_attr_e( 'Search agents...', 'agentbuilder' ); ?>"
					class="wp-filter-search">
			<input type="submit" id="search-submit" class="button hide-if-js"
					value="<?php esc_attr_e( 'Search Agents', 'agentbuilder' ); ?>">
		</p>
	</form>

	<br class="clear">

	<?php if ( empty( $agentic_library['agents'] ) ) : ?>
		<div class="no-plugin-results">
			<?php if ( $agentic_search_term ) : ?>
				<p><?php esc_html_e( 'No agents found matching your search.', 'agentbuilder' ); ?></p>
			<?php else : ?>
				<div class="agentic-empty-library">
					<h2><?php esc_html_e( 'Agent Library is Empty', 'agentbuilder' ); ?></h2>
					<p><?php esc_html_e( 'No agents are available in the library yet. Check back soon or contribute your own agents!', 'agentbuilder' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<!-- Agent Cards Grid -->
		<div id="the-list" class="agentic-agent-cards">
			<?php foreach ( $agentic_library['agents'] as $agentic_slug => $agentic_agent ) : ?>
				<?php
				$agentic_install_url = wp_nonce_url(
					admin_url( 'admin.php?page=agentic-agents-add&action=install&agent=' . $agentic_slug ),
					'agentic_install_agent'
				);
				$agentic_icon        = ! empty( $agentic_agent['icon'] ) ? $agentic_agent['icon'] : '🤖';
				?>
				<div class="plugin-card plugin-card-<?php echo esc_attr( $agentic_slug ); ?>">
					<div class="plugin-card-top">
						<div class="name column-name">
							<h3>
								<?php echo esc_html( $agentic_agent['name'] ); ?>
								<span class="plugin-icon agent-icon-emoji"><?php echo esc_html( $agentic_icon ); ?></span>
							</h3>
						</div>
						<div class="action-links">
							<ul class="plugin-action-buttons">
								<li>
									<?php if ( $agentic_agent['installed'] ) : ?>
										<button type="button" class="button button-disabled" disabled="disabled">
											<?php esc_html_e( 'Installed', 'agentbuilder' ); ?>
										</button>
									<?php else : ?>
										<a class="install-now button" href="<?php echo esc_url( $agentic_install_url ); ?>">
											<?php esc_html_e( 'Install Now', 'agentbuilder' ); ?>
										</a>
									<?php endif; ?>
								</li>
								<li>
									<a href="#" class="agent-more-details" data-slug="<?php echo esc_attr( $agentic_slug ); ?>">
										<?php esc_html_e( 'More Details', 'agentbuilder' ); ?>
									</a>
								</li>
							</ul>
						</div>
						<div class="desc column-description">
							<p><?php echo esc_html( $agentic_agent['description'] ); ?></p>
							<p class="authors">
								<cite>
									<?php esc_html_e( 'By', 'agentbuilder' ); ?>
									<?php if ( ! empty( $agentic_agent['author_uri'] ) ) : ?>
										<a href="<?php echo esc_url( $agentic_agent['author_uri'] ); ?>" target="_blank">
											<?php echo esc_html( $agentic_agent['author'] ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $agentic_agent['author'] ); ?>
									<?php endif; ?>
								</cite>
							</p>
						</div>
					</div>
					<div class="plugin-card-bottom">
						<div class="vers column-rating">
							<?php if ( ! empty( $agentic_agent['category'] ) ) : ?>
								<span class="agent-category-badge">
									<?php echo esc_html( $agentic_agent['category'] ); ?>
								</span>
							<?php endif; ?>
						</div>
						<div class="column-updated">
							<strong><?php esc_html_e( 'Version:', 'agentbuilder' ); ?></strong>
							<?php echo esc_html( $agentic_agent['version'] ); ?>
						</div>
						<div class="column-downloaded">
							<?php if ( ! empty( $agentic_agent['capabilities'] ) ) : ?>
								<?php
								printf(
									/* translators: %d: Number of capabilities */
									esc_html( _n( '%d Capability', '%d Capabilities', count( $agentic_agent['capabilities'] ), 'agentbuilder' ) ),
									esc_html( count( $agentic_agent['capabilities'] ) )
								);
								?>
							<?php endif; ?>
						</div>
						<div class="column-compatibility">
							<?php if ( ! empty( $agentic_agent['tags'] ) ) : ?>
								<?php foreach ( array_slice( $agentic_agent['tags'], 0, 3 ) as $agentic_agent_tag ) : ?>
									<span class="agent-tag"><?php echo esc_html( $agentic_agent_tag ); ?></span>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $agentic_library['pages'] > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %s: Number of agents */
							esc_html( _n( '%s agent', '%s agents', $agentic_library['total'], 'agentbuilder' ) ),
							esc_html( number_format_i18n( $agentic_library['total'] ) )
						);
						?>
					</span>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	</div><!-- /.agentic-browse-content -->

	<!-- Info Box: Creating Your Own Agent -->
	<div class="agentic-create-agent-info">
		<h3><?php esc_html_e( 'Create Your Own Agent', 'agentbuilder' ); ?></h3>
		<p><?php esc_html_e( 'Agents are modular components that can be built by any developer. Like WordPress plugins, agents follow a standard structure:', 'agentbuilder' ); ?></p>
		<pre><code>wp-content/agents/my-agent/
├── agent.php                       # Main file with agent headers &amp; class
└── templates/
	└── system-prompt.txt           # System prompt for the AI provider</code></pre>
		<p>
			<strong><?php esc_html_e( 'Agent Headers:', 'agentbuilder' ); ?></strong>
		</p>
		<pre><code>&lt;?php
/**
 * Agent Name: My Custom Agent
 * Version: 1.0.0
 * Description: A helpful agent that does something cool.
 * Author: Your Name
 * Category: Content
 * Capabilities: read_posts, create_posts
 */</code></pre>
		<p>
			<a href="https://agentic-plugin.com/documentation/" target="_blank" class="button">
				<?php esc_html_e( 'View Documentation', 'agentbuilder' ); ?>
			</a>
		</p>
	</div>
</div>

<style>
.agentic-add-agents-page .agentic-add-agents-description {
	font-size: 13px;
	color: #50575e;
	margin: 15px 0 10px;
}

.agentic-add-agents-page .upload-view-toggle .browse {
	display: none;
}

.agentic-add-agents-page.show-upload-view .upload-view-toggle .upload {
	display: none;
}

.agentic-add-agents-page.show-upload-view .upload-view-toggle .browse {
	display: inline;
}

.upload-agent-wrap {
	overflow: hidden;
}

.upload-agent {
	box-sizing: border-box;
	display: block;
	margin: 0;
	padding: 50px 0;
	width: 100%;
	overflow: hidden;
	position: relative;
	text-align: center;
}

.upload-agent .install-help {
	color: #50575e;
	font-size: 18px;
	font-style: normal;
	margin: 0;
	padding: 0;
	text-align: center;
}

.upload-agent .wp-upload-form {
	background: #f6f7f7;
	border: 1px solid #c3c4c7;
	padding: 30px;
	margin: 30px auto;
	display: inline-flex;
	justify-content: space-between;
	align-items: center;
}

.upload-agent .wp-upload-form input[type="file"] {
	margin-right: 10px;
}

.agentic-add-agents-page .filter-links {
	display: flex;
	gap: 0;
	flex-wrap: wrap;
	margin: 0 0 15px;
	padding: 0;
	list-style: none;
	border-bottom: 1px solid #c3c4c7;
}

.agentic-add-agents-page .filter-links li {
	margin: 0;
}

.agentic-add-agents-page .filter-links a {
	display: inline-block;
	padding: 4px 14px;
	text-decoration: none;
	color: #50575e;
	font-size: 13px;
	line-height: 2;
	border: 1px solid transparent;
	border-bottom: none;
	background: none;
	border-radius: 0;
}

.agentic-add-agents-page .filter-links a:hover {
	color: #135e96;
}

.agentic-add-agents-page .filter-links a.current {
	background: #f0f0f1;
	border-color: #c3c4c7;
	color: #000;
	font-weight: 600;
	margin-bottom: -1px;
	padding-bottom: 5px;
}

.agentic-add-agents-page .search-form {
	float: right;
	margin-top: -40px;
}

/* Agent cards — mirror WP plugin-card layout */
.agentic-agent-cards {
	display: flex;
	flex-wrap: wrap;
}

.agentic-agent-cards .plugin-card {
	float: left;
	margin: 0 8px 16px;
	width: 48.5%;
	width: calc(50% - 8px);
	background-color: #fff;
	border: 1px solid #dcdcde;
	box-sizing: border-box;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}

.agentic-agent-cards .plugin-card:nth-child(odd) {
	clear: both;
	margin-left: 0;
}

.agentic-agent-cards .plugin-card:nth-child(even) {
	margin-right: 0;
}

@media screen and (min-width: 1100px) {
	.agentic-agent-cards .plugin-card {
		width: 30%;
		width: calc(33.1% - 8px);
	}

	.agentic-agent-cards .plugin-card:nth-child(odd) {
		clear: none;
		margin-left: 8px;
	}

	.agentic-agent-cards .plugin-card:nth-child(even) {
		margin-right: 8px;
	}

	.agentic-agent-cards .plugin-card:nth-child(3n+1) {
		clear: both;
		margin-left: 0;
	}

	.agentic-agent-cards .plugin-card:nth-child(3n) {
		margin-right: 0;
	}
}

.agentic-agent-cards .plugin-card-top {
	position: relative;
	padding: 20px 20px 10px;
	min-height: 135px;
}

.agentic-agent-cards .plugin-card h3 {
	margin: 0 12px 12px 0;
	font-size: 18px;
	line-height: 1.3;
}

.agentic-agent-cards .name.column-name,
.agentic-agent-cards .desc.column-description > p,
.agentic-agent-cards .desc.column-description .authors {
	margin-left: 148px;
}

@media (min-width: 1101px) {
	.agentic-agent-cards .name.column-name,
	.agentic-agent-cards .desc.column-description > p,
	.agentic-agent-cards .desc.column-description .authors {
		margin-right: 128px;
	}
}

@media (max-width: 1100px) {
	.agentic-agent-cards .name.column-name,
	.agentic-agent-cards .desc.column-description > p,
	.agentic-agent-cards .desc.column-description .authors {
		margin-left: 0;
	}

	.agentic-agent-cards .agent-icon-emoji {
		display: none;
	}
}

.agentic-agent-cards .desc.column-description {
	display: flex;
	flex-direction: column;
	justify-content: flex-start;
}

.agentic-agent-cards .desc.column-description > p {
	margin-top: 0;
}

.agentic-agent-cards .authors {
	margin: 0 0 8px 0;
	font-size: 13px;
	color: #646970;
}

/* Agent icon — emoji square mimicking the 128x128 plugin icon */
.agentic-agent-cards .agent-icon-emoji {
	position: absolute;
	top: 20px;
	left: 20px;
	width: 128px;
	height: 128px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 4px;
	font-size: 56px;
	line-height: 1;
}

/* Action links — top right, matching WP */
.agentic-agent-cards .action-links {
	position: absolute;
	top: 20px;
	right: 20px;
	width: 120px;
}

.agentic-agent-cards .plugin-action-buttons {
	clear: right;
	float: right;
	margin: 0;
	padding: 0;
	list-style: none;
	text-align: right;
}

.agentic-agent-cards .plugin-action-buttons li {
	margin-bottom: 10px;
}

/* Bottom bar — matches WP plugin-card-bottom */
.agentic-agent-cards .plugin-card-bottom {
	clear: both;
	padding: 12px 20px;
	background-color: #f6f7f7;
	border-top: 1px solid #dcdcde;
	overflow: hidden;
}

.agentic-agent-cards .vers.column-rating {
	float: left;
	line-height: 1.76923076;
}

.agentic-agent-cards .column-rating,
.agentic-agent-cards .column-updated {
	margin-bottom: 4px;
}

.agentic-agent-cards .column-rating,
.agentic-agent-cards .column-downloaded {
	float: left;
	clear: left;
	max-width: 180px;
}

.agentic-agent-cards .column-updated,
.agentic-agent-cards .column-compatibility {
	text-align: right;
	float: right;
	clear: right;
	max-width: 260px;
}

.agentic-agent-cards .agent-category-badge {
	display: inline-block;
	padding: 2px 8px;
	background: #2271b1;
	color: #fff;
	border-radius: 3px;
	font-size: 11px;
	text-transform: uppercase;
	font-weight: 600;
}

.agentic-agent-cards .agent-tag {
	display: inline-block;
	padding: 1px 6px;
	background: #f0f0f1;
	border: 1px solid #dcdcde;
	border-radius: 3px;
	font-size: 11px;
	color: #50575e;
	margin-right: 2px;
}

.agentic-empty-library {
	text-align: center;
	padding: 60px 20px;
	color: #646970;
	font-size: 18px;
	font-style: normal;
	margin: 0;
}

.agentic-empty-library h2 {
	margin-bottom: 10px;
}

.no-plugin-results {
	width: 100%;
}

.agentic-create-agent-info {
	margin-top: 40px;
	padding: 25px;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-left: 4px solid #2271b1;
}

.agentic-create-agent-info h3 {
	margin-top: 0;
}

.agentic-create-agent-info pre {
	background: #23282d;
	color: #eee;
	padding: 15px;
	border-radius: 4px;
	overflow-x: auto;
}

.agentic-create-agent-info code {
	font-size: 12px;
}

@media screen and (max-width: 782px) {
	.agentic-add-agents-page .search-form {
		float: none;
		margin: 15px 0;
	}

	.agentic-agent-cards .plugin-card {
		width: 100%;
		margin-left: 0;
		margin-right: 0;
	}

	.agentic-agent-cards .plugin-card:nth-child(odd) {
		clear: none;
	}
}
</style>

<script>
(function() {
	var toggle = document.getElementById('agentic-upload-toggle');
	var wrap = document.querySelector('.upload-agent-wrap');
	var page = document.querySelector('.agentic-add-agents-page');
	var browseContent = document.querySelector('.agentic-browse-content');

	if ( toggle && wrap && page ) {
		toggle.addEventListener('click', function(e) {
			e.preventDefault();
			var isShowing = page.classList.contains('show-upload-view');
			if ( isShowing ) {
				page.classList.remove('show-upload-view');
				wrap.style.display = 'none';
				if ( browseContent ) browseContent.style.display = '';
			} else {
				page.classList.add('show-upload-view');
				wrap.style.display = 'block';
				if ( browseContent ) browseContent.style.display = 'none';
			}
		});

		<?php if ( $agentic_upload_error ) : ?>
		// If there was an upload error, show the upload form.
		page.classList.add('show-upload-view');
		wrap.style.display = 'block';
		if ( browseContent ) browseContent.style.display = 'none';
		<?php endif; ?>
	}
})();
</script>
