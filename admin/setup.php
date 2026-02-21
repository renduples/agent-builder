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

		update_option( 'agentic_llm_provider', $provider );
		update_option( 'agentic_llm_api_key', $api_key );
		update_option( 'agentic_model', $provider_defaults[ $provider ] );
		update_option( 'agentic_onboarding_complete', true );

		wp_safe_redirect( admin_url( 'admin.php?page=agentic-chat&onboarding=1' ) );
		exit;
	}
}

if ( isset( $_GET['skip_setup'] ) && check_admin_referer( 'agentic_skip_setup' ) ) {
	update_option( 'agentic_onboarding_complete', true );
	wp_safe_redirect( admin_url( 'admin.php?page=agentbuilder' ) );
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
		'key_label'   => 'Get API key at console.x.ai → API Keys',
		'placeholder' => 'xai-…',
		'steps'       => array(
			array(
				'text'       => 'Go to <a href="https://accounts.x.ai/sign-up" target="_blank" rel="noopener">accounts.x.ai/sign-up</a> and create your account.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/xai-sign-up.png',
				'alt'        => 'xAI sign-up page',
			),
			array(
				'text'       => 'Once logged in, go to <a href="https://console.x.ai" target="_blank" rel="noopener">console.x.ai</a> → <strong>API Keys</strong> → <strong>Create API Key</strong>. Copy the key — it is only shown once.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/xai-create-key.png',
				'alt'        => 'xAI create API key',
			),
		),
	),
	'openai'    => array(
		'name'        => 'OpenAI',
		'icon'        => 'openai',
		'badge'       => '',
		'badge_class' => '',
		'signup_url'  => 'https://auth.openai.com/create-account',
		'key_url'     => 'https://platform.openai.com/api-keys',
		'key_label'   => 'Get API key at platform.openai.com → API keys',
		'placeholder' => 'sk-…',
		'steps'       => array(
			array(
				'text'       => 'Go to <a href="https://auth.openai.com/create-account" target="_blank" rel="noopener">auth.openai.com/create-account</a> and create your account.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/openai-sign-up.png',
				'alt'        => 'OpenAI sign-up page',
			),
			array(
				'text'       => 'Confirm your age when prompted.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/openai-confirm-age.png',
				'alt'        => 'OpenAI confirm age',
			),
			array(
				'text'       => 'Go to <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com → API keys</a> → <strong>Create new secret key</strong>. Copy it — it is only shown once. <strong>Note:</strong> you may need to add a payment method before the API will work.',
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
		'key_label'   => 'Get API key at aistudio.google.com → Get API key',
		'placeholder' => 'AIza…',
		'steps'       => array(
			array(
				'text'       => 'Go to <a href="https://aistudio.google.com" target="_blank" rel="noopener">aistudio.google.com</a>, click <strong>Get Started</strong>, and sign in with your Google account. Accept the Terms of Service.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/gemini-sign-up.png',
				'alt'        => 'Google AI Studio sign-up',
			),
			array(
				'text'       => 'Click <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener"><strong>Get API key</strong></a> in the left sidebar → <strong>Create API key</strong>. Copy the key.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/gemini-create-key.png',
				'alt'        => 'Google AI Studio create key',
			),
		),
	),
	'anthropic' => array(
		'name'        => 'Anthropic (Claude)',
		'icon'        => 'anthropic',
		'badge'       => 'Credits required',
		'badge_class' => 'badge-paid',
		'signup_url'  => 'https://platform.claude.com/login?returnTo=%2F%3F',
		'key_url'     => 'https://platform.claude.com/settings/keys',
		'key_label'   => 'Get API key at platform.claude.com → API Keys',
		'placeholder' => 'sk-ant-…',
		'steps'       => array(
			array(
				'text'       => 'Go to <a href="https://platform.claude.com/login?returnTo=%2F%3F" target="_blank" rel="noopener">platform.claude.com</a> and create your account.',
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
				'text'       => 'Go to <strong>Settings → API Keys → Create Key</strong>. Copy it — it is only shown once. <strong>Note:</strong> you will need to add credits (minimum $5) before the API will work.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/claude-create-key.png',
				'alt'        => 'Anthropic create API key',
			),
		),
	),
	'mistral'   => array(
		'name'        => 'Mistral AI',
		'icon'        => 'mistral',
		'badge'       => '',
		'badge_class' => '',
		'signup_url'  => 'https://console.mistral.ai',
		'key_url'     => 'https://console.mistral.ai/api-keys',
		'key_label'   => 'Get API key at console.mistral.ai → API Keys',
		'placeholder' => '',
		'steps'       => array(
			array(
				'text'       => 'Go to <a href="https://console.mistral.ai" target="_blank" rel="noopener">console.mistral.ai</a> and sign up.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/mistral-sign-up.png',
				'alt'        => 'Mistral sign-up page',
			),
			array(
				'text'       => 'Create a team when prompted.',
				'screenshot' => 'https://agentic-plugin.com/wp-content/uploads/2026/02/mistral-create-team.png',
				'alt'        => 'Mistral create team',
			),
			array(
				'text'       => 'Go to <a href="https://console.mistral.ai/api-keys" target="_blank" rel="noopener"><strong>API Keys → Create new key</strong></a>. Copy it — it is only shown once.',
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
				'text'       => 'Go to <a href="https://ollama.com/download" target="_blank" rel="noopener">ollama.com/download</a> and download Ollama for your operating system.',
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

		.step-screenshot {
			display: block;
			max-width: 100%;
			border: 1px solid #c3c4c7;
			border-radius: 6px;
			margin-top: 8px;
			cursor: pointer;
			transition: box-shadow 0.15s;
			max-height: 200px;
			object-fit: cover;
			object-position: top;
		}

		.step-screenshot:hover {
			box-shadow: 0 4px 16px rgba(0,0,0,0.18);
		}

		/* ── Key field ───────────────────────────────── */
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
			gap: 10px;
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
		<p><?php esc_html_e( 'Connect an AI provider to get started. This takes about 2 minutes.', 'agentbuilder' ); ?></p>
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
	</div>

	<form method="post" action="" id="setup-form">
		<?php wp_nonce_field( 'agentic_setup', 'agentic_setup_nonce' ); ?>
		<input type="hidden" name="agentic_llm_provider" id="hidden_provider" value="">
		<input type="hidden" name="agentic_llm_api_key" id="hidden_api_key" value="">

		<div class="setup-card">

			<!-- ── Phase 1: Do you have an account? ─────────────────── -->
			<div class="setup-card-inner" id="phase-account">
				<div class="account-question">
					<h2><?php esc_html_e( 'Do you already have an account with an AI provider?', 'agentbuilder' ); ?></h2>
					<p><?php esc_html_e( 'An AI provider gives Agent Builder the ability to understand and respond in your WordPress admin. You need an account with at least one provider.', 'agentbuilder' ); ?></p>
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
			</div>

			<!-- ── Phase 3: Per-provider instructions + key entry ─────── -->
			<?php foreach ( $providers as $slug => $p ) : ?>
			<div class="setup-card-inner provider-steps-section" id="phase-steps-<?php echo esc_attr( $slug ); ?>">
				<div class="provider-steps-header">
					<button type="button" class="btn-back" onclick="showPhase('provider-pick', window.hasAccount)">← <?php esc_html_e( 'Back', 'agentbuilder' ); ?></button>
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
							<img
								src="<?php echo esc_url( $step['screenshot'] ); ?>"
								alt="<?php echo esc_attr( $step['alt'] ); ?>"
								class="step-screenshot"
								onclick="openLightbox(this.src)"
								loading="lazy"
							>
							<?php endif; ?>
						</div>
					</li>
					<?php
					++$step_num;
					endforeach;
					?>
				</ul>

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
					<label for="key-input-<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Paste your API key', 'agentbuilder' ); ?></label>
					<p class="key-hint"><?php echo esc_html( $p['key_label'] ); ?></p>
					<div class="key-input-row">
						<input
							type="password"
							id="key-input-<?php echo esc_attr( $slug ); ?>"
							placeholder="<?php echo esc_attr( $p['placeholder'] ); ?>"
							autocomplete="off"
							oninput="onKeyInput('<?php echo esc_js( $slug ); ?>')"
						>
					</div>
					<?php endif; ?>

					<div class="test-status" id="test-status-<?php echo esc_attr( $slug ); ?>">
						<span class="test-icon"></span>
						<span class="test-msg"></span>
					</div>
				</div>
			</div>
			<?php endforeach; ?>

			<div class="setup-card-footer" id="setup-footer">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=agentic-setup&skip_setup=1' ), 'agentic_skip_setup' ) ); ?>" class="btn-skip">
					<?php esc_html_e( 'Skip for now', 'agentbuilder' ); ?>
				</a>
				<button type="button" class="btn-primary" id="btn-test" style="display:none" onclick="testAndConnect()">
					<?php esc_html_e( 'Test & Connect', 'agentbuilder' ); ?>
				</button>
			</div>

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
var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
var ajaxNonce = <?php echo wp_json_encode( wp_create_nonce( 'agentic_test_connection' ) ); ?>;
var providerSignupUrls = <?php echo wp_json_encode( array_map( fn( $p ) => $p['signup_url'], $providers ) ); ?>;

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
	var phases = ['phase-account', 'phase-provider-pick'];
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

	// Reset.
	[p1, p2, p3].forEach(function(el) {
		el.classList.remove('active', 'done');
	});

	if (phase === 'account') {
		p1.classList.add('active');
	} else if (phase === 'provider-pick') {
		p1.classList.add('done');
		p2.classList.add('active');
	} else {
		p1.classList.add('done');
		p2.classList.add('done');
		p3.classList.add('active');
	}

	document.getElementById('btn-test').style.display = 'none';
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

	// If signing up, open sign-up URL in new tab.
	if (!hasAccount && providerSignupUrls[slug]) {
		window.open(providerSignupUrls[slug], '_blank', 'noopener');
	}

	showPhase('steps-' + slug, hasAccount);

	// Show test button only if Ollama (no key needed) or after key input.
	if (slug === 'ollama') {
		document.getElementById('btn-test').style.display = 'block';
	} else {
		document.getElementById('btn-test').style.display = 'none';
	}
}

function onKeyInput(slug) {
	var input = document.getElementById('key-input-' + slug);
	var btn = document.getElementById('btn-test');
	if (input && input.value.trim().length > 10) {
		btn.style.display = 'block';
	} else {
		btn.style.display = 'none';
	}
	// Reset any test status.
	setTestStatus(slug, '');
}

function setTestStatus(slug, state, msg) {
	var el = document.getElementById('test-status-' + slug);
	if (!el) return;
	el.className = 'test-status ' + (state || '');
	var icon = { testing: '⏳', success: '✅', error: '❌' };
	el.querySelector('.test-icon').textContent = icon[state] || '';
	el.querySelector('.test-msg').textContent = msg || '';
}

function testAndConnect() {
	if (!selectedProvider) return;

	var key = '';
	if (selectedProvider === 'ollama') {
		key = document.getElementById('key-input-ollama').value.trim() || 'http://localhost:11434';
	} else {
		var keyInput = document.getElementById('key-input-' + selectedProvider);
		if (!keyInput || !keyInput.value.trim()) return;
		key = keyInput.value.trim();
	}

	var btn = document.getElementById('btn-test');
	btn.disabled = true;
	btn.textContent = 'Testing…';
	setTestStatus(selectedProvider, 'testing', 'Connecting to ' + selectedProvider + '…');

	var formData = new FormData();
	formData.append('action', 'agentic_test_connection');
	formData.append('nonce', ajaxNonce);
	formData.append('provider', selectedProvider);
	formData.append('api_key', key);

	fetch(ajaxUrl, { method: 'POST', body: formData })
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (data.success) {
				setTestStatus(selectedProvider, 'success', data.data.message || 'Connected successfully!');
				document.getElementById('hidden_provider').value = selectedProvider;
				document.getElementById('hidden_api_key').value = key;
				btn.textContent = 'Save & Continue →';
				btn.disabled = false;
				btn.onclick = function() {
					document.getElementById('setup-form').submit();
				};
			} else {
				setTestStatus(selectedProvider, 'error', data.data.message || 'Connection failed. Check your API key and try again.');
				btn.textContent = 'Test & Connect';
				btn.disabled = false;
				btn.onclick = testAndConnect;
			}
		})
		.catch(function() {
			setTestStatus(selectedProvider, 'error', 'Network error. Please try again.');
			btn.textContent = 'Test & Connect';
			btn.disabled = false;
			btn.onclick = testAndConnect;
		});
}

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
