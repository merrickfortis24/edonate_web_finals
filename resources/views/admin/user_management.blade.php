@extends('layouts.admin')

@section('title', 'eDonate - User Management')
@section('admin_page_class', 'admin-users-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('hamburger_id', 'hamburger')
@section('render_default_hamburger', 'false')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')

@section('header_title', 'User Management')
@section('header_subtitle', 'Manage donor registration, updates, and account validation')

@section('header_slot')
    <button class="hamburger" id="hamburger" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
    </button>
@endsection

@section('header_actions')
    <button class="btn-export btn" aria-label="Export donor data" type="button">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        Export Data
    </button>
@endsection

@section('admin_page_data')
{!! json_encode([
    'page' => 'user-management',
    'userManagement' => $userManagementPayload ?? [
        'api' => [
            'listUrl' => '',
        ],
        'filters' => [
            'bloodTypes' => [],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
@php
    $bloodTypeOptions = data_get($userManagementPayload ?? [], 'filters.bloodTypes', []);
@endphp
<main class="main container-fluid px-0">
    <div class="content container-fluid py-3">
        <section class="stats row g-3" aria-label="Donor statistics">
            <div class="col-6">
                <div class="stat-card stat-card--red h-100">
                    <span class="stat-card__label">Total Donors</span>
                    <span class="stat-card__value" id="userStatTotalDonors">0</span>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card stat-card--green h-100">
                    <span class="stat-card__label">Eligible Donors</span>
                    <span class="stat-card__value" id="userStatEligibleDonors">0</span>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card stat-card--blue h-100">
                    <span class="stat-card__label">Not Eligible</span>
                    <span class="stat-card__value" id="userStatNotEligibleDonors">0</span>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card stat-card--gold h-100">
                    <span class="stat-card__label">Total Donations</span>
                    <span class="stat-card__value" id="userStatTotalDonations">0</span>
                </div>
            </div>
        </section>

        <div class="filter-bar row g-3 align-items-center" role="search">
            <div class="filter-bar__search col-12 col-lg">
                <span class="filter-bar__search-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.45)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </span>
                <input
                    id="userManagementSearchInput"
                    type="text"
                    class="filter-bar__search-input form-control"
                    placeholder="Search by name, ID, or email..."
                    aria-label="Search donors"
                >
            </div>

            <div class="filter-bar__dropdown col-12 col-md-6 col-xl-4">
                <span class="filter-bar__dropdown-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4.5C12 4.5 6.5 11.5 6.5 16C6.5 19.038 9.014 21.5 12 21.5C14.986 21.5 17.5 19.038 17.5 16C17.5 11.5 12 4.5 12 4.5Z" fill="#b60c0c"/>
                    </svg>
                </span>
                <select id="userManagementBloodTypeFilter" class="filter-bar__select filter-bar__select--blood form-select" aria-label="Filter by blood type">
                    <option value="">All Blood Types</option>
                    @foreach ($bloodTypeOptions as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
                <span class="filter-bar__dropdown-arrow" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </span>
            </div>

            <div class="filter-bar__dropdown col-12 col-md-6 col-xl-3">
                <span class="filter-bar__dropdown-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                </span>
                <select id="userManagementStatusFilter" class="filter-bar__select filter-bar__select--status form-select" aria-label="Filter by eligibility status">
                    <option value="">All Status</option>
                    <option value="eligible">Eligible</option>
                    <option value="not_eligible">Not Eligible</option>
                </select>
                <span class="filter-bar__dropdown-arrow" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </span>
            </div>
        </div>

        <section class="table-wrap" aria-label="Donor list">
            <div class="table-inner table-responsive">
                <div class="table-grid table-thead">
                    <div class="table-th">Donor ID</div>
                    <div class="table-th">Name</div>
                    <div class="table-th">Blood Type</div>
                    <div class="table-th">Contact</div>
                    <div class="table-th">Last Donation</div>
                    <div class="table-th">Status</div>
                    <div class="table-th cell-center">Donations</div>
                    <div class="table-th">Actions</div>
                </div>

                <div class="table-body" id="userManagementTableBody">
                    <div class="table-grid table-row">
                        <div class="table-td">Loading...</div>
                        <div class="table-td">-</div>
                        <div class="table-td">-</div>
                        <div class="table-td">-</div>
                        <div class="table-td">-</div>
                        <div class="table-td">-</div>
                        <div class="table-td cell-center">-</div>
                        <div class="table-td">-</div>
                    </div>
                </div>

                <nav class="pagination" aria-label="Table pagination">
                    <span class="pagination__info" id="userManagementPaginationInfo">Showing 0-0 of 0 donors</span>
                    <div class="pagination__controls" id="userManagementPaginationControls"></div>
                </nav>
            </div>
        </section>
    </div>
</main>
@endsection

@push('admin_scripts')
<script>
    (function () {
        var payload = (window.AdminPageData && window.AdminPageData.userManagement) ? window.AdminPageData.userManagement : {};
        var listUrl = payload.api && payload.api.listUrl ? payload.api.listUrl : '';

        var searchInput = document.getElementById('userManagementSearchInput');
        var bloodTypeFilter = document.getElementById('userManagementBloodTypeFilter');
        var statusFilter = document.getElementById('userManagementStatusFilter');
        var tableBody = document.getElementById('userManagementTableBody');
        var paginationInfo = document.getElementById('userManagementPaginationInfo');
        var paginationControls = document.getElementById('userManagementPaginationControls');
        var exportButton = document.querySelector('.btn-export');

        var totalDonorsEl = document.getElementById('userStatTotalDonors');
        var eligibleDonorsEl = document.getElementById('userStatEligibleDonors');
        var notEligibleDonorsEl = document.getElementById('userStatNotEligibleDonors');
        var totalDonationsEl = document.getElementById('userStatTotalDonations');

        var state = {
            page: 1,
            perPage: 10,
            search: '',
            bloodType: '',
            status: ''
        };

        var searchDebounceTimer = null;

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatDate(value) {
            if (!value) {
                return '-';
            }

            var parsed = new Date(String(value) + 'T00:00:00');
            if (Number.isNaN(parsed.getTime())) {
                return String(value);
            }

            return parsed.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function formatNumber(value) {
            var numberValue = Number(value || 0);
            return numberValue.toLocaleString('en-US');
        }

        function normalizeStatus(value) {
            return String(value || '').toLowerCase() === 'not_eligible' ? 'not_eligible' : 'eligible';
        }

        function statusLabel(value) {
            return normalizeStatus(value) === 'not_eligible' ? 'Not Eligible' : 'Eligible';
        }

        function statusClass(value) {
            return normalizeStatus(value) === 'not_eligible' ? 'badge--not-eligible' : 'badge--eligible';
        }

        function renderActionButtons() {
            return ''
                + '<button class="btn-action btn-action--view" type="button" aria-label="View donor">'
                + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
                + '</button>'
                + '<button class="btn-action btn-action--edit" type="button" aria-label="Edit donor">'
                + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>'
                + '</button>'
                + '<button class="btn-action btn-action--delete" type="button" aria-label="Delete donor">'
                + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg>'
                + '</button>';
        }

        function renderRows(rows) {
            if (!tableBody) {
                return;
            }

            if (!Array.isArray(rows) || rows.length === 0) {
                tableBody.innerHTML = ''
                    + '<div class="table-grid table-row">'
                    + '<div class="table-td">No records</div>'
                    + '<div class="table-td">No donor data found for the selected filters.</div>'
                    + '<div class="table-td">-</div>'
                    + '<div class="table-td">-</div>'
                    + '<div class="table-td">-</div>'
                    + '<div class="table-td">-</div>'
                    + '<div class="table-td cell-center">-</div>'
                    + '<div class="table-td">-</div>'
                    + '</div>';
                return;
            }

            tableBody.innerHTML = rows.map(function (item) {
                var donorCode = escapeHtml(item.donor_code || ('D' + String(item.donor_id || '0')));
                var fullName = escapeHtml(item.full_name || 'Unknown Donor');
                var email = escapeHtml(item.email || '-');
                var bloodType = escapeHtml(item.blood_type || '-');
                var contact = escapeHtml(item.contact_number || '-');
                var lastDonation = escapeHtml(formatDate(item.last_donation_date));
                var donationCount = escapeHtml(formatNumber(item.total_donations));
                var badge = statusClass(item.eligibility_status);
                var badgeLabel = escapeHtml(statusLabel(item.eligibility_status));

                return ''
                    + '<div class="table-grid table-row">'
                    + '<div class="table-td">' + donorCode + '</div>'
                    + '<div class="table-td"><div class="donor-name__primary">' + fullName + '</div><div class="donor-name__email">' + email + '</div></div>'
                    + '<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> ' + bloodType + '</div>'
                    + '<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> ' + contact + '</div>'
                    + '<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> ' + lastDonation + '</div>'
                    + '<div class="table-td"><span class="badge ' + badge + '">' + badgeLabel + '</span></div>'
                    + '<div class="table-td cell-center">' + donationCount + '</div>'
                    + '<div class="table-td actions">' + renderActionButtons() + '</div>'
                    + '</div>';
            }).join('');
        }

        function setLoadingState() {
            if (!tableBody) {
                return;
            }

            tableBody.innerHTML = ''
                + '<div class="table-grid table-row">'
                + '<div class="table-td">Loading...</div>'
                + '<div class="table-td">Fetching donor records</div>'
                + '<div class="table-td">-</div>'
                + '<div class="table-td">-</div>'
                + '<div class="table-td">-</div>'
                + '<div class="table-td">-</div>'
                + '<div class="table-td cell-center">-</div>'
                + '<div class="table-td">-</div>'
                + '</div>';
        }

        function updateStats(stats) {
            if (totalDonorsEl) {
                totalDonorsEl.textContent = formatNumber(stats.total_donors || 0);
            }
            if (eligibleDonorsEl) {
                eligibleDonorsEl.textContent = formatNumber(stats.eligible_donors || 0);
            }
            if (notEligibleDonorsEl) {
                notEligibleDonorsEl.textContent = formatNumber(stats.not_eligible_donors || 0);
            }
            if (totalDonationsEl) {
                totalDonationsEl.textContent = formatNumber(stats.total_donations || 0);
            }
        }

        function createPageButton(label, targetPage, options) {
            var button = document.createElement('button');
            button.className = 'pagination__btn' + (options && options.active ? ' pagination__btn--active' : '');
            button.type = 'button';
            button.textContent = label;
            button.setAttribute('aria-label', options && options.ariaLabel ? options.ariaLabel : ('Page ' + label));
            if (options && options.active) {
                button.setAttribute('aria-current', 'page');
            }
            if (options && options.disabled) {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            }

            if (!button.disabled) {
                button.dataset.page = String(targetPage);
            }

            return button;
        }

        function renderPagination(meta) {
            if (!paginationInfo || !paginationControls) {
                return;
            }

            var total = Number(meta.total || 0);
            var from = Number(meta.from || 0);
            var to = Number(meta.to || 0);
            var currentPage = Number(meta.current_page || 1);
            var lastPage = Number(meta.last_page || 1);

            paginationInfo.textContent = 'Showing ' + from + '-' + to + ' of ' + total + ' donors';
            paginationControls.innerHTML = '';

            if (total <= 0) {
                return;
            }

            paginationControls.appendChild(createPageButton('<', Math.max(1, currentPage - 1), {
                disabled: currentPage <= 1,
                ariaLabel: 'Previous page'
            }));

            var start = Math.max(1, currentPage - 2);
            var end = Math.min(lastPage, start + 4);
            start = Math.max(1, end - 4);

            for (var pageIndex = start; pageIndex <= end; pageIndex += 1) {
                paginationControls.appendChild(createPageButton(String(pageIndex), pageIndex, {
                    active: pageIndex === currentPage,
                    ariaLabel: 'Page ' + pageIndex
                }));
            }

            paginationControls.appendChild(createPageButton('>', Math.min(lastPage, currentPage + 1), {
                disabled: currentPage >= lastPage,
                ariaLabel: 'Next page'
            }));
        }

        function buildRequestUrl() {
            var url = new URL(listUrl, window.location.origin);
            var params = url.searchParams;

            params.set('page', String(state.page));
            params.set('per_page', String(state.perPage));

            if (state.search !== '') {
                params.set('search', state.search);
            }
            if (state.bloodType !== '') {
                params.set('blood_type', state.bloodType);
            }
            if (state.status !== '') {
                params.set('status', state.status);
            }

            return url.toString();
        }

        function hydrateBloodTypeFilter(bloodTypes) {
            if (!bloodTypeFilter || !Array.isArray(bloodTypes)) {
                return;
            }

            var known = {};
            Array.prototype.forEach.call(bloodTypeFilter.options, function (option) {
                known[String(option.value)] = true;
            });

            bloodTypes.forEach(function (value) {
                var bloodType = String(value || '').trim();
                if (bloodType === '' || known[bloodType]) {
                    return;
                }

                var option = document.createElement('option');
                option.value = bloodType;
                option.textContent = bloodType;
                bloodTypeFilter.appendChild(option);
                known[bloodType] = true;
            });
        }

        function loadUsers() {
            if (!listUrl) {
                renderRows([]);
                return;
            }

            setLoadingState();

            fetch(buildRequestUrl(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Failed to load donor records.');
                    }
                    return response.json();
                })
                .then(function (responsePayload) {
                    updateStats(responsePayload.stats || {});
                    hydrateBloodTypeFilter((responsePayload.filters && responsePayload.filters.blood_types) || []);
                    renderRows(responsePayload.data || []);
                    renderPagination(responsePayload.meta || {});
                })
                .catch(function () {
                    renderRows([]);
                    if (paginationInfo) {
                        paginationInfo.textContent = 'Unable to load donor data right now.';
                    }
                });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var value = String(searchInput.value || '').trim();
                clearTimeout(searchDebounceTimer);

                searchDebounceTimer = setTimeout(function () {
                    state.search = value;
                    state.page = 1;
                    loadUsers();
                }, 300);
            });
        }

        if (bloodTypeFilter) {
            bloodTypeFilter.addEventListener('change', function () {
                state.bloodType = String(bloodTypeFilter.value || '').trim();
                state.page = 1;
                loadUsers();
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
                state.status = String(statusFilter.value || '').trim();
                state.page = 1;
                loadUsers();
            });
        }

        if (paginationControls) {
            paginationControls.addEventListener('click', function (event) {
                var target = event.target;
                if (!target) {
                    return;
                }

                var button = target.closest('button[data-page]');
                if (!button) {
                    return;
                }

                var nextPage = Number(button.dataset.page || '1');
                if (Number.isNaN(nextPage) || nextPage <= 0 || nextPage === state.page) {
                    return;
                }

                state.page = nextPage;
                loadUsers();
            });
        }

        if (tableBody) {
            tableBody.addEventListener('click', function (event) {
                var target = event.target;
                if (!target) {
                    return;
                }

                var actionButton = target.closest('.btn-action');
                if (!actionButton) {
                    return;
                }

                event.preventDefault();
            });
        }

        if (exportButton) {
            exportButton.addEventListener('click', function (event) {
                event.preventDefault();
            });
        }

        hydrateBloodTypeFilter((payload.filters && payload.filters.bloodTypes) || []);
        loadUsers();
    })();
</script>
@endpush
