@extends('layouts.admin')

@section('title', 'eDonate - Donation Records / Check-in')
@section('admin_page_class', 'admin-donor-records-page')
@section('header_title', 'Donation Records / Check-in')
@section('header_subtitle', 'Manage donor arrival, completion, on-site deferral, and no-show records')

@section('admin_page_data')
{!! json_encode([
  'page' => 'donation-records',
  'donationRecords' => $donationRecordsPayload ?? [
    'api' => [
      'listUrl' => '',
      'checkInUrlTemplate' => '',
      'completeUrlTemplate' => '',
      'deferUrlTemplate' => '',
      'noShowUrlTemplate' => '',
    ],
    'filters' => [
      'bloodTypes' => [],
      'events' => [],
      'centers' => [],
    ],
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
@php
  $bloodTypeOptions = data_get($donationRecordsPayload ?? [], 'filters.bloodTypes', []);
  $verificationBloodTypeOptions = data_get($donationRecordsPayload ?? [], 'filters.verificationBloodTypes', []);
  $canVerifyBloodType = (bool) data_get($donationRecordsPayload ?? [], 'canVerifyBloodType', false);
  $eventOptions = data_get($donationRecordsPayload ?? [], 'filters.events', []);
  $centerOptions = data_get($donationRecordsPayload ?? [], 'filters.centers', []);
@endphp

<div class="main container-fluid px-0">
  <main class="page-body container-fluid py-3">
    <section class="stats-grid row g-3" aria-label="Donation processing overview">
      <div class="col-6 col-xl-3">
        <div class="stat-card stat-card--red h-100">
          <div class="stat-card__header"><span class="stat-card__label">Expected Today</span></div>
          <div class="stat-card__value" id="processingStatExpected">0</div>
          <div class="stat-card__note">Confirmed appointments</div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card stat-card--blue h-100">
          <div class="stat-card__header"><span class="stat-card__label">Checked In</span></div>
          <div class="stat-card__value" id="processingStatCheckedIn">0</div>
          <div class="stat-card__note">Awaiting outcome today</div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card stat-card--green h-100">
          <div class="stat-card__header"><span class="stat-card__label">Completed</span></div>
          <div class="stat-card__value" id="processingStatCompletedMonth">0</div>
          <div class="stat-card__note">This month</div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card stat-card--gold h-100">
          <div class="stat-card__header"><span class="stat-card__label">No-shows</span></div>
          <div class="stat-card__value" id="processingStatNoShows">0</div>
          <div class="stat-card__note">Recorded appointments</div>
        </div>
      </div>
    </section>

    <div class="filter-bar row g-3 align-items-center">
      <div class="filter-bar__search col-12 col-xl">
        <input id="processingSearchInput" class="form-control" type="search" placeholder="Search donor, appointment, event, or remarks" aria-label="Search donation processing" />
      </div>
      <div class="filter-bar__select-wrap col-12 col-md-6 col-xl-2">
        <select id="processingStatusFilter" class="filter-bar__select form-select" aria-label="Filter by status">
          <option value="">All Status</option>
          <option value="confirmed">Confirmed</option>
          <option value="checked_in">Checked In</option>
          <option value="completed">Completed</option>
          <option value="deferred_on_site">Deferred On Site</option>
          <option value="no_show">No-show</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="filter-bar__select-wrap col-12 col-md-6 col-xl-2">
        <select id="processingBloodFilter" class="filter-bar__select form-select" aria-label="Filter by blood type">
          <option value="">All Blood Types</option>
          @foreach ($bloodTypeOptions as $type)
            <option value="{{ $type }}">{{ $type }}</option>
          @endforeach
        </select>
      </div>
      <div class="filter-bar__select-wrap col-12 col-md-6 col-xl-2">
        <select id="processingEventFilter" class="filter-bar__select form-select" aria-label="Filter by event">
          <option value="">All Events</option>
          @foreach ($eventOptions as $event)
            <option value="{{ $event['event_id'] }}">{{ $event['title'] }} - {{ $event['event_date'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="filter-bar__select-wrap col-12 col-md-6 col-xl-2">
        <select id="processingCenterFilter" class="filter-bar__select form-select" aria-label="Filter by center">
          <option value="">All Centers</option>
          @foreach ($centerOptions as $center)
            <option value="{{ $center }}">{{ $center }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-6 col-xl-2">
        <input id="processingDateFilter" class="form-control" type="date" aria-label="Filter by appointment date" />
      </div>
    </div>

    <section class="records-section" aria-label="Donation processing table">
      <div class="records-table-wrap table-responsive">
        <table class="records-table table align-middle mb-0">
          <thead>
            <tr>
              <th scope="col">Appointment</th>
              <th scope="col">Donor</th>
              <th scope="col">Event / Center</th>
              <th scope="col">Schedule</th>
              <th scope="col">Eligibility</th>
              <th scope="col">Donation Record</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="processingTableBody">
            <tr><td colspan="8">Loading...</td></tr>
          </tbody>
        </table>
      </div>
      <div class="records-footer">
        <p class="records-footer__info" id="processingPaginationInfo">Showing 0 to 0 of 0 records</p>
        <nav class="pagination" id="processingPaginationPages" aria-label="Table pagination"></nav>
      </div>
    </section>
  </main>
</div>

<div class="modal fade" id="completeDonationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="completeDonationForm">
      <div class="modal-header">
        <h5 class="modal-title">Complete Donation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="completeAppointmentId" />
        <div class="mb-3">
          <label class="form-label" for="completeBloodUnits">Blood Units</label>
          <input class="form-control" id="completeBloodUnits" type="number" min="1" max="10" value="1" required />
        </div>
        <div class="mb-3">
          <label class="form-label" for="completeDonationDate">Donation Date</label>
          <input class="form-control" id="completeDonationDate" type="date" />
        </div>
        <div class="mb-3">
          <div class="small text-muted mb-1">Current Blood Type</div>
          <div class="fw-semibold" id="completeCurrentBloodType">Not yet determined</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="completeVerifiedBloodType">Verified Blood Type</label>
          <select class="form-select" id="completeVerifiedBloodType" @disabled(! $canVerifyBloodType)>
            <option value="">Not yet determined</option>
            @foreach ($verificationBloodTypeOptions as $type)
              <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
            @endforeach
          </select>
          <div class="form-text">{{ $canVerifyBloodType ? 'Only enter a confirmed laboratory result.' : 'Only an administrator may record a verified blood type.' }}</div>
        </div>
        <div class="mb-3 d-none" id="completeBloodTypeChangeFields">
          <div class="alert alert-warning py-2 small mb-2">This result differs from the donor's current verified blood type. Confirm the correction and record its reason.</div>
          <div class="form-check mb-2">
            <input class="form-check-input" id="completeConfirmBloodTypeChange" type="checkbox" value="1" />
            <label class="form-check-label" for="completeConfirmBloodTypeChange">I confirm this verified blood type correction.</label>
          </div>
          <label class="form-label" for="completeBloodTypeChangeReason">Reason for change</label>
          <textarea class="form-control" id="completeBloodTypeChangeReason" rows="2" maxlength="1000"></textarea>
        </div>
        <div>
          <label class="form-label" for="completeRemarks">Remarks</label>
          <textarea class="form-control" id="completeRemarks" rows="3" maxlength="1000"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Completion</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="deferDonationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="deferDonationForm">
      <div class="modal-header">
        <h5 class="modal-title">Defer On Site</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="deferAppointmentId" />
        <div class="mb-3">
          <label class="form-label" for="deferReason">Deferral Reason</label>
          <textarea class="form-control" id="deferReason" rows="3" minlength="8" maxlength="1000" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label" for="deferNextEligibleDate">Next Eligible Date</label>
          <input class="form-control" id="deferNextEligibleDate" type="date" />
        </div>
        <div>
          <label class="form-label" for="deferRemarks">Remarks</label>
          <textarea class="form-control" id="deferRemarks" rows="2" maxlength="1000"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-warning">Save Deferral</button>
      </div>
    </form>
  </div>
</div>

@push('admin_scripts')
<script>
  (function () {
    'use strict';

    var config = (window.AdminPageData && window.AdminPageData.donationRecords) || {};
    var api = config.api || {};
    var state = { page: 1, perPage: 10, rows: {} };
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';
    var tableBody = document.getElementById('processingTableBody');
    var paginationInfo = document.getElementById('processingPaginationInfo');
    var paginationPages = document.getElementById('processingPaginationPages');
    var completeModalElement = document.getElementById('completeDonationModal');
    var deferModalElement = document.getElementById('deferDonationModal');
    var completeModal = window.bootstrap && completeModalElement ? new window.bootstrap.Modal(completeModalElement) : null;
    var deferModal = window.bootstrap && deferModalElement ? new window.bootstrap.Modal(deferModalElement) : null;

    function qs(id) { return document.getElementById(id); }
    function esc(value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
      });
    }
    function fmtDate(value) {
      if (!value) return '-';
      var date = new Date(String(value).replace(' ', 'T'));
      return Number.isNaN(date.getTime()) ? esc(value) : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }
    function fmtTime(value) {
      if (!value) return '-';
      var parts = String(value).split(':');
      return parts.length >= 2 ? parts[0] + ':' + parts[1] : esc(value);
    }
    function statusLabel(status) {
      return {
        confirmed: 'Confirmed',
        checked_in: 'Checked In',
        completed: 'Completed',
        deferred_on_site: 'Deferred On Site',
        no_show: 'No-show',
        cancelled: 'Cancelled'
      }[status] || 'Pending';
    }
    function statusClass(status) {
      return {
        confirmed: 'status-badge--warning',
        checked_in: 'status-badge--info',
        completed: 'status-badge--success',
        deferred_on_site: 'status-badge--warning',
        no_show: 'status-badge--danger',
        cancelled: 'status-badge--muted'
      }[status] || 'status-badge--muted';
    }
    function actionUrl(template, id) {
      return String(template || '').replace('__ID__', encodeURIComponent(id));
    }
    function collectFilters() {
      return {
        page: state.page,
        per_page: state.perPage,
        search: qs('processingSearchInput').value.trim(),
        status: qs('processingStatusFilter').value,
        blood_type: qs('processingBloodFilter').value,
        event_id: qs('processingEventFilter').value,
        center: qs('processingCenterFilter').value,
        date: qs('processingDateFilter').value
      };
    }
    function setStats(stats) {
      qs('processingStatExpected').textContent = stats.expected_today || 0;
      qs('processingStatCheckedIn').textContent = stats.checked_in_today || 0;
      qs('processingStatCompletedMonth').textContent = stats.completed_month || 0;
      qs('processingStatNoShows').textContent = stats.no_show || 0;
    }
    function renderActions(row) {
      var buttons = [];
      if (row.actions && row.actions.can_check_in) {
        buttons.push('<button class="btn btn-sm btn-primary" data-action="check-in" data-id="' + row.appointment_id + '">Check In</button>');
      }
      if (row.actions && row.actions.can_complete) {
        buttons.push('<button class="btn btn-sm btn-success" data-action="complete" data-id="' + row.appointment_id + '">Complete</button>');
      }
      if (row.actions && row.actions.can_defer) {
        buttons.push('<button class="btn btn-sm btn-warning" data-action="defer" data-id="' + row.appointment_id + '">Defer</button>');
      }
      if (row.actions && row.actions.can_no_show) {
        buttons.push('<button class="btn btn-sm btn-outline-danger" data-action="no-show" data-id="' + row.appointment_id + '">No-show</button>');
      }
      return buttons.length ? buttons.join(' ') : '<span class="text-muted">No actions</span>';
    }
    function renderRows(rows) {
      state.rows = {};
      if (!rows.length) {
        tableBody.innerHTML = '<tr><td colspan="8">No matching appointments.</td></tr>';
        return;
      }
      tableBody.innerHTML = rows.map(function (row) {
        state.rows[String(row.appointment_id)] = row;
        var record = row.donation_code
          ? '<span class="fw-semibold">' + esc(row.donation_code) + '</span><span class="d-block text-muted small">' + esc(row.donation_status || '-') + (row.blood_units !== null ? ' · ' + esc(row.blood_units) + ' unit(s)' : '') + '</span><span class="d-block text-muted small">Verified type: ' + esc(row.verified_blood_type || 'Not yet determined') + '</span>'
          : '<span class="text-muted">Not recorded</span>';
        var note = row.deferred_reason || row.remarks || '';
        return '<tr>'
          + '<td><span class="fw-semibold">' + esc(row.appointment_code) + '</span><span class="d-block text-muted small">Booked ' + fmtDate(row.booked_at) + '</span></td>'
          + '<td><span class="fw-semibold">' + esc(row.donor_name) + '</span><span class="d-block text-muted small">' + esc(row.donor_email || '-') + '</span><span class="d-block text-muted small">' + esc(row.blood_type || '-') + '</span></td>'
          + '<td><span class="fw-semibold">' + esc(row.event_title || 'Legacy appointment') + '</span><span class="d-block text-muted small">' + esc(row.center_label) + '</span></td>'
          + '<td><span class="fw-semibold">' + fmtDate(row.appointment_date) + '</span><span class="d-block text-muted small">' + fmtTime(row.appointment_time) + '</span></td>'
          + '<td><span class="d-block">' + esc(row.eligibility_status || 'unknown') + '</span><span class="d-block text-muted small">Verify: ' + esc(row.verification_status || 'unverified') + '</span><span class="d-block text-muted small">Next: ' + fmtDate(row.next_eligible_date) + '</span></td>'
          + '<td>' + record + (note ? '<span class="d-block text-muted small">' + esc(note) + '</span>' : '') + '</td>'
          + '<td><span class="status-badge ' + statusClass(row.status) + '">' + statusLabel(row.status) + '</span></td>'
          + '<td class="text-end">' + renderActions(row) + '</td>'
          + '</tr>';
      }).join('');
    }
    function renderPagination(meta) {
      paginationInfo.textContent = 'Showing ' + (meta.from || 0) + ' to ' + (meta.to || 0) + ' of ' + (meta.total || 0) + ' records';
      var pages = [];
      for (var i = 1; i <= (meta.last_page || 1); i += 1) {
        pages.push('<button class="pagination__page ' + (i === meta.current_page ? 'is-active' : '') + '" data-page="' + i + '" type="button">' + i + '</button>');
      }
      paginationPages.innerHTML = pages.join('');
    }
    function loadRows() {
      if (!api.listUrl) return;
      var params = new URLSearchParams(collectFilters());
      fetch(api.listUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          renderRows(payload.data || []);
          renderPagination(payload.meta || {});
          setStats(payload.stats || {});
        })
        .catch(function () {
          tableBody.innerHTML = '<tr><td colspan="8">Unable to load donation processing records.</td></tr>';
        });
    }
    function sendPatch(url, payload) {
      return fetch(url, {
        method: 'PATCH',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token
        },
        body: JSON.stringify(payload || {})
      }).then(function (response) {
        return response.json().then(function (body) {
          if (!response.ok) throw body;
          return body;
        });
      });
    }
    function showError(error) {
      var message = error && error.message ? error.message : 'The request could not be completed.';
      if (error && error.errors) {
        var firstKey = Object.keys(error.errors)[0];
        if (firstKey && error.errors[firstKey][0]) message = error.errors[firstKey][0];
      }
      window.alert(message);
    }
    tableBody.addEventListener('click', function (event) {
      var button = event.target.closest('button[data-action]');
      if (!button) return;
      var id = button.getAttribute('data-id');
      var action = button.getAttribute('data-action');
      if (action === 'check-in') {
        sendPatch(actionUrl(api.checkInUrlTemplate, id)).then(loadRows).catch(showError);
      } else if (action === 'complete') {
        var row = state.rows[String(id)] || {};
        qs('completeAppointmentId').value = id;
        qs('completeDonationDate').value = new Date().toISOString().slice(0, 10);
        qs('completeBloodUnits').value = 1;
        qs('completeRemarks').value = '';
        qs('completeVerifiedBloodType').value = '';
        qs('completeConfirmBloodTypeChange').checked = false;
        qs('completeBloodTypeChangeReason').value = '';
        qs('completeBloodTypeChangeFields').classList.add('d-none');
        var currentType = row.blood_type || 'Not yet determined';
        var currentStatus = String(row.blood_type_status || 'not_yet_determined').replace(/_/g, ' ');
        qs('completeCurrentBloodType').textContent = currentType + ' (' + currentStatus + ')';
        qs('completeVerifiedBloodType').setAttribute('data-current-id', row.blood_type_id || '');
        qs('completeVerifiedBloodType').setAttribute('data-current-status', row.blood_type_status || 'not_yet_determined');
        completeModal ? completeModal.show() : null;
      } else if (action === 'defer') {
        qs('deferAppointmentId').value = id;
        qs('deferReason').value = '';
        qs('deferNextEligibleDate').value = '';
        qs('deferRemarks').value = '';
        deferModal ? deferModal.show() : null;
      } else if (action === 'no-show' && window.confirm('Mark this appointment as no-show?')) {
        sendPatch(actionUrl(api.noShowUrlTemplate, id)).then(loadRows).catch(showError);
      }
    });
    qs('completeDonationForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var id = qs('completeAppointmentId').value;
      sendPatch(actionUrl(api.completeUrlTemplate, id), {
        blood_units: qs('completeBloodUnits').value,
        donation_date: qs('completeDonationDate').value,
        verified_blood_type_id: qs('completeVerifiedBloodType').value || null,
        confirm_blood_type_change: qs('completeConfirmBloodTypeChange').checked,
        blood_type_change_reason: qs('completeBloodTypeChangeReason').value,
        remarks: qs('completeRemarks').value
      }).then(function () {
        completeModal ? completeModal.hide() : null;
        loadRows();
      }).catch(showError);
    });
    qs('completeVerifiedBloodType').addEventListener('change', function () {
      var isDifferentVerifiedType = this.value !== ''
        && this.getAttribute('data-current-status') === 'verified'
        && this.value !== this.getAttribute('data-current-id');
      qs('completeBloodTypeChangeFields').classList.toggle('d-none', !isDifferentVerifiedType);
      if (!isDifferentVerifiedType) {
        qs('completeConfirmBloodTypeChange').checked = false;
        qs('completeBloodTypeChangeReason').value = '';
      }
    });
    qs('deferDonationForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var id = qs('deferAppointmentId').value;
      sendPatch(actionUrl(api.deferUrlTemplate, id), {
        deferred_reason: qs('deferReason').value,
        next_eligible_date: qs('deferNextEligibleDate').value,
        remarks: qs('deferRemarks').value
      }).then(function () {
        deferModal ? deferModal.hide() : null;
        loadRows();
      }).catch(showError);
    });
    paginationPages.addEventListener('click', function (event) {
      var button = event.target.closest('button[data-page]');
      if (!button) return;
      state.page = parseInt(button.getAttribute('data-page'), 10) || 1;
      loadRows();
    });
    ['processingSearchInput', 'processingStatusFilter', 'processingBloodFilter', 'processingEventFilter', 'processingCenterFilter', 'processingDateFilter'].forEach(function (id) {
      var element = qs(id);
      if (!element) return;
      element.addEventListener(id === 'processingSearchInput' ? 'input' : 'change', function () {
        state.page = 1;
        window.clearTimeout(element._timer);
        element._timer = window.setTimeout(loadRows, id === 'processingSearchInput' ? 250 : 0);
      });
    });
    loadRows();
  })();
</script>
@endpush
@endsection
