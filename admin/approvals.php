<?php
/**
 * Agentic Git Branches Page
 *
 * Security note: Git command execution is disabled to avoid server-side command execution risks.
 * This page now only displays guidance and a notice.
 *
 * @package Agentic_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$repo_path = get_option( 'agentic_repo_path', ABSPATH );

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'agentic-core' ) );
}

// Git operations are intentionally disabled to avoid command execution on the server.
// Administrators should review and manage branches via a secure terminal instead.
$branches       = array();
$current_branch = sanitize_text_field( get_option( 'agentic_main_branch', 'main' ) );
$git_disabled   = true;
?>
<div class="wrap">
	<h1>Agent Code Proposals</h1>
	<p>Review code changes proposed by the Developer Agent. Each change is on its own git branch.</p>

	<?php if ( ! empty( $git_disabled ) ) : ?>
		<div class="notice notice-warning">
			<p><?php echo esc_html__( 'Git branch operations are disabled in this environment. Use a secure terminal to review and merge agent branches.', 'agentic-core' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="notice notice-info">
		<p><?php echo esc_html__( 'No automated branch actions are available here. Use the commands below in a trusted shell to review agent proposals.', 'agentic-core' ); ?></p>
	</div>

	<h2>Git Workflow</h2>
	<p>The Developer Agent uses git branches for code proposals:</p>
	<ul>
		<li><strong>Documentation updates</strong> (.md, .txt, .rst) are committed directly to the current branch</li>
		<li><strong>Code changes</strong> (.php, .js, .css, etc.) are committed to a new <code>agent/*</code> branch</li>
	</ul>
	<p>To review a proposal from the command line:</p>
	<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
# View the diff
git diff <?php echo esc_html( $current_branch ); ?>...agent/&lt;branch-name&gt;

# Merge if approved
git merge agent/&lt;branch-name&gt;

# Delete the branch after merging
git branch -d agent/&lt;branch-name&gt;
	</pre>
</div>
