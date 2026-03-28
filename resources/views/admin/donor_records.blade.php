@extends('layouts.admin')

@section('title', 'eDonate - Donation Records')
@section('admin_page_class', 'admin-donor-records-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')

@section('header_title', 'Donation Records')
@section('header_subtitle', 'View donation history and eligibility logs')

@section('header_actions')
  <button class="btn-export" type="button">
    <svg class="btn-export__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M12 3v10M12 3l-3.5 3.5M12 3l3.5 3.5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M5 17v2a1 1 0 001 1h12a1 1 0 001-1v-2" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
    Export Records
  </button>
@endsection

@section('admin_page_data')
@json([
  'page' => 'donation-records',
])
@endsection

@section('main_content')

  <!-- ======================== -->
  <!-- MAIN                     -->
  <!-- ======================== -->
  <div class="main">

    <!-- PAGE BODY -->
    <main class="page-body">

      <!-- STAT CARDS -->
      <section class="stats-grid" aria-label="Statistics overview">

        <!-- Total Donations -->
        <div class="stat-card stat-card--red">
          <div class="stat-card__header">
            <span class="stat-card__label">Total Donations</span>
            <!-- Blood drop icon red -->
            <svg class="stat-card__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M12 3 C12 3 5 11 5 16 C5 19.87 8.13 23 12 23 C15.87 23 19 19.87 19 16 C19 11 12 3 12 3Z" fill="#b60c0c"/>
            </svg>
          </div>
          <div class="stat-card__value">1</div>
          <div class="stat-card__note">All time</div>
        </div>

        <!-- This Month -->
        <div class="stat-card stat-card--green">
          <div class="stat-card__header">
            <span class="stat-card__label">This Month</span>
            <!-- Calendar icon -->
            <svg class="stat-card__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <rect x="3" y="4" width="18" height="17" rx="2" stroke="#129800" stroke-width="1.5"/>
              <path d="M3 9h18" stroke="#129800" stroke-width="1.5"/>
              <path d="M8 2v4M16 2v4" stroke="#129800" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="stat-card__value">1</div>
          <div class="stat-card__note">+12% vs last month</div>
        </div>

        <!-- Active Donors -->
        <div class="stat-card stat-card--blue">
          <div class="stat-card__header">
            <span class="stat-card__label">Active Donors</span>
            <!-- Person icon -->
            <svg class="stat-card__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <circle cx="12" cy="8" r="4" stroke="#0063aa" stroke-width="1.5"/>
              <path d="M4 20c0-4 3.58-7 8-7s8 3 8 7" stroke="#0063aa" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="stat-card__value">1</div>
          <div class="stat-card__note">With donation history</div>
        </div>

        <!-- Average Volume -->
        <div class="stat-card stat-card--gold">
          <div class="stat-card__header">
            <span class="stat-card__label">Average Volume</span>
            <!-- Blood drop gold -->
            <svg class="stat-card__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M12 3 C12 3 5 11 5 16 C5 19.87 8.13 23 12 23 C15.87 23 19 19.87 19 16 C19 11 12 3 12 3Z" fill="#ab9400"/>
            </svg>
          </div>
          <div class="stat-card__value">450 mL</div>
          <div class="stat-card__note">Per donation</div>
        </div>
      </section>

      <!-- FILTER BAR -->
      <div class="filter-bar">
        <!-- Search -->
        <div class="filter-bar__search">
          <svg class="filter-bar__search-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="11" cy="11" r="7" stroke="#555" stroke-width="1.8"/>
            <path d="M16.5 16.5L21 21" stroke="#555" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <input type="search" placeholder="Search by donor name or record ID..." aria-label="Search by donor name or record ID" />
        </div>

        <!-- Blood Type filter -->
        <div class="filter-bar__select-wrap">
          <svg class="filter-bar__select-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 3 C12 3 5 11 5 16 C5 19.87 8.13 23 12 23 C15.87 23 19 19.87 19 16 C19 11 12 3 12 3Z" fill="#b60c0c"/>
          </svg>
          <select class="filter-bar__select" aria-label="Filter by blood type">
            <option>All Blood Types</option>
            <option>A+</option>
            <option>A-</option>
            <option>B+</option>
            <option>B-</option>
            <option>AB+</option>
            <option>AB-</option>
            <option>O+</option>
            <option>O-</option>
          </select>
          <svg class="filter-bar__chevron" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke="#333" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>

        <!-- Status filter -->
        <div class="filter-bar__select-wrap">
          <svg class="filter-bar__select-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M4 6h16M7 12h10M10 18h4" stroke="#555" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <select class="filter-bar__select" aria-label="Filter by status">
            <option>All Status</option>
            <option>Completed</option>
            <option>Pending</option>
            <option>Deferred</option>
          </select>
          <svg class="filter-bar__chevron" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke="#333" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      </div>

      <!-- RECORDS TABLE -->
      <section class="records-section" aria-label="Donation records table">
        <div class="records-table-wrap">
          <table class="records-table">
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
            <tbody>
              <!-- Row 1 – sample data -->
              <tr>
                <td>DR1-0</td>
                <td class="td-name">John Smith</td>
                <td>
                  <div class="td-blood">
                    <svg class="td-blood-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M12 3 C12 3 5 11 5 16 C5 19.87 8.13 23 12 23 C15.87 23 19 19.87 19 16 C19 11 12 3 12 3Z" fill="#b60c0c"/>
                    </svg>
                    O+
                  </div>
                </td>
                <td>
                  <div class="td-date">
                    <svg class="td-date-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <rect x="3" y="4" width="18" height="17" rx="2" stroke="#333" stroke-width="1.5"/>
                      <path d="M3 9h18" stroke="#333" stroke-width="1.5"/>
                      <path d="M8 2v4M16 2v4" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    Jan 10, 2026
                  </div>
                </td>
                <td>
                  <div class="td-location">
                    <svg class="td-location-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="#555"/>
                      <circle cx="12" cy="9" r="2.5" fill="white"/>
                    </svg>
                    Lipa Medix
                  </div>
                </td>
                <td>450 mL</td>
                <td><span class="badge badge--completed">Completed</span></td>
                <td>
                  <div class="td-date">
                    <svg class="td-date-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <rect x="3" y="4" width="18" height="17" rx="2" stroke="#333" stroke-width="1.5"/>
                      <path d="M3 9h18" stroke="#333" stroke-width="1.5"/>
                      <path d="M8 2v4M16 2v4" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    April 10, 2026
                  </div>
                </td>
              </tr>
              <!-- Rows 2–9: empty rows to match design -->
              <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
              <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
              <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
              <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
              <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
              <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
              <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
              <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            </tbody>
          </table>
        </div>

        <!-- FOOTER -->
        <div class="records-footer">
          <p class="records-footer__info">Showing 1 to 9 of 9 donors</p>
          <nav class="pagination" aria-label="Table pagination">
            <button class="pagination__btn" type="button" aria-label="Previous page">
              <svg width="10" height="14" viewBox="0 0 10 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M8 1L2 7L8 13" stroke="#333" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <button class="pagination__btn pagination__btn--active" type="button" aria-label="Page 1" aria-current="page">1</button>
            <button class="pagination__btn" type="button" aria-label="Next page">
              <svg width="10" height="14" viewBox="0 0 10 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M2 1L8 7L2 13" stroke="#333" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </nav>
        </div>
      </section>

    </main>
  </div><!-- /.main -->
@endsection
