<?php
/**
 * Chat interface template
 *
 * Supports dynamic agent selection - users can chat with any active agent.
 *
 * @package Agentic_Plugin
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user = wp_get_current_user();

// Get accessible agents.
$registry = Agentic_Agent_Registry::get_instance();
$agents   = $registry->get_accessible_instances();

// Default to first available agent or passed agent_id.
$default_agent_id = isset( $_GET['agent'] ) ? sanitize_key( $_GET['agent'] ) : '';
$current_agent    = null;
$current_agent_id = '';

if ( $default_agent_id && isset( $agents[ $default_agent_id ] ) ) {
	$current_agent    = $agents[ $default_agent_id ];
	$current_agent_id = $default_agent_id;
} elseif ( ! empty( $agents ) ) {
	$current_agent    = reset( $agents );
	$current_agent_id = $current_agent->get_id();
}
?>
<div id="agentic-chat" class="agentic-chat-container" data-agent-id="<?php echo esc_attr( $current_agent_id ); ?>">
	<div class="agentic-chat-header">
		<div class="agentic-agent-info">
			<?php if ( count( $agents ) > 1 ) : ?>
			<div class="agentic-agent-selector">
				<select id="agentic-agent-select" class="agentic-agent-dropdown">
					<?php foreach ( $agents as $agent ) : ?>
						<option value="<?php echo esc_attr( $agent->get_id() ); ?>" 
								data-icon="<?php echo esc_attr( $agent->get_icon() ); ?>"
								data-welcome="<?php echo esc_attr( $agent->get_welcome_message() ); ?>"
								<?php selected( $agent->get_id(), $current_agent_id ); ?>>
							<?php echo esc_html( $agent->get_icon() . ' ' . $agent->get_name() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php else : ?>
			<div class="agentic-agent-avatar">
				<?php echo esc_html( $current_agent ? $current_agent->get_icon() : '🤖' ); ?>
			</div>
			<?php endif; ?>
			<div class="agentic-agent-details">
				<h3 id="agentic-agent-name"><?php echo esc_html( $current_agent ? $current_agent->get_name() : 'No Agent Available' ); ?></h3>
				<span class="agentic-status">
					<span class="agentic-status-dot"></span>
					Online
				</span>
			</div>
		</div>
		<div class="agentic-chat-actions">
			<button id="agentic-clear-chat" class="agentic-btn-secondary" title="Clear conversation">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M3 6h18"/>
					<path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
					<path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
				</svg>
			</button>
		</div>
	</div>

	<div id="agentic-messages" class="agentic-chat-messages">
		<?php if ( $current_agent ) : ?>
		<div class="agentic-message agentic-message-agent">
			<div class="agentic-message-avatar"><?php echo esc_html( $current_agent->get_icon() ); ?></div>
			<div class="agentic-message-content">
				<?php echo wp_kses_post( nl2br( $current_agent->get_welcome_message() ) ); ?>
				
				<?php
				$prompts = $current_agent->get_suggested_prompts();
				if ( ! empty( $prompts ) ) :
					?>
				<div class="agentic-suggested-prompts">
					<?php foreach ( $prompts as $prompt ) : ?>
						<button class="agentic-prompt-btn" data-prompt="<?php echo esc_attr( $prompt ); ?>">
							<?php echo esc_html( $prompt ); ?>
						</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php else : ?>
		<div class="agentic-empty-state">
			<div class="empty-state-icon">🤖</div>
			<h2>No Agents Activated</h2>
			<p>Activate an AI agent to start chatting. Agents can help you with content, SEO, security, and more.</p>
			
			<div class="available-agents-preview">
				<h4>Available Agents</h4>
				<div class="agent-grid">
					<div class="agent-preview-card">
						<span class="agent-icon">👨‍💻</span>
						<span class="agent-name">Developer Agent</span>
						<span class="agent-desc">Code & debugging help</span>
					</div>
					<div class="agent-preview-card">
						<span class="agent-icon">✍️</span>
						<span class="agent-name">Content Assistant</span>
						<span class="agent-desc">Write blog posts & pages</span>
					</div>
					<div class="agent-preview-card">
						<span class="agent-icon">📈</span>
						<span class="agent-name">SEO Analyzer</span>
						<span class="agent-desc">Optimize for search</span>
					</div>
					<div class="agent-preview-card">
						<span class="agent-icon">🛡️</span>
						<span class="agent-name">Security Monitor</span>
						<span class="agent-desc">Protect your site</span>
					</div>
				</div>
			</div>
			
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentic-add-agent' ) ); ?>" class="activate-agents-btn">
				Activate Agents in Dashboard
			</a>
			<?php else : ?>
			<p class="contact-admin">Contact your site administrator to activate AI agents.</p>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>

	<?php if ( $current_agent ) : ?>
	<div class="agentic-chat-input-container">
		<div class="agentic-typing-indicator" id="agentic-typing" style="display: none;">
			<span></span>
			<span></span>
			<span></span>
			<span id="agentic-typing-text">Agent is thinking...</span>
		</div>
		<form id="agentic-chat-form" class="agentic-chat-form">
			<textarea 
				id="agentic-input" 
				class="agentic-chat-input" 
				placeholder="Ask <?php echo esc_attr( $current_agent->get_name() ); ?> a question..."
				rows="1"
			></textarea>
			<button type="submit" class="agentic-send-btn" id="agentic-send">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="22" x2="11" y1="2" y2="13"/>
					<polygon points="22 2 15 22 11 13 2 9 22 2"/>
				</svg>
			</button>
		</form>
	</div>
	<?php endif; ?>

	<div class="agentic-chat-footer">
		<span class="agentic-footer-info">
			Powered by Agentic Plugin v<?php echo esc_html( AGENTIC_PLUGIN_VERSION ); ?>
		</span>
		<span class="agentic-footer-stats" id="agentic-stats"></span>
	</div>
</div>
