@extends('layouts.admin')

@section('title', 'eDonate - Appointment Management')
@section('admin_page_class', 'admin-appointments-page')
@section('header_title', 'Appointment Management')
@section('header_subtitle', 'Track auto-confirmed appointments and manage operational exceptions')

@section('header_actions')
    <div class="appointment-header__views" role="group" aria-label="Appointment view mode">
        <span class="appointment-view-btn appointment-view-btn--active" role="status" aria-label="Current appointment view: List View">List View</span>
    </div>
@endsection

@section('admin_page_data')
{!! json_encode([
    'page' => 'appointment-management',
        'appointmentManagement' => $appointmentManagementPayload ?? [
            'api' => [
                'listUrl' => '',
                'donationProcessingUrl' => '',
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
                    <p class="appointment-stat__label">Legacy Pending</p>
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

        <div class="appointment-filter row g-3 align-items-center" role="search" aria-label="Filter appointments">
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
                    <option value="checked_in">Checked In</option>
                    <option value="completed">Completed</option>
                    <option value="deferred_on_site">Deferred On Site</option>
                    <option value="no_show">No Show</option>
                </select>
                <span class="appointment-filter__chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </span>
            </div>
        </div>

        <div class="card appointment-table-card border-0 shadow-sm" aria-label="Appointment list">
            <div class="card-header bg-transparent d-flex align-items-center">
                <h2 class="h5 mb-0">Appointment Records</h2>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive appointment-table-wrapper"
                     tabindex="0"
                     aria-label="Scrollable appointment records table">
                    <table class="admin-standard-table admin-standard-table--appointments table table-bordered table-hover table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Appointment ID</th>
                                <th scope="col">Donor</th>
                                <th scope="col">Date &amp; Time</th>
                                <th scope="col">Center</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="appointmentTableBody">
                            <tr class="appointment-data-row">
                                <td><span class="appointment-id">Loading...</span></td>
                                <td><span class="appointment-donor__name">Fetching appointments</span><span class="appointment-donor__meta">Please wait...</span></td>
                                <td>-</td>
                                <td class="appointment-center">-</td>
                                <td>-</td>
                                <td class="appointment-actions">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-transparent p-0 border-0">
                <div class="appointment-pagination admin-pagination admin-pagination--js" aria-label="Table pagination">
                    <span class="admin-pagination__info appointment-pagination__info" id="appointmentPaginationInfo">Showing 0 to 0 of 0 entries</span>
                    <nav class="admin-pagination__links appointment-pagination__pages" id="appointmentPaginationPages" aria-label="Pagination links"></nav>
                </div>
            </div>
        </div>
    </section>
</main>

{{-- ── Complete Donation Modal ── --}}
{{-- Donation completion and rescheduling are handled by their canonical workflows. --}}
@if(false)
<div class="modal fade" id="completeModal" tabindex="-1" role="dialog" aria-labelledby="completeModalTitle" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none; border-radius:20px; overflow:hidden;">

            <div class="modal-header" style="background:#129800; border:none; padding:18px 24px;">
                <h5 class="modal-title" id="completeModalTitle" style="color:#fff; font-weight:700; font-size:18px; display:flex; align-items:center; gap:10px;">
                    <svg viewBox="0 0 24 24" fill="none" style="width:20px;height:20px;" aria-hidden="true">
                        <path d="M20 6L9 17l-5-5" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Complete Donation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter:brightness(0) invert(1);"></button>
            </div>

            <div class="modal-body" style="padding:24px; display:flex; flex-direction:column; gap:20px;">

                {{-- Appointment info --}}
                <div style="background:var(--bs-success-bg-subtle); border:1px solid var(--bs-success-border-subtle); border-radius:12px; padding:14px 16px;">
                    <p style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:#129800; margin-bottom:6px;">Completing Appointment</p>
                    <p class="complete-info-code" style="font-size:15px; font-weight:700; color:#111; margin:0;">—</p>
                    <p class="complete-info-donor" style="font-size:13px; color:#555; margin-top:3px;">—</p>
                </div>

                {{-- Blood units input --}}
                <div style="display:flex; flex-direction:column; gap:7px;">
                    <label for="completeBloodUnits" style="font-size:13px; font-weight:600; color:var(--bs-body-color);">
                        Blood Units Donated <span style="color:#b60c0c;">*</span>
                    </label>
                    <input
                        type="number"
                        id="completeBloodUnits"
                        min="1"
                        max="10"
                        step="1"
                        placeholder="e.g. 1"
                        class="form-control"
                        style="height:44px; padding:0 14px; border-radius:10px; font-size:15px; font-weight:500; outline:none; width:100%;"
                    />
                    <span style="font-size:11px; color:var(--bs-secondary-color);">Standard whole blood donation = 1 unit (450 mL)</span>
                </div>

                {{-- Next eligible date preview --}}
                <div id="completeEligiblePreview" style="display:none; background:var(--bs-warning-bg-subtle); border:1px solid var(--bs-warning-border-subtle); border-radius:10px; padding:12px 14px;">
                    <p style="font-size:12px; font-weight:600; color:var(--bs-warning-text-emphasis); margin:0;">
                        📅 Next eligible donation date: <span id="completeNextEligible" style="font-weight:700;">—</span>
                    </p>
                </div>

                {{-- Error --}}
                <div id="completeError" style="display:none; background:var(--bs-danger-bg-subtle); border:1px solid var(--bs-danger-border-subtle); border-radius:10px; padding:13px 15px; font-size:13px; color:var(--bs-danger-text-emphasis); font-weight:500;">
                    <span id="completeErrorText"></span>
                </div>

            </div>

            <div class="modal-footer" style="padding:16px 24px; border-top:1px solid var(--bs-border-color); gap:10px; justify-content:flex-end;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="height:42px; padding:0 22px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer;">
                    Cancel
                </button>
                <button type="button" id="completeConfirmBtn" style="height:42px; padding:0 24px; border-radius:10px; border:none; background:#129800; font-size:14px; font-weight:600; color:#fff; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                    <span class="complete-spinner" style="display:none; width:15px; height:15px; border:2px solid rgba(255,255,255,0.35); border-top-color:#fff; border-radius:50%; animation:spin 0.65s linear infinite;"></span>
                    <span class="complete-label">Confirm Donation</span>
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ── Reschedule Modal ── --}}
<div class="modal fade reschedule-modal"
     id="rescheduleModal"
     tabindex="-1"
     role="dialog"
     aria-labelledby="rescheduleModalTitle"
     aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="rescheduleModalTitle">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M4 10a6 6 0 1 1 1.76 4.24" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/>
                        <polyline points="4 14 4 10 8 10" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Reschedule Appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                {{-- Current appointment summary (populated by JS) --}}
                <div class="reschedule-current-info">
                    <p class="reschedule-current-info__eyebrow">Currently scheduled</p>
                    <p class="reschedule-current-info__code" id="rescheduleInfoCode">—</p>
                    <p class="reschedule-current-info__datetime" id="rescheduleInfoDatetime">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="17" rx="2" stroke="currentColor" stroke-width="1.6"/>
                            <line x1="3" y1="9" x2="21" y2="9" stroke="currentColor" stroke-width="1.6"/>
                            <line x1="8" y1="2" x2="8" y2="6"  stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                        <span id="rescheduleInfoDatetimeText">—</span>
                    </p>
                </div>

                {{-- Date + Time pickers --}}
                <div class="reschedule-fields-row">
                    <div class="reschedule-field">
                        <label class="reschedule-field__label" for="rescheduleDate">
                            New Date<span class="reschedule-field__required" aria-hidden="true"> *</span>
                        </label>
                        <input
                            type="date"
                            id="rescheduleDate"
                            class="reschedule-field__input"
                            autocomplete="off"
                            aria-required="true"
                            aria-describedby="rescheduleDateHint"
                        />
                        <span class="reschedule-field__hint" id="rescheduleDateHint">Cannot be a past date</span>
                    </div>

                    <div class="reschedule-field">
                        <label class="reschedule-field__label" for="rescheduleTime">
                            New Time<span class="reschedule-field__required" aria-hidden="true"> *</span>
                        </label>
                        <input
                            type="time"
                            id="rescheduleTime"
                            class="reschedule-field__input"
                            autocomplete="off"
                            aria-required="true"
                        />
                        <span class="reschedule-field__hint">Use 24-hour format</span>
                    </div>
                </div>

                {{-- Inline error (hidden until needed) --}}
                <div class="reschedule-error" id="rescheduleError" role="alert" aria-live="polite" style="display:none;">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                        <line x1="12" y1="8"  x2="12" y2="13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <circle cx="12" cy="16" r="0.8" fill="currentColor"/>
                    </svg>
                    <span id="rescheduleErrorText"></span>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="reschedule-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="reschedule-confirm-btn" id="rescheduleConfirmBtn" disabled>
                    <span class="rs-spinner" aria-hidden="true"></span>
                    <span class="rs-label">Confirm Reschedule</span>
                </button>
            </div>

        </div>
    </div>
</div>

{{-- Toast container --}}
<div class="rs-toast-wrap" id="rsToastWrap" aria-live="polite" aria-atomic="true"></div>
@endif

@endsection

@push('admin_scripts')
<script>
    (function () {
        var payload = (window.AdminPageData && window.AdminPageData.appointmentManagement)
            ? window.AdminPageData.appointmentManagement
            : {};

        var listUrl = payload.api && payload.api.listUrl ? payload.api.listUrl : '';
        var csrfToken = @json(csrf_token());
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
            if (['confirmed', 'pending', 'cancelled', 'rescheduled', 'checked_in', 'completed', 'deferred_on_site', 'no_show'].indexOf(status) !== -1) {
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
            if (status === 'completed') {
                return 'Completed';
            }
            if (status === 'checked_in') {
                return 'Checked In';
            }
            if (status === 'deferred_on_site') {
                return 'Deferred On Site';
            }
            if (status === 'no_show') {
                return 'No Show';
            }
            return 'Pending';
        }

        function statusClass(value) {
            return 'appointment-badge--' + normalizeStatus(value);
        }

        var donationProcessingUrl = payload.api && payload.api.donationProcessingUrl
            ? String(payload.api.donationProcessingUrl)
            : '';

        function isAppointmentDateInFuture(dateValue) {
            if (!dateValue) {
                return false;
            }

            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var date = new Date(String(dateValue) + 'T00:00:00');
            return !Number.isNaN(date.getTime()) && date > today;
        }

        function isAppointmentScheduledPast(dateValue, timeValue) {
            if (!dateValue) {
                return false;
            }

            var time = timeValue ? String(timeValue).slice(0, 8) : '23:59:59';
            var scheduled = new Date(String(dateValue) + 'T' + time);
            return !Number.isNaN(scheduled.getTime()) && scheduled <= new Date();
        }

        function renderActionButtons(status, appointmentDate, appointmentTime, appointmentId) {
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
                var actions = '';
                if (!isAppointmentDateInFuture(appointmentDate)) {
                    actions += '<button class="appointment-btn appointment-btn--approve" data-action="check-in" title="Check In Donor" aria-label="Check In Donor" type="button">'
                    + '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>'
                    + 'Check In'
                    + '</button>';
                }
                if (isAppointmentScheduledPast(appointmentDate, appointmentTime)) {
                    actions += '<button class="appointment-btn appointment-btn--no-show" data-action="no-show" title="Mark Donor as No Show" aria-label="Mark Donor as No Show" type="button">'
                        + '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"></circle><path d="M9 9l6 6M15 9l-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>'
                        + 'No Show'
                        + '</button>';
                }
                actions += '<button class="appointment-btn appointment-btn--cancel" data-action="cancel" title="Cancel Appointment" aria-label="Cancel Appointment" type="button">'
                    + '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"></circle><path d="M9 9l6 6M15 9l-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>'
                    + 'Cancel'
                    + '</button>';
                return actions;
            }

            if (normalizedStatus === 'checked_in') {
                if (!donationProcessingUrl) {
                    return '<span class="text-muted" title="Donation processing is unavailable">Processing unavailable</span>';
                }

                var processUrl = donationProcessingUrl + '?appointment_id=' + encodeURIComponent(String(appointmentId || ''));
                return '<a class="appointment-btn appointment-btn--process" href="' + processUrl + '" title="Process Donation" aria-label="Process Donation">'
                    + '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v18M3 12h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>'
                    + 'Process Donation'
                    + '</a>';
            }

            if (normalizedStatus === 'completed' || normalizedStatus === 'deferred_on_site') {
                return '<span class="text-muted" style="font-size:13px;">—</span>';
            }

            return '';
        }

        function renderRows(rows) {
            if (!tableBody) {
                return;
            }

            if (!Array.isArray(rows) || rows.length === 0) {
                tableBody.innerHTML = ''
                    + '<tr class="appointment-data-row">'
                    + '<td><span class="appointment-id">No records</span></td>'
                    + '<td><span class="appointment-donor__name">No appointments found</span><span class="appointment-donor__meta">Try changing your filters.</span></td>'
                    + '<td>-</td>'
                    + '<td>-</td>'
                    + '<td>-</td>'
                    + '<td></td>'
                    + '</tr>';
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
                var actions = renderActionButtons(item.status, item.appointment_date, item.appointment_time, item.appointment_id);

                return ''
                    + '<tr class="appointment-data-row" data-appointment-id="' + escapeHtml(item.appointment_id || '') + '" data-appointment-date="' + escapeHtml(item.appointment_date || '') + '" data-appointment-time="' + escapeHtml(item.appointment_time || '') + '">'
                    + '<td><span class="appointment-id">' + appointmentCode + '</span></td>'
                    + '<td><span class="appointment-donor__name">' + donorName + '</span><span class="appointment-donor__meta">' + donorMeta + '</span></td>'
                    + '<td>'
                    + '<span class="appointment-datetime__date"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1" stroke="#555" stroke-width="1"></rect><line x1="1" y1="4.5" x2="11" y2="4.5" stroke="#555" stroke-width="1"></line><line x1="4" y1="1" x2="4" y2="3" stroke="#555" stroke-width="1"></line><line x1="8" y1="1" x2="8" y2="3" stroke="#555" stroke-width="1"></line></svg></span>' + dateLabel + '</span>'
                    + '<span class="appointment-datetime__time"><span class="appointment-icon-sm" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#555" stroke-width="1"></circle><line x1="6" y1="3" x2="6" y2="6" stroke="#555" stroke-width="1"></line><line x1="6" y1="6" x2="8.5" y2="8" stroke="#555" stroke-width="1"></line></svg></span>' + timeLabel + '</span>'
                    + '</td>'
                    + '<td class="appointment-center"><svg viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1C4.79086 1 3 2.79086 3 5C3 7.5 7 12.5 7 12.5C7 12.5 11 7.5 11 5C11 2.79086 9.20914 1 7 1Z" stroke="#333" stroke-width="1.2"></path><circle cx="7" cy="5" r="1.5" stroke="#333" stroke-width="1.2"></circle></svg>' + centerLabel + '</td>'
                    + '<td><span class="appointment-badge ' + badgeClass + '">' + badgeLabel + '</span></td>'
                    + '<td class="appointment-actions">' + actions + '</td>'
                    + '</tr>';
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

        function renderPagination(meta) {
            if (!paginationInfo || !paginationPages) {
                return;
            }

            if (window.eDonateAdminPagination) {
                window.eDonateAdminPagination.render(paginationPages, meta, null, {
                    infoElement: paginationInfo
                });
            }
        }

        function setLoadingState() {
            if (!tableBody) {
                return;
            }

            tableBody.innerHTML = ''
                + '<tr class="appointment-data-row">'
                + '<td><span class="appointment-id">Loading...</span></td>'
                + '<td><span class="appointment-donor__name">Fetching appointments</span><span class="appointment-donor__meta">Please wait...</span></td>'
                + '<td>-</td>'
                + '<td>-</td>'
                + '<td>-</td>'
                + '<td>-</td>'
                + '</tr>';
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

        function resolveAppointmentActionsBaseUrl() {
            if (!listUrl) {
                return '';
            }

            try {
                var parsed = new URL(String(listUrl), window.location.origin);
                parsed.search = '';
                parsed.hash = '';
                return parsed.toString().replace(/\/data\/?$/, '');
            } catch (error) {
                return String(listUrl || '').replace(/\/data\/?$/, '');
            }
        }

        function performAppointmentAction(appointmentId, action, requestBody) {
            var baseUrl = resolveAppointmentActionsBaseUrl();
            if (!baseUrl) {
                return Promise.reject(new Error('Appointment action URL is unavailable.'));
            }

            var url = baseUrl.replace(/\/$/, '') + '/' + appointmentId + '/' + action;
            var options = {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                }
            };

            if (requestBody && typeof requestBody === 'object') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(requestBody);
            }

            return fetch(url, options)
                .then(function (response) {
                    return response.json()
                        .catch(function () {
                            return {};
                        })
                        .then(function (payload) {
                            if (!response.ok) {
                                throw new Error(String(payload && payload.message ? payload.message : 'Action failed.'));
                            }
                            return payload;
                        });
                });
        }

        if (tableBody) {
            tableBody.addEventListener('click', function (event) {
                var target = event.target;
                if (!target) {
                    return;
                }

                var actionButton = target.closest('button.appointment-btn[data-action]');
                if (!actionButton) {
                    return;
                }

                event.preventDefault();

                var row = actionButton.closest('.appointment-data-row');
                var appointmentId = row ? Number(row.getAttribute('data-appointment-id') || '0') : 0;
                if (!appointmentId) {
                    return;
                }

                var action = String(actionButton.dataset.action || '').trim();
                if (action === '') {
                    return;
                }

                var requestBody = null;

                if (action === 'reject') {
                    Swal.fire({
                        title: 'Reject Appointment?',
                        text: 'This cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#b60c0c',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Reject',
                        cancelButtonText: 'Cancel'
                    }).then(function (result) {
                        if (!result.isConfirmed) { return; }
                        actionButton.disabled = true;
                        performAppointmentAction(appointmentId, action, null)
                            .then(function () { loadAppointments(); })
                            .catch(function (error) {
                                Swal.fire('Error', error && error.message ? error.message : 'Action failed.', 'error');
                            })
                            .then(function () { actionButton.disabled = false; });
                    });
                    return;
                }

                if (action === 'approve') {
                    actionButton.disabled = true;
                    performAppointmentAction(appointmentId, action, null)
                        .then(function () {
                            loadAppointments();
                            Swal.fire({
                                title: 'Approved!',
                                text: 'Appointment has been confirmed.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        })
                        .catch(function (error) {
                            Swal.fire('Error', error && error.message ? error.message : 'Action failed.', 'error');
                        })
                        .then(function () { actionButton.disabled = false; });
                    return;
                }

                if (action === 'check-in') {
                    actionButton.disabled = true;
                    performAppointmentAction(appointmentId, action, null)
                        .then(function () {
                            loadAppointments();
                            Swal.fire({
                                title: 'Checked In',
                                text: 'The donor is ready for on-site donation processing.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        })
                        .catch(function (error) {
                            Swal.fire('Error', error && error.message ? error.message : 'Action failed.', 'error');
                        })
                        .then(function () { actionButton.disabled = false; });
                    return;
                }

                if (action === 'no-show' || action === 'cancel') {
                    var noShowAction = action === 'no-show';
                    var confirmOptions = {
                        title: noShowAction ? 'Mark donor as No Show?' : 'Cancel appointment?',
                        text: noShowAction
                            ? 'This appointment will be marked as not attended.'
                            : 'This will cancel the appointment without deleting its history.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: noShowAction ? '#b60c0c' : '#6c757d',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: noShowAction ? 'Mark No Show' : 'Cancel Appointment',
                        cancelButtonText: 'Keep Appointment'
                    };
                    var confirmation = typeof Swal !== 'undefined'
                        ? Swal.fire(confirmOptions)
                        : Promise.resolve({ isConfirmed: window.confirm(confirmOptions.text) });

                    confirmation.then(function (result) {
                        if (!result || !result.isConfirmed) { return; }
                        actionButton.disabled = true;
                        performAppointmentAction(appointmentId, action, null)
                            .then(function () {
                                loadAppointments();
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        title: noShowAction ? 'Marked No Show' : 'Appointment Cancelled',
                                        text: noShowAction ? 'The appointment was marked as not attended.' : 'The appointment has been cancelled.',
                                        icon: 'success',
                                        timer: 1800,
                                        showConfirmButton: false
                                    });
                                }
                            })
                            .catch(function (error) {
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire('Error', error && error.message ? error.message : 'Action failed.', 'error');
                                }
                            })
                            .then(function () { actionButton.disabled = false; });
                    });
                    return;
                }
            });
        }
        

        /* ── Reschedule Modal Controller ── */
        // Legacy appointment-page modals are intentionally disabled. Attendance
        // actions stay here; donation completion stays on Donation Processing.
        if (false) {
        var rsModal          = null;  // bootstrap.Modal instance (lazy init)
        var rsModalEl        = document.getElementById('rescheduleModal');
        var rsDateInput      = document.getElementById('rescheduleDate');
        var rsTimeInput      = document.getElementById('rescheduleTime');
        var rsConfirmBtn     = document.getElementById('rescheduleConfirmBtn');
        var rsErrorEl        = document.getElementById('rescheduleError');
        var rsErrorText      = document.getElementById('rescheduleErrorText');
        var rsInfoCode       = document.getElementById('rescheduleInfoCode');
        var rsInfoDatetime   = document.getElementById('rescheduleInfoDatetimeText');
        var rsPendingId      = 0;  // appointmentId stored when modal opens

        function getTodayString() {
            var d = new Date();
            var mm = String(d.getMonth() + 1).padStart(2, '0');
            var dd = String(d.getDate()).padStart(2, '0');
            return d.getFullYear() + '-' + mm + '-' + dd;
        }

        function getBootstrapModal() {
            if (!rsModal && rsModalEl && typeof bootstrap !== 'undefined') {
                rsModal = new bootstrap.Modal(rsModalEl, { backdrop: 'static', keyboard: false });
            }
            return rsModal;
        }

        function rsShowError(message) {
            if (!rsErrorEl || !rsErrorText) { return; }
            rsErrorText.textContent = message;
            rsErrorEl.style.display = 'flex';
        }

        function rsHideError() {
            if (!rsErrorEl) { return; }
            rsErrorEl.style.display = 'none';
            rsErrorText.textContent = '';
        }

        function rsSetLoading(isLoading) {
            if (!rsConfirmBtn) { return; }
            rsConfirmBtn.disabled = isLoading;
            rsConfirmBtn.classList.toggle('is-loading', isLoading);
        }

        function rsValidate() {
            var dateVal = rsDateInput ? rsDateInput.value.trim() : '';
            var timeVal = rsTimeInput ? rsTimeInput.value.trim() : '';

            rsDateInput && rsDateInput.classList.remove('is-invalid');
            rsTimeInput && rsTimeInput.classList.remove('is-invalid');
            rsHideError();

            if (!dateVal) {
                rsDateInput && rsDateInput.classList.add('is-invalid');
                rsShowError('Please select a new date.');
                rsDateInput && rsDateInput.focus();
                return null;
            }

            if (dateVal < getTodayString()) {
                rsDateInput && rsDateInput.classList.add('is-invalid');
                rsShowError('The selected date is in the past. Please choose today or a future date.');
                rsDateInput && rsDateInput.focus();
                return null;
            }

            if (!timeVal) {
                rsTimeInput && rsTimeInput.classList.add('is-invalid');
                rsShowError('Please select a new time.');
                rsTimeInput && rsTimeInput.focus();
                return null;
            }

            return { appointment_date: dateVal, appointment_time: timeVal };
        }

        function rsEnableConfirmWhenReady() {
            if (!rsConfirmBtn || !rsDateInput || !rsTimeInput) { return; }
            rsConfirmBtn.disabled = !(rsDateInput.value && rsTimeInput.value);
        }

        function openRescheduleModal(appointmentId, currentDate, currentTime, appointmentCode) {
            var modal = getBootstrapModal();
            if (!modal) { return; }

            rsPendingId = appointmentId;
            rsHideError();
            rsSetLoading(false);

            // Populate current-appointment info box
            if (rsInfoCode) {
                rsInfoCode.textContent = appointmentCode || ('Appointment #' + appointmentId);
            }
            if (rsInfoDatetime) {
                var dateDisplay = currentDate ? formatDate(currentDate) : '—';
                var timeDisplay = currentTime ? formatTime(currentTime) : '—';
                rsInfoDatetime.textContent = dateDisplay + ' at ' + timeDisplay;
            }

            // Pre-fill inputs and enforce min date
            var today = getTodayString();
            if (rsDateInput) {
                rsDateInput.min   = today;
                rsDateInput.value = (currentDate && currentDate >= today) ? currentDate : today;
                rsDateInput.classList.remove('is-invalid');
            }
            if (rsTimeInput) {
                rsTimeInput.value = currentTime || '08:00';
                rsTimeInput.classList.remove('is-invalid');
            }

            rsEnableConfirmWhenReady();
            modal.show();
        }

        // Enable/disable confirm button as user types
        if (rsDateInput) { rsDateInput.addEventListener('input', rsEnableConfirmWhenReady); }
        if (rsTimeInput) { rsTimeInput.addEventListener('input', rsEnableConfirmWhenReady); }

        // Clear invalid state when user corrects a field
        if (rsDateInput) {
            rsDateInput.addEventListener('change', function () {
                rsDateInput.classList.remove('is-invalid');
                rsHideError();
            });
        }
        if (rsTimeInput) {
            rsTimeInput.addEventListener('change', function () {
                rsTimeInput.classList.remove('is-invalid');
                rsHideError();
            });
        }

        // Confirm button click
        if (rsConfirmBtn) {
            rsConfirmBtn.addEventListener('click', function () {
                var body = rsValidate();
                if (!body) { return; }

                rsSetLoading(true);

                performAppointmentAction(rsPendingId, 'reschedule', body)
                    .then(function (response) {
                        var modal = getBootstrapModal();
                        if (modal) { modal.hide(); }
                        loadAppointments();
                        showRsToast(
                            'Appointment Rescheduled',
                            'New date: ' + formatDate(body.appointment_date) + ' at ' + formatTime(body.appointment_time)
                        );
                    })
                    .catch(function (error) {
                        rsSetLoading(false);
                        rsShowError(error && error.message ? error.message : 'Could not reschedule. Please try again.');
                    });
            });
        }

        // Reset state when modal is fully hidden
        if (rsModalEl) {
            rsModalEl.addEventListener('hidden.bs.modal', function () {
                rsHideError();
                rsSetLoading(false);
                rsPendingId = 0;
                if (rsDateInput) { rsDateInput.classList.remove('is-invalid'); }
                if (rsTimeInput) { rsTimeInput.classList.remove('is-invalid'); }
            });
        }

        /* ── Success Toast ── */
        var rsToastWrap = document.getElementById('rsToastWrap');

        function showRsToast(title, subtitle) {
            if (!rsToastWrap) { return; }

            var toast = document.createElement('div');
            toast.className = 'rs-toast';
            toast.innerHTML = ''
                + '<svg class="rs-toast__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
                + '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>'
                + '<path d="M8 12l3 3 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
                + '</svg>'
                + '<div class="rs-toast__body">'
                + '<div class="rs-toast__title">' + escapeHtml(title) + '</div>'
                + '<div class="rs-toast__sub">'   + escapeHtml(subtitle) + '</div>'
                + '</div>';

            rsToastWrap.appendChild(toast);

            setTimeout(function () {
                toast.classList.add('rs-toast--fade-out');
                setTimeout(function () {
                    if (toast.parentNode) { toast.parentNode.removeChild(toast); }
                }, 300);
            }, 3500);
        }

        /* ── Complete Donation Modal Controller ── */
        var completeModalEl    = document.getElementById('completeModal');
        var completeModal      = null;
        var completeConfirmBtn = document.getElementById('completeConfirmBtn');
        var completeBloodUnits = document.getElementById('completeBloodUnits');
        var completeErrorEl    = document.getElementById('completeError');
        var completeErrorText  = document.getElementById('completeErrorText');
        var completeEligiblePreview = document.getElementById('completeEligiblePreview');
        var completeNextEligible    = document.getElementById('completeNextEligible');
        var completePendingId  = 0;

        function getCompleteModal() {
            if (!completeModal && completeModalEl && typeof bootstrap !== 'undefined') {
                completeModal = new bootstrap.Modal(completeModalEl, { backdrop: 'static', keyboard: false });
            }
            return completeModal;
        }

        function openCompleteModal(appointmentId, appointmentCode, donorName) {
            var modal = getCompleteModal();
            if (!modal) { return; }

            completePendingId = appointmentId;

            var infoCode  = completeModalEl.querySelector('.complete-info-code');
            var infoDonor = completeModalEl.querySelector('.complete-info-donor');
            if (infoCode)  { infoCode.textContent  = appointmentCode || ('Appointment #' + appointmentId); }
            if (infoDonor) { infoDonor.textContent = donorName || ''; }
            if (completeBloodUnits)     { completeBloodUnits.value = ''; }
            if (completeErrorEl)        { completeErrorEl.style.display = 'none'; }
            if (completeEligiblePreview){ completeEligiblePreview.style.display = 'none'; }

            modal.show();
        }

        if (completeBloodUnits) {
            completeBloodUnits.addEventListener('input', function () {
                var units = parseInt(completeBloodUnits.value, 10);
                if (!isNaN(units) && units >= 1) {
                    var next = new Date();
                    next.setDate(next.getDate() + 56);
                    var formatted = next.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    if (completeNextEligible)    { completeNextEligible.textContent = formatted; }
                    if (completeEligiblePreview) { completeEligiblePreview.style.display = 'block'; }
                } else {
                    if (completeEligiblePreview) { completeEligiblePreview.style.display = 'none'; }
                }
            });
        }

        if (completeConfirmBtn) {
            completeConfirmBtn.addEventListener('click', function () {
                var units = parseInt(completeBloodUnits ? completeBloodUnits.value : '', 10);
                if (isNaN(units) || units < 1) {
                    if (completeErrorEl)   { completeErrorEl.style.display = 'block'; }
                    if (completeErrorText) { completeErrorText.textContent = 'Please enter a valid number of blood units (minimum 1).'; }
                    if (completeBloodUnits){ completeBloodUnits.focus(); }
                    return;
                }
                if (completeErrorEl) { completeErrorEl.style.display = 'none'; }

                completeConfirmBtn.disabled = true;
                var spinner = completeConfirmBtn.querySelector('.complete-spinner');
                var label   = completeConfirmBtn.querySelector('.complete-label');
                if (spinner) { spinner.style.display = 'inline-block'; }
                if (label)   { label.textContent = 'Processing...'; }

                performAppointmentAction(completePendingId, 'complete', { blood_units: units })
                    .then(function () {
                        var modal = getCompleteModal();
                        if (modal) { modal.hide(); }
                        loadAppointments();

                        var nextDate = new Date();
                        nextDate.setDate(nextDate.getDate() + 56);
                        var nextFormatted = nextDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

                        Swal.fire({
                            title: 'Donation Completed!',
                            html: 'Donation record has been created.<br><br>'
                                + '<strong>Next eligible donation date:</strong><br>'
                                + '<span style="color:#129800; font-size:16px; font-weight:700;">' + nextFormatted + '</span>',
                            icon: 'success',
                            timer: 4000,
                            showConfirmButton: false
                        });
                    })
                    .catch(function (error) {
                        if (completeErrorEl)   { completeErrorEl.style.display = 'block'; }
                        if (completeErrorText) { completeErrorText.textContent = error && error.message ? error.message : 'Action failed.'; }
                    })
                    .then(function () {
                        completeConfirmBtn.disabled = false;
                        if (spinner) { spinner.style.display = 'none'; }
                        if (label)   { label.textContent = 'Confirm Donation'; }
                    });
            });
        }

        if (completeModalEl) {
            completeModalEl.addEventListener('hidden.bs.modal', function () {
                completePendingId = 0;
                if (completeBloodUnits)      { completeBloodUnits.value = ''; }
                if (completeErrorEl)         { completeErrorEl.style.display = 'none'; }
                if (completeEligiblePreview) { completeEligiblePreview.style.display = 'none'; }
                if (completeConfirmBtn)      { completeConfirmBtn.disabled = false; }
            });
        }

        }

        hydrateCenterFilter((payload.filters && payload.filters.centers) || []);
        loadAppointments();
    })();
</script>
@endpush
