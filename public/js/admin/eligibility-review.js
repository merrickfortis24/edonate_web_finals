document.addEventListener('DOMContentLoaded', function () {
    const config = {
        currentPage: 1,
        perPage: 10,
        totalRecords: 0,
        selectedId: null,
        isLoading: false,
        currentMode: 'view', // view | review
    };

    const selectors = {
        searchInput: '#eligibilitySearchInput',
        statusFilter: '#eligibilityStatusFilter',
        sourceFilter: '#eligibilitySourceFilter',
        refreshBtn: '#eligibilityRefreshBtn',
        tableBody: '#eligibilityTableBody',
        paginationInfo: '#eligibilityPaginationInfo',
        prevBtn: '#eligibilityPrevBtn',
        nextBtn: '#eligibilityNextBtn',
        statTotal: '#eligibilityStatTotal',
        statForReview: '#eligibilityStatForReview',
        statEligible: '#eligibilityStatEligible',
        statDeferred: '#eligibilityStatDeferred',
        statNotEligible: '#eligibilityStatNotEligible',
        modal: '#eligibilityReviewModal',
        modalHeader: '#reviewModalHeader',
        modalLabel: '#reviewModalLabel',
        modalContent: '#reviewModalContent',
        actionBanner: '#reviewActionBanner',
        actionText: '#reviewActionText',
        decisionWrapper: '#reviewDecisionWrapper',
        decisionSelect: '#reviewDecisionSelect',
        deferControls: '#reviewDeferControls',
        deferralDays: '#reviewDeferralDays',
        nextEligibleDate: '#reviewNextEligibleDate',
        notesWrapper: '#reviewNotesWrapper',
        notesField: '#reviewNotesField',
        confirmBtn: '#reviewConfirmBtn',
    };

    const statusBadges = {
        eligible: 'bg-success text-white',
        not_eligible: 'bg-danger text-white',
        temporary_deferred: 'bg-warning text-dark',
        for_review: 'bg-primary text-white',
    };

    const sourceBadges = {
        auto: 'bg-secondary text-white',
        admin_review: 'bg-dark text-white',
    };

    function getApiUrls() {
        const payload = (window.AdminPageData && window.AdminPageData.eligibilityPayload) || {};
        return payload.api || {};
    }

    function normalizeStatus(status) {
        const value = String(status || '').trim().toLowerCase();
        if (['approved', 'qualified', 'ready', 'eligible'].includes(value)) return 'eligible';
        if (['declined', 'not_eligible', 'not eligible', 'ineligible'].includes(value)) return 'not_eligible';
        if (['temporary_deferred', 'temporary deferred', 'deferred'].includes(value)) return 'temporary_deferred';
        if (['for_review', 'for review', 'pending'].includes(value)) return 'for_review';
        return value || 'for_review';
    }

    function normalizeSource(source) {
        const value = String(source || '').trim().toLowerCase();
        return ['auto', 'admin_review'].includes(value) ? value : 'auto';
    }

    function formatLabel(value) {
        return String(value || '')
            .trim()
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null || value === '' ? '-' : String(value);
        return div.innerHTML;
    }

    function truncate(text, maxLength) {
        const value = String(text || '').trim();
        if (value.length <= maxLength) return value;
        return value.slice(0, maxLength - 1) + '…';
    }

    function setLoading(loading) {
        config.isLoading = loading;
        const refresh = document.querySelector(selectors.refreshBtn);
        const prev = document.querySelector(selectors.prevBtn);
        const next = document.querySelector(selectors.nextBtn);
        if (refresh) refresh.disabled = loading;
        if (loading) {
            if (prev) prev.disabled = true;
            if (next) next.disabled = true;
        }
    }

    function setStats(stats) {
        const safe = stats && typeof stats === 'object' ? stats : {};
        setText(selectors.statTotal, safe.total || 0);
        setText(selectors.statForReview, safe.for_review || 0);
        setText(selectors.statEligible, safe.eligible || 0);
        setText(selectors.statDeferred, safe.temporary_deferred || 0);
        setText(selectors.statNotEligible, safe.not_eligible || 0);
    }

    function setText(selector, value) {
        const node = document.querySelector(selector);
        if (node) node.textContent = String(value ?? 0);
    }

    function formatDate(value) {
        if (!value) return '-';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '-';
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function showToast(message, type) {
        if (typeof Swal === 'undefined') {
            if (type === 'error') console.error(message);
            else console.log(message);
            return;
        }

        const icon = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
        Swal.fire({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 2500,
            icon: icon,
            title: message,
        });
    }

    function statusBadge(status) {
        const normalized = normalizeStatus(status);
        const badgeClass = statusBadges[normalized] || 'bg-secondary text-white';
        return '<span class="badge ' + badgeClass + '">' + escapeHtml(formatLabel(normalized)) + '</span>';
    }

    function sourceBadge(source) {
        const normalized = normalizeSource(source);
        const badgeClass = sourceBadges[normalized] || 'bg-secondary text-white';
        return '<span class="badge ' + badgeClass + '">' + escapeHtml(formatLabel(normalized)) + '</span>';
    }

    function setTableState(message, className) {
        const body = document.querySelector(selectors.tableBody);
        if (!body) return;
        const klass = className || 'text-muted';
        body.innerHTML = '<tr><td colspan="10" class="text-center ' + klass + ' py-4">' + escapeHtml(message) + '</td></tr>';
    }

    async function loadSubmissions() {
        if (config.isLoading) return;
        setLoading(true);
        setTableState('Loading submissions...');
        updatePagination({ current_page: config.currentPage, last_page: 1, total: 0, from: 0, to: 0 });

        const urls = getApiUrls();
        if (!urls.listUrl) {
            setTableState('Unable to load submissions.', 'text-danger');
            setLoading(false);
            return;
        }

        try {
            const params = new URLSearchParams({
                page: String(config.currentPage),
                per_page: String(config.perPage),
                search: document.querySelector(selectors.searchInput)?.value || '',
                status: document.querySelector(selectors.statusFilter)?.value || '',
                source: document.querySelector(selectors.sourceFilter)?.value || '',
            });

            const response = await fetch(urls.listUrl + '?' + params.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);
            const payload = await response.json();
            const rows = Array.isArray(payload.data) ? payload.data : [];
            renderTable(rows);
            updatePagination(payload.meta || {});
            setStats(payload.stats || {});
        } catch (error) {
            console.error(error);
            showToast('Failed to load eligibility submissions.', 'error');
            setTableState('Unable to load submissions. Please try again.', 'text-danger');
            updatePagination({ current_page: config.currentPage, last_page: 1, total: 0, from: 0, to: 0 });
        } finally {
            setLoading(false);
        }
    }

    function renderTable(rows) {
        const body = document.querySelector(selectors.tableBody);
        if (!body) return;

        if (!Array.isArray(rows) || rows.length === 0) {
            setTableState('No eligibility submissions found.');
            return;
        }

        body.innerHTML = rows.map(function (row) {
            const status = normalizeStatus(row.status);
            const source = normalizeSource(row.source);
            const reason = row.result_reason || '';
            const recommendation = row.recommendation_message || '';
            const reviewedBy = row.reviewed_by_name || '-';
            const reviewedAt = formatDate(row.reviewed_at);
            const isReviewable = row.admin_is_reviewable === true || status === 'for_review';
            const email = row.donor_email ? '<small class="text-muted d-block">' + escapeHtml(row.donor_email) + '</small>' : '';
            const contact = row.contact_number ? escapeHtml(row.contact_number) : '-';
            const donorMeta = [
                row.donor_code ? escapeHtml(row.donor_code) : null,
                row.blood_type ? 'Blood: ' + escapeHtml(row.blood_type) : null,
            ].filter(Boolean).join(' | ');
            const actionButton = isReviewable
                ? '<button type="button" class="btn btn-sm btn-primary review-btn" data-id="' + escapeHtml(row.eligibility_id) + '">Review</button>'
                : '<button type="button" class="btn btn-sm btn-outline-secondary view-btn" data-id="' + escapeHtml(row.eligibility_id) + '">View</button>';

            return (
                '<tr>' +
                    '<td>' +
                        '<div class="fw-semibold">' + escapeHtml(row.donor_name) + '</div>' +
                        (donorMeta ? '<small class="text-muted d-block">' + donorMeta + '</small>' : '') +
                        email +
                    '</td>' +
                    '<td><small class="d-block">' + contact + '</small></td>' +
                    '<td>' + statusBadge(status) + '</td>' +
                    '<td>' + sourceBadge(source) + '</td>' +
                    '<td><span title="' + escapeHtml(reason) + '">' + escapeHtml(truncate(reason || '-', 90)) + '</span></td>' +
                    '<td><span title="' + escapeHtml(recommendation) + '">' + escapeHtml(truncate(recommendation || '-', 90)) + '</span></td>' +
                    '<td>' + formatDate(row.next_eligible_date) + '</td>' +
                    '<td>' + escapeHtml(reviewedBy) + '</td>' +
                    '<td>' + reviewedAt + '</td>' +
                    '<td class="text-nowrap">' + actionButton + '</td>' +
                '</tr>'
            );
        }).join('');

        body.querySelectorAll('.view-btn').forEach(function (button) {
            button.addEventListener('click', function (event) {
                const id = Number(event.currentTarget.dataset.id || 0);
                if (id > 0) openViewModal(id);
            });
        });
        body.querySelectorAll('.review-btn').forEach(function (button) {
            button.addEventListener('click', function (event) {
                const id = Number(event.currentTarget.dataset.id || 0);
                if (id > 0) openReviewModal(id);
            });
        });
    }

    function updatePagination(meta) {
        const current = Number(meta.current_page || config.currentPage || 1);
        const last = Number(meta.last_page || 1);
        const total = Number(meta.total || 0);
        const from = Number(meta.from || 0);
        const to = Number(meta.to || 0);
        config.currentPage = current;
        config.totalRecords = total;

        const info = document.querySelector(selectors.paginationInfo);
        if (info) {
            info.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total + ' records (Page ' + current + ' of ' + last + ')';
        }

        const prev = document.querySelector(selectors.prevBtn);
        const next = document.querySelector(selectors.nextBtn);
        if (prev) prev.disabled = current <= 1;
        if (next) next.disabled = current >= last;
    }

    async function fetchDetail(id) {
        const urls = getApiUrls();
        if (!urls.detailBaseUrl) return null;
        try {
            const response = await fetch(urls.detailBaseUrl + '/' + id, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return await response.json();
        } catch (error) {
            console.error(error);
            showToast('Failed to load eligibility details.', 'error');
            return null;
        }
    }

    function renderDetailHtml(payload) {
        const donor = payload.donor || {};
        const answers = Array.isArray(payload.answers) ? payload.answers : [];
        const triggered = Array.isArray(payload.triggered_risk_questions) ? payload.triggered_risk_questions : [];

        const triggeredHtml = triggered.length > 0
            ? (
                '<div class="mt-3">' +
                    '<h6 class="text-muted mb-2">Triggered Risk Questions</h6>' +
                    triggered.map(function (item) {
                        return '<div class="small border rounded p-2 mb-2 bg-warning bg-opacity-10">' +
                            '<div class="fw-semibold">' + escapeHtml(item.question || '-') + '</div>' +
                            '<div>Risk: ' + escapeHtml(formatLabel(item.risk_level || 'safe')) + ' | Trigger: ' + escapeHtml(String(item.trigger_answer || '-').toUpperCase()) + '</div>' +
                        '</div>';
                    }).join('') +
                '</div>'
            )
            : '';

        const answersHtml = answers.length > 0
            ? (
                '<div class="mt-3">' +
                    '<h6 class="text-muted mb-2">Screening Answers</h6>' +
                    '<div style="max-height: 300px; overflow-y: auto;">' +
                    answers.map(function (item) {
                        const badge = item.answer === 'yes' ? 'bg-success' : 'bg-secondary';
                        const flagged = item.is_trigger_match ? '<span class="badge bg-danger ms-2">Triggered</span>' : '';
                        return '<div class="border rounded p-2 mb-2 bg-light">' +
                            '<div class="d-flex align-items-start">' +
                                '<span class="badge ' + badge + ' me-2">' + escapeHtml(String(item.answer || '').toUpperCase()) + '</span>' +
                                '<div class="flex-grow-1">' +
                                    '<div class="fw-semibold small">' + escapeHtml(item.question || '-') + flagged + '</div>' +
                                    '<div class="small text-muted">Risk: ' + escapeHtml(formatLabel(item.risk_level || 'safe')) + ' | Trigger: ' + escapeHtml(String(item.trigger_answer || '-').toUpperCase()) + '</div>' +
                                    (item.followup_answer ? '<div class="small mt-1">Follow-up: ' + escapeHtml(item.followup_answer) + '</div>' : '') +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    }).join('') +
                    '</div>' +
                '</div>'
            )
            : '<p class="text-muted small mb-0">No screening answers found for this record.</p>';

        return (
            '<div class="row g-3">' +
                '<div class="col-md-6">' +
                    '<h6 class="text-muted mb-2">Donor Details</h6>' +
                    '<p class="mb-1"><strong>' + escapeHtml(donor.name || '-') + '</strong></p>' +
                    '<p class="small mb-1">ID: ' + escapeHtml(donor.donor_code || '-') + '</p>' +
                    '<p class="small mb-1">Email: ' + escapeHtml(donor.email || '-') + '</p>' +
                    '<p class="small mb-0">Contact: ' + escapeHtml(donor.contact_number || '-') + '</p>' +
                '</div>' +
                '<div class="col-md-6">' +
                    '<h6 class="text-muted mb-2">Decision Details</h6>' +
                    '<p class="small mb-1">Status: ' + statusBadge(payload.status) + '</p>' +
                    '<p class="small mb-1">Source: ' + sourceBadge(payload.source) + '</p>' +
                    '<p class="small mb-1">Next Eligible: <strong>' + escapeHtml(formatDate(payload.next_eligible_date)) + '</strong></p>' +
                    '<p class="small mb-1">Reviewed By: <strong>' + escapeHtml(payload.reviewed_by_name || '-') + '</strong></p>' +
                    '<p class="small mb-0">Reviewed At: <strong>' + escapeHtml(formatDate(payload.reviewed_at)) + '</strong></p>' +
                '</div>' +
            '</div>' +
            '<div class="row g-3 mt-1">' +
                '<div class="col-md-6">' +
                    '<div class="border rounded p-3 bg-light h-100">' +
                        '<h6 class="text-muted mb-2">Result Reason</h6>' +
                        '<p class="small mb-0">' + escapeHtml(payload.result_reason || '-') + '</p>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-6">' +
                    '<div class="border rounded p-3 bg-light h-100">' +
                        '<h6 class="text-muted mb-2">Recommendation</h6>' +
                        '<p class="small mb-0">' + escapeHtml(payload.recommendation_message || '-') + '</p>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            triggeredHtml +
            answersHtml
        );
    }

    function resetModalFields() {
        const decision = document.querySelector(selectors.decisionSelect);
        const deferDays = document.querySelector(selectors.deferralDays);
        const nextDate = document.querySelector(selectors.nextEligibleDate);
        const notes = document.querySelector(selectors.notesField);
        if (decision) decision.value = 'eligible';
        if (deferDays) deferDays.value = '';
        if (nextDate) nextDate.value = '';
        if (notes) notes.value = '';
    }

    function setReviewMode(enabled) {
        config.currentMode = enabled ? 'review' : 'view';
        const decisionWrapper = document.querySelector(selectors.decisionWrapper);
        const deferControls = document.querySelector(selectors.deferControls);
        const notesWrapper = document.querySelector(selectors.notesWrapper);
        const confirmBtn = document.querySelector(selectors.confirmBtn);
        const banner = document.querySelector(selectors.actionBanner);
        const header = document.querySelector(selectors.modalHeader);

        if (decisionWrapper) decisionWrapper.classList.toggle('d-none', !enabled);
        if (deferControls) deferControls.classList.add('d-none');
        if (notesWrapper) notesWrapper.classList.toggle('d-none', !enabled);
        if (banner) banner.classList.toggle('d-none', !enabled);
        if (header) header.className = enabled ? 'modal-header bg-primary bg-opacity-10' : 'modal-header';
        if (confirmBtn) {
            confirmBtn.classList.toggle('d-none', !enabled);
            confirmBtn.textContent = 'Submit Decision';
            confirmBtn.classList.remove('btn-success', 'btn-danger', 'btn-warning');
            confirmBtn.classList.add('btn-primary');
            confirmBtn.disabled = false;
        }
    }

    function updateDecisionUi() {
        const decision = document.querySelector(selectors.decisionSelect)?.value || 'eligible';
        const deferControls = document.querySelector(selectors.deferControls);
        const banner = document.querySelector(selectors.actionBanner);
        const text = document.querySelector(selectors.actionText);
        const confirm = document.querySelector(selectors.confirmBtn);

        if (deferControls) deferControls.classList.toggle('d-none', decision !== 'temporary_deferred');
        if (!banner || !text || !confirm) return;

        if (decision === 'eligible') {
            banner.className = 'alert alert-success mb-3';
            text.textContent = 'This donor will be marked as Eligible after review.';
            confirm.className = 'btn btn-success';
            confirm.textContent = 'Approve as Eligible';
        } else if (decision === 'not_eligible') {
            banner.className = 'alert alert-danger mb-3';
            text.textContent = 'This donor will be marked as Not Eligible after review.';
            confirm.className = 'btn btn-danger';
            confirm.textContent = 'Reject as Not Eligible';
        } else {
            banner.className = 'alert alert-warning mb-3';
            text.textContent = 'This donor will be marked as Temporarily Deferred after review.';
            confirm.className = 'btn btn-warning';
            confirm.textContent = 'Set Temporary Defer';
        }
    }

    async function openViewModal(id) {
        config.selectedId = id;
        resetModalFields();
        setReviewMode(false);
        const content = document.querySelector(selectors.modalContent);
        const title = document.querySelector(selectors.modalLabel);
        if (title) title.textContent = 'Eligibility Details';
        if (content) content.innerHTML = '<div class="text-center text-muted py-4">Loading details...</div>';
        const modalEl = document.querySelector(selectors.modal);
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        const payload = await fetchDetail(id);
        if (payload && content) {
            content.innerHTML = renderDetailHtml(payload);
        } else if (content) {
            content.innerHTML = '<div class="text-danger text-center py-4">Unable to load details.</div>';
        }
    }

    async function openReviewModal(id) {
        config.selectedId = id;
        resetModalFields();
        setReviewMode(true);
        updateDecisionUi();
        const content = document.querySelector(selectors.modalContent);
        const title = document.querySelector(selectors.modalLabel);
        if (title) title.textContent = 'Review For-Review Submission';
        if (content) content.innerHTML = '<div class="text-center text-muted py-4">Loading details...</div>';
        const modalEl = document.querySelector(selectors.modal);
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        const payload = await fetchDetail(id);
        if (!payload) {
            if (content) content.innerHTML = '<div class="text-danger text-center py-4">Unable to load details.</div>';
            return;
        }

        if (payload.admin_is_reviewable !== true && normalizeStatus(payload.status) !== 'for_review') {
            showToast('Only for-review records can be manually reviewed.', 'error');
            setReviewMode(false);
        }

        if (content) content.innerHTML = renderDetailHtml(payload);
    }

    async function submitDecision() {
        if (config.currentMode !== 'review' || !config.selectedId) return;

        const urls = getApiUrls();
        if (!urls.reviewBaseUrl) return;

        const decision = document.querySelector(selectors.decisionSelect)?.value || 'eligible';
        const deferralDays = document.querySelector(selectors.deferralDays)?.value || '';
        const nextEligibleDate = document.querySelector(selectors.nextEligibleDate)?.value || '';
        const reviewNotes = document.querySelector(selectors.notesField)?.value || '';

        if (decision === 'temporary_deferred' && !deferralDays && !nextEligibleDate) {
            showToast('Set deferral days or next eligible date for temporary defer.', 'error');
            return;
        }

        const confirmBtn = document.querySelector(selectors.confirmBtn);
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Saving...';
        }

        try {
            const body = {
                status: decision,
                review_notes: reviewNotes || null,
                deferral_days: deferralDays ? Number(deferralDays) : null,
                next_eligible_date: nextEligibleDate || null,
            };

            const response = await fetch(urls.reviewBaseUrl + '/' + config.selectedId + '/review', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(body),
            });

            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.message || 'Failed to submit review decision.');
            }

            bootstrap.Modal.getInstance(document.querySelector(selectors.modal))?.hide();
            showToast('Eligibility review decision saved.', 'success');
            loadSubmissions();
        } catch (error) {
            console.error(error);
            showToast(error.message || 'Failed to save review decision.', 'error');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                updateDecisionUi();
            }
        }
    }

    function bindEvents() {
        document.querySelector(selectors.refreshBtn)?.addEventListener('click', function () {
            config.currentPage = 1;
            loadSubmissions();
        });
        document.querySelector(selectors.prevBtn)?.addEventListener('click', function () {
            if (config.currentPage > 1) {
                config.currentPage -= 1;
                loadSubmissions();
            }
        });
        document.querySelector(selectors.nextBtn)?.addEventListener('click', function () {
            config.currentPage += 1;
            loadSubmissions();
        });
        document.querySelector(selectors.searchInput)?.addEventListener('input', function () {
            config.currentPage = 1;
            loadSubmissions();
        });
        document.querySelector(selectors.statusFilter)?.addEventListener('change', function () {
            config.currentPage = 1;
            loadSubmissions();
        });
        document.querySelector(selectors.sourceFilter)?.addEventListener('change', function () {
            config.currentPage = 1;
            loadSubmissions();
        });
        document.querySelector(selectors.decisionSelect)?.addEventListener('change', updateDecisionUi);
        document.querySelector(selectors.confirmBtn)?.addEventListener('click', submitDecision);
    }

    bindEvents();
    loadSubmissions();
});

