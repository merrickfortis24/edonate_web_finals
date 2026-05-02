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
    <button class="hamburger" id="eligibilityHamburger" aria-label="Toggle navigation menu" aria-expanded="false"
        aria-controls="eligibilitySidebar">
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
                'listUrl' => '',
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
                    <article class="stat-card eligibility-stat eligibility-stat--approved h-100">
                        <p class="eligibility-stat__label">Approved</p>
                        <p class="eligibility-stat__value" id="eligibilityStatApproved">0</p>
                    </article>
                </div>
                <div class="col-6 col-xl-3">
                    <article class="stat-card eligibility-stat eligibility-stat--declined h-100">
                        <p class="eligibility-stat__label">Declined</p>
                        <p class="eligibility-stat__value" id="eligibilityStatDeclined">0</p>
                    </article>
                </div>
            </div>

            <!-- Filters -->
            <form class="eligibility-filter row g-3 align-items-center" role="search" aria-label="Filter submissions"
                action="#" method="get" onsubmit="return false;">
                <div class="eligibility-filter__search col-12 col-lg">
                    <span class="eligibility-filter__search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <line x1="16.5" y1="16.5" x2="22" y2="22"></line>
                        </svg>
                    </span>
                    <input id="eligibilitySearchInput" type="search" class="eligibility-filter__input form-control"
                        placeholder="Search by name, ID, or blood type..." aria-label="Search submissions">
                </div>

                <div class="eligibility-filter__select-wrap col-12 col-md-6 col-xl-2">
                    <select id="eligibilityStatusFilter" class="eligibility-filter__select form-select"
                        aria-label="Filter by status" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="declined">Declined</option>
                    </select>
                </div>

                <div class="eligibility-filter__select-wrap col-12 col-md-6 col-xl-2">
                    <select id="eligibilityBloodTypeFilter" class="eligibility-filter__select form-select"
                        aria-label="Filter by blood type" name="blood_type">
                        <option value="">All Blood Types</option>
                        @foreach(\App\Models\BloodType::orderBy('blood_type')->get() as $bt)
                            <option value="{{ $bt->blood_type }}">{{ $bt->blood_type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="eligibility-filter__select-wrap col-12 col-md-6 col-xl-2">
                    <select id="eligibilityLocationFilter" class="eligibility-filter__select form-select"
                        aria-label="Filter by location" name="location">
                        <option value="">All Locations</option>
                        @foreach(\App\Models\Location::select('city')->distinct()->whereNotNull('city')->get() as $loc)
                            <option value="{{ $loc->city }}">{{ $loc->city }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="button" class="btn btn-primary" id="eligibilityRefreshBtn" aria-label="Refresh submissions">
                    <span aria-hidden="true">↻</span> Refresh
                </button>
            </form>

            <!-- Table -->
            <div class="eligibility-table-wrapper mt-4 table-responsive">
                <table class="eligibility-table table table-hover" role="grid" aria-label="Eligibility submissions table">
                    <thead>
                        <tr>
                            <th scope="col">Donor</th>
                            <th scope="col">Blood Type</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Donation</th>
                            <th scope="col">Next Eligible</th>
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
                            <button type="button" class="btn btn-outline-secondary" id="eligibilityPrevBtn"
                                aria-label="Previous page">Previous</button>
                            <button type="button" class="btn btn-outline-secondary" id="eligibilityNextBtn"
                                aria-label="Next page">Next</button>
                        </div>
                    </div>
                </div>
            </nav>
        </section>
    </main>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="eligibilityReviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" id="reviewModalHeader">
                    <h5 class="modal-title" id="reviewModalLabel">Review Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Action banner -->
                    <div class="alert d-none mb-3" id="reviewActionBanner" role="alert">
                        <strong id="reviewActionText"></strong>
                    </div>

                    <!-- Donor details -->
                    <div id="reviewModalContent">
                        <div class="text-center text-muted py-4">Loading submission details...</div>
                    </div>

                    <!-- Review notes -->
                    <div class="mt-3 d-none" id="reviewNotesWrapper">
                        <label for="reviewNotesField" class="form-label fw-semibold">Review Notes (optional)</label>
                        <textarea id="reviewNotesField" class="form-control" rows="3"
                            placeholder="Add notes for this review..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn d-none" id="reviewConfirmBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('admin_scripts')
    <script src="{{ asset('js/admin/eligibility-review.js') }}"></script>
@endpush