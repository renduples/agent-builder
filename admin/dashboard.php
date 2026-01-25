<?php
/**
 * Agentic Admin Dashboard
 *
 * @package Agentic_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Agentic\Core\Audit_Log;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'agentic-core' ) );
}

use Agentic\Core\OpenAI_Client;

$audit = new Audit_Log();
$stats = $audit->get_stats( 'week' );
$openai = new OpenAI_Client();
$is_configured = $openai->is_configured();
?>
<div class="wrap agentic-admin">
    <h1>
        <span class="dashicons dashicons-superhero" style="font-size: 30px; margin-right: 10px;"></span>
        Agentic Plugin
    </h1>

    <?php if ( ! $is_configured ) : ?>
    <div class="notice notice-warning">
        <p>
            <strong>xAI API key not configured.</strong>
            The Developer Agent requires an xAI API key for Grok to function.
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-settings' ) ); ?>">Configure now</a>
        </p>
    </div>
    <?php endif; ?>

    <div class="agentic-dashboard-grid">
        <div class="agentic-card">
            <h2>Status</h2>
            <table class="widefat">
                <tr>
                    <td><strong>Version</strong></td>
                    <td><?php echo esc_html( AGENTIC_CORE_VERSION ); ?></td>
                </tr>
                <tr>
                    <td><strong>Mode</strong></td>
                    <td><?php echo esc_html( ucfirst( get_option( 'agentic_agent_mode', 'supervised' ) ) ); ?></td>
                </tr>
                <tr>
                    <td><strong>AI Provider</strong></td>
                    <td><?php echo $is_configured ? 'xAI Grok (Connected)' : '<span style="color: #b91c1c;">Not configured</span>'; ?></td>
                </tr>
                <tr>
                    <td><strong>Model</strong></td>
                    <td><?php echo esc_html( get_option( 'agentic_model', 'grok-3' ) ); ?></td>
                </tr>
            </table>
        </div>

        <div class="agentic-card">
            <h2>Weekly Statistics</h2>
            <table class="widefat">
                <tr>
                    <td><strong>Total Actions</strong></td>
                    <td><?php echo esc_html( number_format( (int) ( $stats['total_actions'] ?? 0 ) ) ); ?></td>
                </tr>
                <tr>
                    <td><strong>Tokens Used</strong></td>
                    <td><?php echo esc_html( number_format( (int) ( $stats['total_tokens'] ?? 0 ) ) ); ?></td>
                </tr>
                <tr>
                    <td><strong>Estimated Cost</strong></td>
                    <td>$<?php echo esc_html( number_format( (float) ( $stats['total_cost'] ?? 0 ), 4 ) ); ?></td>
                </tr>
                <tr>
                    <td><strong>Active Agents</strong></td>
                    <td><?php echo esc_html( (int) ( $stats['active_agents'] ?? 0 ) ); ?></td>
                </tr>
            </table>
        </div>

        <div class="agentic-card">
            <h2>Quick Actions</h2>
            <p>
                <a href="<?php echo esc_url( home_url( '/agent-chat/' ) ); ?>" class="button button-primary" target="_blank">
                    Open Chat Interface
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-approvals' ) ); ?>" class="button">
                    View Approval Queue
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-audit' ) ); ?>" class="button">
                    View Audit Log
                </a>
            </p>
        </div>

        <div class="agentic-card agentic-card-wide">
            <h2>Recent Activity</h2>
            <?php
            $recent = $audit->get_recent( 10 );
            if ( empty( $recent ) ) :
            ?>
                <p>No agent activity recorded yet.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Agent</th>
                            <th>Action</th>
                            <th>Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent as $entry ) : ?>
                        <tr>
                            <td><?php echo esc_html( human_time_diff( strtotime( $entry['created_at'] ) ) ); ?> ago</td>
                            <td><?php echo esc_html( $entry['agent_id'] ); ?></td>
                            <td><?php echo esc_html( $entry['action'] ); ?></td>
                            <td><?php echo esc_html( $entry['target_type'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.agentic-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.agentic-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}

.agentic-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.agentic-card-wide {
    grid-column: 1 / -1;
}

.agentic-card .button {
    margin-bottom: 5px;
}
</style>
