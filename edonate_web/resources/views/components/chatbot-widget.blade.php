@once
<div id="edonate-chatbot"
     data-endpoint="{{ route('chat.store', [], false) }}"
     data-history="{{ route('chat.history', [], false) }}"
     data-csrf="{{ csrf_token() }}">
    <button type="button" class="ec-launch" aria-label="Open eDonate assistant"
            aria-controls="ec-window" aria-expanded="false">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/></svg>
    </button>

    <section id="ec-window" class="ec-window" role="dialog" aria-modal="false"
             aria-labelledby="ec-title" hidden>
        <header class="ec-header">
            <div>
                <h2 id="ec-title">eDonate Assistant</h2>
                <p>Ask about eDonate</p>
            </div>
            <button type="button" class="ec-icon ec-new" aria-label="Start a new chat" title="New chat">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <button type="button" class="ec-icon ec-close" aria-label="Close chat">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg>
            </button>
        </header>

        <div class="ec-messages" role="log" aria-live="polite"
             aria-relevant="additions text" aria-label="Chat messages"></div>
        <p class="ec-notice" role="status" hidden></p>

        <form class="ec-form">
            <label for="ec-input" class="ec-sr-only">Your message</label>
            <input id="ec-input" name="message" type="text"
                   maxlength="{{ config('chatbot.max_message_length', 4000) }}"
                   placeholder="Type a message…" autocomplete="off" required>
            <button type="submit" class="ec-icon ec-send" aria-label="Send message">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/></svg>
            </button>
        </form>
        <p class="ec-footnote">Messages are saved and processed by Google Gemini.</p>
    </section>
</div>

<style>
    #edonate-chatbot {
        position: fixed;
        right: max(16px, env(safe-area-inset-right));
        bottom: max(16px, env(safe-area-inset-bottom));
        z-index: 1040;
        color: #f3f4f6;
        font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
        text-align: left;
    }
    #edonate-chatbot *, #edonate-chatbot *::before, #edonate-chatbot *::after { box-sizing: border-box; }
    #edonate-chatbot [hidden] { display: none !important; }
    #edonate-chatbot button, #edonate-chatbot input { font: inherit; margin: 0; }
    #edonate-chatbot button { display: inline-flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; border: 0; }
    #edonate-chatbot button:disabled { opacity: .45; cursor: wait; }
    #edonate-chatbot button:focus-visible, #edonate-chatbot input:focus-visible { outline: 2px solid #fca5a5; outline-offset: 3px; }
    #edonate-chatbot svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    #edonate-chatbot .ec-launch { width: 58px; height: 58px; border-radius: 50%; background: #b91c1c; box-shadow: 0 8px 28px #0005; }
    #edonate-chatbot .ec-launch:hover { background: #991b1b; }
    #edonate-chatbot .ec-launch svg { width: 28px; height: 28px; }
    #edonate-chatbot .ec-window {
        display: flex;
        flex-direction: column;
        width: min(380px, calc(100vw - 32px));
        height: min(560px, calc(100vh - 32px));
        height: min(560px, calc(100dvh - 32px));
        overflow: hidden;
        border: 1px solid #555665;
        border-radius: 18px;
        background: #343541;
        box-shadow: 0 16px 48px #0006;
    }
    #edonate-chatbot .ec-header { display: flex; align-items: center; gap: 8px; padding: 16px; background: #282934; border-bottom: 1px solid #484957; flex-shrink: 0; }
    #edonate-chatbot .ec-header > div { flex: 1; min-width: 0; }
    #edonate-chatbot .ec-header h2 { margin: 0; font: 700 16px/1.4 system-ui, sans-serif; color: #fff; }
    #edonate-chatbot .ec-header p { margin: 3px 0 0; color: #c2c3cc; font-size: 12px; }
    #edonate-chatbot .ec-icon { width: 36px; height: 36px; padding: 8px; flex-shrink: 0; border-radius: 8px; background: transparent; }
    #edonate-chatbot .ec-icon:hover { background: #494a59; }
    #edonate-chatbot .ec-messages { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; scrollbar-color: #737482 #343541; }
    #edonate-chatbot .ec-message { padding: 16px; border-bottom: 1px solid #ffffff0a; background: #343541; }
    #edonate-chatbot .ec-message--user { background: #2e2f3a; }
    #edonate-chatbot .ec-message strong { display: block; color: #fca5a5; font-size: 11px; letter-spacing: .05em; margin-bottom: 5px; }
    #edonate-chatbot .ec-message p { margin: 0; color: #f3f4f6; white-space: pre-wrap; overflow-wrap: anywhere; }
    #edonate-chatbot .ec-notice { margin: 0; padding: 10px 16px; color: #fecaca; background: #472f37; font-size: 12px; overflow-wrap: anywhere; }
    #edonate-chatbot .ec-form { display: flex; align-items: center; gap: 8px; padding: 12px; border-top: 1px solid #555665; background: #282934; flex-shrink: 0; }
    #edonate-chatbot .ec-form input { flex: 1; min-width: 0; width: 100%; padding: 10px 12px; color: #fff; background: #40414f; border: 1px solid #696a79; border-radius: 9px; font-size: 16px; }
    #edonate-chatbot .ec-form input::placeholder { color: #c2c3cc; opacity: 1; }
    #edonate-chatbot .ec-send { background: #b91c1c; width: 42px; height: 42px; }
    #edonate-chatbot .ec-send:hover { background: #991b1b; }
    #edonate-chatbot .ec-footnote { margin: 0; padding: 0 12px 10px; color: #c2c3cc; background: #282934; font-size: 10px; text-align: center; flex-shrink: 0; }
    #edonate-chatbot .ec-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
