/**
 * Agentic Chat Interface
 * 
 * Supports per-agent session isolation - each agent has its own
 * conversation history and session ID.
 */
(function() {
    'use strict';

    // Get current agent from data attribute (supports both admin template ID and shortcode dynamic ID)
    const chatContainer = document.getElementById('agentic-chat') || document.querySelector('.agentic-chat-container[data-agent-id]');
    const currentAgentId = chatContainer ? chatContainer.dataset.agentId || 'default' : 'default';

    // State - keyed by agent
    let conversationHistory = [];
    let sessionId = localStorage.getItem(`agentic_session_${currentAgentId}`) || generateUUID();
    let isProcessing = false;
    let totalTokens = 0;
    let totalCost = 0;
    let pendingImage = null; // { dataUrl, mimeType, name }

    // Store session ID for this agent
    localStorage.setItem(`agentic_session_${currentAgentId}`, sessionId);

    // Elements
    const form = document.getElementById('agentic-chat-form');
    const input = document.getElementById('agentic-input');
    const messages = document.getElementById('agentic-messages');
    const sendBtn = document.getElementById('agentic-send');
    const typingIndicator = document.getElementById('agentic-typing');
    const clearBtn = document.getElementById('agentic-clear-chat');
    const stats = document.getElementById('agentic-stats');
    const agentSelect = document.getElementById('agentic-agent-select');

    // Initialize
    function init() {
        if (!form) return;

        form.addEventListener('submit', handleSubmit);
        input.addEventListener('keydown', handleKeydown);
        input.addEventListener('input', autoResize);
        if (clearBtn) {
            clearBtn.addEventListener('click', clearConversation);
        }

        // New Chat button (header)
        const newChatBtn = chatContainer ? chatContainer.querySelector('.agentic-new-chat-btn') : null;
        if (newChatBtn) {
            newChatBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                clearConversation();
            });
        }

        // Minimize button (header)
        const minimizeBtn = chatContainer ? chatContainer.querySelector('.agentic-minimize-btn') : null;
        if (minimizeBtn) {
            minimizeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                chatContainer.classList.toggle('agentic-chat-minimized');
            });
            // Also allow clicking the header itself to expand when minimized
            const header = chatContainer.querySelector('.agentic-chat-header');
            if (header) {
                header.addEventListener('click', function(e) {
                    if (chatContainer.classList.contains('agentic-chat-minimized')) {
                        chatContainer.classList.remove('agentic-chat-minimized');
                    }
                });
            }
        }

        // Handle agent switching
        if (agentSelect) {
            agentSelect.addEventListener('change', handleAgentSwitch);
        }

        // Handle suggested prompt clicks
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('agentic-prompt-btn')) {
                const prompt = e.target.getAttribute('data-prompt');
                if (prompt && input) {
                    input.value = prompt;
                    input.focus();
                    autoResize();
                }
            }
        });

        // Voice input (Web Speech API — graceful degradation)
        initVoiceInput();

        // File/image upload
        initFileUpload();

        // Load saved conversation for current agent
        loadConversation();
    }

    // Voice input via Web Speech API
    function initVoiceInput() {
        const voiceBtn = document.getElementById('agentic-voice-btn');
        if (!voiceBtn) return;

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        // Always show the button — handle unsupported/insecure at click time
        voiceBtn.style.display = '';

        if (!SpeechRecognition) {
            voiceBtn.title = 'Voice input requires Chrome, Edge, or Safari with HTTPS';
            voiceBtn.style.opacity = '0.4';
            voiceBtn.addEventListener('click', function() {
                alert('Voice input is not available.\n\nRequirements:\n• Chrome, Edge, or Safari\n• HTTPS connection (not plain HTTP)');
            });
            return;
        }

        let recognition = null;
        let isListening = false;

        voiceBtn.addEventListener('click', function() {
            if (isListening) {
                recognition.stop();
                return;
            }

            recognition = new SpeechRecognition();
            recognition.lang = document.documentElement.lang || 'en-US';
            recognition.interimResults = true;
            recognition.continuous = false;
            recognition.maxAlternatives = 1;

            const existingText = input.value;

            recognition.onstart = function() {
                isListening = true;
                voiceBtn.classList.add('agentic-voice-active');
                input.placeholder = 'Listening...';
            };

            recognition.onresult = function(event) {
                let interim = '';
                let final = '';
                for (let i = 0; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        final += event.results[i][0].transcript;
                    } else {
                        interim += event.results[i][0].transcript;
                    }
                }
                // Show interim results live, append final when done
                input.value = existingText + (existingText ? ' ' : '') + (final || interim);
                autoResize();
            };

            recognition.onend = function() {
                isListening = false;
                voiceBtn.classList.remove('agentic-voice-active');
                input.placeholder = input.getAttribute('data-original-placeholder') || 'Type your message...';
                input.focus();
            };

            recognition.onerror = function(event) {
                isListening = false;
                voiceBtn.classList.remove('agentic-voice-active');
                input.placeholder = input.getAttribute('data-original-placeholder') || 'Type your message...';
                if (event.error === 'not-allowed') {
                    alert('Microphone access denied.\n\nThis may require HTTPS. Try accessing this page over https://.');
                } else if (event.error !== 'aborted' && event.error !== 'no-speech') {
                    console.warn('Speech recognition error:', event.error);
                }
            };

            // Save original placeholder
            if (!input.getAttribute('data-original-placeholder')) {
                input.setAttribute('data-original-placeholder', input.placeholder);
            }

            recognition.start();
        });
    }

    // File/image upload
    function initFileUpload() {
        const attachBtn = document.getElementById('agentic-attach-btn');
        const fileInput = document.getElementById('agentic-file-input');
        const previewWrap = document.getElementById('agentic-image-preview');
        const previewImg = document.getElementById('agentic-preview-img');
        const removeBtn = document.getElementById('agentic-remove-image');
        const MAX_SIZE = 5 * 1024 * 1024; // 5 MB

        if (!attachBtn || !fileInput) return;

        attachBtn.addEventListener('click', function() {
            fileInput.click();
        });

        fileInput.addEventListener('change', function() {
            const file = fileInput.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                alert('Only image files are supported (JPEG, PNG, GIF, WebP).');
                fileInput.value = '';
                return;
            }

            if (file.size > MAX_SIZE) {
                alert('Image must be under 5 MB.');
                fileInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                pendingImage = {
                    dataUrl: e.target.result,
                    mimeType: file.type,
                    name: file.name
                };
                if (previewWrap && previewImg) {
                    previewImg.src = e.target.result;
                    previewWrap.style.display = 'flex';
                }
                // Adjust attach button style
                attachBtn.classList.add('agentic-attach-active');
            };
            reader.readAsDataURL(file);
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                clearPendingImage();
            });
        }
    }

    function clearPendingImage() {
        pendingImage = null;
        const fileInput = document.getElementById('agentic-file-input');
        const previewWrap = document.getElementById('agentic-image-preview');
        const attachBtn = document.getElementById('agentic-attach-btn');
        if (fileInput) fileInput.value = '';
        if (previewWrap) previewWrap.style.display = 'none';
        if (attachBtn) attachBtn.classList.remove('agentic-attach-active');
    }

    // Handle agent switch from dropdown
    function handleAgentSwitch(e) {
        const newAgentId = e.target.value;
        
        // Check if "Load more..." was selected
        if (newAgentId === 'load-more') {
            window.location.href = agenticChat.restUrl.replace('/wp-json/agentic/v1/', '/wp-admin/admin.php?page=agentic-agents');
            return;
        }
        
        // Save selected agent to localStorage
        localStorage.setItem('agentic_last_selected_agent', newAgentId);
        
        // Reload page with new agent (simplest approach for full state reset)
        const url = new URL(window.location.href);
        url.searchParams.set('agent', newAgentId);
        window.location.href = url.toString();
    }

    // Handle form submission
    async function handleSubmit(e) {
        e.preventDefault();
        
        const message = input.value.trim();
        if ((!message && !pendingImage) || isProcessing) return;

        // Capture image before clearing
        const imageData = pendingImage ? { ...pendingImage } : null;

        // Add user message to UI (with optional image)
        addMessage(message, 'user', {}, imageData);
        
        // Clear input and image
        input.value = '';
        input.style.height = 'auto';
        clearPendingImage();

        // Add to history
        conversationHistory.push({ role: 'user', content: message || 'Describe this image.' });
        saveConversation();

        // Send to agent
        await sendMessage(message || 'Describe this image.', imageData);
    }

    // Handle keyboard shortcuts
    function handleKeydown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    }

    // Auto-resize textarea
    function autoResize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 150) + 'px';
    }

    // Add message to UI
    function addMessage(content, role, meta = {}, imageData = null) {
        const div = document.createElement('div');
        div.className = `agentic-message agentic-message-${role}`;

        // Show attached image
        if (imageData && imageData.dataUrl) {
            const imgEl = document.createElement('img');
            imgEl.src = imageData.dataUrl;
            imgEl.className = 'agentic-chat-image';
            imgEl.alt = imageData.name || 'Attached image';
            div.appendChild(imgEl);
        }

        const contentDiv = document.createElement('div');
        contentDiv.className = 'agentic-message-content';
        if (content) {
            contentDiv.innerHTML = role === 'agent' ? renderMarkdown(content) : escapeHtml(content);
        }

        div.appendChild(contentDiv);

        // Add meta info for agent messages
        if (role === 'agent' && (meta.tokens || meta.tools || meta.cached)) {
            const metaDiv = document.createElement('div');
            metaDiv.className = 'agentic-message-meta';
            
            if (meta.cached) {
                metaDiv.innerHTML += `<span class="agentic-cached-indicator" title="Response served from cache">⚡ cached</span>`;
            }
            if (meta.tokens) {
                metaDiv.innerHTML += `<span>Tokens: ${meta.tokens}</span>`;
            }
            if (meta.cost) {
                metaDiv.innerHTML += `<span>Cost: $${meta.cost.toFixed(6)}</span>`;
            }

            div.appendChild(metaDiv);

            // Show tools used
            if (meta.tools && meta.tools.length > 0) {
                const toolsDiv = document.createElement('div');
                toolsDiv.className = 'agentic-tools-used';
                meta.tools.forEach(tool => {
                    const tag = document.createElement('span');
                    tag.className = 'agentic-tool-tag';
                    tag.textContent = tool;
                    toolsDiv.appendChild(tag);
                });
                div.appendChild(toolsDiv);
            }

            // Show proposal card for pending user-space changes
            if (meta.proposal) {
                const proposalDiv = renderProposal(meta.proposal);
                div.appendChild(proposalDiv);
            }
        }

        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    // Send message to API
    async function sendMessage(message, imageData = null) {
        isProcessing = true;
        sendBtn.disabled = true;
        typingIndicator.style.display = 'flex';

        try {
            const payload = {
                message: message,
                session_id: sessionId,
                agent_id: currentAgentId,
                history: conversationHistory.slice(-20) // Last 20 messages for context
            };

            // Attach image as base64
            if (imageData && imageData.dataUrl) {
                payload.image = imageData.dataUrl;
            }

            const response = await fetch(agenticChat.restUrl + 'chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': agenticChat.nonce
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.error) {
                addMessage('Error: ' + data.response, 'agent');
            } else {
                // Add to history
                conversationHistory.push({ role: 'assistant', content: data.response });
                saveConversation();

                // Update totals
                totalTokens += data.tokens_used || 0;
                totalCost += data.cost || 0;
                updateStats();

                // Show cache indicator if response was cached
                const meta = {
                    tokens: data.tokens_used,
                    cost: data.cost,
                    tools: data.tools_used,
                    cached: data.cached || false,
                    proposal: data.pending_proposal ? data.proposal : null
                };

                // Add to UI
                addMessage(data.response, 'agent', meta);
            }
        } catch (error) {
            console.error('Chat error:', error);
            addMessage('Sorry, there was an error connecting to the agent. Please try again.', 'agent');
        } finally {
            isProcessing = false;
            sendBtn.disabled = false;
            typingIndicator.style.display = 'none';
        }
    }

    // Update stats display
    function updateStats() {
        stats.innerHTML = `Tokens: ${totalTokens.toLocaleString()} | Cost: $${totalCost.toFixed(4)}`;
        
        // Save stats to localStorage
        localStorage.setItem(`agentic_stats_${currentAgentId}`, JSON.stringify({
            tokens: totalTokens,
            cost: totalCost
        }));
    }

    // Save conversation to localStorage (per-agent)
    function saveConversation() {
        localStorage.setItem(`agentic_history_${currentAgentId}`, JSON.stringify(conversationHistory));
    }

    // Load conversation from localStorage (per-agent)
    function loadConversation() {
        const saved = localStorage.getItem(`agentic_history_${currentAgentId}`);
        if (saved) {
            try {
                conversationHistory = JSON.parse(saved);
                // Replay messages to UI (skip initial greeting)
                conversationHistory.forEach(msg => {
                    addMessage(msg.content, msg.role === 'user' ? 'user' : 'agent');
                });
            } catch (e) {
                conversationHistory = [];
            }
        }
        
        // Load saved stats
        const savedStats = localStorage.getItem(`agentic_stats_${currentAgentId}`);
        if (savedStats) {
            try {
                const stats = JSON.parse(savedStats);
                totalTokens = stats.tokens || 0;
                totalCost = stats.cost || 0;
                updateStats();
            } catch (e) {
                totalTokens = 0;
                totalCost = 0;
            }
        }
    }

    // Clear conversation (for current agent only)
    function clearConversation() {
        if (!confirm('Clear the conversation history?')) return;
        
        conversationHistory = [];
        localStorage.removeItem(`agentic_history_${currentAgentId}`);
        localStorage.removeItem(`agentic_stats_${currentAgentId}`);
        sessionId = generateUUID();
        localStorage.setItem(`agentic_session_${currentAgentId}`, sessionId);
        totalTokens = 0;
        totalCost = 0;
        updateStats();

        // Clear messages except the first greeting
        while (messages.children.length > 1) {
            messages.removeChild(messages.lastChild);
        }
    }

    // Render a proposal card with diff and approve/reject buttons
    function renderProposal(proposal) {
        const card = document.createElement('div');
        card.className = 'agentic-proposal-card';
        card.dataset.proposalId = proposal.id;

        // Header
        const header = document.createElement('div');
        header.className = 'agentic-proposal-header';
        header.innerHTML = '<span class="dashicons dashicons-editor-code"></span> <strong>Proposed Change</strong>';
        card.appendChild(header);

        // Description
        const desc = document.createElement('div');
        desc.className = 'agentic-proposal-desc';
        desc.textContent = proposal.description || 'Agent wants to make a change.';
        card.appendChild(desc);

        // Diff view
        if (proposal.diff) {
            const diffToggle = document.createElement('button');
            diffToggle.type = 'button';
            diffToggle.className = 'agentic-proposal-toggle';
            diffToggle.textContent = '▶ Show Diff';
            card.appendChild(diffToggle);

            const diffPre = document.createElement('pre');
            diffPre.className = 'agentic-proposal-diff';
            diffPre.style.display = 'none';
            diffPre.innerHTML = formatDiff(proposal.diff);
            card.appendChild(diffPre);

            diffToggle.addEventListener('click', function() {
                const visible = diffPre.style.display !== 'none';
                diffPre.style.display = visible ? 'none' : 'block';
                diffToggle.textContent = visible ? '▶ Show Diff' : '▼ Hide Diff';
            });
        }

        // Action buttons
        const actions = document.createElement('div');
        actions.className = 'agentic-proposal-actions';

        const approveBtn = document.createElement('button');
        approveBtn.type = 'button';
        approveBtn.className = 'agentic-proposal-btn agentic-proposal-approve';
        approveBtn.innerHTML = '<span class="dashicons dashicons-yes"></span> Approve';
        approveBtn.addEventListener('click', () => handleProposalAction(proposal.id, 'approve', card));

        const rejectBtn = document.createElement('button');
        rejectBtn.type = 'button';
        rejectBtn.className = 'agentic-proposal-btn agentic-proposal-reject';
        rejectBtn.innerHTML = '<span class="dashicons dashicons-no"></span> Reject';
        rejectBtn.addEventListener('click', () => handleProposalAction(proposal.id, 'reject', card));

        actions.appendChild(approveBtn);
        actions.appendChild(rejectBtn);
        card.appendChild(actions);

        return card;
    }

    // Format diff with color highlighting
    function formatDiff(diff) {
        return diff.split('\n').map(line => {
            const escaped = escapeHtml(line);
            if (line.startsWith('+')) {
                return '<span class="diff-add">' + escaped + '</span>';
            } else if (line.startsWith('-')) {
                return '<span class="diff-del">' + escaped + '</span>';
            } else if (line.startsWith('@@')) {
                return '<span class="diff-hunk">' + escaped + '</span>';
            }
            return escaped;
        }).join('\n');
    }

    // Send approve/reject to REST API
    async function handleProposalAction(proposalId, action, cardElement) {
        const buttons = cardElement.querySelectorAll('.agentic-proposal-btn');
        buttons.forEach(btn => btn.disabled = true);

        try {
            const response = await fetch(agenticChat.restUrl + 'proposals/' + proposalId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': agenticChat.nonce
                },
                body: JSON.stringify({ action: action })
            });

            const data = await response.json();

            // Update card styling
            cardElement.classList.add('agentic-proposal-' + (action === 'approve' ? 'approved' : 'rejected'));

            // Replace buttons with status
            const actionsDiv = cardElement.querySelector('.agentic-proposal-actions');
            const statusText = action === 'approve' ? '✅ Approved — change applied.' : '❌ Rejected — no change made.';
            actionsDiv.innerHTML = '<div class="agentic-proposal-status">' + statusText + '</div>';

            if (data.error) {
                actionsDiv.innerHTML = '<div class="agentic-proposal-status agentic-proposal-error">⚠️ ' + escapeHtml(data.error) + '</div>';
            }
        } catch (error) {
            console.error('Proposal action error:', error);
            buttons.forEach(btn => btn.disabled = false);
            addMessage('Error processing proposal: ' + error.message, 'agent');
        }
    }

    // Simple markdown renderer
    function renderMarkdown(text) {
        if (!text) return '';
        
        // Escape HTML first
        let html = escapeHtml(text);

        // Code blocks
        html = html.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>');
        
        // Inline code
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Headers
        html = html.replace(/^### (.*$)/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.*$)/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.*$)/gm, '<h1>$1</h1>');
        
        // Bold and italic
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        
        // Links
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        
        // Lists
        html = html.replace(/^\s*[-*]\s+(.*)$/gm, '<li>$1</li>');
        html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');
        
        // Numbered lists
        html = html.replace(/^\s*\d+\.\s+(.*)$/gm, '<li>$1</li>');
        
        // Blockquotes
        html = html.replace(/^>\s+(.*)$/gm, '<blockquote>$1</blockquote>');
        
        // Paragraphs
        html = html.replace(/\n\n/g, '</p><p>');
        html = '<p>' + html + '</p>';
        html = html.replace(/<p><\/p>/g, '');
        html = html.replace(/<p>(<h[1-6]>)/g, '$1');
        html = html.replace(/(<\/h[1-6]>)<\/p>/g, '$1');
        html = html.replace(/<p>(<ul>)/g, '$1');
        html = html.replace(/(<\/ul>)<\/p>/g, '$1');
        html = html.replace(/<p>(<pre>)/g, '$1');
        html = html.replace(/(<\/pre>)<\/p>/g, '$1');
        html = html.replace(/<p>(<blockquote>)/g, '$1');
        html = html.replace(/(<\/blockquote>)<\/p>/g, '$1');

        return html;
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Generate UUID
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
