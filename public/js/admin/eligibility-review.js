document.addEventListener('DOMContentLoaded', function () {
    const config = {
        currentPage: 1,
        perPage: 10,
        totalRecords: 0,
        selectedId: null,
        pendingAction: null, // 'approved' or 'declined'
        isLoading: false,
    };

    const selectors = {
        searchInput: '#eligibilitySearchInput',
        statusFilter: '#eligibilityStatusFilter',
        bloodTypeFilter: '#eligibilityBloodTypeFilter',
        locationFilter: '#eligibilityLocationFilter',
        refreshBtn: '#eligibilityRefreshBtn',
        tableBody: '#eligibilityTableBody',
        paginationInfo: '#eligibilityPaginationInfo',
        prevBtn: '#eligibilityPrevBtn',
        nextBtn: '#eligibilityNextBtn',
        modal: '#eligibilityReviewModal',
        modalHeader: '#reviewModalHeader',
        modalLabel: '#reviewModalLabel',
        modalContent: '#reviewModalContent',
        actionBanner: '#reviewActionBanner',
        actionText: '#reviewActionText',
        notesWrapper: '#reviewNotesWrapper',
        notesField: '#reviewNotesField',
        confirmBtn: '#reviewConfirmBtn',
        statTotal: '#eligibilityStatTotal',
        statPending: '#eligibilityStatPending',
        statApproved: '#eligibilityStatApproved',
        statDeclined: '#eligibilityStatDeclined',
    };

    const apiUrls = () => {
        const payload = (window.AdminPageData && window.AdminPageData.eligibilityPayload) || {};
        return payload.api || {};
    };

    // ── Utilities ────────────────────────────────────────────────────────────

    function showToast(message, type = 'info') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        Toast.fire({
            icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'),
            title: message
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function setLoading(loading) {
        config.isLoading = loading;
        const btn = document.querySelector(selectors.refreshBtn);
        if (btn) btn.disabled = loading;
        const prevBtn = document.querySelector(selectors.prevBtn);
        const nextBtn = document.querySelector(selectors.nextBtn);
        if (prevBtn) prevBtn.disabled = loading;
        if (nextBtn) nextBtn.disabled = loading;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    function getStatusBadge(status) {
        switch (status) {
            case 'pending':
                return '<span class="badge bg-warning text-dark">Pending</span>';
            case 'approved':
                return '<span class="badge bg-success text-white">Approved</span>';
            case 'declined':
                return '<span class="badge bg-danger text-white">Declined</span>';
            default:
                return '<span class="badge bg-secondary text-white">' + escapeHtml(status) + '</span>';
        }
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    function updateStats(stats) {
        if (!stats) return;
        document.querySelector(selectors.statTotal).textContent = stats.total || 0;
        document.querySelector(selectors.statPending).textContent = stats.pending || 0;
        document.querySelector(selectors.statApproved).textContent = stats.approved || 0;
        document.querySelector(selectors.statDeclined).textContent = stats.declined || 0;
    }

    // ── Data loading ─────────────────────────────────────────────────────────

    async function loadSubmissions() {
        if (config.isLoading) return;
        setLoading(true);

        const urls = apiUrls();
        if (!urls.listUrl) {
            showToast('API configuration missing', 'error');
            setLoading(false);
            return;
        }

        try {
            const searchTerm = document.querySelector(selectors.searchInput)?.value || '';
            const status = document.querySelector(selectors.statusFilter)?.value || '';
            const bloodType = document.querySelector(selectors.bloodTypeFilter)?.value || '';
            const location = document.querySelector(selectors.locationFilter)?.value || '';

            const params = new URLSearchParams({
                page: config.currentPage,
                per_page: config.perPage,
                search: searchTerm,
                status: status,
                blood_type: bloodType,
                location: location,
            });

            const response = await fetch(`${urls.listUrl}?${params}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const json = await response.json();
            renderTable(json.data || []);
            updatePagination(json.meta || {});
            updateStats(json.stats || {});
        } catch (error) {
            console.error('Failed to load submissions:', error);
            showToast('Failed to load submissions', 'error');
            renderTable([]);
        } finally {
            setLoading(false);
        }
    }

    // ── Table rendering ──────────────────────────────────────────────────────

    function renderTable(rows) {
        const tbody = document.querySelector(selectors.tableBody);
        if (!tbody) return;

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No submissions found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => {
            const isPending = row.status === 'pending';
            const actionButtons = isPending
                ? `<button type="button" class="btn btn-sm btn-outline-primary view-btn me-1" data-id="${row.eligibility_id}">View</button>
                   <button type="button" class="btn btn-sm btn-success approve-btn me-1" data-id="${row.eligibility_id}">Approve</button>
                   <button type="button" class="btn btn-sm btn-danger decline-btn" data-id="${row.eligibility_id}">Decline</button>`
                : `<button type="button" class="btn btn-sm btn-outline-primary view-btn" data-id="${row.eligibility_id}">View</button>`;

            return `
                <tr role="row">
                    <td>
                        <div class="fw-semibold">${escapeHtml(row.donor_name)}</div>
                        <small class="text-muted">${escapeHtml(row.donor_code)}</small>
                    </td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(row.blood_type)}</span></td>
                    <td>${getStatusBadge(row.status)}</td>
                    <td>${formatDate(row.last_donation_date)}</td>
                    <td>${formatDate(row.next_eligible_date)}</td>
                    <td class="text-nowrap">${actionButtons}</td>
                </tr>
            `;
        }).join('');

        // Attach event listeners
        tbody.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id);
                openViewModal(id);
            });
        });

        tbody.querySelectorAll('.approve-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id);
                openConfirmModal(id, 'approved');
            });
        });

        tbody.querySelectorAll('.decline-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id);
                openConfirmModal(id, 'declined');
            });
        });
    }

    function updatePagination(meta) {
        config.totalRecords = meta.total || 0;
        const info = document.querySelector(selectors.paginationInfo);
        if (info) {
            info.textContent = `Showing ${meta.from || 0} to ${meta.to || 0} of ${meta.total || 0} submissions`;
        }

        const prevBtn = document.querySelector(selectors.prevBtn);
        const nextBtn = document.querySelector(selectors.nextBtn);
        if (prevBtn) prevBtn.disabled = config.currentPage === 1;
        if (nextBtn) nextBtn.disabled = config.currentPage >= (meta.last_page || 1);
    }

    // ── Detail fetching ──────────────────────────────────────────────────────

    async function fetchDetail(id) {
        const urls = apiUrls();
        if (!urls.detailBaseUrl) {
            showToast('API configuration missing', 'error');
            return null;
        }

        try {
            const response = await fetch(`${urls.detailBaseUrl}/${id}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return await response.json();
        } catch (error) {
            console.error('Failed to load submission:', error);
            showToast('Failed to load submission details', 'error');
            return null;
        }
    }

    function renderDetailHtml(submission) {
        return `
            <div class="row mb-3">
                <div class="col-md-6">
                    <h6 class="text-muted mb-2">Donor Information</h6>
                    <p class="mb-1"><strong>${escapeHtml(submission.donor.name)}</strong></p>
                    <p class="small text-muted mb-1">ID: ${escapeHtml(submission.donor.donor_code)}</p>
                    <p class="small mb-1">Blood Type: <strong>${escapeHtml(submission.donor.blood_type)}</strong></p>
                    <p class="small mb-0">Contact: ${escapeHtml(submission.donor.contact_number || '—')}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-2">Eligibility Information</h6>
                    <p class="mb-1">Status: ${getStatusBadge(submission.status)}</p>
                    <p class="small mb-1">Last Donation: <strong>${formatDate(submission.donor.last_donation_date)}</strong></p>
                    <p class="small mb-0">Next Eligible: <strong>${formatDate(submission.donor.next_eligible_date)}</strong></p>
                </div>
            </div>

            ${submission.answers && submission.answers.length > 0 ? `
                <hr>
                <h6 class="text-muted mb-2">Screening Answers</h6>
                <div class="answers-list" style="max-height: 300px; overflow-y: auto;">
                    ${submission.answers.map((ans, idx) => `
                        <div class="d-flex align-items-start gap-2 mb-2 p-2 rounded ${ans.is_flag ? 'bg-danger bg-opacity-10 border border-danger border-opacity-25' : 'bg-light'}">
                            <span class="badge ${ans.answer === 'yes' ? 'bg-success' : 'bg-secondary'} mt-1">${escapeHtml(ans.answer).toUpperCase()}</span>
                            <div class="flex-grow-1">
                                <p class="small mb-0 fw-semibold">${escapeHtml(ans.question)}</p>
                                ${ans.followup_answer ? `<p class="small text-muted mb-0 mt-1">↳ ${escapeHtml(ans.followup_answer)}</p>` : ''}
                            </div>
                            ${ans.is_flag ? '<span class="badge bg-danger ms-auto mt-1">⚠ Flag</span>' : ''}
                        </div>
                    `).join('')}
                </div>
            ` : ''}
        `;
    }

    // ── Modal modes ──────────────────────────────────────────────────────────

    async function openViewModal(id) {
        config.selectedId = id;
        config.pendingAction = null;

        const modalContent = document.querySelector(selectors.modalContent);
        modalContent.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';

        // Set modal to view-only mode
        document.querySelector(selectors.modalLabel).textContent = 'Eligibility Details';
        document.querySelector(selectors.actionBanner).classList.add('d-none');
        document.querySelector(selectors.notesWrapper).classList.add('d-none');
        document.querySelector(selectors.confirmBtn).classList.add('d-none');
        document.querySelector(selectors.modalHeader).className = 'modal-header';

        const modalEl = document.querySelector(selectors.modal);
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        const data = await fetchDetail(id);
        if (data) {
            modalContent.innerHTML = renderDetailHtml(data);
        } else {
            modalContent.innerHTML = '<div class="text-center text-danger py-4">Failed to load details</div>';
        }
    }

    async function openConfirmModal(id, action) {
        config.selectedId = id;
        config.pendingAction = action;

        const isApprove = action === 'approved';
        const label = isApprove ? 'Approve' : 'Decline';
        const colorClass = isApprove ? 'success' : 'danger';

        const modalContent = document.querySelector(selectors.modalContent);
        modalContent.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';

        // Set modal to confirmation mode
        document.querySelector(selectors.modalLabel).textContent = `${label} Donor Eligibility`;
        document.querySelector(selectors.modalHeader).className = `modal-header bg-${colorClass} bg-opacity-10`;

        const banner = document.querySelector(selectors.actionBanner);
        banner.className = `alert alert-${colorClass} mb-3`;
        banner.classList.remove('d-none');
        document.querySelector(selectors.actionText).textContent =
            isApprove
                ? '✓ You are about to APPROVE this donor\'s eligibility.'
                : '✗ You are about to DECLINE this donor\'s eligibility.';

        document.querySelector(selectors.notesWrapper).classList.remove('d-none');
        document.querySelector(selectors.notesField).value = '';

        const confirmBtn = document.querySelector(selectors.confirmBtn);
        confirmBtn.classList.remove('d-none', 'btn-success', 'btn-danger');
        confirmBtn.classList.add(`btn-${colorClass}`);
        confirmBtn.textContent = `Confirm ${label}`;
        confirmBtn.disabled = false;

        const modalEl = document.querySelector(selectors.modal);
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        const data = await fetchDetail(id);
        if (data) {
            modalContent.innerHTML = renderDetailHtml(data);
        } else {
            modalContent.innerHTML = '<div class="text-center text-danger py-4">Failed to load details</div>';
        }
    }

    // ── Submit decision ──────────────────────────────────────────────────────

    async function submitDecision() {
        if (!config.selectedId || !config.pendingAction) return;

        const urls = apiUrls();
        if (!urls.reviewBaseUrl) {
            showToast('API configuration missing', 'error');
            return;
        }

        const confirmBtn = document.querySelector(selectors.confirmBtn);
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Processing...';

        const notes = document.querySelector(selectors.notesField)?.value || '';

        try {
            const response = await fetch(`${urls.reviewBaseUrl}/${config.selectedId}/review`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    status: config.pendingAction,
                    notes: notes || null,
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to update');
            }

            const modalEl = document.querySelector(selectors.modal);
            bootstrap.Modal.getInstance(modalEl)?.hide();

            const label = config.pendingAction === 'approved' ? 'approved' : 'declined';
            showToast(`Submission successfully ${label}`, 'success');
            loadSubmissions();
        } catch (error) {
            console.error('Failed to submit decision:', error);
            showToast(error.message || 'Failed to submit decision', 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = config.pendingAction === 'approved' ? 'Confirm Approve' : 'Confirm Decline';
        }
    }

    // ── Event listeners ──────────────────────────────────────────────────────

    document.querySelector(selectors.refreshBtn)?.addEventListener('click', () => {
        config.currentPage = 1;
        loadSubmissions();
    });

    document.querySelector(selectors.prevBtn)?.addEventListener('click', () => {
        if (config.currentPage > 1) {
            config.currentPage--;
            loadSubmissions();
        }
    });

    document.querySelector(selectors.nextBtn)?.addEventListener('click', () => {
        config.currentPage++;
        loadSubmissions();
    });

    document.querySelector(selectors.searchInput)?.addEventListener('input', () => {
        config.currentPage = 1;
        loadSubmissions();
    });

    document.querySelector(selectors.statusFilter)?.addEventListener('change', () => {
        config.currentPage = 1;
        loadSubmissions();
    });

    document.querySelector(selectors.bloodTypeFilter)?.addEventListener('change', () => {
        config.currentPage = 1;
        loadSubmissions();
    });

    document.querySelector(selectors.locationFilter)?.addEventListener('change', () => {
        config.currentPage = 1;
        loadSubmissions();
    });

    document.querySelector(selectors.confirmBtn)?.addEventListener('click', submitDecision);

    // ── Initial load ─────────────────────────────────────────────────────────
    loadSubmissions();
});
