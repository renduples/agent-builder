<?php
/**
 * Onboarding Setup Wizard
 *
 * Shown once after plugin activation until the user connects an AI provider
 * or explicitly skips. Accessed at admin.php?page=agentic-setup.
 *
 * @package AgentBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Handle form submission ────────────────────────────────────────────────────

if ( isset( $_POST['agentic_setup_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agentic_setup_nonce'] ) ), 'agentic_setup' ) ) {
	$provider = sanitize_text_field( wp_unslash( $_POST['agentic_llm_provider'] ?? '' ) );
	$api_key  = sanitize_text_field( wp_unslash( $_POST['agentic_llm_api_key'] ?? '' ) );

	$allowed_providers = array( 'xai', 'openai', 'google', 'anthropic', 'mistral', 'ollama' );

	if ( in_array( $provider, $allowed_providers, true ) && ! empty( $api_key ) ) {
		$provider_defaults = array(
			'openai'    => 'gpt-4o',
			'anthropic' => 'claude-3-5-sonnet-20241022',
			'xai'       => 'grok-3',
			'google'    => 'gemini-2.0-flash-exp',
			'mistral'   => 'mistral-large-latest',
			'ollama'    => 'llama3.2',
		);

		// Save into per-provider keys array.
		$all_keys            = get_option( 'agentic_llm_api_keys', array() );
		$all_keys[ $provider ] = $api_key;
		update_option( 'agentic_llm_api_keys', $all_keys );

		update_option( 'agentic_llm_provider', $provider );
		// Keep legacy single-key option in sync.
		update_option( 'agentic_llm_api_key', $api_key );
		update_option( 'agentic_model', $provider_defaults[ $provider ] );
		update_option( 'agentic_onboarding_complete', true );

		$redirect_url = admin_url( 'admin.php?page=agentic-chat&onboarding=1' );
		echo '<script>window.location.href=' . wp_json_encode( $redirect_url ) . ';</script>';
		exit;
	}
}

if ( isset( $_GET['skip_setup'] ) && check_admin_referer( 'agentic_skip_setup' ) ) {
	update_option( 'agentic_onboarding_complete', true );
	$redirect_url = admin_url( 'admin.php?page=agentbuilder' );
	echo '<script>window.location.href=' . wp_json_encode( $redirect_url ) . ';</script>';
	exit;
}

// ── Provider data ─────────────────────────────────────────────────────────────

$providers = array(
	'xai'       => array(
		'name'        => 'xAI (Grok)',
		'icon'        => 'xai',
		'badge'       => '$25 free credits',
		'badge_class' => 'badge-free',
		'signup_url'  => 'https://accounts.x.ai/sign-up',
		'key_url'     => 'https://console.x.ai',
		'key_label'   => '',
		'placeholder' => 'xai-…',
		'steps'       => array(
			array(
				'text'       => 'Create your account at accounts.x.ai/sign-up.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/xai-sign-up.png',
				'alt'        => 'xAI sign-up page',
			),
			array(
				'text'       => 'Once logged in, go to console.x.ai → API Keys → Create API Key. Copy the key.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/xai-create-key.png',
				'alt'        => 'xAI create API key',
			),
		),
	),
	'openai'    => array(
		'name'        => 'OpenAI',
		'icon'        => 'openai',
		'badge'       => 'Pay-as-you-go',
		'badge_class' => 'badge-paid',
		'signup_url'  => 'https://auth.openai.com/create-account',
		'key_url'     => 'https://platform.openai.com/api-keys',
		'key_label'   => '',
		'credit_note' => 'Note: you may need to add a payment method before the API will work.',
		'placeholder' => 'sk-…',
		'steps'       => array(
			array(
				'text'       => 'Create your account at auth.openai.com.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/openai-sign-up.png',
				'alt'        => 'OpenAI sign-up page',
			),
			array(
				'text'       => 'Confirm your age when prompted.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/openai-confirm-age.png',
				'alt'        => 'OpenAI confirm age',
			),
			array(
				'text'       => 'Go to platform.openai.com → API keys → Create new secret key. Copy it.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/openai-create-key.png',
				'alt'        => 'OpenAI create API key',
			),
		),
	),
	'google'    => array(
		'name'        => 'Google (Gemini)',
		'icon'        => 'google',
		'badge'       => 'Free tier available',
		'badge_class' => 'badge-free',
		'signup_url'  => 'https://aistudio.google.com',
		'key_url'     => 'https://aistudio.google.com/apikey',
		'key_label'   => '',
		'placeholder' => 'AIza…',
		'steps'       => array(
			array(
				'text'       => 'Go to aistudio.google.com, click Get Started, and sign in with your Google account. Accept the Terms of Service.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/gemini-sign-up.png',
				'alt'        => 'Google AI Studio sign-up',
			),
			array(
				'text'       => 'Click Get API key in the left sidebar → Create API key. Copy the key.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/gemini-create-key.png',
				'alt'        => 'Google AI Studio create key',
			),
		),
	),
	'anthropic' => array(
		'name'        => 'Anthropic (Claude)',
		'icon'        => 'anthropic',
		'badge'       => 'Pay-as-you-go',
		'badge_class' => 'badge-paid',
		'signup_url'  => 'https://platform.claude.com/login?returnTo=%2F%3F',
		'key_url'     => 'https://platform.claude.com/settings/keys',
		'key_label'   => '',
		'credit_note' => 'Note: you will need to add credits (minimum $5) before the API will work.',
		'placeholder' => 'sk-ant-…',
		'steps'       => array(
			array(
				'text'       => 'Create your account at platform.claude.com.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/claude-sign-up.png',
				'alt'        => 'Anthropic sign-up page',
			),
			array(
				'text'       => 'Confirm your age when prompted.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/claude-confirm-age.png',
				'alt'        => 'Anthropic confirm age',
			),
			array(
				'text'       => 'Choose your account type (Personal or API).',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/claude-choose-account.png',
				'alt'        => 'Anthropic choose account type',
			),
			array(
				'text'       => 'Go to Settings → API Keys → Create Key. Copy it.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/claude-create-key.png',
				'alt'        => 'Anthropic create API key',
			),
		),
	),
	'mistral'   => array(
		'name'        => 'Mistral AI',
		'icon'        => 'mistral',
		'badge'       => 'Pay-as-you-go',
		'badge_class' => 'badge-paid',
		'signup_url'  => 'https://console.mistral.ai',
		'key_url'     => 'https://console.mistral.ai/api-keys',
		'key_label'   => '',
		'placeholder' => '',
		'steps'       => array(
			array(
				'text'       => 'Create an account at console.mistral.ai.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/mistral-sign-up.png',
				'alt'        => 'Mistral sign-up page',
			),
			array(
				'text'       => 'Create a team when prompted.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/mistral-create-team.png',
				'alt'        => 'Mistral create team',
			),
			array(
				'text'       => 'Go to API Keys → Create new key. Copy it.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/mistral-create-key.png',
				'alt'        => 'Mistral create API key',
			),
		),
	),
	'ollama'    => array(
		'name'        => 'Ollama',
		'icon'        => 'ollama',
		'badge'       => 'Free · runs locally',
		'badge_class' => 'badge-free',
		'signup_url'  => 'https://ollama.com/download',
		'key_url'     => '',
		'key_label'   => 'No API key needed — Ollama runs on your server',
		'placeholder' => '',
		'steps'       => array(
			array(
				'text'       => 'Download and install Ollama from ollama.com/download.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/ollama-download.png',
				'alt'        => 'Ollama download page',
			),
			array(
				'text'       => 'Install and start Ollama. Then pull a model by running this command in your terminal:<br><code>ollama pull llama3.2</code><br>Ollama will be available at <code>http://localhost:11434</code>.',
				'screenshot' => '',
				'alt'        => '',
			),
		),
	),
);

// ── Provider information cards ───────────────────────────────────────────────

$provider_info = array(
	'xai'       => array(
		'tagline'        => 'Includes $25 free credits — no card required',
		'pricing_detail' => 'Free credits to start · then ~$3 / 1M input tokens',
		'rate_limits'    => '60 req/min (free tier)',
		'best_for'       => 'Getting started · coding · fast responses',
		'models'         => array(
			array( 'name' => 'grok-3',           'tags' => array( 'Flagship', 'Text & code' ), 'recommended' => true ),
			array( 'name' => 'grok-3-fast',      'tags' => array( 'Fast', 'Low latency' ) ),
			array( 'name' => 'grok-3-mini',      'tags' => array( 'Budget-friendly' ) ),
			array( 'name' => 'grok-2-vision',    'tags' => array( 'Images', 'Vision' ) ),
		),
	),
	'openai'    => array(
		'tagline'        => 'Industry standard with the broadest ecosystem',
		'pricing_detail' => 'GPT-4o: $2.50 / 1M input · $10 / 1M output',
		'rate_limits'    => '500 req/min (Tier 1)',
		'best_for'       => 'Code generation · function calling · broad tooling',
		'models'         => array(
			array( 'name' => 'gpt-4o',        'tags' => array( 'Flagship', 'Vision' ), 'recommended' => true ),
			array( 'name' => 'gpt-4o-mini',   'tags' => array( 'Fast', 'Cheap' ) ),
			array( 'name' => 'gpt-4-turbo',   'tags' => array( 'Vision', 'Powerful' ) ),
			array( 'name' => 'gpt-3.5-turbo', 'tags' => array( 'Budget' ) ),
		),
	),
	'google'    => array(
		'tagline'        => 'Generous free tier with up to 2M token context window',
		'pricing_detail' => 'Free: 15 req/min · Flash: $0.075 / 1M tokens',
		'rate_limits'    => '15 req/min (free) · 1,000+ req/min (paid)',
		'best_for'       => 'Long documents · image analysis · low cost',
		'models'         => array(
			array( 'name' => 'gemini-2.0-flash-exp', 'tags' => array( 'Fastest', 'Vision' ), 'recommended' => true ),
			array( 'name' => 'gemini-1.5-pro',       'tags' => array( '1M context', 'Vision' ) ),
			array( 'name' => 'gemini-1.5-flash',     'tags' => array( 'Fast', 'Budget' ) ),
		),
	),
	'anthropic' => array(
		'tagline'        => 'Best for nuanced writing, analysis and complex reasoning',
		'pricing_detail' => 'Sonnet 3.5: $3 / 1M input · $15 / 1M output',
		'rate_limits'    => '50 req/min (Tier 1)',
		'best_for'       => 'Writing · analysis · complex reasoning',
		'models'         => array(
			array( 'name' => 'claude-3-5-sonnet-20241022', 'tags' => array( 'Flagship', 'Vision' ), 'recommended' => true ),
			array( 'name' => 'claude-3-5-haiku-20241022',  'tags' => array( 'Fast', 'Affordable' ) ),
			array( 'name' => 'claude-3-opus-20240229',     'tags' => array( 'Most capable' ) ),
		),
	),
	'mistral'   => array(
		'tagline'        => 'Strong multilingual support, hosted in Europe',
		'pricing_detail' => 'Large: $2 / 1M input · $6 / 1M output',
		'rate_limits'    => '500 req/min',
		'best_for'       => 'Multilingual · EU data residency · cost-efficient',
		'models'         => array(
			array( 'name' => 'mistral-large-latest',  'tags' => array( 'Flagship', 'Multilingual' ), 'recommended' => true ),
			array( 'name' => 'pixtral-large-latest',  'tags' => array( 'Vision' ) ),
			array( 'name' => 'mistral-small-latest',  'tags' => array( 'Fast', 'Cheap' ) ),
			array( 'name' => 'mistral-medium-latest', 'tags' => array( 'Balanced' ) ),
		),
	),
	'ollama'    => array(
		'tagline'        => 'Run open-source models privately on your own server',
		'pricing_detail' => 'Completely free — you pay only for hardware',
		'rate_limits'    => 'Limited only by your hardware',
		'best_for'       => 'Privacy · offline use · custom models',
		'models'         => array(
			array( 'name' => 'llama3.2', 'tags' => array( 'Popular', 'Text & code' ), 'recommended' => true ),
			array( 'name' => 'gemma3',   'tags' => array( 'Google', 'Efficient' ) ),
			array( 'name' => 'qwen2.5',  'tags' => array( 'Multilingual' ) ),
			array( 'name' => 'mistral',  'tags' => array( 'Fast' ) ),
		),
	),
);

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Connect an AI Provider — Agent Builder', 'agentbuilder' ); ?></title>
	<?php wp_head(); ?>
	<style>
		* { box-sizing: border-box; }

		body {
			background: #f0f0f1;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			margin: 0;
			padding: 0;
		}

		.setup-wrap {
			max-width: 860px;
			margin: 48px auto 80px;
			padding: 0 20px;
		}

		.setup-header {
			text-align: center;
			margin-bottom: 36px;
		}

		.setup-header h1 {
			font-size: 26px;
			font-weight: 700;
			color: #1e1e1e;
			margin: 0 0 8px;
		}

		.setup-header p {
			font-size: 15px;
			color: #50575e;
			margin: 0;
		}

		.setup-logo {
			width: 48px;
			height: 48px;
			margin: 0 auto 16px;
			display: block;
		}

		/* ── Step indicators ─────────────────────────── */
		.setup-steps {
			display: flex;
			justify-content: center;
			gap: 0;
			margin-bottom: 36px;
		}

		.setup-step-item {
			display: flex;
			align-items: center;
			font-size: 13px;
			color: #8c8f94;
		}

		.setup-step-item .step-num {
			width: 28px;
			height: 28px;
			border-radius: 50%;
			background: #ddd;
			color: #50575e;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: 600;
			font-size: 13px;
			margin-right: 6px;
			flex-shrink: 0;
		}

		.setup-step-item.active .step-num {
			background: #2271b1;
			color: #fff;
		}

		.setup-step-item.done .step-num {
			background: #00a32a;
			color: #fff;
		}

		.setup-step-item .step-label {
			white-space: nowrap;
		}

		.step-connector {
			width: 40px;
			height: 2px;
			background: #ddd;
			margin: 0 8px;
			flex-shrink: 0;
		}

		/* ── Card ────────────────────────────────────── */
		.setup-card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			overflow: hidden;
		}

		.setup-card-inner {
			padding: 32px 36px;
		}

		/* ── Phase 1: Account question ───────────────── */
		.account-question h2 {
			font-size: 18px;
			font-weight: 600;
			color: #1e1e1e;
			margin: 0 0 6px;
		}

		.account-question p {
			color: #50575e;
			margin: 0 0 28px;
			font-size: 14px;
		}

		.account-buttons {
			display: flex;
			gap: 16px;
		}

		.provider-list {
			margin: 0 0 20px 0;
			padding: 0;
			list-style: none;
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
		}

		.provider-list li {
			background: #f0f0f1;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 4px 10px;
			font-size: 13px;
			color: #3c434a;
		}

		.provider-list-badge {
			background: #d1e7dd;
			color: #0a3622;
			font-size: 11px;
			font-weight: 600;
			border-radius: 3px;
			padding: 1px 5px;
			margin-left: 4px;
		}

		.btn-account {
			flex: 1;
			padding: 16px 20px;
			border: 2px solid #c3c4c7;
			border-radius: 8px;
			background: #fff;
			cursor: pointer;
			text-align: center;
			font-size: 15px;
			font-weight: 600;
			color: #1e1e1e;
			transition: border-color 0.15s, background 0.15s;
		}

		.btn-account:hover {
			border-color: #2271b1;
			background: #f0f6fc;
		}

		.btn-account .btn-icon {
			font-size: 28px;
			display: block;
			margin-bottom: 6px;
		}

		/* ── Phase 2: Provider grid ──────────────────── */
		.provider-grid {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 14px;
		}

		@media (max-width: 600px) {
			.provider-grid { grid-template-columns: repeat(2, 1fr); }
		}

		.provider-card {
			border: 2px solid #c3c4c7;
			border-radius: 8px;
			padding: 18px 14px 14px;
			cursor: pointer;
			text-align: center;
			transition: border-color 0.15s, box-shadow 0.15s;
			background: #fff;
			position: relative;
		}

		.provider-card:hover {
			border-color: #2271b1;
			box-shadow: 0 2px 8px rgba(0,0,0,0.08);
		}

		.provider-card.selected {
			border-color: #2271b1;
			background: #f0f6fc;
		}

		.provider-card .provider-name {
			font-size: 14px;
			font-weight: 600;
			color: #1e1e1e;
			margin-top: 8px;
		}

		.provider-card .provider-icon {
			width: 40px;
			height: 40px;
			margin: 0 auto;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.provider-card .provider-icon svg,
		.provider-card .provider-icon img {
			width: 36px;
			height: 36px;
		}

		.badge {
			display: inline-block;
			font-size: 10px;
			font-weight: 600;
			padding: 2px 7px;
			border-radius: 20px;
			margin-top: 6px;
		}

		.badge-free {
			background: #e6f4ea;
			color: #1a7431;
		}

		.badge-paid {
			background: #fff3cd;
			color: #856404;
		}

		/* ── Phase 3: Setup steps ────────────────────── */
		.provider-steps-section {
			display: none;
		}

		.provider-steps-section.active {
			display: block;
		}

		.provider-steps-header {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 24px;
		}

		.provider-steps-header h2 {
			font-size: 17px;
			font-weight: 600;
			margin: 0;
			color: #1e1e1e;
		}

		.btn-back {
			background: none;
			border: none;
			color: #2271b1;
			cursor: pointer;
			font-size: 13px;
			padding: 0;
			display: flex;
			align-items: center;
			gap: 4px;
		}

		.btn-back:hover { text-decoration: underline; }

		.step-list {
			list-style: none;
			padding: 0;
			margin: 0 0 24px;
		}

		.step-list li {
			display: flex;
			gap: 14px;
			margin-bottom: 20px;
			align-items: flex-start;
		}

		.step-list .step-n {
			width: 26px;
			height: 26px;
			background: #2271b1;
			color: #fff;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 12px;
			font-weight: 700;
			flex-shrink: 0;
			margin-top: 1px;
		}

		.step-list .step-body {
			flex: 1;
		}

		.step-list .step-body p {
			margin: 0 0 10px;
			font-size: 14px;
			line-height: 1.6;
			color: #1e1e1e;
		}

		.step-list .step-body a {
			color: #2271b1;
		}

		.screenshot-wrap {
			display: inline-block;
			position: relative;
			margin-top: 8px;
			max-width: 100%;
			cursor: zoom-in;
			border: 1px solid #c3c4c7;
			border-radius: 6px;
			overflow: hidden;
			transition: box-shadow 0.15s;
		}

		.screenshot-wrap:hover {
			box-shadow: 0 4px 16px rgba(0,0,0,0.18);
		}

		.screenshot-wrap:hover .screenshot-zoom-icon {
			opacity: 1;
		}

		.screenshot-zoom-icon {
			position: absolute;
			bottom: 8px;
			right: 8px;
			width: 30px;
			height: 30px;
			background: rgba(0,0,0,0.55);
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			opacity: 0;
			transition: opacity 0.15s;
			pointer-events: none;
		}

		.screenshot-zoom-icon svg {
			width: 16px;
			height: 16px;
			fill: none;
			stroke: #fff;
			stroke-width: 2;
			stroke-linecap: round;
		}

		.step-screenshot {
			display: block;
			max-width: 100%;
			max-height: 200px;
			object-fit: cover;
			object-position: top;
		}

		.credit-note {
			font-size: 12px;
			color: #50575e;
			margin: 12px 0 0;
			padding: 10px 14px;
			background: #fff8e1;
			border-left: 3px solid #f0b429;
			border-radius: 3px;
		}

		.get-key-section {
			display: flex;
			align-items: center;
			gap: 14px;
			margin-top: 20px;
			padding: 16px;
			background: #f6f7f7;
			border-radius: 6px;
		}

		.get-key-section .btn-primary {
			flex-shrink: 0;
		}

		.get-key-note {
			font-size: 12px;
			color: #50575e;
			margin: 0;
			line-height: 1.5;
		}
		.key-section {
			border-top: 1px solid #f0f0f1;
			padding-top: 24px;
		}

		.key-section label {
			display: block;
			font-size: 13px;
			font-weight: 600;
			margin-bottom: 8px;
			color: #1e1e1e;
		}

		.key-section .key-hint {
			font-size: 12px;
			color: #50575e;
			margin-bottom: 10px;
		}

		.key-input-row {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 20px;
		}

		.key-input-row input[type="password"] {
			min-width: 0;
		}

		.key-input-row .btn-save,
		.key-input-row .btn-test-inline {
			flex-shrink: 0;
			white-space: nowrap;
		}

		.key-input-row input[type="password"],
		.key-input-row input[type="text"] {
			flex: 1;
			padding: 9px 12px;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			font-size: 14px;
			font-family: monospace;
		}

		.key-input-row input:focus {
			border-color: #2271b1;
			outline: 2px solid rgba(34,113,177,0.2);
		}

		.ollama-url-row {
			display: flex;
			flex-direction: column;
			gap: 8px;
		}

		.ollama-url-row input {
			padding: 9px 12px;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			font-size: 14px;
			font-family: monospace;
			width: 100%;
		}

		.test-status {
			display: none;
			align-items: center;
			gap: 8px;
			font-size: 13px;
			margin-top: 10px;
			padding: 10px 14px;
			border-radius: 6px;
		}

		.test-status.testing {
			display: flex;
			background: #f0f6fc;
			color: #2271b1;
		}

		.test-status.success {
			display: flex;
			background: #e6f4ea;
			color: #1a7431;
		}

		.test-status.error {
			display: flex;
			background: #fff4f4;
			color: #d63638;
		}

		/* ── Footer ──────────────────────────────────── */
		.setup-card-footer {
			padding: 18px 36px;
			background: #f6f7f7;
			border-top: 1px solid #c3c4c7;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.footer-right {
			display: flex;
			gap: 10px;
			align-items: center;
		}

		.btn-primary {
			background: #2271b1;
			color: #fff;
			border: none;
			border-radius: 4px;
			padding: 10px 22px;
			font-size: 14px;
			font-weight: 600;
			cursor: pointer;
			transition: background 0.15s;
		}

		.btn-primary:hover { background: #135e96; }
		.btn-primary:disabled { background: #a5c4e0; cursor: not-allowed; }

		.btn-secondary {
			background: #fff;
			color: #2271b1;
			border: 1px solid #2271b1;
			border-radius: 4px;
			padding: 10px 22px;
			font-size: 14px;
			font-weight: 600;
			cursor: pointer;
			transition: background 0.15s, color 0.15s;
		}

		.btn-secondary:hover { background: #f0f6fc; }

		.btn-skip {
			color: #8c8f94;
			font-size: 13px;
			text-decoration: none;
		}

		.btn-skip:hover { color: #50575e; text-decoration: underline; }

		/* ── Phase 4: Done ───────────────────────────── */
		.done-section {
			text-align: center;
			padding: 20px 0;
		}

		.done-section .done-icon {
			font-size: 52px;
			margin-bottom: 16px;
		}

		.done-section h2 {
			font-size: 22px;
			font-weight: 700;
			color: #1e1e1e;
			margin: 0 0 8px;
		}

		.done-section p {
			color: #50575e;
			font-size: 15px;
			margin: 0 0 28px;
		}

		.btn-large {
			display: inline-block;
			background: #2271b1;
			color: #fff;
			border-radius: 6px;
			padding: 13px 32px;
			font-size: 16px;
			font-weight: 600;
			text-decoration: none;
			transition: background 0.15s;
		}

		.btn-large:hover { background: #135e96; color: #fff; }

		/* ── Lightbox ────────────────────────────────── */
		.lightbox {
			display: none;
			position: fixed;
			inset: 0;
			background: rgba(0,0,0,0.8);
			z-index: 99999;
			align-items: center;
			justify-content: center;
			cursor: zoom-out;
		}

		.lightbox.open { display: flex; }

		.lightbox img {
			max-width: 90vw;
			max-height: 90vh;
			border-radius: 6px;
			box-shadow: 0 8px 40px rgba(0,0,0,0.5);
		}

		code {
			background: #f6f7f7;
			padding: 2px 6px;
			border-radius: 3px;
			font-size: 13px;
		}

		/* ── Provider info panel ─────────────────────── */
		.provider-info-panel {
			display: none;
			margin-top: 18px;
			border: 1px solid #2271b1;
			border-radius: 8px;
			background: #f8fbff;
			overflow: hidden;
		}

		.provider-info-panel.visible {
			display: block;
			animation: pipFadeIn 0.18s ease;
		}

		@keyframes pipFadeIn {
			from { opacity: 0; transform: translateY(-6px); }
			to   { opacity: 1; transform: translateY(0); }
		}

		.pip-header {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 14px 20px 12px;
			border-bottom: 1px solid #dce3eb;
			background: #fff;
		}

		.pip-header h3 {
			font-size: 14px;
			font-weight: 700;
			color: #1e1e1e;
			margin: 0 0 3px;
		}

		.pip-header p {
			font-size: 12px;
			color: #50575e;
			margin: 0;
		}

		.pip-stats {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			border-bottom: 1px solid #dce3eb;
		}

		.pip-stat {
			padding: 10px 20px;
			border-right: 1px solid #dce3eb;
		}

		.pip-stat:last-child { border-right: none; }

		.pip-stat-label {
			font-size: 10px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #8c8f94;
			margin-bottom: 4px;
		}

		.pip-stat-value {
			font-size: 12px;
			color: #1e1e1e;
			font-weight: 500;
			line-height: 1.45;
		}

		.pip-models {
			padding: 10px 20px 14px;
		}

		.pip-models-label {
			font-size: 10px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #8c8f94;
			margin-bottom: 8px;
		}

		.pip-model-list {
			display: flex;
			flex-wrap: wrap;
			gap: 7px;
		}

		.pip-model-chip {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			padding: 4px 10px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 20px;
			font-size: 12px;
			color: #1e1e1e;
		}

		.pip-model-chip.recommended {
			border-color: #2271b1;
			background: #e8f0fa;
			font-weight: 600;
		}

		.pip-tag {
			font-size: 10px;
			color: #50575e;
			background: #f0f0f1;
			padding: 1px 5px;
			border-radius: 10px;
		}

		.pip-model-chip.recommended .pip-tag {
			background: #c7dbf5;
			color: #1a4a7e;
		}

		/* ── Phase 4: Test ───────────────────────────── */
		.wizard-test-section {
			text-align: center;
			padding: 12px 0 28px;
			border-bottom: 1px solid #f0f0f1;
		}

		.wizard-test-section h2 {
			font-size: 18px;
			font-weight: 600;
			margin: 0 0 8px;
			color: #1e1e1e;
		}

		.wizard-test-section p {
			color: #50575e;
			font-size: 14px;
			margin: 0 0 20px;
		}

		#test-status-wizard {
			justify-content: center;
		}

		.wizard-congrats-banner {
			text-align: center;
			padding: 36px 24px 32px;
			background: linear-gradient(135deg, #f0f6fc 0%, #e8f5e9 100%);
			border-top: 1px solid #c3c4c7;
			border-bottom: 1px solid #c3c4c7;
			margin: 0 -36px;
		}

		.wizard-congrats-icon {
			font-size: 56px;
			line-height: 1;
			margin-bottom: 14px;
		}

		.wizard-congrats-banner h2 {
			font-size: 23px;
			font-weight: 700;
			color: #1e1e1e;
			margin: 0 0 10px;
		}

		.wizard-congrats-banner p {
			color: #3c434a;
			font-size: 16px;
			margin: 0;
		}

		.wizard-chatbox {
			border-radius: 14px;
			overflow: hidden;
			margin: 28px 0 0;
			background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
			box-shadow: 0 20px 60px rgba(0,0,0,0.4);
		}

		.wizard-chat-titlebar {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 12px 18px;
			background: rgba(0,0,0,0.3);
			border-bottom: 1px solid rgba(255,255,255,0.08);
		}

		.wizard-win-dot {
			width: 12px;
			height: 12px;
			border-radius: 50%;
		}
		.wizard-win-dot--red    { background: #ff5f56; }
		.wizard-win-dot--yellow { background: #ffbd2e; }
		.wizard-win-dot--green  { background: #27c93f; }

		.wizard-chat-title-text {
			flex: 1;
			text-align: center;
			color: rgba(255,255,255,0.4);
			font-size: 12px;
		}

		.wizard-chat-messages {
			height: 430px;
			overflow-y: auto;
			padding: 20px;
			display: flex;
			flex-direction: column;
			gap: 14px;
			background: transparent;
		}

		.wizard-chat-msg {
			display: flex;
			gap: 10px;
			max-width: 88%;
		}

		.wizard-chat-msg--user {
			align-self: flex-end;
			flex-direction: row-reverse;
		}

		.wizard-chat-msg--agent {
			align-self: flex-start;
		}

		.wizard-chat-bubble {
			padding: 11px 15px;
			border-radius: 12px;
			font-size: 13.5px;
			line-height: 1.65;
			white-space: pre-wrap;
			word-break: break-word;
		}

		.wizard-chat-msg--user .wizard-chat-bubble {
			background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%);
			color: #fff;
		}

		.wizard-chat-msg--agent .wizard-chat-bubble {
			background: rgba(255,255,255,0.07);
			color: rgba(255,255,255,0.92);
			border: 1px solid rgba(255,255,255,0.1);
		}

		.wizard-chat-msg--thinking .wizard-chat-bubble {
			color: rgba(255,255,255,0.4);
			font-style: italic;
		}

		.wizard-suggested-prompts {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			padding: 0 18px 14px;
		}

		.wizard-suggested-prompts button {
			background: rgba(168,85,247,0.15);
			border: 1px solid rgba(168,85,247,0.32);
			color: rgba(255,255,255,0.85);
			padding: 7px 14px;
			border-radius: 20px;
			font-size: 12.5px;
			cursor: pointer;
			transition: all 0.18s;
			box-shadow: none;
		}

		.wizard-suggested-prompts button:hover {
			background: rgba(168,85,247,0.28);
			border-color: rgba(168,85,247,0.55);
			transform: translateY(-1px);
		}

		.wizard-chat-input-wrap {
			display: flex;
			border-top: 1px solid rgba(255,255,255,0.08);
			background: rgba(0,0,0,0.25);
		}

		.wizard-chat-input-wrap input {
			flex: 1;
			padding: 13px 16px;
			border: none;
			font-size: 14px;
			outline: none;
			background: transparent;
			color: #fff;
		}

		.wizard-chat-input-wrap input::placeholder {
			color: rgba(255,255,255,0.35);
		}

		.wizard-chat-input-wrap .btn-primary {
			border-radius: 0;
			padding: 13px 22px;
			border: none;
		}

		.wizard-mic-btn {
			background: none;
			border: none;
			padding: 0 10px;
			color: rgba(255,255,255,0.45);
			cursor: pointer;
			display: flex;
			align-items: center;
			transition: color 0.2s;
			flex-shrink: 0;
		}

		.wizard-mic-btn:hover { color: rgba(255,255,255,0.85); }

		.wizard-mic-btn.wizard-mic-active {
			color: #d63638;
			animation: wizardMicPulse 1.5s ease-in-out infinite;
		}

		@keyframes wizardMicPulse {
			0%, 100% { opacity: 1; }
			50% { opacity: 0.4; }
		}

		.wizard-exit-row {
			text-align: center;
			margin-top: 28px;
		}

		/* ── Model + Mode pickers ── */
		.wizard-pref-section {
			margin: 0 0 32px;
			padding: 24px 28px;
			background: #f9fafb;
			border: 1px solid #e5e7eb;
			border-radius: 10px;
		}

		.wizard-pref-section h3 {
			margin: 0 0 4px;
			font-size: 15px;
			font-weight: 600;
			color: #1d2327;
		}

		.wizard-pref-hint {
			margin: 0 0 16px;
			color: #6b7280;
			font-size: 13px;
		}

		.wizard-model-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
			gap: 10px;
		}

		.wizard-model-card {
			padding: 12px 14px;
			border: 2px solid #e5e7eb;
			border-radius: 8px;
			background: #fff;
			cursor: pointer;
			transition: border-color 0.15s, background 0.15s;
			position: relative;
		}

		.wizard-model-card:hover { border-color: #2563eb; }

		.wizard-model-card.selected {
			border-color: #2563eb;
			background: #eff6ff;
		}

		.wizard-model-name {
			font-size: 13px;
			font-weight: 600;
			color: #1d2327;
			margin-bottom: 6px;
			word-break: break-all;
		}

		.wizard-model-tags {
			display: flex;
			flex-wrap: wrap;
			gap: 4px;
		}

		.wizard-model-tag {
			font-size: 11px;
			padding: 2px 7px;
			border-radius: 99px;
			background: #e5e7eb;
			color: #374151;
		}

		.wizard-model-tag.tag-recommended {
			background: #dcfce7;
			color: #15803d;
			font-weight: 600;
		}

		.wizard-model-check {
			position: absolute;
			top: 8px;
			right: 8px;
			width: 18px;
			height: 18px;
			border-radius: 50%;
			background: #2563eb;
			color: #fff;
			font-size: 11px;
			display: none;
			align-items: center;
			justify-content: center;
		}

		.wizard-model-card.selected .wizard-model-check { display: flex; }

		.wizard-mode-grid {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 10px;
		}

		@media (max-width: 620px) {
			.wizard-mode-grid { grid-template-columns: 1fr; }
			.wizard-model-grid { grid-template-columns: 1fr 1fr; }
		}

		.wizard-mode-card {
			display: block;
			padding: 14px 16px;
			border: 2px solid #e5e7eb;
			border-radius: 8px;
			background: #fff;
			cursor: pointer;
			transition: border-color 0.15s, background 0.15s;
		}

		.wizard-mode-card:hover { border-color: #2563eb; }

		.wizard-mode-card input[type="radio"] { display: none; }

		.wizard-mode-card:has(input:checked) {
			border-color: #2563eb;
			background: #eff6ff;
		}

		.wizard-mode-header {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 8px;
		}

		.wizard-mode-icon { font-size: 18px; }

		.wizard-mode-header strong {
			font-size: 14px;
			color: #1d2327;
		}

		.wizard-mode-badge {
			margin-left: auto;
			font-size: 11px;
			padding: 2px 8px;
			border-radius: 99px;
			background: #dcfce7;
			color: #15803d;
			font-weight: 600;
		}

		.wizard-mode-card p {
			margin: 0;
			font-size: 12px;
			color: #6b7280;
			line-height: 1.5;
		}
	</style>
</head>
<body class="wp-admin">

<div class="setup-wrap">

	<!-- Header -->
	<div class="setup-header">
		<div class="setup-logo" aria-hidden="true">
			<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
				<rect width="48" height="48" rx="10" fill="#2271b1"/>
				<path d="M14 24c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10S14 29.523 14 24z" fill="rgba(255,255,255,0.2)"/>
				<path d="M20 24l3 3 6-6" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>
		<h1><?php esc_html_e( 'Welcome to Agent Builder', 'agentbuilder' ); ?></h1>
		<p><?php esc_html_e( 'Connect to your preferred AI Provider to get started. This takes only a few minutes.', 'agentbuilder' ); ?></p>
	</div>

	<!-- Step indicators -->
	<div class="setup-steps" id="setup-progress">
		<div class="setup-step-item active" id="progress-1">
			<div class="step-num">1</div>
			<div class="step-label"><?php esc_html_e( 'Choose provider', 'agentbuilder' ); ?></div>
		</div>
		<div class="step-connector"></div>
		<div class="setup-step-item" id="progress-2">
			<div class="step-num">2</div>
			<div class="step-label"><?php esc_html_e( 'Get API key', 'agentbuilder' ); ?></div>
		</div>
		<div class="step-connector"></div>
		<div class="setup-step-item" id="progress-3">
			<div class="step-num">3</div>
			<div class="step-label"><?php esc_html_e( 'Connect', 'agentbuilder' ); ?></div>
		</div>
		<div class="step-connector"></div>
		<div class="setup-step-item" id="progress-4">
			<div class="step-num">4</div>
			<div class="step-label"><?php esc_html_e( 'Test', 'agentbuilder' ); ?></div>
		</div>
	</div>

	<form method="post" action="" id="setup-form">
		<?php wp_nonce_field( 'agentic_setup', 'agentic_setup_nonce' ); ?>
		<input type="hidden" name="agentic_llm_provider" id="hidden_provider" value="">
		<input type="hidden" name="agentic_llm_api_key" id="hidden_api_key" value="">

		<div class="setup-card">

			<!-- ── Phase 1: Do you have an account? ─────────────────── -->
			<div class="setup-card-inner" id="phase-account">
				<div class="account-question">
					<h2><?php esc_html_e( 'Do you already have an account with an AI Provider?', 'agentbuilder' ); ?></h2>
					<p><?php esc_html_e( 'At least one AI Provider is needed in order for your AI Assistants to respond to requests.', 'agentbuilder' ); ?></p>
					<p><strong><?php esc_html_e( 'Popular AI Providers:', 'agentbuilder' ); ?></strong></p>
					<ul class="provider-list">
						<?php foreach ( $providers as $agentic_p_slug => $agentic_p ) : ?>
						<li><?php echo esc_html( $agentic_p['name'] ); ?><?php if ( ! empty( $agentic_p['badge'] ) ) : ?> <span class="provider-list-badge"><?php echo esc_html( $agentic_p['badge'] ); ?></span><?php endif; ?></li>
						<?php endforeach; ?>
					</ul>
					<div class="account-buttons">
						<button type="button" class="btn-account" onclick="showPhase('provider-pick', true)">
							<span class="btn-icon">✅</span>
							<?php esc_html_e( 'Yes, I have an account', 'agentbuilder' ); ?>
						</button>
						<button type="button" class="btn-account" onclick="showPhase('provider-pick', false)">
							<span class="btn-icon">🆕</span>
							<?php esc_html_e( "No, I'll create one now", 'agentbuilder' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- ── Phase 2: Pick a provider ──────────────────────────── -->
			<div class="setup-card-inner" id="phase-provider-pick" style="display:none">
				<h2 style="font-size:17px;font-weight:600;margin:0 0 6px;color:#1e1e1e;" id="provider-pick-title">
					<?php esc_html_e( 'Choose your AI provider', 'agentbuilder' ); ?>
				</h2>
				<p style="color:#50575e;font-size:14px;margin:0 0 24px;" id="provider-pick-sub">
					<?php esc_html_e( 'Select the provider you want to connect.', 'agentbuilder' ); ?>
				</p>
				<div class="provider-grid">
					<?php foreach ( $providers as $slug => $p ) : ?>
					<div class="provider-card" id="card-<?php echo esc_attr( $slug ); ?>" onclick="selectProvider('<?php echo esc_js( $slug ); ?>')">
						<div class="provider-icon">
							<?php echo wp_kses( agentic_setup_provider_icon( $slug ), array( 'svg' => array( 'xmlns' => array(), 'viewbox' => array(), 'fill' => array(), 'width' => array(), 'height' => array(), 'role' => array(), 'aria-label' => array() ), 'circle' => array( 'cx' => array(), 'cy' => array(), 'r' => array(), 'fill' => array() ), 'path' => array( 'd' => array(), 'fill' => array(), 'stroke' => array(), 'stroke-width' => array(), 'stroke-linecap' => array(), 'stroke-linejoin' => array() ), 'rect' => array( 'width' => array(), 'height' => array(), 'rx' => array(), 'fill' => array(), 'x' => array(), 'y' => array() ), 'ellipse' => array( 'cx' => array(), 'cy' => array(), 'rx' => array(), 'ry' => array(), 'fill' => array() ), 'polygon' => array( 'points' => array(), 'fill' => array() ), 'text' => array( 'x' => array(), 'y' => array(), 'font-size' => array(), 'font-weight' => array(), 'fill' => array(), 'font-family' => array(), 'dominant-baseline' => array(), 'text-anchor' => array() ) ) ); ?>
						</div>
						<div class="provider-name"><?php echo esc_html( $p['name'] ); ?></div>
						<?php if ( ! empty( $p['badge'] ) ) : ?>
							<span class="badge <?php echo esc_attr( $p['badge_class'] ); ?>"><?php echo esc_html( $p['badge'] ); ?></span>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Provider info panel — revealed when a card is clicked -->
				<div class="provider-info-panel" id="provider-info-panel">
					<div class="pip-header">
						<div id="pip-icon" style="flex-shrink:0; line-height:0;"></div>
						<div>
							<h3 id="pip-name"></h3>
							<p id="pip-tagline"></p>
						</div>
					</div>
					<div class="pip-stats">
						<div class="pip-stat">
							<div class="pip-stat-label">Pricing</div>
							<div class="pip-stat-value" id="pip-pricing"></div>
						</div>
						<div class="pip-stat">
							<div class="pip-stat-label">Rate Limits</div>
							<div class="pip-stat-value" id="pip-rate-limits"></div>
						</div>
						<div class="pip-stat">
							<div class="pip-stat-label">Best For</div>
							<div class="pip-stat-value" id="pip-best-for"></div>
						</div>
					</div>
					<div class="pip-models">
						<div class="pip-models-label">Available Models</div>
						<div class="pip-model-list" id="pip-model-list"></div>
					</div>
				</div>
			</div>

			<!-- ── Phase 3: Per-provider instructions + key entry ─────── -->
			<?php foreach ( $providers as $slug => $p ) : ?>
			<div class="setup-card-inner provider-steps-section" id="phase-steps-<?php echo esc_attr( $slug ); ?>">
				<div class="provider-steps-header">
					<h2>
						<?php
						echo wp_kses(
							agentic_setup_provider_icon( $slug, 24 ),
							array( 'svg' => array( 'xmlns' => array(), 'viewbox' => array(), 'fill' => array(), 'width' => array(), 'height' => array(), 'role' => array(), 'aria-label' => array() ), 'circle' => array( 'cx' => array(), 'cy' => array(), 'r' => array(), 'fill' => array() ), 'path' => array( 'd' => array(), 'fill' => array(), 'stroke' => array(), 'stroke-width' => array(), 'stroke-linecap' => array(), 'stroke-linejoin' => array() ), 'rect' => array( 'width' => array(), 'height' => array(), 'rx' => array(), 'fill' => array(), 'x' => array(), 'y' => array() ), 'ellipse' => array( 'cx' => array(), 'cy' => array(), 'rx' => array(), 'ry' => array(), 'fill' => array() ), 'polygon' => array( 'points' => array(), 'fill' => array() ), 'text' => array( 'x' => array(), 'y' => array(), 'font-size' => array(), 'font-weight' => array(), 'fill' => array(), 'font-family' => array(), 'dominant-baseline' => array(), 'text-anchor' => array() ) )
						);
						?>
						<?php echo esc_html( $p['name'] ); ?>
					</h2>
				</div>

				<!-- Key entry (special case: Ollama uses URL not a key) -->
				<div class="key-section">
					<?php if ( 'ollama' === $slug ) : ?>
					<label><?php esc_html_e( 'Ollama Server URL', 'agentbuilder' ); ?></label>
					<p class="key-hint"><?php esc_html_e( 'Leave as default unless you changed the port or are using a remote server.', 'agentbuilder' ); ?></p>
					<div class="ollama-url-row">
						<input
							type="text"
							id="key-input-ollama"
							value="http://localhost:11434"
							placeholder="http://localhost:11434"
							autocomplete="off"
						>
					</div>
					<?php else : ?>
					<label for="key-input-<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Paste your API key here once you have followed the steps below.', 'agentbuilder' ); ?></label>
					<div class="key-input-row" id="key-input-row-<?php echo esc_attr( $slug ); ?>">
						<input
							type="password"
							id="key-input-<?php echo esc_attr( $slug ); ?>"
							placeholder="<?php echo esc_attr( $p['placeholder'] ); ?>"
							autocomplete="off"
							oninput="onKeyInput('<?php echo esc_js( $slug ); ?>')"
						>
						<button type="button" class="btn-secondary btn-save" id="btn-save-<?php echo esc_attr( $slug ); ?>" style="display:none" onclick="saveKey()"><?php esc_html_e( 'Save', 'agentbuilder' ); ?></button>
					<button type="button" class="btn-primary btn-continue-test" id="btn-continue-<?php echo esc_attr( $slug ); ?>" style="display:none" onclick="goNext()"><?php esc_html_e( 'Continue to Testing →', 'agentbuilder' ); ?></button>
					</div>
					<?php endif; ?>

					<div class="test-status" id="test-status-<?php echo esc_attr( $slug ); ?>">
						<span class="test-icon"></span>
						<span class="test-msg"></span>
					</div>
				</div>

				<ul class="step-list" id="steps-list-<?php echo esc_attr( $slug ); ?>">
					<?php
					$step_num = 1;
					foreach ( $p['steps'] as $step ) :
						// For "has account" users, skip the sign-up step (first step).
						$is_signup_step = ( $step_num === 1 );
						?>
					<li class="<?php echo $is_signup_step ? 'signup-step' : ''; ?>">
						<div class="step-n"><?php echo esc_html( (string) $step_num ); ?></div>
						<div class="step-body">
							<p><?php echo wp_kses( $step['text'], array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'strong' => array(), 'br' => array(), 'code' => array() ) ); ?></p>
							<?php if ( ! empty( $step['screenshot'] ) ) : ?>
							<div class="screenshot-wrap" onclick="openLightbox(this.querySelector('img').src)">
								<img
									src="<?php echo esc_url( $step['screenshot'] ); ?>"
									alt="<?php echo esc_attr( $step['alt'] ); ?>"
									class="step-screenshot"
									loading="lazy"
								>
								<span class="screenshot-zoom-icon" aria-hidden="true">
									<svg viewBox="0 0 20 20"><circle cx="8" cy="8" r="5"/><line x1="13" y1="13" x2="18" y2="18"/><line x1="6" y1="8" x2="10" y2="8"/><line x1="8" y1="6" x2="8" y2="10"/></svg>
								</span>
							</div>
							<?php endif; ?>
						</div>
					</li>
					<?php
					++$step_num;
					endforeach;
					?>
				</ul>
				<?php if ( ! empty( $p['credit_note'] ) ) : ?>
				<p class="credit-note"><?php echo esc_html( $p['credit_note'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $p['key_url'] ) && 'ollama' !== $slug ) : ?>
				<div class="get-key-section">
					<button type="button" class="btn-primary" onclick="openProviderKey('<?php echo esc_js( $slug ); ?>')"><?php esc_html_e( 'Get Key', 'agentbuilder' ); ?></button>
					<p class="get-key-note"><?php esc_html_e( 'Opens new window so you can perform the steps above. Remember to return here and save the key — only shown once.', 'agentbuilder' ); ?></p>
				</div>
				<?php endif; ?>
			</div><!-- .provider-steps-section -->
			<?php endforeach; ?>

			<!-- ── Phase 4: Test connection ──────────────────────────── -->
			<div class="setup-card-inner" id="phase-test" style="display:none">

				<!-- Model Selection -->
				<div class="wizard-pref-section">
					<h3><?php esc_html_e( 'Choose a model', 'agentbuilder' ); ?></h3>
					<p class="wizard-pref-hint"><?php esc_html_e( 'Each model has different strengths. You can change this any time in Settings.', 'agentbuilder' ); ?></p>
					<div id="wizard-model-grid" class="wizard-model-grid"></div>
				</div>

				<!-- Agent Mode -->
				<div class="wizard-pref-section">
					<h3><?php esc_html_e( 'Choose an agent mode', 'agentbuilder' ); ?></h3>
					<p class="wizard-pref-hint"><?php esc_html_e( 'Controls how much authority your AI assistants have. You can change this any time in Settings.', 'agentbuilder' ); ?></p>
					<div class="wizard-mode-grid">
						<label class="wizard-mode-card" data-mode="supervised">
							<input type="radio" name="wizard_mode" value="supervised" checked>
							<div class="wizard-mode-header">
								<span class="wizard-mode-icon">👁️</span>
								<strong><?php esc_html_e( 'Supervised', 'agentbuilder' ); ?></strong>
								<span class="wizard-mode-badge"><?php esc_html_e( 'Recommended', 'agentbuilder' ); ?></span>
							</div>
							<p><?php esc_html_e( 'The AI proposes changes — you review and approve before anything is saved or published. Nothing happens without your sign-off.', 'agentbuilder' ); ?></p>
						</label>
						<label class="wizard-mode-card" data-mode="autonomous">
							<input type="radio" name="wizard_mode" value="autonomous">
							<div class="wizard-mode-header">
								<span class="wizard-mode-icon">⚡</span>
								<strong><?php esc_html_e( 'Autonomous', 'agentbuilder' ); ?></strong>
							</div>
							<p><?php esc_html_e( 'The AI executes tasks immediately without asking for approval. Best for experienced users who trust the agent\'s judgment.', 'agentbuilder' ); ?></p>
						</label>
						<label class="wizard-mode-card" data-mode="disabled">
							<input type="radio" name="wizard_mode" value="disabled">
							<div class="wizard-mode-header">
								<span class="wizard-mode-icon">💬</span>
								<strong><?php esc_html_e( 'Chat only', 'agentbuilder' ); ?></strong>
							</div>
							<p><?php esc_html_e( 'Assistants can answer questions and give advice, but cannot read data from or make any changes to your site.', 'agentbuilder' ); ?></p>
						</label>
					</div>
				</div>

				<div class="wizard-test-section">
					<h2><?php esc_html_e( 'Test your connection', 'agentbuilder' ); ?></h2>
					<p><?php esc_html_e( 'Click below to verify your AI provider is responding correctly.', 'agentbuilder' ); ?></p>
					<button type="button" class="btn-primary" id="btn-test-connection" onclick="runWizardTest()">
						<?php esc_html_e( 'Test Connection', 'agentbuilder' ); ?>
					</button>
					<div class="test-status" id="test-status-wizard">
						<span class="test-icon"></span>
						<span class="test-msg"></span>
					</div>
				</div>

				<!-- Congrats + chatbox — revealed on success -->
				<div id="wizard-congrats" style="display:none">
					<div class="wizard-congrats-banner">
						<div class="wizard-congrats-icon">🎊</div>
						<h2><?php esc_html_e( 'Your WordPress site just got much smarter.', 'agentbuilder' ); ?></h2>
						<p><?php esc_html_e( 'Go ahead, talk to WordPress!!', 'agentbuilder' ); ?></p>
					</div>

					<?php
					$agentic_wizard_agent     = Agentic_Agent_Registry::get_instance()->get_agent_instance( 'wordpress-assistant' );
					$agentic_wizard_greeting  = $agentic_wizard_agent ? $agentic_wizard_agent->get_welcome_message() : "Hi! I'm your WordPress Assistant. Ask me anything about your site.";
					$agentic_wizard_prompts   = $agentic_wizard_agent ? $agentic_wizard_agent->get_suggested_prompts() : array();
					$agentic_wizard_shortcuts = ( $agentic_wizard_agent && method_exists( $agentic_wizard_agent, 'get_agent_shortcuts' ) )
						? $agentic_wizard_agent->get_agent_shortcuts()
						: array();
					?>
					<div class="wizard-chatbox">
						<div class="wizard-chat-titlebar">
							<div class="wizard-win-dot wizard-win-dot--red"></div>
							<div class="wizard-win-dot wizard-win-dot--yellow"></div>
							<div class="wizard-win-dot wizard-win-dot--green"></div>
							<div class="wizard-chat-title-text"><?php esc_html_e( 'WordPress Assistant', 'agentbuilder' ); ?></div>
						</div>
						<div class="wizard-chat-messages" id="wizard-chat-messages">
							<div class="wizard-chat-msg wizard-chat-msg--agent">
								<span class="wizard-chat-bubble"><?php echo wp_kses_post( $agentic_wizard_greeting ); ?></span>
							</div>
						</div>
						<div class="wizard-suggested-prompts" id="wizard-suggested-prompts"></div>
						<div class="wizard-chat-input-wrap">
							<input type="text" id="wizard-chat-input" placeholder="<?php esc_attr_e( 'Ask me anything about your WordPress site…', 'agentbuilder' ); ?>" autocomplete="off" onkeydown="if(event.key==='Enter')sendWizardMessage()">
							<button type="button" id="wizard-mic-btn" class="wizard-mic-btn" title="<?php esc_attr_e( 'Voice input', 'agentbuilder' ); ?>" style="display:none;">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>
							</button>
							<button type="button" id="wizard-chat-send" class="btn-primary" onclick="sendWizardMessage()"><?php esc_html_e( 'Send', 'agentbuilder' ); ?></button>
						</div>
					</div>

					<div class="wizard-exit-row">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentbuilder' ) ); ?>" class="btn-secondary"><?php esc_html_e( 'Exit Wizard', 'agentbuilder' ); ?></a>
					</div>
				</div>
			</div><!-- #phase-test -->

			<div class="setup-card-footer" id="setup-footer">
				<div class="footer-left">
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=agentic-setup&skip_setup=1' ), 'agentic_skip_setup' ) ); ?>" class="btn-skip">
						<?php esc_html_e( 'Skip for now', 'agentbuilder' ); ?>
					</a>
				</div>
				<div class="footer-right">
					<button type="button" class="btn-secondary" id="btn-back" style="display:none" onclick="goBack()">
						← <?php esc_html_e( 'Back', 'agentbuilder' ); ?>
					</button>
					<button type="button" class="btn-secondary" id="btn-next" style="display:none" onclick="goNext()">
						<?php esc_html_e( 'Next', 'agentbuilder' ); ?> →
					</button>
				</div>
			</div><!-- .setup-card-footer -->

		</div><!-- .setup-card -->
	</form>

</div><!-- .setup-wrap -->

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
	<img src="" alt="" id="lightbox-img">
</div>

<script>
var hasAccount = false;
var selectedProvider = null;
var wizardKeySaved = false;
var wizardSavedProvider = null;
var wizardSavedKey = null;
var wizardSessionId = 'wizard-' + Math.random().toString(36).substr(2, 9);
var wizardChatHistory = [];
var wizardChatNonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
var wizardRestUrl    = <?php echo wp_json_encode( rest_url( 'agentic/v1/' ) ); ?>;
var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
var ajaxNonce = <?php echo wp_json_encode( wp_create_nonce( 'agentic_test_connection' ) ); ?>;
var providerSignupUrls = <?php echo wp_json_encode( array_map( fn( $p ) => $p['signup_url'], $providers ) ); ?>;
var providerNames      = <?php echo wp_json_encode( array_map( fn( $p ) => $p['name'], $providers ) ); ?>;
var providerInfoData       = <?php echo wp_json_encode( $provider_info ); ?>;
var wizardSuggestedPrompts = <?php echo wp_json_encode( array_values( $agentic_wizard_prompts ) ); ?>;
var wizardAgentShortcuts   = <?php echo wp_json_encode( array_values( $agentic_wizard_shortcuts ) ); ?>;
var wizardChatPageUrl      = <?php echo wp_json_encode( admin_url( 'admin.php?page=agentic-chat' ) ); ?>;

function showPhase(phase, userHasAccount) {
	hasAccount = userHasAccount;

	// Update provider selection title/subtitle based on choice.
	if (phase === 'provider-pick') {
		var title = document.getElementById('provider-pick-title');
		var sub = document.getElementById('provider-pick-sub');
		if (userHasAccount) {
			title.textContent = 'Which provider do you use?';
			sub.textContent = 'Select the provider whose API key you already have.';
		} else {
			title.textContent = 'Choose a provider to sign up with';
			sub.textContent = 'We recommend xAI (Grok) — it includes $25 free credits with no card required.';
		}
	}

	// Hide all phases.
	var phases = ['phase-account', 'phase-provider-pick', 'phase-test'];
	<?php foreach ( array_keys( $providers ) as $slug ) : ?>
	phases.push('phase-steps-<?php echo esc_attr( $slug ); ?>');
	<?php endforeach; ?>

	phases.forEach(function(id) {
		var el = document.getElementById(id);
		if (el) el.style.display = 'none';
	});

	var target = document.getElementById('phase-' + phase);
	if (target) target.style.display = 'block';

	// Update progress indicators.
	var p1 = document.getElementById('progress-1');
	var p2 = document.getElementById('progress-2');
	var p3 = document.getElementById('progress-3');
	var p4 = document.getElementById('progress-4');

	// Reset.
	[p1, p2, p3, p4].forEach(function(el) {
		el.classList.remove('active', 'done');
	});

	if (phase === 'account') {
		p1.classList.add('active');
	} else if (phase === 'provider-pick') {
		p1.classList.add('done');
		p2.classList.add('active');
	} else if (phase === 'test') {
		p1.classList.add('done');
		p2.classList.add('done');
		p3.classList.add('done');
		p4.classList.add('active');
		// Populate model cards for the selected provider.
		buildModelCards(wizardSavedProvider || selectedProvider);
	} else {
		p1.classList.add('done');
		p2.classList.add('done');
		p3.classList.add('active');
	}

	// Update footer nav buttons.
	var btnBack = document.getElementById('btn-back');
	var btnNext = document.getElementById('btn-next');

	// Reset.
	[btnBack, btnNext].forEach(function(b) { b.style.display = 'none'; });

	if (phase === 'provider-pick') {
		btnBack.style.display = 'inline-block';
	} else if (phase.indexOf('steps-') === 0) {
		btnBack.style.display = 'inline-block';
		// Show Next if this provider's key was already saved.
		var slug = phase.replace('steps-', '');
		if (wizardKeySaved && wizardSavedProvider === slug) {
			btnNext.style.display = 'inline-block';
		}
	} else if (phase === 'test') {
		btnBack.style.display = 'inline-block';
	}
}

function goBack() {
	var phases = ['phase-account', 'phase-provider-pick', 'phase-test'];
	<?php foreach ( array_keys( $providers ) as $slug ) : ?>
	phases.push('phase-steps-<?php echo esc_attr( $slug ); ?>');
	<?php endforeach; ?>

	var current = null;
	phases.forEach(function(id) {
		var el = document.getElementById(id);
		if (el && el.style.display !== 'none') current = id;
	});

	if (!current) return;
	if (current === 'phase-provider-pick') {
		showPhase('account', hasAccount);
	} else if (current.indexOf('phase-steps-') === 0) {
		showPhase('provider-pick', hasAccount);
	} else if (current === 'phase-test') {
		showPhase('steps-' + (wizardSavedProvider || selectedProvider), hasAccount);
	}
}

function goNext() {
	var phases = ['phase-account', 'phase-provider-pick', 'phase-test'];
	<?php foreach ( array_keys( $providers ) as $slug ) : ?>
	phases.push('phase-steps-<?php echo esc_attr( $slug ); ?>');
	<?php endforeach; ?>

	var current = null;
	phases.forEach(function(id) {
		var el = document.getElementById(id);
		if (el && el.style.display !== 'none') current = id;
	});

	if (!current) return;

	if (current === 'phase-provider-pick') {
		if (!selectedProvider) return;
		showPhase('steps-' + selectedProvider, hasAccount);
	} else if (current.indexOf('phase-steps-') === 0 && wizardKeySaved) {
		showPhase('test', hasAccount);
	}

	var card = document.querySelector('.setup-card');
	if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

var providerKeyUrls = <?php echo wp_json_encode( array_map( fn( $p ) => $p['key_url'] ?? '', $providers ) ); ?>;

function openProviderKey(slug) {
	var url = providerKeyUrls[slug] || providerSignupUrls[slug];
	if (url) window.open(url, '_blank', 'noopener');
	// Scroll to top and focus the key input.
	var card = document.getElementById('phase-steps-' + slug);
	if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
	setTimeout(function() {
		var input = document.getElementById('key-input-' + slug);
		if (input) input.focus();
	}, 400);
}

function selectProvider(slug) {
	// Deselect all cards.
	document.querySelectorAll('.provider-card').forEach(function(c) {
		c.classList.remove('selected');
	});
	document.getElementById('card-' + slug).classList.add('selected');

	selectedProvider = slug;

	// Show/hide sign-up steps based on whether user has account.
	var signupSteps = document.querySelectorAll('#steps-list-' + slug + ' .signup-step');
	signupSteps.forEach(function(li) {
		li.style.display = hasAccount ? 'none' : 'flex';
	});

	// Renumber visible steps.
	var visibleSteps = document.querySelectorAll('#steps-list-' + slug + ' li:not([style*="display: none"])');
	visibleSteps.forEach(function(li, idx) {
		var numEl = li.querySelector('.step-n');
		if (numEl) numEl.textContent = idx + 1;
	});

	// Populate and reveal the provider info panel.
	var info  = providerInfoData[slug];
	var panel = document.getElementById('provider-info-panel');
	if (info && panel) {
		document.getElementById('pip-name').textContent     = providerNames[slug] || slug;
		document.getElementById('pip-tagline').textContent  = info.tagline || '';
		document.getElementById('pip-pricing').textContent  = info.pricing_detail || '';
		document.getElementById('pip-rate-limits').textContent = info.rate_limits || '';
		document.getElementById('pip-best-for').textContent = info.best_for || '';

		// Copy the provider icon from the card.
		var pipIcon = document.getElementById('pip-icon');
		var srcCard = document.getElementById('card-' + slug);
		if (srcCard && pipIcon) {
			var iconEl = srcCard.querySelector('.provider-icon');
			pipIcon.innerHTML = iconEl ? iconEl.innerHTML : '';
		}

		// Render model chips.
		var modelList = document.getElementById('pip-model-list');
		modelList.innerHTML = '';
		(info.models || []).forEach(function(m) {
			var chip = document.createElement('div');
			chip.className = 'pip-model-chip' + (m.recommended ? ' recommended' : '');
			var tagsHtml = (m.tags || []).map(function(t) {
				return '<span class="pip-tag">' + t + '</span>';
			}).join('');
			chip.innerHTML = '<span>' + m.name + '</span>' + tagsHtml;
			modelList.appendChild(chip);
		});

		panel.classList.add('visible');
	}

	// Stay on provider-pick and show Next so user can confirm or change choice.
	document.getElementById('btn-back').style.display = 'inline-block';
	document.getElementById('btn-next').style.display = 'inline-block';
}

function onKeyInput(slug) {
	var input = document.getElementById('key-input-' + slug);
	var btnTest = document.getElementById('btn-test-' + slug);
	var btnSave = document.getElementById('btn-save-' + slug);
	if (input && input.value.trim().length > 10) {
		if (btnTest) btnTest.style.display = 'inline-block';
		if (btnSave) btnSave.style.display = 'inline-block';
	} else {
		if (btnTest) btnTest.style.display = 'none';
		if (btnSave) btnSave.style.display = 'none';
	}
	// Reset any test status.
	setTestStatus(slug, '');
}

function saveKey() {
	var slug = selectedProvider;
	if (!slug) return;

	var key;
	if (slug === 'ollama') {
		key = (document.getElementById('key-input-ollama').value.trim()) || 'http://localhost:11434';
	} else {
		var input = document.getElementById('key-input-' + slug);
		if (!input || !input.value.trim()) return;
		key = input.value.trim();
	}

	var btn = document.getElementById('btn-save-' + slug);
	if (btn) {
		btn.textContent = 'Saving…';
		btn.disabled = true;
	}

	var formData = new FormData();
	formData.append('action', 'agentic_wizard_save_key');
	formData.append('nonce', ajaxNonce);
	formData.append('provider', slug);
	formData.append('api_key', key);

	fetch(ajaxUrl, { method: 'POST', body: formData })
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (data.success) {
				if (btn) {
					btn.textContent = '✓ Key saved!';
					btn.style.background = '#00a32a';
					btn.style.color = '#fff';
					btn.style.border = '1px solid #00a32a';
				}
				wizardKeySaved = true;
				wizardSavedProvider = slug;
				wizardSavedKey = key;
				document.getElementById('btn-next').style.display = 'inline-block';
				var contBtn = document.getElementById('btn-continue-' + slug);
				if (contBtn) contBtn.style.display = 'inline-block';
			} else {
				if (btn) {
					btn.textContent = 'Save';
					btn.disabled = false;
					btn.style = '';
				}
				var errMsg = (data.data && data.data.message) ? data.data.message : 'Failed to save. Please try again.';
				setTestStatus(slug, 'error', errMsg);
			}
		})
		.catch(function() {
			if (btn) {
				btn.textContent = 'Save';
				btn.disabled = false;
				btn.style = '';
			}
			setTestStatus(slug, 'error', 'Network error. Please try again.');
		});
}

function setTestStatus(slug, state, msg) {
	var el = document.getElementById('test-status-' + slug);
	if (!el) return;
	el.className = 'test-status ' + (state || '');
	var icon = { testing: '⏳', success: '✅', error: '❌' };
	el.querySelector('.test-icon').textContent = icon[state] || '';
	el.querySelector('.test-msg').textContent = msg || '';
}

// ── Model & mode picker ───────────────────────────────────────────────────────
var wizardSelectedModel = null;
var wizardSelectedMode  = 'supervised';

function buildModelCards(provider) {
	var grid = document.getElementById('wizard-model-grid');
	if (!grid) return;
	var info = (typeof providerInfoData !== 'undefined') ? providerInfoData[provider] : null;
	if (!info || !info.models || !info.models.length) {
		grid.innerHTML = '<p style="color:#6b7280;font-size:13px">No models listed for this provider — you can select one in Settings after setup.</p>';
		return;
	}
	grid.innerHTML = '';
	wizardSelectedModel = null;
	info.models.forEach(function(m) {
		var card = document.createElement('div');
		card.className = 'wizard-model-card' + (m.recommended ? ' selected' : '');
		card.dataset.model = m.name;
		if (m.recommended && !wizardSelectedModel) wizardSelectedModel = m.name;
		var tags = (m.tags || []).map(function(t) {
			return '<span class="wizard-model-tag' + (t === 'Recommended' || m.recommended && (m.tags||[])[0] === t ? '' : '') + '">' + t + '</span>';
		}).join('');
		if (m.recommended) tags = '<span class="wizard-model-tag tag-recommended">Recommended</span>' + tags;
		card.innerHTML = '<div class="wizard-model-check">✓</div><div class="wizard-model-name">' + m.name + '</div><div class="wizard-model-tags">' + tags + '</div>';
		card.addEventListener('click', function() {
			document.querySelectorAll('.wizard-model-card').forEach(function(c) { c.classList.remove('selected'); });
			card.classList.add('selected');
			wizardSelectedModel = m.name;
		});
		grid.appendChild(card);
	});
	if (!wizardSelectedModel && info.models.length) wizardSelectedModel = info.models[0].name;
}

document.querySelectorAll('.wizard-mode-card').forEach(function(card) {
	card.addEventListener('click', function() {
		var radio = card.querySelector('input[type="radio"]');
		if (radio) { radio.checked = true; wizardSelectedMode = radio.value; }
	});
});

function saveWizardPreferences(onDone) {
	var fd = new FormData();
	fd.append('action', 'agentic_wizard_save_preferences');
	fd.append('nonce', ajaxNonce);
	fd.append('model', wizardSelectedModel || '');
	fd.append('mode', wizardSelectedMode);
	fetch(ajaxUrl, { method: 'POST', body: fd }).then(onDone).catch(onDone);
}

function runWizardTest() {
	if (!wizardSavedProvider || !wizardSavedKey) return;

	var btn = document.getElementById('btn-test-connection');
	btn.disabled = true;
	btn.textContent = 'Testing…';

	var statusEl = document.getElementById('test-status-wizard');
	statusEl.className = 'test-status testing';
	statusEl.querySelector('.test-icon').textContent = '⏳';
	statusEl.querySelector('.test-msg').textContent = 'Connecting to ' + (providerNames[wizardSavedProvider] || wizardSavedProvider) + '…';

	var formData = new FormData();
	formData.append('action', 'agentic_test_connection');
	formData.append('nonce', ajaxNonce);
	formData.append('provider', wizardSavedProvider);
	formData.append('api_key', wizardSavedKey);

	fetch(ajaxUrl, { method: 'POST', body: formData })
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (data.success) {
				statusEl.className = 'test-status success';
				statusEl.querySelector('.test-icon').textContent = '✅';
				statusEl.querySelector('.test-msg').textContent = 'Connected! Saving your preferences…';
				btn.textContent = 'Test Connection';
				btn.disabled = false;
				saveWizardPreferences(function() {
					statusEl.querySelector('.test-msg').textContent = 'Connected successfully!';
					setTimeout(function() {
						var congrats = document.getElementById('wizard-congrats');
						congrats.style.display = 'block';
						congrats.scrollIntoView({ behavior: 'smooth', block: 'start' });
						document.getElementById('btn-next').style.display = 'none';
						document.getElementById('btn-back').style.display = 'none';
					}, 400);
				});
			} else {
				statusEl.className = 'test-status error';
				statusEl.querySelector('.test-icon').textContent = '❌';
				var msg = (data.data && data.data.message) ? data.data.message : 'Connection failed. Check your API key.';
				statusEl.querySelector('.test-msg').textContent = msg;
				btn.textContent = 'Try Again';
				btn.disabled = false;
			}
		})
		.catch(function() {
			statusEl.className = 'test-status error';
			statusEl.querySelector('.test-icon').textContent = '❌';
			statusEl.querySelector('.test-msg').textContent = 'Network error. Please try again.';
			btn.textContent = 'Try Again';
			btn.disabled = false;
		});
}

// Render agent shortcut pills (navigate to chat page with ?agent=) or fall back to text prompts
(function() {
	var container = document.getElementById('wizard-suggested-prompts');
	if (!container) return;

	// Prefer agent shortcuts — navigate to chat page.
	if (wizardAgentShortcuts && wizardAgentShortcuts.length) {
		wizardAgentShortcuts.forEach(function(shortcut) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.textContent = shortcut.icon + ' ' + shortcut.label;
			btn.title = 'Open ' + shortcut.label;
			btn.addEventListener('click', function() {
				window.location.href = wizardChatPageUrl + '&agent=' + encodeURIComponent(shortcut.agent_id);
			});
			container.appendChild(btn);
		});
		return;
	}

	// Fallback: text prompts that fill the wizard chat input.
	if (wizardSuggestedPrompts && wizardSuggestedPrompts.length) {
		wizardSuggestedPrompts.forEach(function(prompt) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.textContent = prompt;
			btn.addEventListener('click', function() {
				container.style.display = 'none';
				var input = document.getElementById('wizard-chat-input');
				input.value = prompt;
				sendWizardMessage();
			});
			container.appendChild(btn);
		});
	}
})();

function appendWizardMessage(role, text, isThinking, id) {
	var messages = document.getElementById('wizard-chat-messages');
	var div = document.createElement('div');
	div.className = 'wizard-chat-msg wizard-chat-msg--' + role + (isThinking ? ' wizard-chat-msg--thinking' : '');
	if (id) div.id = id;
	var bubble = document.createElement('span');
	bubble.className = 'wizard-chat-bubble';
	bubble.textContent = text;
	div.appendChild(bubble);
	messages.appendChild(div);
	messages.scrollTop = messages.scrollHeight;
}

function sendWizardMessage() {
	var input = document.getElementById('wizard-chat-input');
	var msg = input.value.trim();
	if (!msg) return;
	input.value = '';
	// Hide prompts once user starts chatting
	var promptsEl = document.getElementById('wizard-suggested-prompts');
	if (promptsEl) promptsEl.style.display = 'none';

	appendWizardMessage('user', msg);

	var sendBtn = document.getElementById('wizard-chat-send');
	sendBtn.disabled = true;
	input.disabled = true;

	var thinkingId = 'wiz-think-' + Date.now();
	appendWizardMessage('agent', 'Thinking…', true, thinkingId);

	wizardChatHistory.push({ role: 'user', content: msg });

	fetch(wizardRestUrl + 'chat', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': wizardChatNonce
		},
		body: JSON.stringify({
			message: msg,
			session_id: wizardSessionId,
			agent_id: 'wordpress-assistant',
			history: wizardChatHistory.slice(-10)
		})
	})
	.then(function(r) { return r.json(); })
	.then(function(data) {
		var el = document.getElementById(thinkingId);
		if (el) el.remove();
		var reply = (data && data.response) ? data.response : ((data && data.error && data.response) ? data.response : 'Sorry, I could not get a response.');
		appendWizardMessage('agent', reply);
		wizardChatHistory.push({ role: 'assistant', content: reply });
		sendBtn.disabled = false;
		input.disabled = false;
		input.focus();
	})
	.catch(function() {
		var el = document.getElementById(thinkingId);
		if (el) el.remove();
		appendWizardMessage('agent', 'Sorry, something went wrong. Please try again.');
		sendBtn.disabled = false;
		input.disabled = false;
	});
}

// Voice input for wizard chat
(function() {
	var micBtn = document.getElementById('wizard-mic-btn');
	if (!micBtn) return;
	var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
	micBtn.style.display = '';
	if (!SpeechRecognition) {
		micBtn.title = 'Voice input requires Chrome, Edge, or Safari';
		micBtn.style.opacity = '0.4';
		micBtn.addEventListener('click', function() {
			alert('Voice input is not supported in this browser.\n\nTry Chrome, Edge, or Safari.');
		});
		return;
	}
	var recognition = null;
	var isListening = false;
	var input = document.getElementById('wizard-chat-input');
	micBtn.addEventListener('click', function() {
		if (isListening) { recognition.stop(); return; }
		recognition = new SpeechRecognition();
		recognition.lang = document.documentElement.lang || 'en-US';
		recognition.interimResults = true;
		recognition.continuous = false;
		recognition.maxAlternatives = 1;
		var existingText = input.value;
		recognition.onstart = function() {
			isListening = true;
			micBtn.classList.add('wizard-mic-active');
			input.placeholder = 'Listening…';
		};
		recognition.onresult = function(event) {
			var interim = '', final = '';
			for (var i = 0; i < event.results.length; i++) {
				if (event.results[i].isFinal) { final += event.results[i][0].transcript; }
				else { interim += event.results[i][0].transcript; }
			}
			input.value = existingText + (existingText ? ' ' : '') + (final || interim);
		};
		recognition.onend = function() {
			isListening = false;
			micBtn.classList.remove('wizard-mic-active');
			input.placeholder = input.getAttribute('data-orig-placeholder') || 'Ask me anything about your WordPress site…';
			input.focus();
		};
		recognition.onerror = function(event) {
			isListening = false;
			micBtn.classList.remove('wizard-mic-active');
			input.placeholder = input.getAttribute('data-orig-placeholder') || 'Ask me anything about your WordPress site…';
			if (event.error === 'not-allowed') {
				alert('Microphone access denied. This may require HTTPS.');
			} else if (event.error !== 'aborted' && event.error !== 'no-speech') {
				console.warn('Speech recognition error:', event.error);
			}
		};
		if (!input.getAttribute('data-orig-placeholder')) {
			input.setAttribute('data-orig-placeholder', input.placeholder);
		}
		recognition.start();
	});
})();

function openLightbox(src) {
	document.getElementById('lightbox-img').src = src;
	document.getElementById('lightbox').classList.add('open');
}

function closeLightbox() {
	document.getElementById('lightbox').classList.remove('open');
}

document.addEventListener('keydown', function(e) {
	if (e.key === 'Escape') closeLightbox();
});
</script>

<?php wp_footer(); ?>
</body>
</html>
<?php
// ── Provider icon helper ──────────────────────────────────────────────────────

/**
 * Returns an inline SVG icon for the given provider slug.
 *
 * @param string $slug  Provider slug.
 * @param int    $size  Icon size in px.
 * @return string
 */
function agentic_setup_provider_icon( string $slug, int $size = 36 ): string {
	$s = (string) $size;
	$icons = array(
		'xai'       => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" role="img" aria-label="xAI"><path d="M3 3l7.5 9L3 21h2.5l6.25-7.5L17.5 21H21l-7.75-9.5L20.5 3H18l-5.75 7L7 3H3z" fill="#000"/></svg>',
		'openai'    => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" role="img" aria-label="OpenAI"><path d="M22.28 9.84a5.83 5.83 0 0 0-.5-4.79 5.9 5.9 0 0 0-6.35-2.83A5.85 5.85 0 0 0 11.02 1a5.9 5.9 0 0 0-5.62 4.09 5.85 5.85 0 0 0-3.91 2.84 5.9 5.9 0 0 0 .73 6.92 5.83 5.83 0 0 0 .5 4.79 5.9 5.9 0 0 0 6.35 2.83A5.85 5.85 0 0 0 12.98 23a5.9 5.9 0 0 0 5.62-4.09 5.85 5.85 0 0 0 3.91-2.84 5.9 5.9 0 0 0-.73-6.92l.5.73z" fill="#000"/></svg>',
		'google'    => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" role="img" aria-label="Google"><path d="M12 11v2.4h6.8c-.3 1.8-2 5.2-6.8 5.2-4.1 0-7.4-3.4-7.4-7.6S7.9 3.4 12 3.4c2.3 0 3.9.98 4.8 1.84l3.28-3.16C18.18.9 15.36 0 12 0 5.37 0 0 5.37 0 12s5.37 12 12 12c6.93 0 11.52-4.87 11.52-11.72 0-.79-.08-1.39-.19-1.99L12 11z" fill="#4285F4"/></svg>',
		'anthropic' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" role="img" aria-label="Anthropic"><path d="M13.83 3h-3.66L4 21h3.66l1.22-3.33h6.24L16.34 21H20L13.83 3zm-3.94 11.67 2.11-5.77 2.1 5.77H9.9z" fill="#D97757"/></svg>',
		'mistral'   => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" role="img" aria-label="Mistral"><rect x="0" y="0" width="8" height="8" fill="#000"/><rect x="8" y="0" width="8" height="8" fill="#f80"/><rect x="16" y="0" width="8" height="8" fill="#000"/><rect x="0" y="8" width="8" height="8" fill="#f80"/><rect x="8" y="8" width="8" height="8" fill="#000"/><rect x="16" y="8" width="8" height="8" fill="#f80"/><rect x="0" y="16" width="8" height="8" fill="#000"/><rect x="8" y="16" width="8" height="8" fill="#f80"/><rect x="16" y="16" width="8" height="8" fill="#000"/></svg>',
		'ollama'    => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" role="img" aria-label="Ollama"><circle cx="12" cy="12" r="10" fill="#000"/><text x="12" y="16" font-size="10" font-weight="bold" fill="#fff" font-family="monospace" dominant-baseline="auto" text-anchor="middle">ol</text></svg>',
	);
	return $icons[ $slug ] ?? '';
}
