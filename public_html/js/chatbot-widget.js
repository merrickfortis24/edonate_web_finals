(() => {
    'use strict';
    const root = document.getElementById('edonate-chatbot');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';
    const launch = root.querySelector('.ec-launch'), panel = root.querySelector('.ec-window');
    const log = root.querySelector('.ec-messages'), form = root.querySelector('.ec-form');
    const input = root.querySelector('#ec-input'), send = root.querySelector('.ec-send');
    const notice = root.querySelector('p.ec-notice'), newChat = root.querySelector('.ec-new');
    const erase = root.querySelector('.ec-delete');
    const storageKey = 'edonate.chat.session';
    let sessionId = null, busy = false, loaded = false, activeRequest;
    const permitted = () => root.dataset.enabled === 'true' && window.eDonatePrivacy?.allowed('ai') === true;
    const showNotice = (text = '') => { notice.textContent = text; notice.hidden = !text; };
    const scroll = () => { log.scrollTop = log.scrollHeight; };
    function setBusy(value) {
        busy = value;
        send.disabled = value || !loaded || !permitted();
        input.disabled = value || !loaded || !permitted();
        newChat.disabled = value || !permitted();
        erase.disabled = value;
        log.setAttribute('aria-busy', String(value));
    }
    function uuid() {
        if (crypto.randomUUID) return crypto.randomUUID();
        const b = crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
        const h = Array.from(b, n => n.toString(16).padStart(2, '0')).join('');
        return [h.slice(0,8), h.slice(8,12), h.slice(12,16), h.slice(16,20), h.slice(20)].join('-');
    }
    function ensureSession(create = true) {
        if (!sessionId) {
            try { sessionId = sessionStorage.getItem(storageKey); } catch (_) { /* Memory-only mode. */ }
            if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(sessionId || '')) sessionId = null;
        }
        if (!sessionId && create && permitted()) {
            sessionId = uuid();
            try { sessionStorage.setItem(storageKey, sessionId); } catch (_) { /* Memory-only mode. */ }
        }
        return sessionId;
    }
    function append(role, text) {
        const row = document.createElement('div');
        row.className = role === 'user' ? 'ec-message ec-message--user' : 'ec-message';
        const label = document.createElement('strong'), body = document.createElement('p');
        label.textContent = role === 'user' ? 'YOU' : 'EDONATE ASSISTANT';
        body.textContent = text;
        row.append(label, body); log.append(row); scroll();
        return row;
    }
    async function request(url, options = {}) {
        const controller = new AbortController();
        activeRequest = controller;
        const timer = setTimeout(() => controller.abort(), 45000);
        try {
            const response = await fetch(url, { ...options, credentials: 'same-origin', signal: controller.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf } });
            const data = await response.json().catch(() => ({}));
            if (response.status === 419) throw new Error('Your session expired. Refresh to continue.');
            if (!response.ok) throw new Error(data.message || 'Chat is unavailable. Please retry.');
            return data;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('The request was cancelled or timed out. It may already have reached the server.');
            throw error;
        } finally { clearTimeout(timer); if (activeRequest === controller) activeRequest = null; }
    }
    async function load() {
        if (!permitted() || busy || loaded) return;
        ensureSession(); setBusy(true);
        try {
            const url = new URL(root.dataset.history, location.origin);
            url.searchParams.set('session_id', sessionId);
            const data = await request(url);
            if (!Array.isArray(data.messages)) throw new Error('History could not be loaded.');
            if (!permitted()) return;
            log.replaceChildren();
            data.messages.forEach(m => { if (['user','model'].includes(m.role) && typeof m.message === 'string') append(m.role, m.message); });
            if (!data.messages.length) append('model', 'How can I help you navigate eDonate? Do not send personal or medical information.');
            loaded = true; showNotice();
        } catch (error) { showNotice(error.message); }
        finally { setBusy(false); }
    }
    function syncConsent() {
        if (!permitted()) {
            activeRequest?.abort();
            loaded = false;
            showNotice(root.dataset.enabled === 'true' ? 'Allow the AI assistant in Privacy choices before sending a message.' : 'The assistant is disabled pending operator review.');
        } else if (!panel.hidden) { load(); }
        setBusy(busy);
    }
    launch.addEventListener('click', async () => {
        panel.hidden = false; launch.hidden = true; launch.setAttribute('aria-expanded', 'true');
        root.querySelector('.ec-close').focus();
        syncConsent();
        await load();
        if (!panel.hidden && !input.disabled) input.focus();
    });
    function close() { panel.hidden = true; launch.hidden = false; launch.setAttribute('aria-expanded','false'); launch.focus(); }
    root.querySelector('.ec-close').addEventListener('click', close);
    panel.addEventListener('keydown', event => { if (event.key === 'Escape') { event.stopPropagation(); close(); } });
    async function deleteConversation() {
        if (busy) return false;
        if (!ensureSession(false)) { showNotice('No conversation is stored for this browser session.'); return true; }
        if (!confirm('Delete this conversation from eDonate? This cannot erase copies already processed by Google.')) return false;
        setBusy(true);
        try {
            const data = await request(root.dataset.delete, { method: 'DELETE', body: JSON.stringify({ session_id: sessionId }) });
            try { sessionStorage.removeItem(storageKey); } catch (_) { /* Storage can be unavailable. */ }
            sessionId = null; loaded = false; log.replaceChildren(); showNotice(data.message);
            return true;
        } catch (error) { showNotice(error.message); return false; }
        finally { setBusy(false); }
    }
    erase.addEventListener('click', deleteConversation);
    newChat.addEventListener('click', async () => { if (permitted() && await deleteConversation()) { await load(); input.focus(); } });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || busy || !loaded || !permitted()) return;
        const user = append('user', message), typing = append('model', 'AI is typing…');
        input.value = ''; showNotice(); setBusy(true);
        try {
            const data = await request(root.dataset.endpoint, { method: 'POST', body: JSON.stringify({ session_id: sessionId, message }) });
            if (typeof data.reply !== 'string' || !data.reply.trim()) throw new Error('The assistant returned an empty answer.');
            if (permitted()) append('model', data.reply);
        } catch (error) { user.remove(); input.value = message; showNotice(error.message); }
        finally { typing.remove(); setBusy(false); scroll(); if (!panel.hidden && !input.disabled) input.focus(); }
    });
    input.addEventListener('keydown', event => { if (event.key === 'Enter' && event.isComposing) event.preventDefault(); });
    document.addEventListener('edonate:privacy-changed', syncConsent);
    syncConsent();
})();
