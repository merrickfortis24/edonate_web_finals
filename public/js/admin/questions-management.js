document.addEventListener('DOMContentLoaded', function () {
    const config = {
        currentPage: 1,
        perPage: 20,
        totalRecords: 0,
        isLoading: false,
    };

    const selectors = {
        searchInput: '#questionsSearchInput',
        statusFilter: '#questionsStatusFilter',
        refreshBtn: '#questionsRefreshBtn',
        tableBody: '#questionsTableBody',
        paginationInfo: '#questionsPaginationInfo',
        prevBtn: '#questionsPrevBtn',
        nextBtn: '#questionsNextBtn',
        
        modal: '#questionFormModal',
        form: '#questionForm',
        modalLabel: '#questionFormLabel',
        submitBtn: '#questionSubmitBtn',
        
        idField: '#questionIdField',
        textField: '#questionTextField',
        orderField: '#questionOrderField',
        triggerField: '#followupTriggerField',
        promptField: '#followupPromptField',
        promptWrapper: '#followupPromptWrapper',
        
        textError: '#questionTextError',
        orderError: '#questionOrderError',
        
        statTotal: '#questionsStatTotal',
        statActive: '#questionsStatActive',
        statInactive: '#questionsStatInactive',
    };

    const apiUrls = () => {
        const payload = (window.AdminPageData && window.AdminPageData.questionsPayload) || {};
        return payload.api || {};
    };

    let questionsData = [];

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
        if (!text) return '';
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

    function getStatusBadge(isActive) {
        return isActive
            ? '<span class="badge bg-success text-white">Active</span>'
            : '<span class="badge bg-secondary text-white">Inactive</span>';
    }

    function getTriggerBadge(trigger) {
        if (!trigger || trigger === '') return '<span class="text-muted small">None</span>';
        return `<span class="badge bg-info text-dark">If '${trigger.toUpperCase()}'</span>`;
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    function updateStats(stats) {
        if (!stats) return;
        document.querySelector(selectors.statTotal).textContent = stats.total || 0;
        document.querySelector(selectors.statActive).textContent = stats.active || 0;
        document.querySelector(selectors.statInactive).textContent = stats.inactive || 0;
    }

    // ── Data loading ─────────────────────────────────────────────────────────

    async function loadQuestions() {
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

            const params = new URLSearchParams({
                page: config.currentPage,
                per_page: config.perPage,
                search: searchTerm,
                is_active: status,
            });

            const response = await fetch(`${urls.listUrl}?${params}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const json = await response.json();
            questionsData = json.data || [];
            renderTable(questionsData);
            updatePagination(json.meta || {});
            updateStats(json.stats || {});
        } catch (error) {
            console.error('Failed to load questions:', error);
            showToast('Failed to load questions', 'error');
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
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No questions found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => {
            return `
                <tr>
                    <td class="text-center fw-semibold">${row.question_order}</td>
                    <td><div class="text-break" style="max-width: 400px;">${escapeHtml(row.question_text)}</div></td>
                    <td>${getTriggerBadge(row.followup_trigger)}</td>
                    <td><div class="text-break text-muted small" style="max-width: 250px;">${escapeHtml(row.followup_prompt || '—')}</div></td>
                    <td>${getStatusBadge(row.is_active)}</td>
                    <td class="text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary edit-btn me-1" data-id="${row.question_id}">Edit</button>
                        <button type="button" class="btn btn-sm ${row.is_active ? 'btn-outline-danger' : 'btn-outline-success'} toggle-btn" data-id="${row.question_id}">
                            ${row.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        // Attach event listeners
        tbody.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id);
                openEditModal(id);
            });
        });

        tbody.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = parseInt(e.currentTarget.dataset.id);
                toggleQuestion(id);
            });
        });
    }

    function updatePagination(meta) {
        config.totalRecords = meta.total || 0;
        const info = document.querySelector(selectors.paginationInfo);
        if (info) {
            info.textContent = `Showing ${meta.from || 0} to ${meta.to || 0} of ${meta.total || 0} questions`;
        }

        const prevBtn = document.querySelector(selectors.prevBtn);
        const nextBtn = document.querySelector(selectors.nextBtn);
        if (prevBtn) prevBtn.disabled = config.currentPage === 1;
        if (nextBtn) nextBtn.disabled = config.currentPage >= (meta.last_page || 1);
    }

    // ── Form Modal ───────────────────────────────────────────────────────────

    function resetForm() {
        const form = document.querySelector(selectors.form);
        form.reset();
        form.classList.remove('was-validated');
        
        document.querySelector(selectors.idField).value = '';
        document.querySelector(selectors.textField).classList.remove('is-invalid');
        document.querySelector(selectors.orderField).classList.remove('is-invalid');
        
        // Defaults
        document.querySelector(selectors.orderField).value = (questionsData.length > 0 ? Math.max(...questionsData.map(q => q.question_order)) + 1 : 1);
        document.querySelector(selectors.triggerField).value = '';
        document.querySelector(selectors.promptField).value = '';
        
        toggleFollowupPrompt();
    }

    function toggleFollowupPrompt() {
        const trigger = document.querySelector(selectors.triggerField).value;
        const wrapper = document.querySelector(selectors.promptWrapper);
        if (trigger) {
            wrapper.classList.remove('d-none');
        } else {
            wrapper.classList.add('d-none');
            document.querySelector(selectors.promptField).value = '';
        }
    }

    function openAddModal() {
        resetForm();
        document.querySelector(selectors.modalLabel).textContent = 'Add Question';
        document.querySelector(selectors.submitBtn).textContent = 'Add Question';
        
        const modal = bootstrap.Modal.getOrCreateInstance(document.querySelector(selectors.modal));
        modal.show();
    }

    function openEditModal(id) {
        const question = questionsData.find(q => q.question_id === id);
        if (!question) return;

        resetForm();
        document.querySelector(selectors.modalLabel).textContent = 'Edit Question';
        document.querySelector(selectors.submitBtn).textContent = 'Save Changes';
        
        document.querySelector(selectors.idField).value = question.question_id;
        document.querySelector(selectors.textField).value = question.question_text;
        document.querySelector(selectors.orderField).value = question.question_order;
        document.querySelector(selectors.triggerField).value = question.followup_trigger || '';
        document.querySelector(selectors.promptField).value = question.followup_prompt || '';
        
        toggleFollowupPrompt();
        
        const modal = bootstrap.Modal.getOrCreateInstance(document.querySelector(selectors.modal));
        modal.show();
    }

    async function submitForm(e) {
        e.preventDefault();
        
        const form = document.querySelector(selectors.form);
        const textInput = document.querySelector(selectors.textField);
        const orderInput = document.querySelector(selectors.orderField);
        
        let isValid = true;
        
        if (!textInput.value.trim() || textInput.value.trim().length < 5) {
            textInput.classList.add('is-invalid');
            isValid = false;
        } else {
            textInput.classList.remove('is-invalid');
        }
        
        if (!orderInput.value || parseInt(orderInput.value) < 1) {
            orderInput.classList.add('is-invalid');
            isValid = false;
        } else {
            orderInput.classList.remove('is-invalid');
        }

        if (!isValid) return;

        const id = document.querySelector(selectors.idField).value;
        const isEdit = id !== '';
        
        const urls = apiUrls();
        const url = isEdit ? `${urls.updateUrl}/${id}` : urls.storeUrl;
        const method = isEdit ? 'PUT' : 'POST';

        const submitBtn = document.querySelector(selectors.submitBtn);
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    question_text: textInput.value.trim(),
                    question_order: parseInt(orderInput.value),
                    followup_trigger: document.querySelector(selectors.triggerField).value,
                    followup_prompt: document.querySelector(selectors.promptField).value.trim(),
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to save question');
            }

            const modal = bootstrap.Modal.getInstance(document.querySelector(selectors.modal));
            modal.hide();

            showToast(`Question successfully ${isEdit ? 'updated' : 'created'}`, 'success');
            loadQuestions();
        } catch (error) {
            console.error('Save failed:', error);
            showToast(error.message || 'Failed to save question', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    // ── Toggle Active Status ─────────────────────────────────────────────────

    async function toggleQuestion(id) {
        const urls = apiUrls();
        if (!urls.toggleUrl) return;

        const question = questionsData.find(q => q.question_id === id);
        if (!question) return;

        const isActivating = !question.is_active;
        const actionText = isActivating ? 'activate' : 'deactivate';

        try {
            const response = await fetch(`${urls.toggleUrl}/${id}/toggle`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || `Failed to ${actionText} question`);
            }

            showToast(`Question successfully ${isActivating ? 'activated' : 'deactivated'}`, 'success');
            loadQuestions();
        } catch (error) {
            console.error('Toggle failed:', error);
            showToast(error.message || `Failed to ${actionText} question`, 'error');
        }
    }

    // ── Event listeners ──────────────────────────────────────────────────────

    document.querySelector(selectors.refreshBtn)?.addEventListener('click', () => {
        config.currentPage = 1;
        loadQuestions();
    });

    document.querySelector(selectors.prevBtn)?.addEventListener('click', () => {
        if (config.currentPage > 1) {
            config.currentPage--;
            loadQuestions();
        }
    });

    document.querySelector(selectors.nextBtn)?.addEventListener('click', () => {
        config.currentPage++;
        loadQuestions();
    });

    document.querySelector(selectors.searchInput)?.addEventListener('input', () => {
        config.currentPage = 1;
        loadQuestions();
    });

    document.querySelector(selectors.statusFilter)?.addEventListener('change', () => {
        config.currentPage = 1;
        loadQuestions();
    });

    document.querySelector('#addQuestionBtn')?.addEventListener('click', openAddModal);
    
    document.querySelector(selectors.triggerField)?.addEventListener('change', toggleFollowupPrompt);

    document.querySelector(selectors.form)?.addEventListener('submit', submitForm);

    // ── Initial load ─────────────────────────────────────────────────────────
    loadQuestions();
});
