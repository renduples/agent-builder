<?php
/**
 * Add New Agent Admin Page
 *
 * Similar to WordPress Add New Plugin page - browse and install agents
 * from the library.
 *
 * @package Agentic_Plugin
 * @since 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle install action.

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agentic-plugin' ) );
}

$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
$slug    = isset( $_GET['agent'] ) ? sanitize_text_field( wp_unslash( $_GET['agent'] ) ) : '';
$message = '';
$error   = '';

if ( 'install' === $action && $slug && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'agentic_install_agent' ) ) {
	$registry = Agentic_Agent_Registry::get_instance();
	$result   = $registry->install_agent( $slug );

	if ( is_wp_error( $result ) ) {
		$error = $result->get_error_message();
	} else {
		$message = __( 'Agent installed successfully.', 'agentic-plugin' );
	}
}

$registry   = Agentic_Agent_Registry::get_instance();
$categories = $registry->get_agent_categories();

// Get search/filter params.
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
$tab      = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'featured';

// Fetch library agents.
$library = $registry->get_library_agents(
	array(
		'search'   => $search,
		'category' => $category,
	)
);
?>

<div class="wrap agentic-add-agents-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Add Agents', 'agentic-plugin' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php echo esc_html( $message ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents' ) ); ?>">
					<?php esc_html_e( 'Go to Installed Agents', 'agentic-plugin' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Navigation Tabs -->
	<ul class="filter-links">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add&tab=featured' ) ); ?>"
				class="<?php echo ( $tab === 'featured' && empty( $category ) ) ? 'current' : ''; ?>">
				<?php esc_html_e( 'Featured', 'agentic-plugin' ); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add&tab=popular' ) ); ?>"
				class="<?php echo $tab === 'popular' ? 'current' : ''; ?>">
				<?php esc_html_e( 'Popular', 'agentic-plugin' ); ?>
			</a>
		</li>
		<?php foreach ( $categories as $cat => $count ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-agents-add&category=' . urlencode( $cat ) ) ); ?>"
					class="<?php echo $category === $cat ? 'current' : ''; ?>">
					<?php echo esc_html( $cat ); ?>
					<span class="count">(<?php echo esc_html( $count ); ?>)</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<!-- Search Box -->
	<form method="get" class="search-form search-plugins">
		<input type="hidden" name="page" value="agentic-agents-add">
		<p class="search-box">
			<label class="screen-reader-text" for="agent-search-input">
				<?php esc_html_e( 'Search Agents', 'agentic-plugin' ); ?>
			</label>
			<input type="search" id="agent-search-input" name="s"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php esc_attr_e( 'Search agents...', 'agentic-plugin' ); ?>"
					class="wp-filter-search">
			<input type="submit" id="search-submit" class="button hide-if-js"
					value="<?php esc_attr_e( 'Search Agents', 'agentic-plugin' ); ?>">
		</p>
	</form>

	<br class="clear">

	<?php if ( empty( $library['agents'] ) ) : ?>
		<div class="no-plugin-results">
			<?php if ( $search ) : ?>
				<p><?php esc_html_e( 'No agents found matching your search.', 'agentic-plugin' ); ?></p>
			<?php else : ?>
				<div class="agentic-empty-library">
					<h2><?php esc_html_e( 'Agent Library is Empty', 'agentic-plugin' ); ?></h2>
					<p><?php esc_html_e( 'No agents are available in the library yet. Check back soon or contribute your own agents!', 'agentic-plugin' ); ?></p>
					<p>
						<a href="https://github.com/renduples/agentic-plugin" target="_blank" class="button button-primary">
							<?php esc_html_e( 'Learn How to Create Agents', 'agentic-plugin' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<!-- Agent Cards Grid -->
		<div id="the-list" class="agentic-agent-cards">
			<?php foreach ( $library['agents'] as $slug => $agent ) : ?>
				<?php
				$install_url = wp_nonce_url(
					admin_url( 'admin.php?page=agentic-agents-add&action=install&agent=' . $slug ),
					'agentic_install_agent'
				);
				$icon        = ! empty( $agent['icon'] ) ? $agent['icon'] : '🤖';
				?>
				<div class="plugin-card plugin-card-<?php echo esc_attr( $slug ); ?>">
					<div class="plugin-card-top">
						<div class="plugin-card-top-header">
							<div class="agent-icon">
								<?php echo esc_html( $icon ); ?>
							</div>
							<div class="name column-name">
								<h3><?php echo esc_html( $agent['name'] ); ?></h3>
							</div>
						</div>
						<p class="authors">
							<cite>
								<?php esc_html_e( 'By', 'agentic-plugin' ); ?>
								<?php if ( ! empty( $agent['author_uri'] ) ) : ?>
									<a href="<?php echo esc_url( $agent['author_uri'] ); ?>" target="_blank">
										<?php echo esc_html( $agent['author'] ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $agent['author'] ); ?>
								<?php endif; ?>
							</cite>
						</p>
						<div class="desc column-description">
							<p><?php echo esc_html( $agent['description'] ); ?></p>
						</div>
						<div class="action-links">
							<?php if ( $agent['installed'] ) : ?>
								<button type="button" class="button button-disabled" disabled="disabled">
									<?php esc_html_e( 'Installed', 'agentic-plugin' ); ?>
								</button>
							<?php else : ?>
								<a class="install-now button button-primary"
									href="<?php echo esc_url( $install_url ); ?>">
									<?php esc_html_e( 'Install Now', 'agentic-plugin' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
					<div class="plugin-card-bottom">
						<div class="vers column-rating">
							<?php if ( ! empty( $agent['category'] ) ) : ?>
								<span class="agent-category-badge">
									<?php echo esc_html( $agent['category'] ); ?>
								</span>
							<?php endif; ?>
						</div>
						<div class="column-updated">
							<strong><?php esc_html_e( 'Version:', 'agentic-plugin' ); ?></strong>
							<?php echo esc_html( $agent['version'] ); ?>
						</div>
						<div class="column-compatibility">
							<?php if ( ! empty( $agent['capabilities'] ) ) : ?>
								<span class="agent-caps" title="<?php echo esc_attr( implode( ', ', $agent['capabilities'] ) ); ?>">
									<?php
									printf(
										esc_html( _n( '%d capability', '%d capabilities', count( $agent['capabilities'] ), 'agentic-plugin' ) ),
										count( $agent['capabilities'] )
									);
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( ! empty( $agent['tags'] ) ) : ?>
						<div class="plugin-card-tags">
							<?php foreach ( $agent['tags'] as $tag ) : ?>
								<span class="agent-tag"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $library['pages'] > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							esc_html( _n( '%s agent', '%s agents', $library['total'], 'agentic-plugin' ) ),
							number_format_i18n( $library['total'] )
						);
						?>
					</span>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<!-- Info Box: Creating Your Own Agent -->
	<div class="agentic-create-agent-info">
		<h3><?php esc_html_e( 'Create Your Own Agent', 'agentic-plugin' ); ?></h3>
		<p><?php esc_html_e( 'Agents are modular components that can be built by any developer. Like WordPress plugins, agents follow a standard structure:', 'agentic-plugin' ); ?></p>
		<pre><code>wp-content/agents/my-agent/
├── agent.php          # Main file with agent headers
├── includes/          # Agent logic
└── README.md          # Documentation</code></pre>
		<p>
			<strong><?php esc_html_e( 'Agent Headers:', 'agentic-plugin' ); ?></strong>
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
			<a href="https://github.com/renduples/agentic-plugin/wiki/Creating-Agents" target="_blank" class="button">
				<?php esc_html_e( 'View Documentation', 'agentic-plugin' ); ?>
			</a>
		</p>
	</div>
</div>

<style>
.agentic-add-agents-page .filter-links {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
	margin: 15px 0;
	padding: 0;
	list-style: none;
}

.agentic-add-agents-page .filter-links a {
	display: inline-block;
	padding: 8px 16px;
	background: #f0f0f1;
	border-radius: 4px;
	text-decoration: none;
	color: #50575e;
	font-size: 13px;
}

.agentic-add-agents-page .filter-links a:hover {
	background: #e0e0e0;
}

.agentic-add-agents-page .filter-links a.current {
	background: #0073aa;
	color: #fff;
}

.agentic-add-agents-page .search-form {
	float: right;
	margin-top: -50px;
}

.agentic-agent-cards {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.agentic-agent-cards .plugin-card {
	margin: 0;
	float: none;
	width: 100%;
	display: flex;
	flex-direction: column;
}

.agentic-agent-cards .plugin-card-top {
	display: block;
	padding: 20px;
}

.agentic-agent-cards .plugin-card-top-header {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	margin-bottom: 12px;
}

.agentic-agent-cards .agent-icon {
	width: 48px;
	height: 48px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 8px;
	font-size: 24px;
	flex-shrink: 0;
}

.agentic-agent-cards .name {
	flex: 1;
}

.agentic-agent-cards .name h3 {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	color: #1e1e1e;
}

.agentic-agent-cards .authors {
	margin: 4px 0 8px 0;
	font-size: 12px;
	color: #666;
}

.agentic-agent-cards .action-links {
	margin-top: 12px;
}

.plugin-card .action-links {
	position: absolute;
	top: 20px;
	right: 20px;
	width: 190px;
}

.agentic-agent-cards .desc {
	margin-top: 0;
}

.agentic-agent-cards .desc p {
	margin: 0 0 5px;
	word-wrap: break-word;
	overflow-wrap: break-word;
}

.agentic-agent-cards .desc p:first-child {
	line-height: 1.5;
}

.agentic-agent-cards .plugin-card-bottom {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12px 20px;
	background: #f9f9f9;
	border-top: 1px solid #dcdcde;
}

.agentic-agent-cards .plugin-card-tags {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	padding: 10px 20px;
	background: #f9f9f9;
	border-top: 1px solid #dcdcde;
}

.agentic-agent-cards .agent-tag {
	display: inline-block;
	padding: 2px 8px;
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 12px;
	font-size: 11px;
	color: #50575e;
}

.agentic-agent-cards .agent-tag:hover {
	background: #e0e0e0;
	cursor: pointer;
}

.agentic-agent-cards .agent-category-badge {
	display: inline-block;
	padding: 3px 8px;
	background: #e7e7e7;
	border-radius: 3px;
	font-size: 11px;
	text-transform: uppercase;
}

.agentic-empty-library {
	text-align: center;
	padding: 60px 20px;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	margin-top: 20px;
}

.agentic-empty-library h2 {
	margin-bottom: 10px;
}

.agentic-create-agent-info {
	margin-top: 40px;
	padding: 25px;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-left: 4px solid #0073aa;
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

	.agentic-agent-cards .plugin-card-top {
		grid-template-columns: 50px 1fr;
	}

	.agentic-agent-cards .action-links {
		grid-column: 2;
	}

	.agentic-agent-cards .desc {
		grid-column: 1 / 3;
	}
}
</style>
