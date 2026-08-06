@extends('layouts.admin')

@section('title', 'eDonate - Donation Records')
@section('admin_page_class', 'admin-donor-records-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('render_default_hamburger', 'false')

@section('header_title', 'Donation Records')
@section('header_subtitle', 'View donation history and eligibility logs')

@section('header_slot')
  <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
    <span class="hamburger__bar"></span>
    <span class="hamburger__bar"></span>
    <span class="hamburger__bar"></span>
  </button>
@endsection

@section('header_actions')
  <button class="btn-export btn" type="button">
    <svg class="btn-export__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M12 3v10M12 3l-3.5 3.5M12 3l3.5 3.5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M5 17v2a1 1 0 001 1h12a1 1 0 001-1v-2" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
    Export Records
  </button>
@endsection

@section('admin_page_data')
{!! json_encode([
  'page' => 'donation-records',
  'donationRecords' => $donationRecordsPayload ?? [
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
  $bloodTypeOptions = data_get($donationRecordsPayload ?? [], 'filters.bloodTypes', []);
@endphp

  <!-- ======================== -->
  <!-- MAIN                     -->
  <!-- ======================== -->
  <div class="main container-fluid px-0">

    <!-- PAGE BODY -->
    <main class="page-body container-fluid py-3">

      <!-- STAT CARDS -->
      <section class="stats-grid row g-3" aria-label="Statistics overview">

        <!-- Total Donations -->
        <div class="col-6 col-xl-3">
        <div class="stat-card stat-card--red h-100">
          <div class="stat-card__header">
            <span class="stat-card__label">Total Donations</span>
            <!-- Blood drop icon red -->
            <svg class="stat-card__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M12 3 C12 3 5 11 5 16 C5 19.87 8.13 23 12 23 C15.87 23 19 19.87 19 16 C19 11 12 3 12 3Z" fill="#b60c0c"/>
            </svg>
          </div>
          <div class="stat-card__value" id="donationStatTotalDonations">0</div>
          <div class="stat-card__note">All time</div>
        </div>
        </div>

        <!-- This Month -->
        <div class="col-6 col-xl-3">
        <div class="stat-card stat-card--green h-100">
          <div class="stat-card__header">
            <span class="stat-card__label">This Month</span>
            <!-- Calendar icon -->
            <svg class="stat-card__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <rect x="3" y="4" width="18" height="17" rx="2" stroke="#129800" stroke-width="1.5"/>
              <path d="M3 9h18" stroke="#129800" stroke-width="1.5"/>
              <path d="M8 2v4M16 2v4" stroke="#129800" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="stat-card__value" id="donationStatThisMonth">0</div>
          <div class="stat-card__note">Current month</div>
        </div>
        </div>

        <!-- Active Donors -->
        <div class="col-6 col-xl-3">
        <div class="stat-card stat-card--blue h-100">
          <div class="stat-card__header">
            <span class="stat-card__label">Active Donors</span>
            <!-- Person icon -->
            <svg class="stat-card__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <circle cx="12" cy="8" r="4" stroke="#0063aa" stroke-width="1.5"/>
              <path d="M4 20c0-4 3.58-7 8-7s8 3 8 7" stroke="#0063aa" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="stat-card__value" id="donationStatActiveDonors">0</div>
          <div class="stat-card__note">With donation history</div>
        </div>
        </div>

        <!-- Average Volume -->
        <div class="col-6 col-xl-3">
        <div class="stat-card stat-card--gold h-100">
          <div class="stat-card__header">
            <span class="stat-card__label">Average Volume</span>
            <!-- Blood drop gold -->
            <svg class="stat-card__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M12 3 C12 3 5 11 5 16 C5 19.87 8.13 23 12 23 C15.87 23 19 19.87 19 16 C19 11 12 3 12 3Z" fill="#ab9400"/>
            </svg>
          </div>
          <div class="stat-card__value" id="donationStatAverageVolume">0 mL</div>
          <div class="stat-card__note">Per donation</div>
        </div>
        </div>
      </section>

      <!-- FILTER BAR -->
      <div class="filter-bar row g-3 align-items-center">
        <!-- Search -->
        <div class="filter-bar__search col-12 col-lg">
          <svg class="filter-bar__search-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="11" cy="11" r="7" stroke="#555" stroke-width="1.8"/>
            <path d="M16.5 16.5L21 21" stroke="#555" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <input id="donationRecordsSearchInput" class="form-control" type="search" placeholder="Search by donor name or record ID..." aria-label="Search by donor name or record ID" />
        </div>

        <!-- Blood Type filter -->
        <div class="filter-bar__select-wrap col-12 col-md-6 col-xl-3">
          <svg class="filter-bar__select-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 3 C12 3 5 11 5 16 C5 19.87 8.13 23 12 23 C15.87 23 19 19.87 19 16 C19 11 12 3 12 3Z" fill="#b60c0c"/>
          </svg>
          <select id="donationRecordsBloodTypeFilter" class="filter-bar__select form-select" aria-label="Filter by blood type">
            <option value="">All Blood Types</option>
            @foreach ($bloodTypeOptions as $type)
              <option value="{{ $type }}">{{ $type }}</option>
            @endforeach
          </select>
          <svg class="filter-bar__chevron" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke="#333" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>

        <!-- Status filter -->
        <div class="filter-bar__select-wrap col-12 col-md-6 col-xl-3">
          <svg class="filter-bar__select-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M4 6h16M7 12h10M10 18h4" stroke="#555" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <select id="donationRecordsStatusFilter" class="filter-bar__select form-select" aria-label="Filter by status">
            <option value="">All Status</option>
            <option value="completed">Completed</option>
            <option value="pending">Pending</option>
            <option value="deferred">Deferred</option>
          </select>
          <svg class="filter-bar__chevron" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke="#333" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      </div>

      <!-- RECORDS TABLE -->
      <section class="records-section" aria-label="Donation records table">
        <div class="records-table-wrap table-responsive">
          <table class="records-table table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Record ID</th>
                <th scope="col">Name</th>
                <th scope="col">Blood Type</th>
                <th scope="col">Date</th>
                <th scope="col">Location</th>
                <th scope="col">Volume</th>
                <th scope="col">Status</th>
                <th scope="col">Next Eligible</th>
              </tr>
            </thead>
            <tbody id="donationRecordsTableBody">
              <tr><td colspan="8">Loading...</td></tr>
            </tbody>
          </table>
        </div>

        <!-- FOOTER -->
        <div class="records-footer">
          <p class="records-footer__info" id="donationRecordsPaginationInfo">Showing 0 to 0 of 0 records</p>
          <nav class="pagination" id="donationRecordsPaginationPages" aria-label="Table pagination"></nav>
        </div>
      </section>

    </main>
  </div><!-- /.main -->

@push('admin_scripts')
<script>
  (function () {
    'use strict';

    var payload = window.AdminPageData || {};
    var config = payload.donationRecords || {};
    var listUrl = (config.api && config.api.listUrl) ? String(config.api.listUrl) : '';

    var statTotal = document.getElementById('donationStatTotalDonations');
    var statMonth = document.getElementById('donationStatThisMonth');
    var statActive = document.getElementById('donationStatActiveDonors');
    var statAvg = document.getElementById('donationStatAverageVolume');

    var searchInput = document.getElementById('donationRecordsSearchInput');
    var bloodTypeFilter = document.getElementById('donationRecordsBloodTypeFilter');
    var statusFilter = document.getElementById('donationRecordsStatusFilter');

    var tableBody = document.getElementById('donationRecordsTableBody');
    var paginationInfo = document.getElementById('donationRecordsPaginationInfo');
    var paginationPages = document.getElementById('donationRecordsPaginationPages');

    var state = {
      page: 1,
      perPage: 9,
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
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
      var num = Number(value || 0);
      if (Number.isNaN(num)) {
        num = 0;
      }
      return num.toLocaleString();
    }

    function normalizeStatus(value) {
      var status = String(value || '').toLowerCase();
      if (['completed', 'pending', 'deferred'].indexOf(status) !== -1) {
        return status;
      }
      return 'pending';
    }

    function statusLabel(value) {
      var status = normalizeStatus(value);
      if (status === 'completed') {
        return 'Completed';
      }
      if (status === 'deferred') {
        return 'Deferred';
      }
      return 'Pending';
    }

    function badgeClass(value) {
      return 'badge--' + normalizeStatus(value);
    }

    function formatDate(value) {
      var raw = String(value || '').trim();
      if (raw === '') {
        return '-';
      }

      var date = new Date(raw + 'T00:00:00');
      if (Number.isNaN(date.getTime())) {
        return raw;
      }

      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatVolume(volumeMl) {
      var volume = Number(volumeMl || 0);
      if (Number.isNaN(volume) || volume <= 0) {
        return '-';
      }
      return Math.round(volume) + ' mL';
    }

    function setLoadingState() {
      if (!tableBody) {
        return;
      }
      tableBody.innerHTML = '<tr><td colspan="8">Loading...</td></tr>';
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
        var type = String(value || '').trim();
        if (type === '' || known[type]) {
          return;
        }

        var option = document.createElement('option');
        option.value = type;
        option.textContent = type;
        bloodTypeFilter.appendChild(option);
        known[type] = true;
      });
    }

    function updateStats(stats) {
      if (statTotal) {
        statTotal.textContent = formatNumber(stats.total_donations || 0);
      }
      if (statMonth) {
        statMonth.textContent = formatNumber(stats.this_month || 0);
      }
      if (statActive) {
        statActive.textContent = formatNumber(stats.active_donors || 0);
      }
      if (statAvg) {
        statAvg.textContent = formatVolume(stats.average_volume_ml || 0);
      }
    }

    function renderRows(rows) {
      if (!tableBody) {
        return;
      }

      if (!Array.isArray(rows) || rows.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8">No donation records found.</td></tr>';
        return;
      }

      tableBody.innerHTML = rows.map(function (item) {
        var recordCode = escapeHtml(item.record_code || '-');
        var donorName = escapeHtml(item.donor_name || 'Unknown Donor');
        var bloodType = escapeHtml(item.blood_type || '-');
        var donationDate = escapeHtml(formatDate(item.donation_date));
        var centerLabel = escapeHtml(item.center_label || 'N/A');
        var volumeLabel = escapeHtml(formatVolume(item.volume_ml));
        var badge = '<span class="badge ' + badgeClass(item.status) + '">' + escapeHtml(statusLabel(item.status)) + '</span>';
        var nextEligible = escapeHtml(formatDate(item.next_eligible_date));

        return ''
          + '<tr>'
          + '<td>' + recordCode + '</td>'
          + '<td class="td-name">' + donorName + '</td>'
          + '<td><div class="td-blood">'
          + '<svg class="td-blood-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
          + '<path d="M12 3 C12 3 5 11 5 16 C5 19.87 8.13 23 12 23 C15.87 23 19 19.87 19 16 C19 11 12 3 12 3Z" fill="#b60c0c"/>'
          + '</svg>'
          + bloodType
          + '</div></td>'
          + '<td><div class="td-date">'
          + '<svg class="td-date-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
          + '<rect x="3" y="4" width="18" height="17" rx="2" stroke="#333" stroke-width="1.5"/>'
          + '<path d="M3 9h18" stroke="#333" stroke-width="1.5"/>'
          + '<path d="M8 2v4M16 2v4" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>'
          + '</svg>'
          + donationDate
          + '</div></td>'
          + '<td><div class="td-location">'
          + '<svg class="td-location-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
          + '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="#555"/>'
          + '<circle cx="12" cy="9" r="2.5" fill="white"/>'
          + '</svg>'
          + centerLabel
          + '</div></td>'
          + '<td>' + volumeLabel + '</td>'
          + '<td>' + badge + '</td>'
          + '<td><div class="td-date">'
          + '<svg class="td-date-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
          + '<rect x="3" y="4" width="18" height="17" rx="2" stroke="#333" stroke-width="1.5"/>'
          + '<path d="M3 9h18" stroke="#333" stroke-width="1.5"/>'
          + '<path d="M8 2v4M16 2v4" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>'
          + '</svg>'
          + nextEligible
          + '</div></td>'
          + '</tr>';
      }).join('');
    }

    function createNavButton(direction, targetPage, disabled) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'pagination__btn';
      button.setAttribute('aria-label', direction === 'prev' ? 'Previous page' : 'Next page');

      button.innerHTML = direction === 'prev'
        ? '<svg width="10" height="14" viewBox="0 0 10 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 1L2 7L8 13" stroke="#333" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        : '<svg width="10" height="14" viewBox="0 0 10 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M2 1L8 7L2 13" stroke="#333" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

      if (disabled) {
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
      } else {
        button.dataset.page = String(targetPage);
      }

      return button;
    }

    function createPageButton(pageIndex, active) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'pagination__btn' + (active ? ' pagination__btn--active' : '');
      button.textContent = String(pageIndex);
      button.setAttribute('aria-label', 'Page ' + pageIndex);

      if (active) {
        button.setAttribute('aria-current', 'page');
      } else {
        button.dataset.page = String(pageIndex);
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

      paginationInfo.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total + ' records';
      paginationPages.innerHTML = '';

      if (total <= 0) {
        return;
      }

      paginationPages.appendChild(createNavButton('prev', Math.max(1, currentPage - 1), currentPage <= 1));

      var start = Math.max(1, currentPage - 2);
      var end = Math.min(lastPage, start + 4);
      start = Math.max(1, end - 4);

      for (var pageIndex = start; pageIndex <= end; pageIndex += 1) {
        paginationPages.appendChild(createPageButton(pageIndex, pageIndex === currentPage));
      }

      paginationPages.appendChild(createNavButton('next', Math.min(lastPage, currentPage + 1), currentPage >= lastPage));
    }

    function loadRecords() {
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
            throw new Error('Failed to load donation records.');
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
            paginationInfo.textContent = 'Unable to load records right now.';
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
          loadRecords();
        }, 300);
      });
    }

    if (bloodTypeFilter) {
      bloodTypeFilter.addEventListener('change', function () {
        state.bloodType = String(bloodTypeFilter.value || '').trim();
        state.page = 1;
        loadRecords();
      });
    }

    if (statusFilter) {
      statusFilter.addEventListener('change', function () {
        state.status = String(statusFilter.value || '').trim();
        state.page = 1;
        loadRecords();
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
        loadRecords();
      });
    }

    hydrateBloodTypeFilter((config.filters && config.filters.bloodTypes) || []);
    loadRecords();
  })();
</script>
@endpush
@endsection
