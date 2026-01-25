<?php
/**
 * Agentic Git Branches Page
 *
 * @package Agentic_WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$repo_path = get_option( 'agentic_repo_path', ABSPATH );

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'agentic-core' ) );
}


// Get agent branches
$branches = [];
$cwd = getcwd();
chdir( $repo_path );
exec( 'git branch --list "agent/*" 2>/dev/null', $branch_output );
$current_branch = trim( shell_exec( 'git rev-parse --abbrev-ref HEAD 2>/dev/null' ) ?? '' );

foreach ( $branch_output as $branch ) {
    $branch_name = trim( str_replace( '*', '', $branch ) );
    if ( empty( $branch_name ) ) continue;
    
    // Get branch info
    $log = shell_exec( 'git log -1 --format="%s|%ar|%H" ' . escapeshellarg( $branch_name ) . ' 2>/dev/null' );
    $parts = explode( '|', trim( $log ?? '' ) );
    
    $branches[] = [
        'name'    => $branch_name,
        'message' => $parts[0] ?? '',
        'date'    => $parts[1] ?? '',
        'hash'    => $parts[2] ?? '',
    ];
}
chdir( $cwd );

// Handle merge/delete actions
if ( isset( $_POST['agentic_branch_action'] ) && check_admin_referer( 'agentic_branch_nonce' ) ) {
    $branch = sanitize_text_field( $_POST['branch_name'] ?? '' );
    $action = sanitize_text_field( $_POST['agentic_branch_action'] );
    
    if ( $branch && strpos( $branch, 'agent/' ) === 0 ) {
        chdir( $repo_path );
        if ( $action === 'merge' ) {
            exec( 'git merge ' . escapeshellarg( $branch ) . ' 2>&1', $output, $result );
            if ( $result === 0 ) {
                exec( 'git branch -d ' . escapeshellarg( $branch ) . ' 2>&1' );
                echo '<div class="notice notice-success"><p>Branch merged and deleted successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Merge failed: ' . esc_html( implode( "\n", $output ) ) . '</p></div>';
            }
        } elseif ( $action === 'delete' ) {
            exec( 'git branch -D ' . escapeshellarg( $branch ) . ' 2>&1', $output, $result );
            if ( $result === 0 ) {
                echo '<div class="notice notice-success"><p>Branch deleted.</p></div>';
            }
        }
        chdir( $cwd );
        
        // Refresh branch list
        chdir( $repo_path );
        $branches = [];
        exec( 'git branch --list "agent/*" 2>/dev/null', $branch_output );
        foreach ( $branch_output as $b ) {
            $bn = trim( str_replace( '*', '', $b ) );
            if ( empty( $bn ) ) continue;
            $log = shell_exec( 'git log -1 --format="%s|%ar|%H" ' . escapeshellarg( $bn ) . ' 2>/dev/null' );
            $parts = explode( '|', trim( $log ?? '' ) );
            $branches[] = [ 'name' => $bn, 'message' => $parts[0] ?? '', 'date' => $parts[1] ?? '', 'hash' => $parts[2] ?? '' ];
        }
        chdir( $cwd );
    }
}
?>
<div class="wrap">
    <h1>Agent Code Proposals</h1>
    <p>Review code changes proposed by the Developer Agent. Each change is on its own git branch.</p>

    <?php if ( empty( $branches ) ) : ?>
        <div class="notice notice-info">
            <p>No pending code proposals. When the agent suggests code changes, they will appear here as git branches.</p>
        </div>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Branch</th>
                    <th>Commit Message</th>
                    <th>Created</th>
                    <th>Review</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $branches as $branch ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $branch['name'] ); ?></code></td>
                    <td><?php echo esc_html( $branch['message'] ); ?></td>
                    <td><?php echo esc_html( $branch['date'] ); ?></td>
                    <td>
                        <code style="font-size: 11px;">git diff <?php echo esc_html( $current_branch ); ?>...<?php echo esc_html( $branch['name'] ); ?></code>
                    </td>
                    <td>
                        <form method="post" style="display: inline-block;">
                            <?php wp_nonce_field( 'agentic_branch_nonce' ); ?>
                            <input type="hidden" name="branch_name" value="<?php echo esc_attr( $branch['name'] ); ?>">
                            <button type="submit" name="agentic_branch_action" value="merge" class="button button-primary button-small" onclick="return confirm('Merge this branch into <?php echo esc_js( $current_branch ); ?>?')">
                                Merge
                            </button>
                            <button type="submit" name="agentic_branch_action" value="delete" class="button button-small" onclick="return confirm('Delete this branch without merging?')">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

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
