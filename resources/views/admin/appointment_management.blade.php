@extends('layouts.admin')

@section('title', 'eDonate - Appointment Management')
@section('admin_page_class', 'admin-appointments-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_id', 'appointmentSidebar')
@section('sidebar_aria_label', 'Admin navigation')
@section('sidebar_nav_aria_label', 'Primary navigation')
@section('sidebar_link_mode', 'link')
@section('sidebar_open_class', 'is-open')
@section('overlay_id', 'appointmentOverlay')
@section('overlay_class', 'appointment-overlay overlay')
@section('overlay_open_class', 'is-visible')
@section('hamburger_id', 'appointmentHamburger')
@section('render_default_hamburger', 'false')

@section('header_title', 'Appointment Management')
@section('header_subtitle', 'Approve, reject, or reschedule donation appointments')

@section('header_slot')
    <button class="hamburger" id="appointmentHamburger" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="appointmentSidebar">
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
    </button>
@endsection

@section('header_actions')
    <div class="appointment-header__views" role="group" aria-label="Appointment view mode">
        <button class="appointment-view-btn appointment-view-btn--active btn" type="button">List View</button>
        <button class="appointment-view-btn appointment-view-btn--outline btn" type="button">Calendar View</button>
    </div>
@endsection

@section('admin_page_data')
{!! json_encode([
    'page' => 'appointment-management',
    'appointmentManagement' => $appointmentManagementPayload ?? [
        'api' => [
            'listUrl' => '',
        ],
        'filters' => [
            'centers' => [],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
@php
    $centerOptions = data_get($appointmentManagementPayload ?? [], 'filters.centers', []);
@endphp
<main class="appointment-main container-fluid px-0">
    <section class="appointment-content container-fluid py-3" aria-label="Appointments content">
        <div class="appointment-stats row g-3" aria-label="Appointment summary">
            <div class="col-6 col-xl-3">
                <article class="stat-card appointment-stat appointment-stat--green h-100">
                    <p class="appointment-stat__label">Confirmed</p>
                    <p class="appointment-stat__value" id="appointmentStatConfirmed">0</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card appointment-stat appointment-stat--gold h-100">
                    <p class="appointment-stat__label">Pending Approval</p>
                    <p class="appointment-stat__value" id="appointmentStatPending">0</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card appointment-stat appointment-stat--red h-100">
                    <p class="appointment-stat__label">Cancelled</p>
                    <p class="appointment-stat__value" id="appointmentStatCancelled">0</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card appointment-stat appointment-stat--blue h-100">
                    <p class="appointment-stat__label">Rescheduled</p>
                    <p class="appointment-stat__value" id="appointmentStatRescheduled">0</p>
                </article>
            </div>
        </div>

        <form class="appointment-filter row g-3 align-items-center" role="search" aria-label="Filter appointments" action="#" method="get" onsubmit="return false;">
            <div class="appointment-filter__search col-12 col-lg">
                <span class="appointment-filter__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="16.5" y1="16.5" x2="22" y2="22"></line>
                    </svg>
                </span>
                <input id="appointmentSearchInput" type="search" class="appointment-filter__input form-control" placeholder="Search by name, ID, or email..." aria-label="Search appointments">
            </div>

            <div class="appointment-filter__select-wrap appointment-filter__select-wrap--center col-12 col-md-6 col-xl-3">
                <span class="appointment-filter__select-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 2C7.23858 2 5 4.23858 5 7C5 10.5 10 17 10 17C10 17 15 10.5 15 7C15 4.23858 12.7614 2 10 2Z" stroke="#333" stroke-width="1.5"></path>
                        <circle cx="10" cy="7" r="2" stroke="#333" stroke-width="1.5"></circle>
                    </svg>
                </span>
                <select id="appointmentCenterFilter" class="appointment-filter__select form-select" aria-label="Filter by center" name="center">
                    <option value="">Filter By Center</option>
                    @foreach ($centerOptions as $center)
                        <option value="{{ $center }}">{{ $center }}</option>
                    @endforeach
                </select>
                <span class="appointment-filter__chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </span>
            </div>

            <div class="appointment-filter__select-wrap appointment-filter__select-wrap--status col-12 col-md-6 col-xl-2">
                <span class="appointment-filter__select-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 3C10 3 5 9 5 13C5 15.7614 7.23858 18 10 18C12.7614 18 15 15.7614 15 13C15 9 10 3 10 3Z" stroke="#b60c0c" stroke-width="1.5"></path>
                    </svg>
                </span>
                <select id="appointmentStatusFilter" class="appointment-filter__select form-select" aria-label="Filter by status" name="status">
                    <option value="">All Status</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="pending">Pending</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="rescheduled">Rescheduled</option>
                </select>
                <span class="appointment-filter__chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </span>
            </div>
        </form>

        <section class="appointment-table" aria-label="Appointment list">
            <div class="appointment-table-scroll table-responsive">
                <div class="appointment-table__head" role="rowgroup">
                    <div class="appointment-table__head-cell">Appointment ID</div>
                    <div class="appointment-table__head-cell">Donor</div>
                    <div class="appointment-table__head-cell">Date &amp; Time</div>
                    <div class="appointment-table__head-cell">Center</div>
                    <div class="appointment-table__head-cell">Status</div>
                    <div class="appointment-table__head-cell">Actions</div>
                </div>

                <div class="appointment-table__body" role="rowgroup" id="appointmentTableBody">
                    <div class="appointment-row" role="row">
                        <div class="appointment-cell"><span class="appointment-id">Loading...</span></div>
                        <div class="appointment-cell"><span class="appointment-donor__name">Fetching appointments</span><span class="appointment-donor__meta">Please wait...</span></div>
                        <div class="appointment-cell">-</div>
                        <div class="appointment-cell appointment-center">-</div>
                        <div class="appointment-cell">-</div>
                        <div class="appointment-cell appointment-actions">-</div>
                    </div>
                </div>
            </div>

            <div class="appointment-pagination" aria-label="Pagination">
                <span class="appointment-pagination__info" id="appointmentPaginationInfo">Showing 0 to 0 of 0 appointments</span>
                <div class="appointment-pagination__pages" id="appointmentPaginationPages"></div>
            </div>
        </section>
    </section>
</main>
@endsection

@push('admin_scripts')
<script>
    (function () {
        var payload = (window.AdminPageData && window.AdminPageData.appointmentManagement)
            ? window.AdminPageData.appointmentManagement
            : {};

        var listUrl = payload.api && payload.api.listUrl ? payload.api.listUrl : '';
        var searchInput = document.getElementById('appointmentSearchInput');
        var centerFilter = document.getElementById('appointmentCenterFilter');
        var statusFilter = document.getElementById('appointmentStatusFilter');
        var tableBody = document.getElementById('appointmentTableBody');
        var paginationInfo = document.getElementById('appointmentPaginationInfo');
        var paginationPages = document.getElementById('appointmentPaginationPages');

        var statConfirmed = document.getElementById('appointmentStatConfirmed');
        var statPending = document.getElementById('appointmentStatPending');
        var statCancelled = document.getElementById('appointmentStatCancelled');
        var statRescheduled = document.getElementById('appointmentStatRescheduled');

        var state = {
            page: 1,
            perPage: 10,
            search: '',
            center: '',
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

        function formatNumber(value) {
            var numeric = Number(value || 0);
            return numeric.toLocaleString('en-US');
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

        function formatTime(value) {
            if (!value) {
                return '-';
            }

            var parsed = new Date('1970-01-01T' + String(value));
            if (Number.isNaN(parsed.getTime())) {
                return String(value);
            }

            return parsed.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function normalizeStatus(value) {
            var status = String(value || '').toLowerCase();
            if (['confirmed', 'pending', 'cancelled', 'rescheduled'].indexOf(status) !== -1) {
                return status;
            }
            return 'pending';
        }

        function statusLabel(value) {
            var status = normalizeStatus(value);
            if (status === 'confirmed') {
                return 'Confirmed';
            }
            if (status === 'cancelled') {
                return 'Cancelled';
            }
            if (status === 'rescheduled') {
                return 'Rescheduled';
            }
            return 'Pending';
        }

        function statusClass(value) {
            return 'appointment-badge--' + normalizeStatus(value);
        }

        function renderActionButtons(status) {
            var normalizedStatus = normalizeStatus(status);

            if (normalizedStatus === 'pending') {
                return ''
                    + '<button class="appointment-btn appointment-btn--approve" data-action="approve" type="button">'
                    + '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>'
                    + 'Approve'
                    + '</button>'
                    + '<button class="appointment-btn appointment-btn--reject" data-action="reject" type="button">'
                    + '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="#b60c0c" stroke-width="1.8"></circle><line x1="15" y1="9" x2="9" y2="15" stroke="#b60c0c" stroke-width="1.8" stroke-linecap="round"></line><line x1="9" y1="9" x2="15" y2="15" stroke="#b60c0c" stroke-width="1.8" stroke-linecap="round"></line></svg>'
                    + 'Reject'
                    + '</button>';
            }

            if (normalizedStatus === 'confirmed') {
                return ''
                    + '<button class="appointment-btn appointment-btn--reschedule" data-action="reschedule" type="button">'
                    + '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10a6 6 0 1 1 1.76 4.24" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round"></path><polyline points="4 14 4 10 8 10" stroke="#0063aa" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>'
                    + 'Reschedule'
                    + '</button>';
            }

            return '';
        }

        function renderRows(rows) {
            if (!tableBody) {
                return;
            }

            if (!Array.isArray(rows) || rows.length === 0) {
                tableBody.innerHTML = ''
                    + '<div class="appointment-row" role="row">'
                    + '<div class="appointment-cell"><span class="appointment-id">No records</span></div>'
                    + '<div class="appointment-cell"><span class="appointment-donor__name">No appointments found</span><span class="appointment-donor__meta">Try changing your filters.</span></div>'
                    + '<div class="appointment-cell">-</div>'
                    + '<div class="appointment-cell appointment-center">-</div>'
                    + '<div class="appointment-cell">-</div>'
                    + '<div class="appointment-cell appointment-actions"></div>'
                    + '</div>';
                return;
            }

            tableBody.innerHTML = rows.map(function (item) {
                var appointmentCode = escapeHtml(item.appointment_code || '-');
                var donorName = escapeHtml(item.donor_name || 'Unknown Donor');
                var donorCode = escapeHtml(item.donor_code || '-');
                var bloodType = escapeHtml(item.blood_type || '-');
                var donorMeta = donorCode + ' &bull; ' + bloodType;
                var dateLabel = escapeHtml(formatDate(item.appointment_date));
                var timeLabel = escapeHtml(formatTime(item.appointment_time));
                var centerLabel = escapeHtml(item.center_label || 'N/A');
                var badgeClass = statusClass(item.status);
                var badgeLabel = escapeHtml(statusLabel(item.status));
                var actions = renderActionButtons(item.status);

                return ''
                    + '<div class="appointment-row" role="row">'
                    + '<div class="appointment-cell"><span class="appointment-id">' + appointmentCode + '</span></div>'
                    + '<div class="appointment-cell"><span class="appointment-donor__name">' + donorName + '</span><span class="appointment-donor__meta">' + donorMeta + '</span></div>'
                    + '<div class="appointment-cell">'
                    + '<span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>' + dateLabel + '</span>'
                    + '<span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>' + timeLabel + '</span>'
                    + '</div>'
                    + '<div class="appointment-cell appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>' + centerLabel + '</div>'
                    + '<div class="appointment-cell"><span class="appointment-badge ' + badgeClass + '">' + badgeLabel + '</span></div>'
                    + '<div class="appointment-cell appointment-actions">' + actions + '</div>'
                    + '</div>';
            }).join('');
        }

        function updateStats(stats) {
            if (statConfirmed) {
                statConfirmed.textContent = formatNumber(stats.confirmed || 0);
            }
            if (statPending) {
                statPending.textContent = formatNumber(stats.pending || 0);
            }
            if (statCancelled) {
                statCancelled.textContent = formatNumber(stats.cancelled || 0);
            }
            if (statRescheduled) {
                statRescheduled.textContent = formatNumber(stats.rescheduled || 0);
            }
        }

        function createPageButton(label, targetPage, options) {
            var button = document.createElement('button');
            button.className = 'appointment-page-btn';
            if (options && options.active) {
                button.className += ' appointment-page-btn--active';
            }
            if (options && options.nav) {
                button.className += ' appointment-page-btn--nav';
            }

            button.type = 'button';
            button.textContent = label;
            button.setAttribute('aria-label', options && options.ariaLabel ? options.ariaLabel : ('Page ' + label));

            if (options && options.active) {
                button.setAttribute('aria-current', 'page');
            }

            if (options && options.disabled) {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            } else {
                button.dataset.page = String(targetPage);
            }

            return button;
        }

        function renderPagination(meta) {
            if (!paginationInfo || !paginationPages) {
                return;
            }

            var total = Number(meta.total || 0);
            var from = Number(meta.from || 0);
            var to = Number(meta.to || 0);
            var currentPage = Number(meta.current_page || 1);
            var lastPage = Number(meta.last_page || 1);

            paginationInfo.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total + ' appointments';
            paginationPages.innerHTML = '';

            if (total <= 0) {
                return;
            }

            paginationPages.appendChild(createPageButton('<', Math.max(1, currentPage - 1), {
                nav: true,
                disabled: currentPage <= 1,
                ariaLabel: 'Previous page'
            }));

            var start = Math.max(1, currentPage - 2);
            var end = Math.min(lastPage, start + 4);
            start = Math.max(1, end - 4);

            for (var pageIndex = start; pageIndex <= end; pageIndex += 1) {
                paginationPages.appendChild(createPageButton(String(pageIndex), pageIndex, {
                    active: pageIndex === currentPage,
                    ariaLabel: 'Page ' + pageIndex
                }));
            }

            paginationPages.appendChild(createPageButton('>', Math.min(lastPage, currentPage + 1), {
                nav: true,
                disabled: currentPage >= lastPage,
                ariaLabel: 'Next page'
            }));
        }

        function setLoadingState() {
            if (!tableBody) {
                return;
            }

            tableBody.innerHTML = ''
                + '<div class="appointment-row" role="row">'
                + '<div class="appointment-cell"><span class="appointment-id">Loading...</span></div>'
                + '<div class="appointment-cell"><span class="appointment-donor__name">Fetching appointments</span><span class="appointment-donor__meta">Please wait...</span></div>'
                + '<div class="appointment-cell">-</div>'
                + '<div class="appointment-cell appointment-center">-</div>'
                + '<div class="appointment-cell">-</div>'
                + '<div class="appointment-cell appointment-actions">-</div>'
                + '</div>';
        }

        function buildRequestUrl() {
            var url = new URL(listUrl, window.location.origin);
            var params = url.searchParams;

            params.set('page', String(state.page));
            params.set('per_page', String(state.perPage));

            if (state.search !== '') {
                params.set('search', state.search);
            }
            if (state.center !== '') {
                params.set('center', state.center);
            }
            if (state.status !== '') {
                params.set('status', state.status);
            }

            return url.toString();
        }

        function hydrateCenterFilter(centers) {
            if (!centerFilter || !Array.isArray(centers)) {
                return;
            }

            var known = {};
            Array.prototype.forEach.call(centerFilter.options, function (option) {
                known[String(option.value)] = true;
            });

            centers.forEach(function (value) {
                var center = String(value || '').trim();
                if (center === '' || known[center]) {
                    return;
                }

                var option = document.createElement('option');
                option.value = center;
                option.textContent = center;
                centerFilter.appendChild(option);
                known[center] = true;
            });
        }

        function loadAppointments() {
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
                        throw new Error('Failed to load appointments.');
                    }
                    return response.json();
                })
                .then(function (responsePayload) {
                    updateStats(responsePayload.stats || {});
                    hydrateCenterFilter((responsePayload.filters && responsePayload.filters.centers) || []);
                    renderRows(responsePayload.data || []);
                    renderPagination(responsePayload.meta || {});
                })
                .catch(function () {
                    renderRows([]);
                    if (paginationInfo) {
                        paginationInfo.textContent = 'Unable to load appointments right now.';
                    }
                });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var nextValue = String(searchInput.value || '').trim();
                clearTimeout(searchDebounceTimer);

                searchDebounceTimer = setTimeout(function () {
                    state.search = nextValue;
                    state.page = 1;
                    loadAppointments();
                }, 300);
            });
        }

        if (centerFilter) {
            centerFilter.addEventListener('change', function () {
                state.center = String(centerFilter.value || '').trim();
                state.page = 1;
                loadAppointments();
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
                state.status = String(statusFilter.value || '').trim();
                state.page = 1;
                loadAppointments();
            });
        }

        if (paginationPages) {
            paginationPages.addEventListener('click', function (event) {
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
                loadAppointments();
            });
        }

        if (tableBody) {
            tableBody.addEventListener('click', function (event) {
                var target = event.target;
                if (!target) {
                    return;
                }

                var actionButton = target.closest('.appointment-btn');
                if (!actionButton) {
                    return;
                }

                event.preventDefault();
            });
        }

        document.querySelectorAll('.appointment-view-btn').forEach(function (element) {
            element.addEventListener('click', function (event) {
                event.preventDefault();
            });
        });

        hydrateCenterFilter((payload.filters && payload.filters.centers) || []);
        loadAppointments();
    })();
</script>
@endpush
