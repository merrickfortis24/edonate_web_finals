(() => {
    'use strict';
    if (window.eDonatePrivacy) return;
    const panel = document.getElementById('ed-privacy-panel');
    const stateElement = document.getElementById('ed-privacy-state');
    let choices = { decided: false, necessary: true, analytics: false, maps: false, ai: false, expires_at: 0 };
    try { if (stateElement) choices = { ...choices, ...JSON.parse(stateElement.textContent) }; } catch (_) { /* Denied by default. */ }
    let opener = null;
    let saving = false;
    let channel;
    const form = panel?.querySelector('form');
    const status = document.getElementById('ed-privacy-status');
    const categories = ['analytics', 'maps', 'ai'];
    const enforceBanner = panel?.dataset.enforceBanner !== 'false';

    function allowed(category) {
        return categories.includes(category) && choices[category] === true && choices.expires_at * 1000 > Date.now();
    }
    function announce() {
        document.dispatchEvent(new CustomEvent('edonate:privacy-changed', { detail: { ...choices } }));
    }
    function open(trigger) {
        if (!panel) return;
        opener = trigger || document.activeElement;
        categories.forEach(name => { const input = form.elements.namedItem(name); if (input) input.checked = allowed(name); });
        panel.hidden = false;
        document.querySelectorAll('[data-privacy-open]').forEach(el => el.setAttribute('aria-expanded', 'true'));
        panel.querySelector('h2').focus();
    }
    function close() {
        if (!panel) return;
        panel.hidden = true;
        document.querySelectorAll('[data-privacy-open]').forEach(el => el.setAttribute('aria-expanded', 'false'));
        if (opener?.isConnected) opener.focus();
    }
    async function save(selected) {
        if (saving || !panel) return;
        saving = true;
        form.querySelectorAll('button').forEach(el => { el.disabled = true; });
        status.textContent = 'Saving your choices…';
        try {
            const response = await fetch(panel.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin', signal: AbortSignal.timeout(15000),
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ ...selected, version: panel.dataset.version })
            });
            if (!response.ok) throw new Error('Your changes could not be saved. Previously saved choices still apply. Please retry.');
            const data = await response.json();
            const revoked = categories.some(name => allowed(name) && data.choices[name] !== true);
            choices = data.choices;
            status.textContent = 'Your choices have been saved.';
            announce();
            channel?.postMessage('changed');
            close();
            if (revoked) {
                // Keep the existing conversation identifier until deletion or tab closure.
                // It enables erasure without re-consenting; it never authorizes an AI send.
                location.reload(); // Removing script tags alone cannot stop already running trackers.
            }
        } catch (error) {
            status.textContent = error.message;
        } finally {
            saving = false;
            form.querySelectorAll('button').forEach(el => { el.disabled = false; });
        }
    }
    // Only explicitly marked, inert resources can be activated by consent.
    // A live src cannot be made private after the browser has already fetched it.
    function loadOptionalResources() {
        document.querySelectorAll('script[type="text/plain"][data-consent-category][data-consent-src]').forEach(source => {
            if (!allowed(source.dataset.consentCategory) || source.dataset.loaded) return;
            const url = new URL(source.dataset.consentSrc, location.href);
            if (url.protocol !== 'https:' || url.username || url.password) return;
            const script = document.createElement('script');
            script.src = url.href;
            script.referrerPolicy = 'no-referrer';
            if (source.dataset.integrity) { script.integrity = source.dataset.integrity; script.crossOrigin = 'anonymous'; }
            source.dataset.loaded = 'true';
            script.addEventListener('error', () => { source.dataset.loaded = ''; });
            document.head.append(script);
        });
        document.querySelectorAll('iframe[data-consent-category][data-consent-src]').forEach(frame => {
            if (!allowed(frame.dataset.consentCategory) || frame.hasAttribute('src')) return;
            const url = new URL(frame.dataset.consentSrc, location.href);
            if (url.protocol !== 'https:' || !frame.title || url.username || url.password) return;
            // Embed capabilities must be reviewed explicitly; deny everything by default.
            if (!frame.hasAttribute('sandbox')) frame.setAttribute('sandbox', '');
            frame.referrerPolicy = 'no-referrer';
            frame.loading = 'lazy';
            frame.src = url.href;
        });
    }
    window.eDonatePrivacy = Object.freeze({ allowed, open });
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-privacy-open]');
        if (trigger) { event.preventDefault(); open(trigger); }
    });
    panel?.querySelector('[data-privacy-close]').addEventListener('click', close);
    panel?.addEventListener('keydown', event => { if (event.key === 'Escape' && !saving) { event.stopPropagation(); close(); } });
    panel?.querySelector('[data-privacy-reject]').addEventListener('click', () => save({ analytics: false, maps: false, ai: false }));
    panel?.querySelector('[data-privacy-accept]').addEventListener('click', () => save(Object.fromEntries(categories.map(name => [name, !!form.elements.namedItem(name)]))));
    form?.addEventListener('submit', event => {
        event.preventDefault();
        save(Object.fromEntries(categories.map(name => [name, form.elements.namedItem(name)?.checked === true])));
    });
    document.addEventListener('edonate:privacy-changed', loadOptionalResources);
    try { channel = new BroadcastChannel('edonate-privacy'); channel.onmessage = () => location.reload(); } catch (_) { /* Server still enforces its authenticated cookie. */ }
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && choices.decided && Date.now() >= choices.expires_at * 1000) location.reload();
    });
    if (choices.decided) {
        setTimeout(() => location.reload(), Math.min(Math.max(0, choices.expires_at * 1000 - Date.now()), 2147483647));
    }
    announce();
    if (!choices.decided && panel && enforceBanner) {
        // Show without moving focus away from the page's initial reading position.
        panel.hidden = false;
        document.querySelectorAll('[data-privacy-open]').forEach(el => el.setAttribute('aria-expanded', 'true'));
    }

    // Guard custom novalidate/AJAX handlers before they send a data form.
    document.addEventListener('submit', event => {
        const target = event.target;
        // Signup validates every step itself and reveals the first invalid panel.
        if (target.id === 'donorSignupForm') return;
        const invalid = [...target.querySelectorAll('.ed-data-consent input[required]')].find(input => !input.checkValidity());
        if (!invalid) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const error = target.querySelector('.ed-consent-error');
        if (error) error.textContent = 'Review the notices and select both checkboxes before submitting.';
        invalid.setAttribute('aria-invalid', 'true');
        invalid.focus();
        invalid.reportValidity();
    }, true);
    document.addEventListener('change', event => {
        if (event.target.matches('.ed-data-consent input[required]')) event.target.setAttribute('aria-invalid', String(!event.target.checkValidity()));
    });
    document.querySelectorAll('.ed-skip-link').forEach(link => link.addEventListener('click', () => document.querySelector(link.hash)?.focus()));
    document.addEventListener('keydown', event => {
        // Anchors used as disclosure buttons do not implement Space natively.
        if (event.key === ' ' && event.target.matches('.sidebar-menu a[role="button"]')) {
            event.preventDefault();
            event.target.click();
        }
    });
    const modalOpeners = new WeakMap();
    document.addEventListener('show.bs.modal', event => {
        if (event.target.matches('.modal')) modalOpeners.set(event.target, document.activeElement);
    });
    document.addEventListener('hidden.bs.modal', event => {
        const trigger = modalOpeners.get(event.target);
        if (trigger?.isConnected && !trigger.closest('.modal')) trigger.focus();
        modalOpeners.delete(event.target);
    });
})();
