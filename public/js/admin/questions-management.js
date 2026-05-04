document.addEventListener('DOMContentLoaded', function () {
    const config = {
        currentPage: 1,
        perPage: 20,
        totalRecords: 0,
        isLoading: false,
    };

    const riskLabels = {
        safe: 'Safe',
        auto_reject: 'Auto Reject',
        for_review: 'For Review',
        temporary_defer: 'Temporary Defer',
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
        activeField: '#questionActiveField',
        followupTriggerField: '#followupTriggerField',
        promptField: '#followupPromptField',
        promptWrapper: '#followupPromptWrapper',
        riskField: '#riskLevelField',
        triggerAnswerField: '#triggerAnswerField',
        deferralDaysField: '#deferralDaysField',
        recommendationField: '#recommendationMessageField',
        previewModal: '#questionTextPreviewModal',
        previewLabel: '#questionTextPreviewLabel',
        previewContent: '#questionTextPreviewContent',

        statTotal: '#questionsStatTotal',
        statActive: '#questionsStatActive',
        statInactive: '#questionsStatInactive',
    };

    const apiUrls = () => {
        const payload = (window.AdminPageData && window.AdminPageData.questionsPayload) || {};
        return payload.api || {};
    };

    let questionsData = [];
    let searchTimer = null;
    let paginationMeta = {
        current_page: 1,
        last_page: 1,
        total: 0,
        from: 0,
        to: 0,
    };

    function getElement(selector) {
        return document.querySelector(selector);
    }

    function showToast(message, type = 'info') {
        if (typeof Swal === 'undefined') {
            window.alert(message);
            return;
        }

        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            },
        });

        Toast.fire({
            icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'),
            title: message,
        });
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function setLoading(loading) {
        config.isLoading = loading;

        const refreshBtn = getElement(selectors.refreshBtn);
        if (refreshBtn) {
            refreshBtn.disabled = loading;
        }

        updatePagination(paginationMeta);
    }

    function getStatusBadge(isActive) {
        return isActive
            ? '<span class="badge bg-success text-white">Active</span>'
            : '<span class="badge bg-secondary text-white">Inactive</span>';
    }

    function getRiskBadge(riskLevel) {
        const risk = riskLabels[riskLevel] ? riskLevel : 'safe';
        const classMap = {
            safe: 'bg-success text-white',
            auto_reject: 'bg-danger text-white',
            for_review: 'bg-warning text-dark',
            temporary_defer: 'bg-info text-dark',
        };

        return `<span class="badge ${classMap[risk]}">${riskLabels[risk]}</span>`;
    }

    function getAnswerBadge(answer) {
        if (!answer) {
            return '<span class="text-muted small">—</span>';
        }

        return `<span class="badge bg-light text-dark border">${String(answer).toUpperCase()}</span>`;
    }

    function getFollowupBadge(trigger) {
        if (!trigger) {
            return '<span class="text-muted small">None</span>';
        }

        return `<span class="badge bg-info text-dark">If ${String(trigger).toUpperCase()}</span>`;
    }

    function getDeferralText(days) {
        const value = Number(days || 0);
        if (!Number.isFinite(value) || value < 1) {
            return '<span class="text-muted small">—</span>';
        }

        return `${value} day${value === 1 ? '' : 's'}`;
    }

    function safeText(value) {
        return String(value == null ? '' : value).trim();
    }

    function pickValue(row, snakeCaseKey, camelCaseKey) {
        if (row && Object.prototype.hasOwnProperty.call(row, snakeCaseKey)) {
            return row[snakeCaseKey];
        }

        if (row && Object.prototype.hasOwnProperty.call(row, camelCaseKey)) {
            return row[camelCaseKey];
        }

        return null;
    }

    function renderTextCell(text, type) {
        const normalized = safeText(text);
        const fallback = type === 'recommendation' ? 'No recommendation set' : '—';
        const resolved = normalized === '' ? fallback : normalized;
        const encoded = encodeURIComponent(resolved);
        const shouldShowView = resolved.length > 100;

        return `
            <div class="questions-cell">
                <span class="questions-cell__text questions-cell__text--${type}" title="${escapeHtml(resolved)}">${escapeHtml(resolved)}</span>
                ${shouldShowView ? `<button type="button" class="btn btn-link btn-sm questions-cell__view view-text-btn" data-type="${type}" data-text="${encoded}">View</button>` : ''}
            </div>
        `;
    }

    function updateStats(stats) {
        if (!stats) {
            return;
        }

        getElement(selectors.statTotal).textContent = stats.total || 0;
        getElement(selectors.statActive).textContent = stats.active || 0;
        getElement(selectors.statInactive).textContent = stats.inactive || 0;
    }

    async function loadQuestions() {
        if (config.isLoading) {
            return;
        }

        setLoading(true);

        const urls = apiUrls();
        if (!urls.listUrl) {
            showToast('Question API configuration is missing.', 'error');
            setLoading(false);
            return;
        }

        try {
            const searchTerm = getElement(selectors.searchInput)?.value || '';
            const status = getElement(selectors.statusFilter)?.value || '';

            const params = new URLSearchParams({
                page: config.currentPage,
                per_page: config.perPage,
                search: searchTerm,
                is_active: status,
            });

            const response = await fetch(`${urls.listUrl}?${params}`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const json = await response.json();
            questionsData = json.data || [];
            renderTable(questionsData);
            updatePagination(json.meta || {});
            updateStats(json.stats || {});
        } catch (error) {
            console.error('Failed to load questions:', error);
            showToast('Failed to load questions.', 'error');
            renderTable([]);
        } finally {
            setLoading(false);
        }
    }

    function renderTable(rows) {
        const tbody = getElement(selectors.tableBody);
        if (!tbody) {
            return;
        }

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No questions found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((row) => `
            <tr>
                <td class="text-center fw-semibold">${escapeHtml(pickValue(row, 'question_order', 'questionOrder') ?? '')}</td>
                <td>${renderTextCell(pickValue(row, 'question_text', 'questionText'), 'question')}</td>
                <td>${renderTextCell(pickValue(row, 'followup_prompt', 'followupPrompt'), 'followup')}</td>
                <td>${getFollowupBadge(pickValue(row, 'followup_trigger', 'followupTrigger'))}</td>
                <td>${getRiskBadge(pickValue(row, 'risk_level', 'riskLevel'))}</td>
                <td>${getAnswerBadge(pickValue(row, 'trigger_answer', 'triggerAnswer'))}</td>
                <td>${getDeferralText(pickValue(row, 'deferral_days', 'deferralDays'))}</td>
                <td>${renderTextCell(pickValue(row, 'recommendation_message', 'recommendationMessage'), 'recommendation')}</td>
                <td>${getStatusBadge(Boolean(pickValue(row, 'is_active', 'isActive')))}</td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary edit-btn me-1" data-id="${pickValue(row, 'question_id', 'questionId')}">Edit</button>
                    <button type="button" class="btn btn-sm ${Boolean(pickValue(row, 'is_active', 'isActive')) ? 'btn-outline-danger' : 'btn-outline-success'} toggle-btn" data-id="${pickValue(row, 'question_id', 'questionId')}">
                        ${Boolean(pickValue(row, 'is_active', 'isActive')) ? 'Disable' : 'Enable'}
                    </button>
                </td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.edit-btn').forEach((button) => {
            button.addEventListener('click', (event) => {
                openEditModal(Number(event.currentTarget.dataset.id));
            });
        });

        tbody.querySelectorAll('.toggle-btn').forEach((button) => {
            button.addEventListener('click', (event) => {
                toggleQuestion(Number(event.currentTarget.dataset.id));
            });
        });

        tbody.querySelectorAll('.view-text-btn').forEach((button) => {
            button.addEventListener('click', (event) => {
                const target = event.currentTarget;
                const type = String(target.dataset.type || 'Text');
                const text = decodeURIComponent(String(target.dataset.text || ''));
                openTextPreviewModal(type, text);
            });
        });
    }

    function openTextPreviewModal(type, text) {
        const modalLabel = getElement(selectors.previewLabel);
        const modalContent = getElement(selectors.previewContent);
        const modalElement = getElement(selectors.previewModal);
        if (!modalLabel || !modalContent || !modalElement) {
            return;
        }

        const labelMap = {
            question: 'Question',
            followup: 'Follow-up Prompt',
            recommendation: 'Recommendation Message',
        };

        modalLabel.textContent = labelMap[type] || 'Text Preview';
        modalContent.textContent = text || '—';

        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }

    function updatePagination(meta) {
        const currentPage = Number(meta.current_page || config.currentPage || 1);
        const lastPage = Number(meta.last_page || 1);
        const total = Number(meta.total || 0);
        const from = Number(meta.from || 0);
        const to = Number(meta.to || 0);

        paginationMeta = {
            current_page: currentPage,
            last_page: lastPage,
            total,
            from,
            to,
        };
        config.currentPage = currentPage;
        config.totalRecords = total;

        const info = getElement(selectors.paginationInfo);
        if (info) {
            info.textContent = `Showing ${from} to ${to} of ${total} questions`;
        }

        const prevBtn = getElement(selectors.prevBtn);
        const nextBtn = getElement(selectors.nextBtn);
        if (prevBtn) {
            prevBtn.disabled = config.isLoading || currentPage === 1;
        }
        if (nextBtn) {
            nextBtn.disabled = config.isLoading || currentPage >= lastPage;
        }
    }

    function clearValidationState() {
        [
            selectors.textField,
            selectors.orderField,
            selectors.riskField,
            selectors.triggerAnswerField,
            selectors.deferralDaysField,
        ].forEach((selector) => {
            getElement(selector)?.classList.remove('is-invalid');
        });
    }

    function resetForm() {
        const form = getElement(selectors.form);
        form.reset();
        form.classList.remove('was-validated');
        clearValidationState();

        getElement(selectors.idField).value = '';
        getElement(selectors.orderField).value = questionsData.length > 0
            ? Math.max(...questionsData.map((question) => Number(question.question_order) || 0)) + 1
            : 1;
        getElement(selectors.activeField).checked = true;
        getElement(selectors.followupTriggerField).value = '';
        getElement(selectors.promptField).value = '';
        getElement(selectors.riskField).value = 'safe';
        getElement(selectors.triggerAnswerField).value = '';
        getElement(selectors.deferralDaysField).value = '';
        getElement(selectors.recommendationField).value = '';

        toggleFollowupPrompt();
        updateDecisionFields();
    }

    function toggleFollowupPrompt() {
        const trigger = getElement(selectors.followupTriggerField).value;
        const wrapper = getElement(selectors.promptWrapper);

        if (!wrapper) {
            return;
        }

        if (trigger) {
            wrapper.classList.remove('d-none');
        } else {
            wrapper.classList.add('d-none');
            getElement(selectors.promptField).value = '';
        }
    }

    function updateDecisionFields() {
        const risk = getElement(selectors.riskField).value || 'safe';
        const triggerField = getElement(selectors.triggerAnswerField);
        const deferralField = getElement(selectors.deferralDaysField);

        const requiresTrigger = risk !== 'safe';
        triggerField.required = requiresTrigger;
        if (!requiresTrigger) {
            triggerField.value = '';
            triggerField.classList.remove('is-invalid');
        }

        const requiresDeferral = risk === 'temporary_defer';
        deferralField.required = requiresDeferral;
        deferralField.disabled = !requiresDeferral;
        if (!requiresDeferral) {
            deferralField.value = '';
            deferralField.classList.remove('is-invalid');
        }
    }

    function openAddModal() {
        resetForm();
        getElement(selectors.modalLabel).textContent = 'Add Question';
        getElement(selectors.submitBtn).textContent = 'Add Question';

        const modal = bootstrap.Modal.getOrCreateInstance(getElement(selectors.modal));
        modal.show();
    }

    function openEditModal(id) {
        const question = questionsData.find((item) => Number(pickValue(item, 'question_id', 'questionId')) === id);
        if (!question) {
            showToast('Selected question was not found.', 'error');
            return;
        }

        resetForm();
        getElement(selectors.modalLabel).textContent = 'Edit Question';
        getElement(selectors.submitBtn).textContent = 'Save Changes';

        const questionId = pickValue(question, 'question_id', 'questionId');
        const questionText = pickValue(question, 'question_text', 'questionText');
        const questionOrder = pickValue(question, 'question_order', 'questionOrder');
        const isActive = Boolean(pickValue(question, 'is_active', 'isActive'));
        const followupTrigger = pickValue(question, 'followup_trigger', 'followupTrigger');
        const followupPrompt = pickValue(question, 'followup_prompt', 'followupPrompt');
        const riskLevel = pickValue(question, 'risk_level', 'riskLevel');
        const triggerAnswer = pickValue(question, 'trigger_answer', 'triggerAnswer');
        const deferralDays = pickValue(question, 'deferral_days', 'deferralDays');
        const recommendationMessage = pickValue(question, 'recommendation_message', 'recommendationMessage');

        getElement(selectors.idField).value = questionId || '';
        getElement(selectors.textField).value = questionText || '';
        getElement(selectors.orderField).value = questionOrder || 1;
        getElement(selectors.activeField).checked = isActive;
        getElement(selectors.followupTriggerField).value = followupTrigger || '';
        getElement(selectors.promptField).value = followupPrompt || '';
        getElement(selectors.riskField).value = riskLabels[riskLevel] ? riskLevel : 'safe';
        getElement(selectors.triggerAnswerField).value = triggerAnswer || '';
        getElement(selectors.deferralDaysField).value = deferralDays || '';
        getElement(selectors.recommendationField).value = recommendationMessage || '';

        toggleFollowupPrompt();
        updateDecisionFields();

        const modal = bootstrap.Modal.getOrCreateInstance(getElement(selectors.modal));
        modal.show();
    }

    function validateForm() {
        clearValidationState();
        updateDecisionFields();

        const textInput = getElement(selectors.textField);
        const orderInput = getElement(selectors.orderField);
        const riskInput = getElement(selectors.riskField);
        const triggerAnswerInput = getElement(selectors.triggerAnswerField);
        const deferralInput = getElement(selectors.deferralDaysField);

        let isValid = true;

        if (!textInput.value.trim()) {
            textInput.classList.add('is-invalid');
            isValid = false;
        }

        const order = Number(orderInput.value);
        if (!Number.isInteger(order) || order < 1) {
            orderInput.classList.add('is-invalid');
            isValid = false;
        }

        if (!riskLabels[riskInput.value]) {
            riskInput.classList.add('is-invalid');
            isValid = false;
        }

        if (riskInput.value !== 'safe' && !triggerAnswerInput.value) {
            triggerAnswerInput.classList.add('is-invalid');
            isValid = false;
        }

        const deferralDays = Number(deferralInput.value);
        if (riskInput.value === 'temporary_defer' && (!Number.isInteger(deferralDays) || deferralDays < 1)) {
            deferralInput.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }

    async function readErrorMessage(response) {
        try {
            const payload = await response.json();
            if (payload && payload.errors) {
                const firstError = Object.values(payload.errors).flat().find(Boolean);
                if (firstError) {
                    return firstError;
                }
            }

            return (payload && payload.message) || 'Request failed.';
        } catch (error) {
            return 'Request failed.';
        }
    }

    async function submitForm(event) {
        event.preventDefault();

        if (!validateForm()) {
            return;
        }

        const id = getElement(selectors.idField).value;
        const isEdit = id !== '';
        const urls = apiUrls();
        const url = isEdit ? `${urls.updateUrl}/${id}` : urls.storeUrl;
        const method = isEdit ? 'PUT' : 'POST';
        const riskLevel = getElement(selectors.riskField).value;

        if (!url) {
            showToast('Question save route is not configured.', 'error');
            return;
        }

        const submitBtn = getElement(selectors.submitBtn);
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    question_text: getElement(selectors.textField).value.trim(),
                    question_order: Number(getElement(selectors.orderField).value),
                    is_active: getElement(selectors.activeField).checked,
                    followup_trigger: getElement(selectors.followupTriggerField).value || null,
                    followup_prompt: getElement(selectors.promptField).value.trim() || null,
                    risk_level: riskLevel,
                    trigger_answer: riskLevel === 'safe' ? null : getElement(selectors.triggerAnswerField).value,
                    deferral_days: riskLevel === 'temporary_defer' ? Number(getElement(selectors.deferralDaysField).value) : null,
                    recommendation_message: getElement(selectors.recommendationField).value.trim() || null,
                }),
            });

            if (!response.ok) {
                throw new Error(await readErrorMessage(response));
            }

            bootstrap.Modal.getInstance(getElement(selectors.modal))?.hide();
            showToast(`Question successfully ${isEdit ? 'updated' : 'created'}.`, 'success');
            loadQuestions();
        } catch (error) {
            console.error('Save failed:', error);
            showToast(error.message || 'Failed to save question.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    async function toggleQuestion(id) {
        const urls = apiUrls();
        if (!urls.toggleUrl) {
            showToast('Question status route is not configured.', 'error');
            return;
        }

        const question = questionsData.find((item) => Number(pickValue(item, 'question_id', 'questionId')) === id);
        if (!question) {
            showToast('Selected question was not found.', 'error');
            return;
        }

        const isEnabling = !Boolean(pickValue(question, 'is_active', 'isActive'));
        const actionText = isEnabling ? 'enable' : 'disable';

        try {
            const response = await fetch(`${urls.toggleUrl}/${id}/toggle`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });

            if (!response.ok) {
                throw new Error(await readErrorMessage(response));
            }

            showToast(`Question successfully ${isEnabling ? 'enabled' : 'disabled'}.`, 'success');
            loadQuestions();
        } catch (error) {
            console.error('Toggle failed:', error);
            showToast(error.message || `Failed to ${actionText} question.`, 'error');
        }
    }

    getElement(selectors.refreshBtn)?.addEventListener('click', () => {
        config.currentPage = 1;
        loadQuestions();
    });

    getElement(selectors.prevBtn)?.addEventListener('click', () => {
        if (config.currentPage > 1) {
            config.currentPage--;
            loadQuestions();
        }
    });

    getElement(selectors.nextBtn)?.addEventListener('click', () => {
        config.currentPage++;
        loadQuestions();
    });

    getElement(selectors.searchInput)?.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            config.currentPage = 1;
            loadQuestions();
        }, 250);
    });

    getElement(selectors.statusFilter)?.addEventListener('change', () => {
        config.currentPage = 1;
        loadQuestions();
    });

    document.querySelector('#addQuestionBtn')?.addEventListener('click', openAddModal);
    getElement(selectors.followupTriggerField)?.addEventListener('change', toggleFollowupPrompt);
    getElement(selectors.riskField)?.addEventListener('change', updateDecisionFields);
    getElement(selectors.form)?.addEventListener('submit', submitForm);

    loadQuestions();
});
