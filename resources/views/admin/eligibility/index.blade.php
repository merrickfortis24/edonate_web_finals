@extends('layouts.admin')

@section('title', 'eDonate - Eligibility Review')
@section('admin_page_class', 'admin-eligibility-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_id', 'eligibilitySidebar')
@section('sidebar_aria_label', 'Admin navigation')
@section('sidebar_nav_aria_label', 'Primary navigation')
@section('sidebar_link_mode', 'link')
@section('sidebar_open_class', 'is-open')
@section('overlay_id', 'eligibilityOverlay')
@section('overlay_class', 'eligibility-overlay overlay')
@section('overlay_open_class', 'is-visible')
@section('hamburger_id', 'eligibilityHamburger')
@section('render_default_hamburger', 'false')

@section('header_title', 'Eligibility Review')
@section('header_subtitle', 'Review and approve donor eligibility submissions')

@section('header_slot')
    <button class="hamburger" id="eligibilityHamburger" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="eligibilitySidebar">
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
    </button>
@endsection

@section('admin_page_data')
{!! json_encode([
    'page' => 'eligibility-review',
    'eligibilityPayload' => $eligibilityPayload ?? [
        'api' => [
            'listUrl'       => '',
            'detailBaseUrl' => '',
            'reviewBaseUrl' => '',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
<main class="eligibility-main container-fluid px-0">
    <section class="eligibility-content container-fluid py-3" aria-label="Eligibility content">
        <!-- Stats Cards -->
        <div class="eligibility-stats row g-3" aria-label="Eligibility summary">
            <div class="col-6 col-xl-3">
                <article class="stat-card eligibility-stat eligibility-stat--total h-100">
                    <p class="eligibility-stat__label">Total Submissions</p>
                    <p class="eligibility-stat__value" id="eligibilityStatTotal">0</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card eligibility-stat eligibility-stat--pending h-100">
                    <p class="eligibility-stat__label">Pending Review</p>
                    <p class="eligibility-stat__value" id="eligibilityStatPending">0</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card eligibility-stat eligibility-stat--eligible h-100">
                    <p class="eligibility-stat__label">Eligible</p>
                    <p class="eligibility-stat__value" id="eligibilityStatEligible">0</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card eligibility-stat eligibility-stat--ineligible h-100">
                    <p class="eligibility-stat__label">Not Eligible</p>
                    <p class="eligibility-stat__value" id="eligibilityStatIneligible">0</p>
                </article>
            </div>
        </div>

        <!-- Filters -->
        <form class="eligibility-filter row g-3 align-items-center" role="search" aria-label="Filter submissions" action="#" method="get" onsubmit="return false;">
            <div class="eligibility-filter__search col-12 col-lg">
                <span class="eligibility-filter__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="16.5" y1="16.5" x2="22" y2="22"></line>
                    </svg>
                </span>
                <input id="eligibilitySearchInput" type="search" class="eligibility-filter__input form-control" placeholder="Search by name, ID, or blood type..." aria-label="Search submissions">
            </div>

            <div class="eligibility-filter__select-wrap col-12 col-md-6 col-xl-3">
                <span class="eligibility-filter__select-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707l-4.414 4.414a1 1 0 00-.293.707v5.172a1 1 0 01-.414.828l-2 1.5a1 1 0 01-1.586-.828v-6.172a1 1 0 00-.293-.707L3.293 6.707A1 1 0 013 6V4z" stroke="currentColor" stroke-width="1.5"></path>
                    </svg>
                </span>
                <select id="eligibilityStatusFilter" class="eligibility-filter__select form-select" aria-label="Filter by status" name="status">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending Review</option>
                    <option value="eligible">Eligible</option>
                    <option value="not_eligible">Not Eligible</option>
                </select>
            </div>

            <div class="eligibility-filter__select-wrap col-12 col-md-6 col-xl-2">
                <select id="eligibilityBloodTypeFilter" class="eligibility-filter__select form-select" aria-label="Filter by blood type" name="blood_type">
                    <option value="">All Blood Types</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                </select>
            </div>

            <div class="eligibility-filter__select-wrap col-12 col-md-6 col-xl-2">
                <select id="eligibilityLocationFilter" class="eligibility-filter__select form-select" aria-label="Filter by location" name="location">
                    <option value="">All Locations</option>
                    @foreach(\App\Models\Location::select('city')->distinct()->get() as $loc)
                        @if($loc->city)
                            <option value="{{ $loc->city }}">{{ $loc->city }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <button type="button" class="btn btn-primary" id="eligibilityRefreshBtn" aria-label="Refresh submissions">
                <span aria-hidden="true">↻</span> Refresh
            </button>
        </form>

        <!-- Table -->
        <div class="eligibility-table-wrapper mt-4">
            <table class="eligibility-table table table-hover" role="grid" aria-label="Eligibility submissions table">
                <thead>
                    <tr>
                        <th scope="col">Donor</th>
                        <th scope="col">Blood Type</th>
                        <th scope="col">Status</th>
                        <th scope="col">Submitted</th>
                        <th scope="col">Reviewed By</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="eligibilityTableBody">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Loading submissions...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav class="eligibility-pagination mt-4" aria-label="Table pagination">
            <div class="row align-items-center">
                <div class="col-auto">
                    <span id="eligibilityPaginationInfo" class="text-muted">Loading...</span>
                </div>
                <div class="col-auto ms-auto">
                    <div class="btn-group" role="group" aria-label="Pagination controls">
                        <button type="button" class="btn btn-outline-secondary" id="eligibilityPrevBtn" aria-label="Previous page">Previous</button>
                        <button type="button" class="btn btn-outline-secondary" id="eligibilityNextBtn" aria-label="Next page">Next</button>
                    </div>
                </div>
            </div>
        </nav>
    </section>
</main>

<!-- Review Modal -->
<div class="modal fade" id="eligibilityReviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">Review Eligibility Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="reviewModalContent">
                    <div class="text-center text-muted py-4">Loading submission details...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="reviewRejectBtn">Not Eligible</button>
                <button type="button" class="btn btn-success" id="reviewApproveBtn">Eligible</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" role="region" aria-live="polite" aria-atomic="true"></div>

@endsection

@push('admin_scripts')
<script src="{{ asset('js/admin/eligibility-review.js') }}"></script>
@endpush