</style>

<script>
(() => {
    'use strict';
    const root = document.getElementById('edonate-chatbot');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';

    const launch = root.querySelector('.ec-launch');
    const panel = root.querySelector('.ec-window');
    const close = root.querySelector('.ec-close');
    const newChat = root.querySelector('.ec-new');
    const log = root.querySelector('.ec-messages');
    const form = root.querySelector('.ec-form');
    const input = root.querySelector('#ec-input');
    const send = root.querySelector('.ec-send');
    const notice = root.querySelector('.ec-notice');
    const storageKey = 'edonate.chat.session';
    let busy = false;
    let loaded = false;

    function uuid() {
        if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
        return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
    }

    function saveSession(id) {
        try { sessionStorage.setItem(storageKey, id); } catch (error) { /* Memory-only mode when storage is blocked. */ }
        return id;
    }

    let sessionId;
    try { sessionId = sessionStorage.getItem(storageKey); } catch (error) { sessionId = null; }
    if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(sessionId || '')) {
        sessionId = saveSession(uuid());
    }

    function scrollToBottom() { log.scrollTop = log.scrollHeight; }
    function showNotice(text = '') { notice.textContent = text; notice.hidden = !text; }
    function setBusy(value) {
        busy = value;
        send.disabled = value || !loaded;
        input.readOnly = value || !loaded;
        newChat.disabled = value;
        log.setAttribute('aria-busy', String(value));
    }

    function appendMessage(role, text) {
        const row = document.createElement('div');
        row.className = role === 'user' ? 'ec-message ec-message--user' : 'ec-message';
        const label = document.createElement('strong');
        label.textContent = role === 'user' ? 'YOU' : 'EDONATE ASSISTANT';
        const body = document.createElement('p');
        body.textContent = text; // Never render visitor or AI content as HTML.
        row.append(label, body);
        log.append(row);
        scrollToBottom();
        return row;
    }

    async function request(url, options = {}) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 45000);
        try {
            const response = await fetch(url, {
                ...options,
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrf
                }
            });
            const data = await response.json().catch(() => ({}));
            if (response.status === 419) throw new Error('Your session expired. Refresh this page to continue.');
            if (!response.ok) throw new Error(data.message || 'Chat is unavailable. Please try again.');
            return data;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('The request timed out. Please try again.');
            throw error;
        } finally {
            clearTimeout(timer);
        }
    }

    async function loadHistory() {
        setBusy(true);
        showNotice('Loading conversation…');
        try {
            const url = new URL(root.dataset.history, window.location.origin);
            url.searchParams.set('session_id', sessionId);
            const data = await request(url);
            if (!Array.isArray(data.messages)) throw new Error('Chat history could not be loaded.');
            log.replaceChildren();
            data.messages.forEach(item => {
                if (['user', 'model'].includes(item.role) && typeof item.message === 'string') {
                    appendMessage(item.role, item.message);
                }
            });
            if (!data.messages.length) appendMessage('model', 'Hi! How can I help you with eDonate?');
            loaded = true;
            showNotice();
        } catch (error) {
            showNotice(error.message + ' Reopen chat to retry, or start a new chat.');
        } finally {
            setBusy(false);
        }
    }

    launch.addEventListener('click', async () => {
        panel.hidden = false;
        launch.hidden = true;
        launch.setAttribute('aria-expanded', 'true');
        if (!loaded && !busy) await loadHistory();
        if (!panel.hidden) input.focus();
        scrollToBottom();
    });

    function closeChat() {
        panel.hidden = true;
        launch.hidden = false;
        launch.setAttribute('aria-expanded', 'false');
        launch.focus();
    }
    close.addEventListener('click', closeChat);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) closeChat();
    });

    newChat.addEventListener('click', () => {
        if (busy) return;
        sessionId = saveSession(uuid());
        loaded = true;
        log.replaceChildren();
        appendMessage('model', 'Hi! How can I help you with eDonate?');
        showNotice();
        input.value = '';
        setBusy(false);
        input.focus();
    });

    // Form submission handles both the Send button and Enter.
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.isComposing) event.preventDefault();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || busy || !loaded) return;

        const userRow = appendMessage('user', message);
        input.value = '';
        showNotice();
        const typing = appendMessage('model', 'AI is typing…');
        setBusy(true);

        try {
            const data = await request(root.dataset.endpoint, {
                method: 'POST',
                body: JSON.stringify({ session_id: sessionId, message })
            });
            if (typeof data.reply !== 'string' || !data.reply.trim()) {
                throw new Error('The assistant returned an empty response.');
            }
            typing.remove();
            appendMessage('model', data.reply);
        } catch (error) {
            typing.remove();
            userRow.remove();
            input.value = message;
            showNotice(error.message);
        } finally {
            setBusy(false);
            scrollToBottom();
            if (!panel.hidden) input.focus();
        }
    });
})();
</script>
@endonce
