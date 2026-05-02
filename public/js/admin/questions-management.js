document.addEventListener('DOMContentLoaded', function () {
    const config = {
        currentPage: 1,
        perPage: 20,
        totalRecords: 0,
        editingId: null,
        isLoading: false,
    };

    const selectors = {
        addBtn: '#addQuestionBtn',
        searchInput: '#questionsSearchInput',
        statusFilter: '#questionsStatusFilter',
        refreshBtn: '#questionsRefreshBtn',
        tableBody: '#questionsTableBody',
        paginationInfo: '#questionsPaginationInfo',
        prevBtn: '#questionsPrevBtn',
        nextBtn: '#questionsNextBtn',
        modal: '#questionFormModal',
        form: '#questionForm',
        idField: '#questionIdField',
        textField: '#questionTextField',
        typeField: '#questionTypeField',
        sortField: '#sortOrderField',
        disqField: '#disqualifyingField',
        submitBtn: '#questionSubmitBtn',
        toastContainer: '#toastContainer',
        statTotal: '#questionsStatTotal',
        statActive: '#questionsStatActive',
        statInactive: '#questionsStatInactive',
        statDisqualifying: '#questionsStatDisqualifying',
    };

    const apiUrls = () => {
        const payload = (window.AdminPageData && window.AdminPageData.questionsPayload) || {};
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
    }

    function updateStats(stats) {
        if (!stats) return;
        document.querySelector(selectors.statTotal).textContent = stats.total || 0;
        document.querySelector(selectors.statActive).textContent = stats.active || 0;
        document.querySelector(selectors.statInactive).textContent = stats.inactive || 0;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

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
            const isActive = document.querySelector(selectors.statusFilter)?.value || '';

            const params = new URLSearchParams({
                page: config.currentPage,
                per_page: config.perPage,
                search: searchTerm,
                is_active: isActive,
            });

            const response = await fetch(`${urls.listUrl}?${params}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const json = await response.json();
            renderTable(json.data || []);
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

    function renderTable(rows) {
        const tbody = document.querySelector(selectors.tableBody);
        if (!tbody) return;

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No questions found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => `
            <tr role="row">
                <td>
                    <input type="number" class="form-control form-control-sm sort-input"
                           value="${row.sort_order}" data-id="${row.question_id}"
                           min="0" max="999" style="width: 60px;" aria-label="Sort order">
                </td>
                <td>${escapeHtml(row.question_text)}</td>
                <td><span class="badge bg-info">${row.question_type}</span></td>
                <td>
                    <span class="badge ${row.is_disqualifying ? 'bg-danger' : 'bg-secondary'}">
                        ${row.is_disqualifying ? 'Yes' : 'No'}
                    </span>
                </td>
                <td>
                    <button type="button" class="btn btn-sm ${row.is_active ? 'btn-success' : 'btn-secondary'} toggle-btn"
                            data-id="${row.question_id}"
                            aria-label="${row.is_active ? 'Deactivate' : 'Activate'} question">
                        ${row.is_active ? 'Active' : 'Inactive'}
                    </button>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-primary edit-btn" data-id="${row.question_id}" aria-label="Edit question">Edit</button>
                    <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="${row.question_id}" aria-label="Delete question">Delete</button>
                </td>
            </tr>
        `).join('');

        // Attach event listeners
        tbody.querySelectorAll('.sort-input').forEach(input => {
            input.addEventListener('blur', (e) => updateSortOrder(e.target));
        });

        tbody.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.addEventListener('click', (e) => toggleQuestion(parseInt(e.target.dataset.id)));
        });

        tbody.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', (e) => openEditModal(parseInt(e.target.dataset.id)));
        });

        tbody.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const result = await Swal.fire({
                    title: 'Are you sure?',
                    text: 'You are about to delete this question. This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                });
                
                if (result.isConfirmed) {
                    deleteQuestion(parseInt(e.target.dataset.id));
                }
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

    async function updateSortOrder(input) {
        const id = parseInt(input.dataset.id);
        const urls = apiUrls();
        if (!urls.updateUrl) return;

        try {
            // Get current question data first
            const response = await fetch(`${urls.listUrl}?page=1&per_page=1000`);
            if (!response.ok) throw new Error('Failed to fetch questions');

            const json = await response.json();
            const question = json.data.find(q => q.question_id === id);
            if (!question) return;

            const updateResponse = await fetch(`${urls.updateUrl}/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    question_text: question.question_text,
                    question_type: question.question_type,
                    is_disqualifying: question.is_disqualifying,
                    sort_order: parseInt(input.value),
                }),
            });

            if (!updateResponse.ok) throw new Error('Failed to update');
            showToast('Sort order updated', 'success');
            loadQuestions();
        } catch (error) {
            console.error('Failed to update sort order:', error);
            showToast('Failed to update sort order', 'error');
            input.value = input.dataset.oldValue;
        }
    }

    async function toggleQuestion(id) {
        const urls = apiUrls();
        if (!urls.toggleUrl) {
            showToast('API configuration missing', 'error');
            return;
        }

        try {
            const response = await fetch(`${urls.toggleUrl}/${id}/toggle`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({}),
            });

            if (!response.ok) throw new Error('Failed to toggle');

            const json = await response.json();
            showToast(json.message || 'Question updated', 'success');
            loadQuestions();
        } catch (error) {
            console.error('Failed to toggle question:', error);
            showToast('Failed to toggle question', 'error');
        }
    }

    async function deleteQuestion(id) {
        const urls = apiUrls();
        if (!urls.toggleUrl) {
            showToast('API configuration missing', 'error');
            return;
        }

        try {
            const response = await fetch(`${urls.toggleUrl}/${id}/toggle`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({}),
            });

            if (!response.ok) throw new Error('Failed to deactivate');

            showToast('Question deactivated', 'success');
            loadQuestions();
        } catch (error) {
            console.error('Failed to delete question:', error);
            showToast('Failed to deactivate question', 'error');
        }
    }

    function resetForm() {
        config.editingId = null;
        const form = document.querySelector(selectors.form);
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
        }
        document.querySelector(selectors.idField).value = '';
        document.querySelector(selectors.sortField).value = '0';
        document.querySelector(selectors.disqField).checked = false;

        const modalTitle = document.querySelector('#questionFormLabel');
        const submitBtn = document.querySelector(selectors.submitBtn);
        if (modalTitle) modalTitle.textContent = 'Add Question';
        if (submitBtn) submitBtn.textContent = 'Add Question';
    }

    function openAddModal() {
        resetForm();
        const modal = document.querySelector(selectors.modal);
        if (modal) {
            new bootstrap.Modal(modal).show();
        }
    }

    async function openEditModal(id) {
        config.editingId = id;
        const urls = apiUrls();
        if (!urls.listUrl) {
            showToast('API configuration missing', 'error');
            return;
        }

        try {
            const response = await fetch(`${urls.listUrl}?page=1&per_page=1000`);
            if (!response.ok) throw new Error('Failed to fetch questions');

            const json = await response.json();
            const question = json.data.find(q => q.question_id === id);
            if (!question) throw new Error('Question not found');

            document.querySelector(selectors.idField).value = question.question_id;
            document.querySelector(selectors.textField).value = question.question_text;
            document.querySelector(selectors.typeField).value = question.question_type;
            document.querySelector(selectors.sortField).value = question.sort_order;
            document.querySelector(selectors.disqField).checked = question.is_disqualifying;

            const modalTitle = document.querySelector('#questionFormLabel');
            const submitBtn = document.querySelector(selectors.submitBtn);
            if (modalTitle) modalTitle.textContent = 'Edit Question';
            if (submitBtn) submitBtn.textContent = 'Update Question';

            const modal = document.querySelector(selectors.modal);
            if (modal) {
                new bootstrap.Modal(modal).show();
            }
        } catch (error) {
            console.error('Failed to load question:', error);
            showToast('Failed to load question', 'error');
        }
    }

    async function submitForm(e) {
        e.preventDefault();
        const form = document.querySelector(selectors.form);
        if (!form.checkValidity()) {
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }

        const urls = apiUrls();
        const isEdit = config.editingId !== null;
        const url = isEdit ? `${urls.updateUrl}/${config.editingId}` : urls.storeUrl;
        const method = isEdit ? 'PUT' : 'POST';

        const data = {
            question_text: document.querySelector(selectors.textField).value,
            question_type: document.querySelector(selectors.typeField).value,
            is_disqualifying: document.querySelector(selectors.disqField).checked,
            sort_order: parseInt(document.querySelector(selectors.sortField).value),
        };

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to save');
            }

            const modal = document.querySelector(selectors.modal);
            if (modal) {
                bootstrap.Modal.getInstance(modal)?.hide();
            }

            showToast(isEdit ? 'Question updated successfully' : 'Question created successfully', 'success');
            config.currentPage = 1;
            loadQuestions();
        } catch (error) {
            console.error('Failed to submit form:', error);
            showToast(error.message || 'Failed to save question', 'error');
        }
    }

    // Event listeners
    document.querySelector(selectors.addBtn)?.addEventListener('click', openAddModal);

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

    document.querySelector(selectors.form)?.addEventListener('submit', submitForm);

    document.querySelector(selectors.modal)?.addEventListener('hidden.bs.modal', resetForm);

    // Initial load
    loadQuestions();
});
