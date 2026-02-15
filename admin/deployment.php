<?php
/**
 * Agentic Agent Deployment Page
 *
 * Unified management of all agent invocation methods: Scheduled Tasks,
 * Event Listeners, and Shortcodes. Each method is presented as a tab.
 *
 * @package    Agent_Builder
 * @subpackage Admin
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      1.7.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agent-builder' ) );
}

// Determine active tab.
$agentic_active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'shortcodes' ) );
if ( ! in_array( $agentic_active_tab, array( 'scheduled-tasks', 'event-listeners', 'shortcodes' ), true ) ) {
	$agentic_active_tab = 'shortcodes';
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Agent Deployment', 'agent-builder' ); ?></h1>
	<p><?php esc_html_e( 'Manage how agents are invoked: embed them on your site with shortcodes, schedule recurring tasks, or react to WordPress events.', 'agent-builder' ); ?></p>

	<nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-deployment&tab=shortcodes' ) ); ?>"
			class="nav-tab <?php echo 'shortcodes' === $agentic_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Shortcodes', 'agent-builder' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-deployment&tab=scheduled-tasks' ) ); ?>"
			class="nav-tab <?php echo 'scheduled-tasks' === $agentic_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Scheduled Tasks', 'agent-builder' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-deployment&tab=event-listeners' ) ); ?>"
			class="nav-tab <?php echo 'event-listeners' === $agentic_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Event Listeners', 'agent-builder' ); ?>
		</a>
	</nav>

	<?php
	switch ( $agentic_active_tab ) {
		case 'scheduled-tasks':
			include AGENTIC_PLUGIN_DIR . 'admin/deployment-scheduled-tasks.php';
			break;

		case 'event-listeners':
			include AGENTIC_PLUGIN_DIR . 'admin/deployment-event-listeners.php';
			break;

		case 'shortcodes':
		default:
			include AGENTIC_PLUGIN_DIR . 'admin/deployment-shortcodes.php';
			break;
	}
	?>
</div>
