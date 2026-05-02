document.addEventListener('DOMContentLoaded', function () {
    const config = {
        currentPage: 1,
        perPage: 10,
        totalRecords: 0,
        selectedId: null,
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
        modalContent: '#reviewModalContent',
        approveBtn: '#reviewApproveBtn',
        rejectBtn: '#reviewRejectBtn',
        toastContainer: '#toastContainer',
        statTotal: '#eligibilityStatTotal',
        statPending: '#eligibilityStatPending',
        statEligible: '#eligibilityStatEligible',
        statIneligible: '#eligibilityStatIneligible',
    };

    const apiUrls = () => {
        const payload = (window.AdminPageData && window.AdminPageData.eligibilityPayload) || {};
        return payload.api || {};
    };

    function showToast(message, type = 'info') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
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

    function updateStats(stats) {
        if (!stats) return;
        document.querySelector(selectors.statTotal).textContent = stats.total || 0;
        document.querySelector(selectors.statPending).textContent = stats.pending || 0;
        document.querySelector(selectors.statEligible).textContent = stats.eligible || 0;
        document.querySelector(selectors.statIneligible).textContent = stats.not_eligible || 0;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    function getStatusBadgeClass(status) {
        switch (status) {
            case 'pending':
                return 'bg-warning text-dark';
            case 'eligible':
                return 'bg-success text-white';
            case 'not_eligible':
                return 'bg-danger text-white';
            default:
                return 'bg-secondary text-white';
        }
    }

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

    function renderTable(rows) {
        const tbody = document.querySelector(selectors.tableBody);
        if (!tbody) return;

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No submissions found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => `
            <tr role="row">
                <td>${escapeHtml(row.donor_name)}</td>
                <td>${escapeHtml(row.blood_type)}</td>
                <td>
                    <span class="badge ${getStatusBadgeClass(row.status)}">
                        ${row.status.charAt(0).toUpperCase() + row.status.slice(1)}
                    </span>
                </td>
                <td>${formatDate(row.reviewed_at)}</td>
                <td>${escapeHtml(row.reviewed_by || '—')}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-primary view-btn" data-id="${row.eligibility_id}" aria-label="View submission">View</button>
                    ${['eligible', 'not_eligible'].includes(row.status) ? '' : `
                        <button type="button" class="btn btn-sm btn-warning review-btn" data-id="${row.eligibility_id}" aria-label="Review submission">Review</button>
                    `}
                </td>
            </tr>
        `).join('');

        // Attach event listeners
        tbody.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                config.selectedId = parseInt(e.target.dataset.id);
                openViewModal(config.selectedId);
            });
        });

        tbody.querySelectorAll('.review-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                config.selectedId = parseInt(e.target.dataset.id);
                openReviewModal(config.selectedId);
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

    async function viewSubmission(id) {
        const urls = apiUrls();
        if (!urls.detailBaseUrl) {
            showToast('API configuration missing', 'error');
            return;
        }

        try {
            const response = await fetch(`${urls.detailBaseUrl}/${id}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const submission = await response.json();
            displaySubmissionDetail(submission);
        } catch (error) {
            console.error('Failed to load submission:', error);
            showToast('Failed to load submission details', 'error');
        }
    }

    function displaySubmissionDetail(submission) {
        const html = `
            <div class="submission-detail">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Donor Information</h6>
                        <p><strong>${escapeHtml(submission.donor.name)}</strong></p>
                        <p class="small text-muted">ID: ${escapeHtml(submission.donor.donor_code)}</p>
                        <p class="small">Blood Type: <strong>${escapeHtml(submission.donor.blood_type)}</strong></p>
                        <p class="small">Contact: ${escapeHtml(submission.donor.contact_number || '—')}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Status Information</h6>
                        <p>Current: <span class="badge ${getStatusBadgeClass(submission.status)}">${submission.status}</span></p>
                        <p class="small">Last Donation: ${formatDate(submission.donor.last_donation_date)}</p>
                        <p class="small">Next Eligible: ${formatDate(submission.donor.next_eligible_date)}</p>
                        <p class="small">Submitted: ${formatDate(submission.submitted_at)}</p>
                    </div>
                </div>

                ${submission.answers && submission.answers.length > 0 ? `
                    <hr>
                    <h6 class="text-muted">Screening Answers</h6>
                    <div class="answers-list">
                        ${submission.answers.map(ans => `
                            <div class="answer-item mb-2 p-2 bg-light rounded">
                                <p class="small mb-1"><strong>${escapeHtml(ans.question)}</strong></p>
                                <p class="small mb-0">
                                    <span>Answer: ${escapeHtml(ans.answer)}</span>
                                    ${ans.is_flag ? '<span class="badge bg-danger ms-2">⚠ Flag</span>' : ''}
                                </p>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                ${submission.review_notes ? `
                    <hr>
                    <h6 class="text-muted">Review Notes</h6>
                    <p class="small">${escapeHtml(submission.review_notes)}</p>
                ` : ''}
            </div>
        `;

        const modalContent = document.querySelector(selectors.modalContent);
        if (modalContent) {
            modalContent.innerHTML = html;
        }
    }

    async function openReviewModal(id) {
        config.selectedId = id;
        await viewSubmission(id);
        const modal = document.querySelector(selectors.modal);
        if (modal) {
            // Show action buttons
            const approveBtn = document.querySelector(selectors.approveBtn);
            const rejectBtn = document.querySelector(selectors.rejectBtn);
            if (approveBtn) approveBtn.style.display = '';
            if (rejectBtn) rejectBtn.style.display = '';
            
            new bootstrap.Modal(modal).show();
        }
    }

    async function openViewModal(id) {
        config.selectedId = id;
        await viewSubmission(id);
        const modal = document.querySelector(selectors.modal);
        if (modal) {
            // Hide action buttons
            const approveBtn = document.querySelector(selectors.approveBtn);
            const rejectBtn = document.querySelector(selectors.rejectBtn);
            if (approveBtn) approveBtn.style.display = 'none';
            if (rejectBtn) rejectBtn.style.display = 'none';
            
            new bootstrap.Modal(modal).show();
        }
    }

    async function submitDecision(status) {
        if (!config.selectedId) return;

        const urls = apiUrls();
        if (!urls.reviewBaseUrl) {
            showToast('API configuration missing', 'error');
            return;
        }

        const { value: notes } = await Swal.fire({
            title: 'Review Notes',
            input: 'textarea',
            inputLabel: 'Enter review notes (optional):',
            inputPlaceholder: 'Type your notes here...',
            showCancelButton: true
        });

        if (notes === undefined) return; // Cancelled

        try {
            const response = await fetch(`${urls.reviewBaseUrl}/${config.selectedId}/review`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    status: status,
                    notes: notes || null,
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to update');
            }

            const modal = document.querySelector(selectors.modal);
            if (modal) {
                bootstrap.Modal.getInstance(modal)?.hide();
            }

            showToast(`Submission marked as ${status}`, 'success');
            config.currentPage = 1;
            loadSubmissions();
        } catch (error) {
            console.error('Failed to submit decision:', error);
            showToast(error.message || 'Failed to submit decision', 'error');
        }
    }

    // Event listeners
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

    document.querySelector(selectors.approveBtn)?.addEventListener('click', () => {
        submitDecision('eligible');
    });

    document.querySelector(selectors.rejectBtn)?.addEventListener('click', () => {
        submitDecision('not_eligible');
    });

    // Initial load
    loadSubmissions();
});
