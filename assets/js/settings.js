/**
 * Settings page JavaScript for LLM provider configuration
 */

// LLM Provider configuration
const llmProviders = {
	openai: {
		name: 'OpenAI',
		docs: 'https://platform.openai.com/api-keys',
		models: {
			'gpt-4o':         { label: 'GPT-4o (Recommended)',      vision: true },
			'gpt-4o-mini':    { label: 'GPT-4o Mini (Faster, cheaper)', vision: true },
			'gpt-4-turbo':    { label: 'GPT-4 Turbo',               vision: true },
			'gpt-4':          { label: 'GPT-4',                     vision: false },
			'gpt-3.5-turbo':  { label: 'GPT-3.5 Turbo',             vision: false }
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
			'claude-3-5-sonnet-20241022': { label: 'Claude 3.5 Sonnet (Recommended)', vision: true },
			'claude-3-5-haiku-20241022':  { label: 'Claude 3.5 Haiku (Fast)',         vision: true },
			'claude-3-opus-20240229':     { label: 'Claude 3 Opus (Most capable)',    vision: true }
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
			'grok-3':             { label: 'Grok 3 (Recommended)',         vision: false },
			'grok-3-fast':        { label: 'Grok 3 Fast (Lower latency)',  vision: false },
			'grok-3-mini':        { label: 'Grok 3 Mini (Efficient)',      vision: false },
			'grok-3-mini-fast':   { label: 'Grok 3 Mini Fast (Fastest)',   vision: false },
			'grok-2-vision-latest': { label: 'Grok 2 Vision',             vision: true }
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
			'gemini-2.0-flash-exp': { label: 'Gemini 2.0 Flash (Recommended)', vision: true },
			'gemini-1.5-pro':       { label: 'Gemini 1.5 Pro',                 vision: true },
			'gemini-1.5-flash':     { label: 'Gemini 1.5 Flash (Fast)',        vision: true }
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
			'mistral-large-latest':  { label: 'Mistral Large (Recommended)', vision: false },
			'mistral-medium-latest': { label: 'Mistral Medium',              vision: false },
			'mistral-small-latest':  { label: 'Mistral Small (Fast)',        vision: false },
			'pixtral-large-latest':  { label: 'Pixtral Large',              vision: true }
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
	for (const [value, info] of Object.entries(config.models)) {
		const option = document.createElement('option');
		option.value = value;
		option.textContent = info.vision ? info.label + ' 👁' : info.label;
		option.selected = (value === currentModel || value === config.default);
		modelSelect.appendChild(option);
	}

	// Update model help text
	modelHelp.innerHTML = '👁 = supports image/vision';

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

	// Update agent mode help text
	const agentModeSelect = document.getElementById('agentic_agent_mode');
	const agentModeHelp = document.getElementById('agentic-agent-mode-help');
	
	const modeDescriptions = {
		disabled: 'Agent chat only, no file/database actions',
		supervised: 'Documentation updates are autonomous, code changes require approval',
		autonomous: 'All actions are executed immediately (use with caution)'
	};

	function updateAgentModeHelp() {
		if (agentModeSelect && agentModeHelp) {
			const mode = agentModeSelect.value;
			agentModeHelp.textContent = modeDescriptions[mode] || '';
		}
	}

	if (agentModeSelect) {
		updateAgentModeHelp();
		agentModeSelect.addEventListener('change', updateAgentModeHelp);
	}

	// System Requirements Checker
	const systemCheckBtn = document.getElementById('agentic-system-check');
	if (systemCheckBtn) {
		systemCheckBtn.addEventListener('click', async function() {
			const btn = this;
			const spinner = document.getElementById('agentic-check-spinner');
			const resultsDiv = document.getElementById('agentic-system-results');
			
			btn.disabled = true;
			spinner.style.display = 'inline-block';
			resultsDiv.innerHTML = '<p>Running system checks...</p>';
			resultsDiv.style.display = 'block';
			
			try {
				// Run all checks
				const response = await fetch('/wp-json/agentic/v1/system-check', {
					headers: { 
						'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' 
					}
				});
				
				if (!response.ok) {
					throw new Error('System check request failed');
				}
				
				const data = await response.json();
				
				// Build results table
				let html = '';
				
				// Summary
				const passed = data.checks.filter(c => c.status === 'pass').length;
				const total = data.checks.length;
				
				if (passed === total) {
					html += '<div class="notice notice-success inline" style="margin-bottom: 15px;"><p><strong>All checks passed!</strong> Your server is ready for the Agent Builder.</p></div>';
				} else {
					html += '<div class="notice notice-error inline" style="margin-bottom: 15px;"><p><strong>Some checks failed.</strong> Please fix the issues below before using the Agent Builder.</p></div>';
				}
				
				// Results table
				html += '<table class="widefat" style="max-width: 900px;">';
				html += '<thead><tr>';
				html += '<th>Requirement</th>';
				html += '<th>Status</th>';
				html += '<th>Current Value</th>';
				html += '<th>Required</th>';
				html += '<th>Action</th>';
				html += '</tr></thead><tbody>';
				
				data.checks.forEach(check => {
					let statusIcon, statusColor, statusText, rowClass;
					
					if (check.status === 'pass') {
						statusIcon = '<span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span>';
						statusColor = '#22c55e';
						statusText = 'Pass';
						rowClass = 'pass';
					} else if (check.status === 'warning') {
						statusIcon = '<span class="dashicons dashicons-warning" style="color: #f59e0b;"></span>';
						statusColor = '#f59e0b';
						statusText = 'Warning';
						rowClass = 'warning';
					} else {
						statusIcon = '<span class="dashicons dashicons-no-alt" style="color: #b91c1c;"></span>';
						statusColor = '#b91c1c';
						statusText = 'Fail';
						rowClass = 'fail';
					}
					
					html += '<tr class="' + rowClass + '">';
					html += '<td><strong>' + check.name + '</strong></td>';
					html += '<td>' + statusIcon + ' ' + statusText + '</td>';
					html += '<td>' + check.value + '</td>';
					html += '<td>' + check.required + '</td>';
					html += '<td>';
					
					if (check.status !== 'pass') {
						html += '<details style="cursor: pointer;">';
						html += '<summary style="color: #2271b1; text-decoration: underline;">Show fix</summary>';
						html += '<p style="margin: 8px 0 0 0; padding: 8px; background: #f0f0f1; border-left: 3px solid ' + statusColor + ';">';
						html += check.fix;
						html += '</p></details>';
					} else {
						html += '—';
					}
					
					html += '</td>';
					html += '</tr>';
				});
				
				html += '</tbody></table>';
				
				resultsDiv.innerHTML = html;
				
			} catch (error) {
				resultsDiv.innerHTML = '<div class="notice notice-error inline"><p>System check failed: ' + error.message + '</p></div>';
			} finally {
				btn.disabled = false;
				spinner.style.display = 'none';
			}
		});
	}

	// Developer tab - API Key update functionality
	const updateApiKeyBtn = document.getElementById('agentic-update-api-key-btn');
	const updateApiKeyForm = document.getElementById('agentic-update-api-key-form');
	const updateApiKeyInput = document.getElementById('agentic-update-api-key-input');
	const saveUpdatedKeyBtn = document.getElementById('agentic-save-updated-api-key');
	const cancelUpdateBtn = document.getElementById('agentic-cancel-update-api-key');
	const disconnectBtn = document.getElementById('agentic-disconnect-developer');

	// Check for api-key parameter in URL (user returning from registration)
	const urlParams = new URLSearchParams(window.location.search);
	const apiKeyFromUrl = urlParams.get('api-key');
	
	if (apiKeyFromUrl && urlParams.get('tab') === 'developer') {
		// User just registered and returned with API key
		// Check if we have an existing API key or not
		const hasExistingKey = document.getElementById('agentic-update-api-key-btn') !== null;
		
		if (hasExistingKey && updateApiKeyForm && updateApiKeyInput) {
			// User already has a key - show update form with pre-filled key
			updateApiKeyForm.style.display = 'block';
			updateApiKeyInput.value = apiKeyFromUrl;
			updateApiKeyInput.focus();
			
			// Show a notice
			const devTabContent = document.querySelector('.form-table');
			if (devTabContent) {
				const notice = document.createElement('div');
				notice.className = 'notice notice-info inline';
				notice.style.cssText = 'padding: 12px; margin: 15px 0;';
				notice.innerHTML = '<p style="margin: 0;"><strong>Welcome back!</strong> Your new developer API key has been pre-filled below. Click "Save" to update your account.</p>';
				devTabContent.parentNode.insertBefore(notice, devTabContent);
			}
		} else {
			// No existing key - save the new one directly
			(async () => {
				try {
					const response = await fetch('/wp-admin/admin-ajax.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: new URLSearchParams({
							action: 'agentic_save_developer_api_key',
							api_key: apiKeyFromUrl,
							nonce: agenticMarketplace?.nonce || ''
						})
					});

					const data = await response.json();

					if (data.success) {
						// Clean URL first to avoid re-triggering
						const cleanUrl = new URL(window.location);
						cleanUrl.searchParams.delete('api-key');
						window.history.replaceState({}, '', cleanUrl);
						
						// Show success and reload
						alert('Your developer account has been connected successfully!');
						window.location.reload();
					} else {
						alert('Failed to save API key: ' + (data.data?.message || 'Unknown error'));
					}
				} catch (error) {
					alert('Error saving API key: ' + error.message);
				}
			})();
		}
		
		// Clean URL (remove api-key parameter) - only if not saving automatically
		if (updateApiKeyForm) {
			const cleanUrl = new URL(window.location);
			cleanUrl.searchParams.delete('api-key');
			window.history.replaceState({}, '', cleanUrl);
		}
	}

	if (updateApiKeyBtn && updateApiKeyForm) {
		updateApiKeyBtn.addEventListener('click', function() {
			updateApiKeyForm.style.display = 'block';
			updateApiKeyInput.focus();
		});
	}

	if (cancelUpdateBtn && updateApiKeyForm) {
		cancelUpdateBtn.addEventListener('click', function() {
			updateApiKeyForm.style.display = 'none';
			updateApiKeyInput.value = '';
		});
	}

	if (saveUpdatedKeyBtn && updateApiKeyInput) {
		saveUpdatedKeyBtn.addEventListener('click', async function() {
			const newApiKey = updateApiKeyInput.value.trim();
			
			if (!newApiKey) {
				alert('Please enter a valid API key.');
				return;
			}

			saveUpdatedKeyBtn.disabled = true;
			saveUpdatedKeyBtn.textContent = 'Saving...';

			try {
				const response = await fetch('/wp-admin/admin-ajax.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'agentic_save_developer_api_key',
						api_key: newApiKey,
						nonce: agenticMarketplace?.nonce || ''
					})
				});

				const data = await response.json();

				if (data.success) {
					alert('API key updated successfully! Reloading page...');
					window.location.reload();
				} else {
					alert('Failed to update API key: ' + (data.data?.message || 'Unknown error'));
				}
			} catch (error) {
				alert('Error updating API key: ' + error.message);
			} finally {
				saveUpdatedKeyBtn.disabled = false;
				saveUpdatedKeyBtn.textContent = 'Save';
			}
		});
	}

	if (disconnectBtn) {
		disconnectBtn.addEventListener('click', async function() {
			if (!confirm('Are you sure you want to disconnect your developer account? Your revenue data will no longer be accessible from this plugin.')) {
				return;
			}

			disconnectBtn.disabled = true;
			disconnectBtn.textContent = 'Disconnecting...';

			try {
				const response = await fetch('/wp-admin/admin-ajax.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'agentic_disconnect_developer',
						nonce: agenticMarketplace?.nonce || ''
					})
				});

				const data = await response.json();

				if (data.success) {
					alert('Developer account disconnected successfully! Reloading page...');
					window.location.reload();
				} else {
					alert('Failed to disconnect: ' + (data.data?.message || 'Unknown error'));
				}
			} catch (error) {
				alert('Error disconnecting: ' + error.message);
			} finally {
				disconnectBtn.disabled = false;
				disconnectBtn.textContent = 'Disconnect';
			}
		});
	}
});
