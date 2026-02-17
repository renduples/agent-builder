/**
 * Agentic Admin-Bar Chat Overlay
 *
 * Self-contained chat panel that opens when a user clicks an agent
 * in the "AI Agents" admin-bar menu. Each agent gets its own
 * isolated conversation history and session.
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  State                                                              */
    /* ------------------------------------------------------------------ */
    let overlay         = null;   // The overlay wrapper element.
    let activeAgent     = null;   // Current agent slug.
    let sessionId       = null;
    let history         = [];
    let isProcessing    = false;
    let pendingImage    = null;   // { dataUrl, mimeType, name }

    /* ------------------------------------------------------------------ */
    /*  Boot                                                               */
    /* ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        // Intercept clicks on admin-bar agent links.
        document.addEventListener('click', function (e) {
            const link = e.target.closest('#wp-admin-bar-agentic-chat-bar .agentic-chat-trigger-bar a');
            if (!link) return;
            e.preventDefault();

            const href = link.getAttribute('href') || '';
            const match = href.match(/#agentic-chat-(.+)/);
            if (!match) return;

            const slug = match[1];
            openOverlay(slug, link.textContent.trim());
        });

        // Close on Escape.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay) closeOverlay();
        });
    });

    /* ------------------------------------------------------------------ */
    /*  Overlay lifecycle                                                   */
    /* ------------------------------------------------------------------ */
    function openOverlay(slug, displayName) {
        // If same agent already open, just show it.
        if (overlay && activeAgent === slug) {
            overlay.classList.add('agentic-overlay-visible');
            return;
        }

        // Tear down previous overlay.
        if (overlay) overlay.remove();

        activeAgent = slug;
        sessionId   = localStorage.getItem('agentic_overlay_session_' + slug) || uuid();
        localStorage.setItem('agentic_overlay_session_' + slug, sessionId);
        history     = [];
        pendingImage = null;

        // Use the agent's real name for the window title (from welcomeMessages keys or fallback).
        var agentNames = (typeof agenticChat !== 'undefined' && agenticChat.agentNames) || {};
        var title = agentNames[slug] || displayName;
        buildOverlay(title);
        loadHistory();

        // Show with slight delay for CSS transition.
        requestAnimationFrame(function () {
            overlay.classList.add('agentic-overlay-visible');
        });
    }

    function closeOverlay() {
        if (!overlay) return;
        overlay.classList.remove('agentic-overlay-visible');
        // Remove from DOM after transition.
        setTimeout(function () {
            if (overlay) overlay.remove();
            overlay = null;
            activeAgent = null;
        }, 250);
    }

    /* ------------------------------------------------------------------ */
    /*  Build DOM                                                          */
    /* ------------------------------------------------------------------ */
    function buildOverlay(displayName) {
        overlay = el('div', { className: 'agentic-overlay' });

        // Backdrop.
        const backdrop = el('div', { className: 'agentic-overlay-backdrop' });
        backdrop.addEventListener('click', closeOverlay);
        overlay.appendChild(backdrop);

        // Panel.
        const panel = el('div', { className: 'agentic-overlay-panel' });

        // --- Header ---
        const header = el('div', { className: 'agentic-overlay-header' });
        const title  = el('span', { className: 'agentic-overlay-title', textContent: displayName || activeAgent });

        const actions = el('div', { className: 'agentic-overlay-actions' });

        const newBtn = el('button', {
            className: 'agentic-overlay-btn',
            title: 'New conversation',
            innerHTML: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>'
        });
        newBtn.addEventListener('click', clearConversation);

        const closeBtn = el('button', {
            className: 'agentic-overlay-btn agentic-overlay-close',
            title: 'Close',
            innerHTML: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>'
        });
        closeBtn.addEventListener('click', closeOverlay);

        actions.appendChild(newBtn);
        actions.appendChild(closeBtn);
        header.appendChild(title);
        header.appendChild(actions);
        panel.appendChild(header);

        // --- Messages ---
        const msgs = el('div', { className: 'agentic-overlay-messages', id: 'agentic-overlay-msgs' });
        panel.appendChild(msgs);

        // --- Typing indicator ---
        const typing = el('div', { className: 'agentic-overlay-typing', id: 'agentic-overlay-typing', style: 'display:none' });
        typing.innerHTML = '<span></span><span></span><span></span>';
        panel.appendChild(typing);

        // --- Image preview ---
        const previewWrap = el('div', { className: 'agentic-overlay-image-preview', id: 'agentic-overlay-preview', style: 'display:none' });
        const previewImg  = el('img', { id: 'agentic-overlay-preview-img' });
        const removeImg   = el('button', { className: 'agentic-overlay-remove-img', textContent: '✕', title: 'Remove image' });
        removeImg.addEventListener('click', clearImage);
        previewWrap.appendChild(previewImg);
        previewWrap.appendChild(removeImg);
        panel.appendChild(previewWrap);

        // --- Input form ---
        const form = el('form', { className: 'agentic-overlay-form', id: 'agentic-overlay-form' });

        const attachBtn = el('button', {
            type: 'button',
            className: 'agentic-overlay-attach',
            title: 'Attach image',
            innerHTML: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66L9.41 17.41a2 2 0 01-2.83-2.83l8.49-8.49"/></svg>'
        });
        const fileInput = el('input', { type: 'file', accept: 'image/*', style: 'display:none', id: 'agentic-overlay-file' });
        attachBtn.addEventListener('click', function () { fileInput.click(); });
        fileInput.addEventListener('change', handleFileSelect);

        const textarea = el('textarea', {
            className: 'agentic-overlay-input',
            id: 'agentic-overlay-input',
            placeholder: 'Type your message…',
            rows: 1
        });
        textarea.addEventListener('input', autoResize);
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });

        const sendBtn = el('button', {
            type: 'submit',
            className: 'agentic-overlay-send',
            id: 'agentic-overlay-send',
            innerHTML: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>'
        });

        form.appendChild(fileInput);
        form.appendChild(attachBtn);
        form.appendChild(textarea);
        form.appendChild(sendBtn);
        form.addEventListener('submit', handleSubmit);

        panel.appendChild(form);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);
    }

    /* ------------------------------------------------------------------ */
    /*  Messages                                                           */
    /* ------------------------------------------------------------------ */
    function addMessage(content, role, meta, imageData) {
        const msgs = document.getElementById('agentic-overlay-msgs');
        if (!msgs) return;

        const div = el('div', { className: 'agentic-overlay-msg agentic-overlay-msg-' + role });

        // Attached image.
        if (imageData && imageData.dataUrl) {
            const img = el('img', { src: imageData.dataUrl, className: 'agentic-overlay-chat-img', alt: imageData.name || 'image' });
            div.appendChild(img);
        }

        const body = el('div', { className: 'agentic-overlay-msg-body' });
        if (content) {
            body.innerHTML = role === 'agent' ? renderMarkdown(content) : esc(content);
        }
        div.appendChild(body);

        // Meta bar.
        if (role === 'agent' && meta) {
            const metaDiv = el('div', { className: 'agentic-overlay-msg-meta' });
            if (meta.cached) metaDiv.innerHTML += '<span title="Cached">⚡</span> ';
            if (meta.tokens) metaDiv.innerHTML += '<span>Tokens: ' + meta.tokens + '</span> ';
            if (meta.cost)   metaDiv.innerHTML += '<span>$' + meta.cost.toFixed(6) + '</span>';
            div.appendChild(metaDiv);
        }

        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    /* ------------------------------------------------------------------ */
    /*  Send / receive                                                     */
    /* ------------------------------------------------------------------ */
    async function handleSubmit(e) {
        e.preventDefault();
        const input = document.getElementById('agentic-overlay-input');
        const message = (input ? input.value.trim() : '');
        if ((!message && !pendingImage) || isProcessing) return;

        const imageData = pendingImage ? Object.assign({}, pendingImage) : null;
        addMessage(message, 'user', null, imageData);

        if (input) { input.value = ''; input.style.height = 'auto'; }
        clearImage();

        const text = message || 'Describe this image.';
        history.push({ role: 'user', content: text });
        saveHistory();

        await sendMessage(text, imageData);
    }

    async function sendMessage(text, imageData) {
        isProcessing = true;
        toggleSend(true);
        showTyping(true);

        try {
            var payload = {
                message:    text,
                session_id: sessionId,
                agent_id:   activeAgent,
                history:    history.slice(-20)
            };
            if (imageData && imageData.dataUrl) {
                payload.image = imageData.dataUrl;
            }

            var res = await fetch(agenticChat.restUrl + 'chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': agenticChat.nonce
                },
                body: JSON.stringify(payload)
            });

            var data = await res.json();

            if (data.error) {
                addMessage('Error: ' + data.response, 'agent');
            } else {
                history.push({ role: 'assistant', content: data.response });
                saveHistory();
                addMessage(data.response, 'agent', {
                    tokens: data.tokens_used,
                    cost:   data.cost,
                    tools:  data.tools_used,
                    cached: data.cached || false
                });
            }
        } catch (err) {
            addMessage('Connection error — please try again.', 'agent');
        } finally {
            isProcessing = false;
            toggleSend(false);
            showTyping(false);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Image handling                                                     */
    /* ------------------------------------------------------------------ */
    function handleFileSelect() {
        var file = this.files[0]; // eslint-disable-line no-invalid-this
        if (!file) return;
        if (!file.type.startsWith('image/')) { alert('Only images supported.'); this.value = ''; return; }
        if (file.size > 5 * 1024 * 1024) { alert('Max 5 MB.'); this.value = ''; return; }

        var reader = new FileReader();
        reader.onload = function (ev) {
            pendingImage = { dataUrl: ev.target.result, mimeType: file.type, name: file.name };
            var wrap = document.getElementById('agentic-overlay-preview');
            var img  = document.getElementById('agentic-overlay-preview-img');
            if (wrap && img) { img.src = ev.target.result; wrap.style.display = 'flex'; }
        };
        reader.readAsDataURL(file);
    }

    function clearImage() {
        pendingImage = null;
        var wrap  = document.getElementById('agentic-overlay-preview');
        var input = document.getElementById('agentic-overlay-file');
        if (wrap) wrap.style.display = 'none';
        if (input) input.value = '';
    }

    /* ------------------------------------------------------------------ */
    /*  Conversation persistence                                           */
    /* ------------------------------------------------------------------ */
    function saveHistory() {
        if (!activeAgent) return;
        localStorage.setItem('agentic_overlay_history_' + activeAgent, JSON.stringify(history));
    }

    function loadHistory() {
        if (!activeAgent) return;
        var raw = localStorage.getItem('agentic_overlay_history_' + activeAgent);
        if (raw) {
            try {
                history = JSON.parse(raw);
                history.forEach(function (m) {
                    addMessage(m.content, m.role === 'user' ? 'user' : 'agent');
                });
            } catch (e) {
                history = [];
            }
        }

        // Show welcome message for new conversations.
        if (!history.length) {
            showWelcomeMessage();
        }
    }

    function showWelcomeMessage() {
        if (!activeAgent) return;
        var messages = (typeof agenticChat !== 'undefined' && agenticChat.welcomeMessages) || {};
        var msg = messages[activeAgent];
        if (!msg) return;
        addMessage(msg, 'agent');

        // Add quick-action buttons for WordPress Assistant.
        if (activeAgent === 'onboarding-agent') {
            var msgs = document.getElementById('agentic-overlay-msgs');
            if (!msgs) return;
            var btnsWrap = el('div', { className: 'agentic-overlay-quick-actions' });
            var actions = [
                { label: 'Agent Builder', slug: 'agent-builder' },
                { label: 'Content Assistant', slug: 'content-builder' },
                { label: 'Plugin Assistant', slug: 'plugin-builder' },
                { label: 'Theme Assistant', slug: 'theme-builder' }
            ];
            actions.forEach(function (action) {
                var btn = el('button', { className: 'agentic-overlay-quick-btn', textContent: action.label });
                btn.addEventListener('click', function () {
                    // Close onboarding overlay and open the selected agent.
                    closeOverlay();
                    var names = (typeof agenticChat !== 'undefined' && agenticChat.agentNames) || {};
                    var displayName = names[action.slug] || action.label;
                    setTimeout(function () {
                        openOverlay(action.slug, displayName);
                    }, 300);
                });
                btnsWrap.appendChild(btn);
            });
            msgs.appendChild(btnsWrap);
            msgs.scrollTop = msgs.scrollHeight;
        }
    }

    function clearConversation() {
        if (!activeAgent) return;
        history = [];
        localStorage.removeItem('agentic_overlay_history_' + activeAgent);
        sessionId = uuid();
        localStorage.setItem('agentic_overlay_session_' + activeAgent, sessionId);
        var msgs = document.getElementById('agentic-overlay-msgs');
        if (msgs) msgs.innerHTML = '';
        showWelcomeMessage();
    }

    /* ------------------------------------------------------------------ */
    /*  UI helpers                                                         */
    /* ------------------------------------------------------------------ */
    function showTyping(on) {
        var t = document.getElementById('agentic-overlay-typing');
        if (t) t.style.display = on ? 'flex' : 'none';
    }

    function toggleSend(disabled) {
        var btn = document.getElementById('agentic-overlay-send');
        if (btn) btn.disabled = disabled;
    }

    function autoResize() {
        this.style.height = 'auto'; // eslint-disable-line no-invalid-this
        this.style.height = Math.min(this.scrollHeight, 120) + 'px'; // eslint-disable-line no-invalid-this
    }

    /* ------------------------------------------------------------------ */
    /*  Markdown (lightweight)                                             */
    /* ------------------------------------------------------------------ */
    function renderMarkdown(text) {
        if (!text) return '';
        var h = esc(text);
        h = h.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>');
        h = h.replace(/`([^`]+)`/g, '<code>$1</code>');
        h = h.replace(/^### (.*$)/gm, '<h3>$1</h3>');
        h = h.replace(/^## (.*$)/gm,  '<h2>$1</h2>');
        h = h.replace(/^# (.*$)/gm,   '<h1>$1</h1>');
        h = h.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        h = h.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        h = h.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        h = h.replace(/^\s*[-*]\s+(.*)$/gm, '<li>$1</li>');
        h = h.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');
        h = h.replace(/\n\n/g, '</p><p>');
        h = '<p>' + h + '</p>';
        h = h.replace(/<p><\/p>/g, '');
        h = h.replace(/<p>(<(?:h[1-6]|ul|pre|blockquote)>)/g, '$1');
        h = h.replace(/(<\/(?:h[1-6]|ul|pre|blockquote)>)<\/p>/g, '$1');
        return h;
    }

    /* ------------------------------------------------------------------ */
    /*  Utilities                                                          */
    /* ------------------------------------------------------------------ */
    function el(tag, props) {
        var node = document.createElement(tag);
        if (props) Object.keys(props).forEach(function (k) {
            if (k === 'className')       node.className   = props[k];
            else if (k === 'innerHTML')  node.innerHTML   = props[k];
            else if (k === 'textContent') node.textContent = props[k];
            else if (k === 'style' && typeof props[k] === 'string') node.style.cssText = props[k];
            else node.setAttribute(k, props[k]);
        });
        return node;
    }

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function uuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }
})();
