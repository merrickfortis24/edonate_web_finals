document.addEventListener('DOMContentLoaded', function () {
    const payload = (window.AdminPageData && window.AdminPageData.notificationPayload) || {};
    const api = payload.api || {};

    const state = {
        filter: 'all',
        page: 1,
        perPage: 50,
        isLoading: false,
        summary: payload.summary || {},
    };

    const selectors = {
        list: '#notificationList',
        filter: '#notificationFilterSelect',
        sendBtn: '#notificationSendBtn',
        markAllBtn: '#notificationMarkAllReadBtn',
        clearAllBtn: '#notificationClearAllBtn',
        paginationInfo: '#notificationPaginationInfo',
        paginationLinks: '#notificationPaginationLinks',
        statTotal: '#notificationStatTotal',
        statUnread: '#notificationStatUnread',
        statRead: '#notificationStatRead',
        detailModal: '#notificationDetailModal',
        detailTitle: '#notificationDetailTitle',
        detailBody: '#notificationDetailBody',
        relatedLink: '#notificationRelatedLink',
        sendModal: '#notificationSendModal',
        sendForm: '#notificationSendForm',
        submitBtn: '#notificationSubmitBtn',
    };

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function endpoint(base, suffix) {
        return String(base || '').replace(/\/$/, '') + suffix;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null || value === '' ? '-' : String(value);
        return div.innerHTML;
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('en-US');
    }

    function setText(selector, value) {
        const element = document.querySelector(selector);
        if (element) element.textContent = formatNumber(value);
    }

    function showToast(message, type = 'info') {
        if (typeof Swal === 'undefined') {
            const log = type === 'error' ? console.error : console.log;
            log(message);
            return;
        }

        Swal.fire({
            toast: true,
            position: 'bottom-end',
            icon: type,
            title: message,
            timer: 2800,
            timerProgressBar: true,
            showConfirmButton: false,
        });
    }

    async function confirmAction(title, text, confirmButtonText) {
        if (typeof Swal === 'undefined') {
            return window.confirm(text || title);
        }

        const result = await Swal.fire({
            title,
            text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#b60c0c',
            confirmButtonText,
        });

        return result.isConfirmed;
    }

    async function requestJson(url, options = {}) {
        const headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }, options.headers || {});

        if (options.body && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }

        if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(String(options.method || 'GET').toUpperCase())) {
            headers['X-CSRF-TOKEN'] = csrfToken();
        }

        const response = await fetch(url, Object.assign({}, options, { headers }));
        const text = await response.text();
        const data = text ? JSON.parse(text) : {};

        if (!response.ok) {
            const error = new Error(data.message || `Request failed with status ${response.status}`);
            error.response = data;
            throw error;
        }

        return data;
    }

    function setListState(message, className = 'text-muted') {
        const list = document.querySelector(selectors.list);
        if (!list) return;
        list.innerHTML = `<div class="notification-state text-center ${className} py-4">${escapeHtml(message)}</div>`;
    }

    function setLoading(loading) {
        state.isLoading = loading;
        [selectors.filter, selectors.sendBtn, selectors.markAllBtn, selectors.clearAllBtn].forEach((selector) => {
            const element = document.querySelector(selector);
            if (element) element.disabled = loading;
        });
    }

    function updateSummary(summary) {
        const safeSummary = summary || {};
        state.summary = safeSummary;
        setText(selectors.statTotal, safeSummary.total || 0);
        setText(selectors.statUnread, safeSummary.unread || 0);
        setText(selectors.statRead, safeSummary.read || 0);

        const markAllBtn = document.querySelector(selectors.markAllBtn);
        const clearAllBtn = document.querySelector(selectors.clearAllBtn);
        if (markAllBtn) markAllBtn.disabled = Number(safeSummary.unread || 0) <= 0 || state.isLoading;
        if (clearAllBtn) clearAllBtn.disabled = Number(safeSummary.total || 0) <= 0 || state.isLoading;
    }

    function typeMeta(type) {
        const value = String(type || '').toLowerCase();
        if (value.includes('blood_stock') || value.includes('cancel') || value.includes('reject')) {
            return { color: 'yellow', icon: alertIcon('#b8960c') };
        }
        if (value.includes('donor') || value.includes('donation') || value.includes('completed') || value.includes('approved')) {
            return { color: 'green', icon: checkIcon('#129800') };
        }
        return { color: 'blue', icon: infoIcon('#0063aa') };
    }

    function checkIcon(color) {
        return `<svg width="25" height="25" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="${color}" stroke-width="1.8"/>
            <path d="M8.5 12L11 14.5L15.5 9.5" stroke="${color}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>`;
    }

    function alertIcon(color) {
        return `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="${color}" stroke-width="1.8"/>
            <path d="M12 8V12" stroke="${color}" stroke-width="2" stroke-linecap="round"/>
            <circle cx="12" cy="16" r="0.8" fill="${color}" stroke="${color}" stroke-width="0.5"/>
        </svg>`;
    }

    function infoIcon(color) {
        return `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="${color}" stroke-width="1.8"/>
            <path d="M12 16V12" stroke="${color}" stroke-width="2" stroke-linecap="round"/>
            <circle cx="12" cy="8.5" r="0.8" fill="${color}" stroke="${color}" stroke-width="0.5"/>
        </svg>`;
    }

    function deleteIcon() {
        return `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <polyline points="3,6 5,6 21,6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M19 6L18.1 20.1C18 21.2 17.1 22 16 22H8C6.9 22 6 21.2 5.9 20.1L5 6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9 6V4C9 3.4 9.4 3 10 3H14C14.6 3 15 3.4 15 4V6" stroke="#b60c0c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>`;
    }

    function renderNotifications(rows) {
        const list = document.querySelector(selectors.list);
        if (!list) return;

        if (!Array.isArray(rows) || rows.length === 0) {
            setListState('No notifications found.');
            return;
        }

        list.innerHTML = rows.map((notification) => {
            const meta = typeMeta(notification.type);
            const isRead = Boolean(notification.is_read);
            const markReadButton = isRead ? '' : `
                <button class="btn-mark-read" type="button" data-action="mark-read" data-id="${escapeHtml(notification.id)}" aria-label="Mark ${escapeHtml(notification.title)} as read">
                    ${checkIcon('#0063aa')}
                    Mark as Read
                </button>`;

            return `
                <article class="notif-item" data-id="${escapeHtml(notification.id)}">
                    <div class="notif-item__icon notif-item__icon--${meta.color}" aria-hidden="true">
                        ${meta.icon}
                    </div>
                    <div class="notif-item__body">
                        <div class="notif-item__title-row">
                            <span class="notif-item__title">${escapeHtml(notification.title)}</span>
                            ${isRead ? '' : '<span class="badge-new" role="status" aria-label="New notification">New</span>'}
                        </div>
                        <p class="notif-item__desc">${escapeHtml(notification.message)}</p>
                        <div class="notif-item__actions">
                            ${markReadButton}
                            <button class="btn-view-details" type="button" data-action="view" data-id="${escapeHtml(notification.id)}" aria-label="View details for ${escapeHtml(notification.title)}">View Details</button>
                            <button class="btn-delete" type="button" data-action="delete" data-id="${escapeHtml(notification.id)}" aria-label="Delete ${escapeHtml(notification.title)} notification">
                                ${deleteIcon()}
                            </button>
                        </div>
                    </div>
                    <time class="notif-item__time" datetime="${escapeHtml(notification.created_at || '')}">${escapeHtml(notification.created_at_human || '')}</time>
                </article>`;
        }).join('');
    }

    function renderPagination(meta) {
        const links = document.querySelector(selectors.paginationLinks);
        const info = document.querySelector(selectors.paginationInfo);
        if (!window.eDonateAdminPagination || !links) return;

        window.eDonateAdminPagination.render(links, meta || {
            current_page: state.page,
            last_page: 1,
            per_page: state.perPage,
            total: 0,
            from: 0,
            to: 0,
        }, function (nextPage) {
            state.page = nextPage;
            loadNotifications();
        }, { infoElement: info });
    }

    async function loadNotifications() {
        if (state.isLoading || !api.listUrl) return;

        setLoading(true);
        setListState('Loading notifications...');

        try {
            const params = new URLSearchParams({
                filter: state.filter,
                page: state.page,
                per_page: state.perPage,
            });
            const response = await requestJson(`${api.listUrl}?${params}`);

            renderNotifications(response.data || []);
            renderPagination(response.meta || {});
            updateSummary(response.summary || {});
        } catch (error) {
            console.error('Failed to load notifications:', error);
            setListState('Unable to load notifications. Please try again.', 'text-danger');
            renderPagination({ current_page: state.page, last_page: 1, per_page: state.perPage, total: 0, from: 0, to: 0 });
            showToast(error.message || 'Unable to load notifications.', 'error');
        } finally {
            setLoading(false);
            updateSummary(state.summary);
        }
    }

    function detailRow(label, value) {
        return `
            <div class="notification-detail-row">
                <span class="notification-detail-row__label">${escapeHtml(label)}</span>
                <span class="notification-detail-row__value">${escapeHtml(value)}</span>
            </div>`;
    }

    async function viewDetails(id) {
        const modalElement = document.querySelector(selectors.detailModal);
        const title = document.querySelector(selectors.detailTitle);
        const body = document.querySelector(selectors.detailBody);
        const relatedLink = document.querySelector(selectors.relatedLink);
        if (!modalElement || !body) return;

        if (title) title.textContent = 'Notification Details';
        body.innerHTML = '<div class="text-center text-muted py-4">Loading details...</div>';
        if (relatedLink) relatedLink.classList.add('d-none');

        bootstrap.Modal.getOrCreateInstance(modalElement).show();

        try {
            const response = await requestJson(endpoint(api.detailBaseUrl, `/${id}`));
            const notification = response.data || {};

            if (title) title.textContent = notification.title || 'Notification Details';
            body.innerHTML = `
                <div class="notification-detail">
                    <p class="notification-detail__message">${escapeHtml(notification.message)}</p>
                    ${detailRow('Type', notification.type)}
                    ${detailRow('Channel', notification.channel)}
                    ${detailRow('Status', notification.status_label || (notification.is_read ? 'Read' : 'Unread'))}
                    ${detailRow('Created', notification.created_at_display)}
                    ${detailRow('Read at', notification.read_at_display || '-')}
                    ${detailRow('Related record', notification.related_type && notification.related_id ? `${notification.related_type} #${notification.related_id}` : '-')}
                </div>`;

            if (relatedLink && notification.related && notification.related.url) {
                relatedLink.href = notification.related.url;
                relatedLink.textContent = notification.related.label || 'Open Related Record';
                relatedLink.classList.remove('d-none');
            }
        } catch (error) {
            console.error('Failed to load notification details:', error);
            body.innerHTML = '<div class="text-center text-danger py-4">Unable to load notification details.</div>';
        }
    }

    async function markRead(id) {
        try {
            const response = await requestJson(endpoint(api.markReadBaseUrl, `/${id}/read`), { method: 'PATCH' });
            updateSummary(response.summary || {});
            showToast(response.message || 'Notification marked as read.', 'success');
            await loadNotifications();
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
            showToast(error.message || 'Unable to mark notification as read.', 'error');
        }
    }

    async function markAllRead() {
        const confirmed = await confirmAction('Mark all as read?', 'All unread notifications will be marked as read.', 'Mark All as Read');
        if (!confirmed) return;

        try {
            const response = await requestJson(api.markAllReadUrl, { method: 'PATCH' });
            updateSummary(response.summary || {});
            showToast(response.message || 'Notifications marked as read.', 'success');
            await loadNotifications();
        } catch (error) {
            console.error('Failed to mark all notifications as read:', error);
            showToast(error.message || 'Unable to mark all notifications as read.', 'error');
        }
    }

    async function deleteNotification(id) {
        const confirmed = await confirmAction('Delete notification?', 'This notification will be removed from the list.', 'Delete');
        if (!confirmed) return;

        try {
            const response = await requestJson(endpoint(api.deleteBaseUrl, `/${id}`), { method: 'DELETE' });
            updateSummary(response.summary || {});
            showToast(response.message || 'Notification deleted.', 'success');
            await loadNotifications();
        } catch (error) {
            console.error('Failed to delete notification:', error);
            showToast(error.message || 'Unable to delete notification.', 'error');
        }
    }

    async function clearAll() {
        const confirmed = await confirmAction('Clear all notifications?', 'All notifications will be removed from this center.', 'Clear All');
        if (!confirmed) return;

        try {
            const response = await requestJson(api.clearAllUrl, { method: 'DELETE' });
            updateSummary(response.summary || {});
            showToast(response.message || 'Notifications cleared.', 'success');
            await loadNotifications();
        } catch (error) {
            console.error('Failed to clear notifications:', error);
            showToast(error.message || 'Unable to clear notifications.', 'error');
        }
    }

    function clearFormErrors() {
        document.querySelectorAll('#notificationSendForm .is-invalid').forEach((element) => {
            element.classList.remove('is-invalid');
        });
        ['notificationTitleError', 'notificationMessageError'].forEach((id) => {
            const element = document.getElementById(id);
            if (element) element.textContent = '';
        });
    }

    function applyFormErrors(errors) {
        const map = {
            title: ['notificationTitleField', 'notificationTitleError'],
            message: ['notificationMessageField', 'notificationMessageError'],
        };

        Object.keys(errors || {}).forEach((field) => {
            const pair = map[field];
            if (!pair) return;

            const input = document.getElementById(pair[0]);
            const error = document.getElementById(pair[1]);
            if (input) input.classList.add('is-invalid');
            if (error) error.textContent = Array.isArray(errors[field]) ? errors[field][0] : String(errors[field]);
        });
    }

    function openSendModal() {
        const form = document.querySelector(selectors.sendForm);
        const modalElement = document.querySelector(selectors.sendModal);
        if (!form || !modalElement) return;

        form.reset();
        clearFormErrors();
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }

    async function submitNotification(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const submitBtn = document.querySelector(selectors.submitBtn);
        clearFormErrors();

        const formData = new FormData(form);
        const body = {
            title: formData.get('title'),
            message: formData.get('message'),
            type: formData.get('type'),
            channel: formData.get('channel'),
        };

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
        }

        try {
            const response = await requestJson(api.storeUrl, {
                method: 'POST',
                body: JSON.stringify(body),
            });

            const modalElement = document.querySelector(selectors.sendModal);
            if (modalElement) bootstrap.Modal.getInstance(modalElement)?.hide();

            updateSummary(response.summary || {});
            showToast(response.message || 'Notification sent.', 'success');
            await loadNotifications();
        } catch (error) {
            console.error('Failed to send notification:', error);
            applyFormErrors(error.response && error.response.errors ? error.response.errors : {});
            showToast(error.message || 'Unable to send notification.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Notification';
            }
        }
    }

    document.querySelector(selectors.filter)?.addEventListener('change', (event) => {
        state.filter = event.currentTarget.value || 'all';
        state.page = 1;
        loadNotifications();
    });

    document.querySelector(selectors.list)?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;

        const id = button.dataset.id;
        if (!id) return;

        if (button.dataset.action === 'view') viewDetails(id);
        if (button.dataset.action === 'mark-read') markRead(id);
        if (button.dataset.action === 'delete') deleteNotification(id);
    });

    document.querySelector(selectors.sendBtn)?.addEventListener('click', openSendModal);
    document.querySelector(selectors.markAllBtn)?.addEventListener('click', markAllRead);
    document.querySelector(selectors.clearAllBtn)?.addEventListener('click', clearAll);
    document.querySelector(selectors.sendForm)?.addEventListener('submit', submitNotification);

    updateSummary(payload.summary || {});
    loadNotifications();
});
