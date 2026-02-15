<?php
/**
 * Chat interface template
 *
 * Supports dynamic agent selection - users can chat with any active agent.
 *
 * @package    Agent_Builder
 * @subpackage Templates
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      0.1.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$agentic_user = wp_get_current_user();

// Get accessible agents.
$agentic_registry = Agentic_Agent_Registry::get_instance();
$agentic_agents   = $agentic_registry->get_accessible_instances();

// Default to first available agent or passed agent_id.
// Check URL parameter first, then fall back to first agent.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No form submission, just URL parameter for agent selection.
$agentic_default_agent_id = isset( $_GET['agent'] ) ? sanitize_key( $_GET['agent'] ) : '';
$agentic_current_agent    = null;
$agentic_current_agent_id = '';

if ( $agentic_default_agent_id && isset( $agentic_agents[ $agentic_default_agent_id ] ) ) {
	$agentic_current_agent    = $agentic_agents[ $agentic_default_agent_id ];
	$agentic_current_agent_id = $agentic_default_agent_id;
} elseif ( ! empty( $agentic_agents ) ) {
	$agentic_current_agent    = reset( $agentic_agents );
	$agentic_current_agent_id = $agentic_current_agent->get_id();
}
?>
<script>
// Check localStorage for last selected agent on page load
(function() {
	const savedAgent = localStorage.getItem('agentic_last_selected_agent');
	const urlParams = new URLSearchParams(window.location.search);
	const urlAgent = urlParams.get('agent');
	
	// If no agent in URL and we have a saved preference, redirect to it
	if (!urlAgent && savedAgent) {
		// Wait for DOM to be ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function() {
				const select = document.getElementById('agentic-agent-select');
				if (select) {
					const option = select.querySelector(`option[value="${savedAgent}"]`);
					if (option) {
						urlParams.set('agent', savedAgent);
						window.location.search = urlParams.toString();
					}
				}
			});
		} else {
			// DOM already loaded
			const select = document.getElementById('agentic-agent-select');
			if (select) {
				const option = select.querySelector(`option[value="${savedAgent}"]`);
				if (option) {
					urlParams.set('agent', savedAgent);
					window.location.search = urlParams.toString();
				}
			}
		}
	}
})();
</script>
<div id="agentic-chat" class="agentic-chat-container" data-agent-id="<?php echo esc_attr( $agentic_current_agent_id ); ?>">
	<div class="agentic-chat-header">
		<div class="agentic-agent-info">
			<?php if ( count( $agentic_agents ) > 1 ) : ?>
			<div class="agentic-agent-selector">
				<select id="agentic-agent-select" class="agentic-agent-dropdown">
				<?php
				// Sort agents by name (case-insensitive).
				$agentic_sorted_agents = $agentic_agents;
				uasort(
					$agentic_sorted_agents,
					function ( $a, $b ) {
						return strcasecmp( $a->get_name(), $b->get_name() );
					}
				);
				?>
				<?php foreach ( $agentic_sorted_agents as $agentic_agent ) : ?>
						<option value="<?php echo esc_attr( $agentic_agent->get_id() ); ?>" 
								data-icon="<?php echo esc_attr( $agentic_agent->get_icon() ); ?>"
								data-welcome="<?php echo esc_attr( $agentic_agent->get_welcome_message() ); ?>"
								<?php selected( $agentic_agent->get_id(), $agentic_current_agent_id ); ?>>
							<?php echo esc_html( $agentic_agent->get_icon() . ' ' . $agentic_agent->get_name() ); ?>
						</option>
					<?php endforeach; ?>
					<option value="load-more" data-action="load-more">➕ Load more . . .</option>
				</select>
			</div>
			<?php else : ?>
			<div class="agentic-agent-avatar">
				<?php echo esc_html( $agentic_current_agent ? $agentic_current_agent->get_icon() : '🤖' ); ?>
			</div>
			<?php endif; ?>
			<div class="agentic-agent-details">
				<?php if ( $agentic_agentic_current_agent ) : ?>
					<div class="agentic-agent-meta">
						Version <?php echo esc_html( $agentic_current_agent->get_version() ?? '1.0.0' ); ?>
						<span class="agent-meta-separator">|</span>
						By <?php echo esc_html( $agentic_current_agent->get_author() ?? 'Unknown' ); ?>
						<span class="agent-meta-separator">|</span>
						<?php echo esc_html( ucfirst( $agentic_current_agent->get_category() ) ); ?>
						<span class="agent-meta-separator">|</span>
						Capabilities: <?php echo esc_html( implode( ', ', $agentic_current_agent->get_required_capabilities() ) ); ?>
					</div>
				<?php endif; ?>
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
		<?php if ( $agentic_current_agent ) : ?>
		<div class="agentic-message agentic-message-agent">
			<div class="agentic-message-avatar"><?php echo esc_html( $agentic_current_agent->get_icon() ); ?></div>
			<div class="agentic-message-content">
				<?php echo wp_kses_post( nl2br( $agentic_current_agent->get_welcome_message() ) ); ?>
				
				<?php
				$agentic_prompts = $agentic_current_agent->get_suggested_prompts();
				if ( ! empty( $agentic_prompts ) ) :
					?>
				<div class="agentic-suggested-prompts">
					<?php foreach ( $agentic_prompts as $agentic_prompt ) : ?>
						<button class="agentic-prompt-btn" data-prompt="<?php echo esc_attr( $agentic_prompt ); ?>">
							<?php echo esc_html( $agentic_prompt ); ?>
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
						<span class="agent-name">Onboarding Agent</span>
						<span class="agent-desc">Getting started & guidance</span>
					</div>
					<div class="agent-preview-card">
						<span class="agent-icon">✍️</span>
						<span class="agent-name">Content Builder</span>
						<span class="agent-desc">Write blog posts & pages</span>
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

	<?php if ( $agentic_current_agent ) : ?>
	<div class="agentic-chat-input-container">
		<div class="agentic-typing-indicator" id="agentic-typing" style="display: none;">
			<span></span>
			<span></span>
			<span></span>
			<span id="agentic-typing-text">Agent is thinking...</span>
		</div>
		<div id="agentic-image-preview" class="agentic-image-preview" style="display:none;">
			<img id="agentic-preview-img" src="" alt="Preview" />
			<button type="button" id="agentic-remove-image" class="agentic-remove-image" title="<?php esc_attr_e( 'Remove image', 'agent-builder' ); ?>">&times;</button>
		</div>
		<form id="agentic-chat-form" class="agentic-chat-form">
			<input type="file" id="agentic-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" />
			<button type="button" id="agentic-attach-btn" class="agentic-attach-btn" title="<?php esc_attr_e( 'Attach image', 'agent-builder' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
			</button>
			<textarea 
				id="agentic-input" 
				class="agentic-chat-input" 
				placeholder="Ask <?php echo esc_attr( $agentic_current_agent->get_name() ); ?> a question..."
				rows="1"
			></textarea>
			<button type="button" id="agentic-voice-btn" class="agentic-voice-btn" title="<?php esc_attr_e( 'Voice input', 'agent-builder' ); ?>" style="display:none;">
				<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>
			</button>
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
			Powered by Agent Builder v<?php echo esc_html( AGENTIC_PLUGIN_VERSION ); ?>
		</span>
		<span class="agentic-footer-stats" id="agentic-stats"></span>
	</div>
</div>
