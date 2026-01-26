/**
 * Settings page JavaScript for LLM provider configuration
 */

// LLM Provider configuration
const llmProviders = {
	openai: {
		name: 'OpenAI',
		docs: 'https://platform.openai.com/api-keys',
		models: {
			'gpt-4o': 'GPT-4o (Recommended)',
			'gpt-4o-mini': 'GPT-4o Mini (Faster, cheaper)',
			'gpt-4-turbo': 'GPT-4 Turbo',
			'gpt-4': 'GPT-4',
			'gpt-3.5-turbo': 'GPT-3.5 Turbo'
		},
		default: 'gpt-4o',
		steps: [
			'Click the "Get API Key" button above to visit OpenAI Platform',
			'Sign up or log in to your OpenAI account',
			'Navigate to the API Keys section',
			'Click "Create new secret key" and give it a name',
			'Copy the generated API key and paste it below'
		]
	},
	anthropic: {
		name: 'Anthropic',
		docs: 'https://console.anthropic.com/settings/keys',
		models: {
			'claude-3-5-sonnet-20241022': 'Claude 3.5 Sonnet (Recommended)',
			'claude-3-5-haiku-20241022': 'Claude 3.5 Haiku (Fast)',
			'claude-3-opus-20240229': 'Claude 3 Opus (Most capable)'
		},
		default: 'claude-3-5-sonnet-20241022',
		steps: [
			'Click the "Get API Key" button above to visit Anthropic Console',
			'Sign up or log in to your Anthropic account',
			'Go to Settings → API Keys',
			'Click "Create Key" and provide a name',
			'Copy the generated API key and paste it below'
		]
	},
	xai: {
		name: 'xAI',
		docs: 'https://console.x.ai/',
		models: {
			'grok-3': 'Grok 3 (Recommended)',
			'grok-3-fast': 'Grok 3 Fast (Lower latency)',
			'grok-3-mini': 'Grok 3 Mini (Efficient)',
			'grok-3-mini-fast': 'Grok 3 Mini Fast (Fastest)'
		},
		default: 'grok-3',
		steps: [
			'Click the "Get API Key" button above to visit xAI Console',
			'Sign up or log in to your xAI account',
			'Navigate to the API Keys section',
			'Click "Create API Key" and follow the prompts',
			'Copy the generated API key and paste it below'
		]
	},
	google: {
		name: 'Google',
		docs: 'https://makersuite.google.com/app/apikey',
		models: {
			'gemini-2.0-flash-exp': 'Gemini 2.0 Flash (Recommended)',
			'gemini-1.5-pro': 'Gemini 1.5 Pro',
			'gemini-1.5-flash': 'Gemini 1.5 Flash (Fast)'
		},
		default: 'gemini-2.0-flash-exp',
		steps: [
			'Click the "Get API Key" button above to visit Google AI Studio',
			'Sign in with your Google account',
			'Click "Get API key" in the left sidebar',
			'Click "Create API key" and select your Google Cloud project',
			'Copy the generated API key and paste it below'
		]
	},
	mistral: {
		name: 'Mistral',
		docs: 'https://console.mistral.ai/api-keys/',
		models: {
			'mistral-large-latest': 'Mistral Large (Recommended)',
			'mistral-medium-latest': 'Mistral Medium',
			'mistral-small-latest': 'Mistral Small (Fast)'
		},
		default: 'mistral-large-latest',
		steps: [
			'Click the "Get API Key" button above to visit Mistral AI Console',
			'Sign up or log in to your Mistral AI account',
			'Go to the API Keys section',
			'Click "Create new key" and provide a name',
			'Copy the generated API key and paste it below'
		]
	}
};

function updateLLMFields() {
	const providerSelect = document.getElementById('agentic_llm_provider');
	const modelSelect = document.getElementById('agentic_model');
	const apiHelp = document.getElementById('agentic-api-key-help');
	const modelHelp = document.getElementById('agentic-model-help');
	const getApiKeyBtn = document.getElementById('agentic-get-api-key');
	const instructionsDiv = document.getElementById('agentic-api-key-instructions');
	const stepsOl = document.getElementById('agentic-api-steps');

	if (!providerSelect || !modelSelect || !apiHelp || !modelHelp) {
		return;
	}

	const provider = providerSelect.value;
	const config = llmProviders[provider] || llmProviders.openai;
	const currentModel = modelSelect.dataset.currentModel || modelSelect.value;

	// Update Get API Key button link
	if (getApiKeyBtn) {
		getApiKeyBtn.href = config.docs;
	}

	// Update API key help text
	apiHelp.innerHTML = `Your ${config.name} API key. <a href="#" id="agentic-show-instructions" style="text-decoration: underline;">Need help getting an API key?</a>`;

	// Update instructions steps
	if (stepsOl && config.steps) {
		stepsOl.innerHTML = config.steps.map(step => `<li style="margin-bottom: 4px;">${step}</li>`).join('');
	}

	// Update model dropdown
	modelSelect.innerHTML = '';
	for (const [value, label] of Object.entries(config.models)) {
		const option = document.createElement('option');
		option.value = value;
		option.textContent = label;
		option.selected = (value === currentModel || value === config.default);
		modelSelect.appendChild(option);
	}

	// Update model help text
	modelHelp.innerHTML = `The ${config.name} model to use for agent responses. The recommended option provides the best results.`;

	// Re-attach event listener for show instructions link
	setTimeout(() => {
		const showInstructionsLink = document.getElementById('agentic-show-instructions');
		if (showInstructionsLink && instructionsDiv) {
			showInstructionsLink.addEventListener('click', function(e) {
				e.preventDefault();
				const isVisible = instructionsDiv.style.display !== 'none';
				instructionsDiv.style.display = isVisible ? 'none' : 'block';
				showInstructionsLink.textContent = isVisible ? 'Need help getting an API key?' : 'Hide instructions';
			});
		}
	}, 0);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
	updateLLMFields();
	const providerSelect = document.getElementById('agentic_llm_provider');
	if (providerSelect) {
		providerSelect.addEventListener('change', updateLLMFields);
	}
});
