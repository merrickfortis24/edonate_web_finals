@extends('layouts.admin')

@section('title', 'eDonate - Eligibility Review')
@section('admin_page_class', 'admin-eligibility-page')
@section('layout_wrapper_class', 'app')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('sidebar_link_mode', 'link')
@section('render_default_hamburger', 'false')

@section('header_title', 'Eligibility Review')
@section('header_subtitle', 'Review donor eligibility outcomes and decide only for records needing manual review')

@section('header_slot')
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
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
    <main class="main eligibility-main container-fluid px-0">
        <section class="content eligibility-content container-fluid py-3" aria-label="Eligibility content">
            <div class="eligibility-stats row g-3" aria-label="Eligibility summary">
                <div class="col-6 col-xl">
                    <article class="stat-card eligibility-stat eligibility-stat--total h-100">
                        <p class="eligibility-stat__label">Total</p>
                        <p class="eligibility-stat__value" id="eligibilityStatTotal">0</p>
                    </article>
                </div>
                <div class="col-6 col-xl">
                    <article class="stat-card eligibility-stat eligibility-stat--pending h-100">
                        <p class="eligibility-stat__label">For Review</p>
                        <p class="eligibility-stat__value" id="eligibilityStatForReview">0</p>
                    </article>
                </div>
                <div class="col-6 col-xl">
                    <article class="stat-card eligibility-stat eligibility-stat--approved h-100">
                        <p class="eligibility-stat__label">Eligible</p>
                        <p class="eligibility-stat__value" id="eligibilityStatEligible">0</p>
                    </article>
                </div>
                <div class="col-6 col-xl">
                    <article class="stat-card eligibility-stat eligibility-stat--pending h-100">
                        <p class="eligibility-stat__label">Deferred</p>
                        <p class="eligibility-stat__value" id="eligibilityStatDeferred">0</p>
                    </article>
                </div>
                <div class="col-6 col-xl">
                    <article class="stat-card eligibility-stat eligibility-stat--declined h-100">
                        <p class="eligibility-stat__label">Not Eligible</p>
                        <p class="eligibility-stat__value" id="eligibilityStatNotEligible">0</p>
                    </article>
                </div>
            </div>

            <form class="eligibility-filter row g-3 align-items-center" role="search" aria-label="Filter submissions" action="#" method="get" onsubmit="return false;">
                <div class="eligibility-filter__search col-12 col-lg">
                    <span class="eligibility-filter__search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <line x1="16.5" y1="16.5" x2="22" y2="22"></line>
                        </svg>
                    </span>
                    <input id="eligibilitySearchInput" type="search" class="eligibility-filter__input form-control" placeholder="Search donor name, email, contact..." aria-label="Search submissions">
                </div>

                <div class="eligibility-filter__select-wrap col-12 col-md-6 col-xl-2">
                    <select id="eligibilityStatusFilter" class="eligibility-filter__select form-select" aria-label="Filter by status" name="status">
                        <option value="">All Statuses</option>
                        <option value="eligible">Eligible</option>
                        <option value="not_eligible">Not Eligible</option>
                        <option value="temporary_deferred">Temporary Deferred</option>
                        <option value="for_review">For Review</option>
                    </select>
                </div>

                <div class="eligibility-filter__select-wrap col-12 col-md-6 col-xl-2">
                    <select id="eligibilitySourceFilter" class="eligibility-filter__select form-select" aria-label="Filter by source" name="source">
                        <option value="">All Sources</option>
                        <option value="auto">Auto</option>
                        <option value="admin_review">Admin Review</option>
                    </select>
                </div>

                <button type="button" class="btn btn-primary col-12 col-md-auto" id="eligibilityRefreshBtn" aria-label="Refresh submissions">
                    <span aria-hidden="true">Refresh</span>
                </button>
            </form>

            <div class="eligibility-table-wrapper mt-4 table-responsive">
                <table class="eligibility-table table table-hover" role="grid" aria-label="Eligibility submissions table">
                    <thead>
                        <tr>
                            <th scope="col">Donor Name</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Status</th>
                            <th scope="col">Source</th>
                            <th scope="col">Result Reason</th>
                            <th scope="col">Recommendation</th>
                            <th scope="col">Next Eligible Date</th>
                            <th scope="col">Reviewed By</th>
                            <th scope="col">Reviewed At</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eligibilityTableBody">
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">Loading submissions...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

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

    <div class="modal fade" id="eligibilityReviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" id="reviewModalHeader">
                    <h5 class="modal-title" id="reviewModalLabel">Eligibility Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert d-none mb-3" id="reviewActionBanner" role="alert">
                        <strong id="reviewActionText"></strong>
                    </div>

                    <div id="reviewModalContent">
                        <div class="text-center text-muted py-4">Loading submission details...</div>
                    </div>

                    <div class="mt-3 d-none" id="reviewDecisionWrapper">
                        <label for="reviewDecisionSelect" class="form-label fw-semibold">Decision</label>
                        <select id="reviewDecisionSelect" class="form-select">
                            <option value="eligible">Approve as Eligible</option>
                            <option value="not_eligible">Reject as Not Eligible</option>
                            <option value="temporary_deferred">Temporarily Defer</option>
                        </select>
                    </div>

                    <div class="row g-3 mt-1 d-none" id="reviewDeferControls">
                        <div class="col-md-6">
                            <label for="reviewDeferralDays" class="form-label fw-semibold">Deferral Days</label>
                            <input type="number" min="1" class="form-control" id="reviewDeferralDays" placeholder="e.g. 7">
                        </div>
                        <div class="col-md-6">
                            <label for="reviewNextEligibleDate" class="form-label fw-semibold">Next Eligible Date</label>
                            <input type="date" class="form-control" id="reviewNextEligibleDate">
                        </div>
                    </div>

                    <div class="mt-3 d-none" id="reviewNotesWrapper">
                        <label for="reviewNotesField" class="form-label fw-semibold">Review Notes</label>
                        <textarea id="reviewNotesField" class="form-control" rows="3" placeholder="Add review notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn d-none" id="reviewConfirmBtn">Submit Decision</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('admin_scripts')
    <script src="{{ asset('js/admin/eligibility-review.js') }}?v={{ file_exists(public_path('js/admin/eligibility-review.js')) ? filemtime(public_path('js/admin/eligibility-review.js')) : time() }}"></script>
@endpush

