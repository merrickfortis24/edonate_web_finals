@extends('layouts.admin')

@section('title', 'eDonate - User Management')
@section('admin_page_class', 'admin-users-page')
@section('header_title', 'User Management')
@section('header_subtitle', 'Manage donor registration, updates, and account validation')

@section('header_actions')
    <button class="btn-export btn" id="userManagementExportButton" aria-label="Export donor data" type="button">
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
            'exportUrl' => '',
            'showUrlTemplate' => '',
            'updateUrlTemplate' => '',
            'deactivateUrlTemplate' => '',
            'csrfToken' => '',
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
                    <span class="stat-card__label">Temporarily Deferred</span>
                    <span class="stat-card__value" id="userStatDeferredDonors">0</span>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card stat-card--blue h-100">
                    <span class="stat-card__label">For Review</span>
                    <span class="stat-card__value" id="userStatReviewDonors">0</span>
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
                    <option value="temporary_deferred">Temporarily Deferred</option>
                    <option value="for_review">For Review</option>
                    <option value="unknown">Unknown</option>
                </select>
                <span class="filter-bar__dropdown-arrow" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </span>
            </div>
        </div>

        <div id="userManagementAlertHost" class="mb-3" aria-live="polite"></div>

        <section class="table-wrap" aria-label="Donor list">
            <div class="donor-table-wrapper table-responsive" tabindex="0" aria-label="Scrollable donor records table">
                <div class="table-inner">
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
                </div>
            </div>

            <div class="admin-pagination admin-pagination--js" aria-label="Table pagination">
                <span class="admin-pagination__info" id="userManagementPaginationInfo">Showing 0 to 0 of 0 entries</span>
                <nav class="admin-pagination__links" id="userManagementPaginationControls" aria-label="Pagination links"></nav>
            </div>
        </section>
    </div>

    <div class="modal fade" id="userManagementViewModal" tabindex="-1" aria-labelledby="userManagementViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="userManagementViewModalLabel">Digital Donor ID</h5>
                        <p class="mb-0 text-muted small" id="userManagementViewModalSubtitle">Authorized donor identification view</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @php
                        $userManagementDigitalIdDonor = (object) [
                            'name' => 'Donor record',
                            'donor_id' => '—',
                            'blood_type' => null,
                            'address' => null,
                            'contact_number' => null,
                            'last_donation_date' => null,
                            'next_eligible_date' => null,
                            'verification_status' => 'unverified',
                            'eligibility_status' => 'eligible',
                            'is_active' => true,
                        ];
                    @endphp
                    <x-digital-id :donor="$userManagementDigitalIdDonor" field-prefix="userManagementViewCard" />

                    <h6 class="text-uppercase small text-muted mt-4 mb-3">Donor profile details</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Donor ID</div>
                            <div class="fw-semibold" id="userManagementViewDonorCode">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Full Name</div>
                            <div class="fw-semibold" id="userManagementViewFullName">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Email</div>
                            <div class="fw-semibold" id="userManagementViewEmail">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Contact Number</div>
                            <div class="fw-semibold" id="userManagementViewContactNumber">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Gender</div>
                            <div class="fw-semibold" id="userManagementViewGender">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Birthdate</div>
                            <div class="fw-semibold" id="userManagementViewBirthdate">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Blood Type</div>
                            <div class="fw-semibold" id="userManagementViewBloodType">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Blood Type Status</div>
                            <div class="fw-semibold" id="userManagementViewBloodTypeStatus">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Verified Date</div>
                            <div class="fw-semibold" id="userManagementViewBloodTypeVerifiedAt">-</div>
                        </div>
                        <div class="col-md-12 d-none" id="userManagementViewSelfReportedWarning">
                            <div class="alert alert-warning mb-0 py-2 small">Self-reported blood type must not be used as verified blood availability data.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Eligibility Status</div>
                            <div class="fw-semibold" id="userManagementViewEligibilityStatus">-</div>
                        </div>
                        <div class="col-md-12">
                            <div class="small text-muted text-uppercase">Address / Location</div>
                            <div class="fw-semibold" id="userManagementViewAddress">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Latitude</div>
                            <div class="fw-semibold" id="userManagementViewLatitude">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Longitude</div>
                            <div class="fw-semibold" id="userManagementViewLongitude">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Last Donation Date</div>
                            <div class="fw-semibold" id="userManagementViewLastDonationDate">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Next Eligible Date</div>
                            <div class="fw-semibold" id="userManagementViewNextEligibleDate">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Total Donations</div>
                            <div class="fw-semibold" id="userManagementViewTotalDonations">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Date Registered</div>
                            <div class="fw-semibold" id="userManagementViewDateRegistered">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Account Created</div>
                            <div class="fw-semibold" id="userManagementViewAccountCreated">-</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Updated Date</div>
                            <div class="fw-semibold" id="userManagementViewUpdatedAt">-</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="userManagementPrintDigitalId" aria-label="Print Digital Donor ID">
                        <i class="bi bi-printer me-1" aria-hidden="true"></i>Print ID
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="userManagementEditModal" tabindex="-1" aria-labelledby="userManagementEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="userManagementEditModalLabel">Edit Donor</h5>
                        <p class="mb-0 text-muted small" id="userManagementEditModalSubtitle">Update donor information</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="userManagementEditForm" novalidate>
                    <div class="modal-body">
                        <div id="userManagementEditFeedback" class="alert d-none" role="alert"></div>
                        <input type="hidden" id="userManagementEditDonorId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="userManagementEditFirstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="userManagementEditFirstName" maxlength="100" required>
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="userManagementEditLastName" maxlength="100" required>
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="userManagementEditEmail" maxlength="150" required>
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditContactNumber" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="userManagementEditContactNumber" maxlength="20" placeholder="+639XXXXXXXXX or 09XXXXXXXXX">
                            </div>
                            <div class="col-12">
                                <label for="userManagementEditProfilePhoto" class="form-label">Profile Photo</label>
                                <input type="file" class="form-control" id="userManagementEditProfilePhoto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <div class="form-text">Optional. JPG, PNG, or WebP; maximum 2 MB and 3000 × 3000 pixels.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditGender" class="form-label">Gender</label>
                                <select class="form-select" id="userManagementEditGender">
                                    <option value="">Select gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditBirthdate" class="form-label">Birthdate</label>
                                <input type="date" class="form-control" id="userManagementEditBirthdate">
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditBloodType" class="form-label">Blood Type</label>
                                <select class="form-select" id="userManagementEditBloodType">
                                    <option value="">Select blood type</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditEligibilityStatus" class="form-label">Eligibility Status</label>
                                <select class="form-select" id="userManagementEditEligibilityStatus">
                                    <option value="">Select status</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="userManagementEditStreetAddress" class="form-label">Street Address</label>
                                <input type="text" class="form-control" id="userManagementEditStreetAddress" maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditBarangay" class="form-label">Barangay</label>
                                <input type="text" class="form-control" id="userManagementEditBarangay" maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditCity" class="form-label">City</label>
                                <input type="text" class="form-control" id="userManagementEditCity" maxlength="100">
                            </div>
                            <div class="col-md-12">
                                <label for="userManagementEditProvince" class="form-label">Province</label>
                                <input type="text" class="form-control" id="userManagementEditProvince" maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditLatitude" class="form-label">Latitude</label>
                                <input type="number" class="form-control" id="userManagementEditLatitude" min="-90" max="90" step="any" placeholder="Auto-geocode if blank">
                            </div>
                            <div class="col-md-6">
                                <label for="userManagementEditLongitude" class="form-label">Longitude</label>
                                <input type="number" class="form-control" id="userManagementEditLongitude" min="-180" max="180" step="any" placeholder="Auto-geocode if blank">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="userManagementEditSaveBtn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="userManagementDeactivateModal" tabindex="-1" aria-labelledby="userManagementDeactivateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userManagementDeactivateModalLabel">Deactivate Donor?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="userManagementDeactivateFeedback" class="alert d-none" role="alert"></div>
                    <p class="mb-0" id="userManagementDeactivatePrompt">This will prevent the donor from using active donor services but will preserve their historical records.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="userManagementDeactivateConfirmBtn">Deactivate Donor</button>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('admin_scripts')
<script>
    (function () {
        var payload = (window.AdminPageData && window.AdminPageData.userManagement) ? window.AdminPageData.userManagement : {};
        var listUrl = payload.api && payload.api.listUrl ? payload.api.listUrl : '';
        var exportUrl = payload.api && payload.api.exportUrl ? String(payload.api.exportUrl) : '';
        var showUrlTemplate = payload.api && payload.api.showUrlTemplate ? payload.api.showUrlTemplate : '';
        var updateUrlTemplate = payload.api && payload.api.updateUrlTemplate ? payload.api.updateUrlTemplate : '';
        var photoUploadUrlTemplate = payload.api && payload.api.photoUploadUrlTemplate ? payload.api.photoUploadUrlTemplate : '';
        var deactivateUrlTemplate = payload.api && payload.api.deactivateUrlTemplate ? payload.api.deactivateUrlTemplate : '';
        var reactivateUrlTemplate = payload.api && payload.api.reactivateUrlTemplate ? payload.api.reactivateUrlTemplate : '';
        var csrfToken = payload.api && payload.api.csrfToken
            ? String(payload.api.csrfToken)
            : String((document.querySelector('meta[name="csrf-token"]') || {}).content || '');

        var searchInput = document.getElementById('userManagementSearchInput');
        var bloodTypeFilter = document.getElementById('userManagementBloodTypeFilter');
        var statusFilter = document.getElementById('userManagementStatusFilter');
        var tableBody = document.getElementById('userManagementTableBody');
        var paginationInfo = document.getElementById('userManagementPaginationInfo');
        var paginationControls = document.getElementById('userManagementPaginationControls');
        var exportButton = document.getElementById('userManagementExportButton');
        var printDigitalIdButton = document.getElementById('userManagementPrintDigitalId');
        var alertHost = document.getElementById('userManagementAlertHost');

        var totalDonorsEl = document.getElementById('userStatTotalDonors');
        var eligibleDonorsEl = document.getElementById('userStatEligibleDonors');
        var notEligibleDonorsEl = document.getElementById('userStatNotEligibleDonors');
        var deferredDonorsEl = document.getElementById('userStatDeferredDonors');
        var reviewDonorsEl = document.getElementById('userStatReviewDonors');
        var totalDonationsEl = document.getElementById('userStatTotalDonations');

        var viewModalElement = document.getElementById('userManagementViewModal');
        var editModalElement = document.getElementById('userManagementEditModal');
        var deactivateModalElement = document.getElementById('userManagementDeactivateModal');
        var viewModal = null;
        var editModal = null;
        var deactivateModal = null;

        var viewDigitalIdCard = viewModalElement
            ? viewModalElement.querySelector('[data-digital-id-card]')
            : null;

        function digitalIdField(name) {
            if (!viewDigitalIdCard) {
                return null;
            }

            return viewDigitalIdCard.querySelector('[data-digital-id-field="' + name + '"]');
        }

        var viewModalSubtitle = document.getElementById('userManagementViewModalSubtitle');
        var viewVerificationBadge = digitalIdField('verificationBadge');
        var viewCardAvatar = viewDigitalIdCard
            ? viewDigitalIdCard.querySelector('.digital-id-card__avatar')
            : null;
        var viewCardName = digitalIdField('name');
        var viewCardCode = digitalIdField('code');
        var viewCardBloodType = digitalIdField('bloodType');
        var viewCardIdentity = digitalIdField('identity');
        var viewCardEligibility = digitalIdField('eligibility');
        var viewCardAddress = digitalIdField('address');
        var viewCardContactNumber = digitalIdField('contactNumber');
        var viewCardLastDonationDate = digitalIdField('lastDonationDate');
        var viewCardNextEligibleDate = digitalIdField('nextEligibleDate');
        var viewCardAccountStatus = digitalIdField('accountStatus');
        var viewDonorCode = document.getElementById('userManagementViewDonorCode');
        var viewFullName = document.getElementById('userManagementViewFullName');
        var viewEmail = document.getElementById('userManagementViewEmail');
        var viewContactNumber = document.getElementById('userManagementViewContactNumber');
        var viewGender = document.getElementById('userManagementViewGender');
        var viewBirthdate = document.getElementById('userManagementViewBirthdate');
        var viewBloodType = document.getElementById('userManagementViewBloodType');
        var viewBloodTypeStatus = document.getElementById('userManagementViewBloodTypeStatus');
        var viewBloodTypeVerifiedAt = document.getElementById('userManagementViewBloodTypeVerifiedAt');
        var viewSelfReportedWarning = document.getElementById('userManagementViewSelfReportedWarning');
        var viewEligibilityStatus = document.getElementById('userManagementViewEligibilityStatus');
        var viewAddress = document.getElementById('userManagementViewAddress');
        var viewLatitude = document.getElementById('userManagementViewLatitude');
        var viewLongitude = document.getElementById('userManagementViewLongitude');
        var viewLastDonationDate = document.getElementById('userManagementViewLastDonationDate');
        var viewNextEligibleDate = document.getElementById('userManagementViewNextEligibleDate');
        var viewTotalDonations = document.getElementById('userManagementViewTotalDonations');
        var viewDateRegistered = document.getElementById('userManagementViewDateRegistered');
        var viewAccountCreated = document.getElementById('userManagementViewAccountCreated');
        var viewUpdatedAt = document.getElementById('userManagementViewUpdatedAt');

        var editModalSubtitle = document.getElementById('userManagementEditModalSubtitle');
        var editForm = document.getElementById('userManagementEditForm');
        var editFeedback = document.getElementById('userManagementEditFeedback');
        var editDonorIdInput = document.getElementById('userManagementEditDonorId');
        var editFirstNameInput = document.getElementById('userManagementEditFirstName');
        var editLastNameInput = document.getElementById('userManagementEditLastName');
        var editEmailInput = document.getElementById('userManagementEditEmail');
        var editContactNumberInput = document.getElementById('userManagementEditContactNumber');
        var editGenderSelect = document.getElementById('userManagementEditGender');
        var editBirthdateInput = document.getElementById('userManagementEditBirthdate');
        var editBloodTypeSelect = document.getElementById('userManagementEditBloodType');
        var editEligibilityStatusSelect = document.getElementById('userManagementEditEligibilityStatus');
        var editStreetAddressInput = document.getElementById('userManagementEditStreetAddress');
        var editBarangayInput = document.getElementById('userManagementEditBarangay');
        var editCityInput = document.getElementById('userManagementEditCity');
        var editProvinceInput = document.getElementById('userManagementEditProvince');
        var editLatitudeInput = document.getElementById('userManagementEditLatitude');
        var editLongitudeInput = document.getElementById('userManagementEditLongitude');
        var editProfilePhotoInput = document.getElementById('userManagementEditProfilePhoto');
        var editSaveButton = document.getElementById('userManagementEditSaveBtn');

        var deactivateFeedback = document.getElementById('userManagementDeactivateFeedback');
        var deactivatePrompt = document.getElementById('userManagementDeactivatePrompt');
        var deactivateConfirmButton = document.getElementById('userManagementDeactivateConfirmBtn');

        var state = {
            page: 1,
            perPage: 10,
            search: '',
            bloodType: '',
            status: '',
            meta: {
                current_page: 1,
                last_page: 1,
                total: 0,
                from: 0,
                to: 0
            },
            deactivateTarget: null
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

        function extractErrorMessage(payload, fallbackMessage) {
            if (payload && payload.errors && typeof payload.errors === 'object') {
                var firstKey = Object.keys(payload.errors)[0];
                if (firstKey && Array.isArray(payload.errors[firstKey]) && payload.errors[firstKey][0]) {
                    return String(payload.errors[firstKey][0]);
                }
            }

            if (payload && payload.message) {
                return String(payload.message);
            }

            return fallbackMessage;
        }

        function formatDate(value, fallback) {
            if (!value) {
                return fallback || '-';
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

        function formatDateTime(value) {
            if (!value) {
                return '-';
            }

            var rawValue = String(value);
            var normalizedValue = rawValue.indexOf('T') === -1 && rawValue.indexOf(' ') !== -1
                ? rawValue.replace(' ', 'T')
                : rawValue;
            var parsed = new Date(normalizedValue);

            if (Number.isNaN(parsed.getTime())) {
                return rawValue;
            }

            return parsed.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        function formatNumber(value) {
            var numberValue = Number(value || 0);
            return numberValue.toLocaleString('en-US');
        }

        function normalizeStatus(value) {
            var status = String(value || '').toLowerCase().trim().replace(/[\\s-]+/g, '_');
            var aliases = {
                approved: 'eligible', qualified: 'eligible', ready: 'eligible',
                'not_eligible': 'not_eligible', ineligible: 'not_eligible', declined: 'not_eligible', rejected: 'not_eligible',
                'temporary_defer': 'temporary_deferred', temporarily_deferred: 'temporary_deferred', deferred: 'temporary_deferred',
                'pending_review': 'for_review', pending: 'for_review'
            };
            if (['eligible', 'not_eligible', 'temporary_deferred', 'for_review'].indexOf(status) !== -1) return status;
            return aliases[status] || 'unknown';
        }

        function statusLabel(value) {
            return {
                eligible: 'Eligible',
                not_eligible: 'Not Eligible',
                temporary_deferred: 'Temporarily Deferred',
                for_review: 'For Review',
                unknown: 'Unknown'
            }[normalizeStatus(value)];
        }

        function statusClass(value) {
            return {
                eligible: 'badge--eligible',
                not_eligible: 'badge--not-eligible',
                temporary_deferred: 'badge--deferred',
                for_review: 'badge--review',
                unknown: 'badge--unknown'
            }[normalizeStatus(value)];
        }

        function verificationLabel(value) {
            var status = String(value || 'unverified').toLowerCase();
            return {
                verified: 'VERIFIED DONOR',
                pending: 'PENDING VERIFICATION',
                rejected: 'REJECTED',
                unverified: 'UNVERIFIED'
            }[status] || 'UNVERIFIED';
        }

        function verificationText(value) {
            var status = String(value || 'unverified').toLowerCase();
            return status.charAt(0).toUpperCase() + status.slice(1);
        }

        function donorInitials(name) {
            var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
            if (!parts.length) {
                return '--';
            }

            return parts.slice(0, 2).map(function (part) { return part.charAt(0); }).join('').toUpperCase();
        }

        function buildDonorActionUrl(template, donorId) {
            if (!template) {
                return '';
            }

            return String(template).replace('__DONOR_ID__', encodeURIComponent(String(donorId)));
        }

        function displayValue(value, fallback) {
            if (value === null || typeof value === 'undefined') {
                return fallback || '-';
            }

            var stringValue = String(value);
            return stringValue.trim() === '' ? (fallback || '-') : stringValue;
        }

        function setValue(target, value) {
            if (!target) {
                return;
            }

            target.textContent = displayValue(value);
        }

        function setSelectOptions(select, options, placeholder, selectedValue) {
            if (!select) {
                return;
            }

            var normalizedSelectedValue = selectedValue === null || typeof selectedValue === 'undefined'
                ? ''
                : String(selectedValue);

            select.innerHTML = '';

            var placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            select.appendChild(placeholderOption);

            if (!Array.isArray(options)) {
                select.value = normalizedSelectedValue;
                return;
            }

            options.forEach(function (optionPayload) {
                if (!optionPayload || typeof optionPayload !== 'object') {
                    return;
                }

                var option = document.createElement('option');
                option.value = String(optionPayload.id !== undefined ? optionPayload.id : optionPayload.value || '');
                option.textContent = String(optionPayload.label || optionPayload.name || option.value);
                select.appendChild(option);
            });

            select.value = normalizedSelectedValue;
        }

        function showAlert(type, message) {
            if (!alertHost) {
                return;
            }

            var toneMap = {
                success: 'alert-success',
                danger: 'alert-danger',
                warning: 'alert-warning',
                info: 'alert-info'
            };

            var alertElement = document.createElement('div');
            alertElement.className = 'alert ' + (toneMap[type] || 'alert-info') + ' alert-dismissible fade show';
            alertElement.setAttribute('role', 'alert');
            alertElement.innerHTML = escapeHtml(message) + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';

            alertHost.innerHTML = '';
            alertHost.appendChild(alertElement);

            window.setTimeout(function () {
                if (alertElement && alertElement.parentNode && typeof bootstrap !== 'undefined') {
                    bootstrap.Alert.getOrCreateInstance(alertElement).close();
                }
            }, 3200);
        }

        function ensureModal(element, current) {
            var bootstrapApi = window.bootstrap;

            if (current || !element || !bootstrapApi || !bootstrapApi.Modal) {
                return current;
            }

            return bootstrapApi.Modal.getOrCreateInstance(element);
        }

        function showInlineFeedback(element, type, message) {
            if (!element) {
                return;
            }

            element.className = 'alert alert-' + type;
            element.textContent = message;
            element.classList.remove('d-none');
        }

        function clearInlineFeedback(element) {
            if (!element) {
                return;
            }

            element.className = 'alert d-none';
            element.textContent = '';
        }

        function setActionButtonLoading(button, isLoading) {
            if (!button) {
                return;
            }

            if (isLoading) {
                if (!button.dataset.originalHtml) {
                    button.dataset.originalHtml = button.innerHTML;
                }

                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
                return;
            }

            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }
        }

        function setTextButtonLoading(button, isLoading, loadingText) {
            if (!button) {
                return;
            }

            if (!button.dataset.originalText) {
                button.dataset.originalText = button.textContent;
            }

            if (isLoading) {
                button.disabled = true;
                button.textContent = loadingText;
                return;
            }

            button.disabled = false;
            button.textContent = button.dataset.originalText || button.textContent;
        }

        function requestJson(url, options) {
            var requestOptions = Object.assign({}, options || {});
            var headers = Object.assign({
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }, requestOptions.headers || {});

            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            if (requestOptions.body && !headers['Content-Type']) {
                headers['Content-Type'] = 'application/json';
            }

            requestOptions.headers = headers;
            requestOptions.credentials = 'same-origin';

            return fetch(url, requestOptions)
                .then(function (response) {
                    return response.json()
                        .catch(function () {
                            return {};
                        })
                        .then(function (responsePayload) {
                            if (!response.ok) {
                                var error = new Error(extractErrorMessage(responsePayload, 'Request failed.'));
                                error.status = response.status;
                                error.payload = responsePayload;
                                throw error;
                            }

                            return responsePayload;
                        });
                });
        }

        function uploadDonorPhoto(url, file) {
            var formData = new FormData();
            formData.append('photo', file);
            var headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

            return fetch(url, {
                method: 'POST',
                body: formData,
                headers: headers,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (body) {
                    if (!response.ok) {
                        throw new Error(extractErrorMessage(body, 'Unable to upload donor photo.'));
                    }
                    return body;
                });
            });
        }

        function buildExportUrl() {
            if (!exportUrl) {
                return '';
            }

            var url = new URL(exportUrl, window.location.origin);
            if (state.search) {
                url.searchParams.set('search', state.search);
            }
            if (state.bloodType) {
                url.searchParams.set('blood_type', state.bloodType);
            }
            if (state.status) {
                url.searchParams.set('status', state.status);
            }

            return url.toString();
        }

        function renderActionButtons(donorId, isActive) {
            var stateButton = '<button class="btn-action ' + (isActive ? 'btn-action--deactivate' : 'btn-action--reactivate') + '" type="button" title="' + (isActive ? 'Deactivate Donor' : 'Reactivate Donor') + '" aria-label="' + (isActive ? 'Deactivate Donor' : 'Reactivate Donor') + '" data-action="toggle-active" data-is-active="' + (isActive ? 'true' : 'false') + '" data-donor-id="' + escapeHtml(donorId) + '">'
                + (isActive
                    ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b60c0c" stroke-width="2"><path d="M12 3v9" stroke-linecap="round"/><path d="M6.2 6.2a8 8 0 1 0 11.6 0" stroke-linecap="round"/></svg>'
                    : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M12 3v9" stroke-linecap="round"/><path d="M6.2 6.2a8 8 0 1 0 11.6 0" stroke-linecap="round"/></svg>')
                + '</button>';

            return ''
                + '<button class="btn-action btn-action--view" type="button" title="View Digital Donor ID" aria-label="View Digital Donor ID" data-action="view" data-donor-id="' + escapeHtml(donorId) + '">'
                + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0063aa" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
                + '</button>'
                + '<button class="btn-action btn-action--edit" type="button" title="Edit Donor" aria-label="Edit Donor" data-action="edit" data-donor-id="' + escapeHtml(donorId) + '">'
                + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#129800" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>'
                + '</button>'
                + stateButton;
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
                var rawFullName = String(item.full_name || 'Unknown Donor');
                var fullName = escapeHtml(rawFullName);
                var email = escapeHtml(item.email || '-');
                var bloodType = escapeHtml(item.blood_type || '-');
                var contact = escapeHtml(item.contact_number || '-');
                var lastDonation = escapeHtml(formatDate(item.last_donation_date));
                var donationCount = escapeHtml(formatNumber(item.total_donations));
                var badge = statusClass(item.eligibility_status);
                var badgeLabel = escapeHtml(statusLabel(item.eligibility_status));
                var donorId = Number(item.donor_id || 0);
                var isActive = item.is_active !== false;

                return ''
                    + '<div class="table-grid table-row" data-donor-id="' + escapeHtml(donorId) + '" data-donor-name="' + escapeHtml(rawFullName) + '">'
                    + '<div class="table-td">' + donorCode + '</div>'
                    + '<div class="table-td"><div class="donor-name__primary">' + fullName + '</div><div class="donor-name__email">' + email + '</div>' + (isActive ? '' : '<span class="badge badge--inactive">Inactive account</span>') + '</div>'
                    + '<div class="table-td cell-inline"><span aria-hidden="true">&#129656;</span> ' + bloodType + '</div>'
                    + '<div class="table-td cell-inline"><span aria-hidden="true">&#128222;</span> ' + contact + '</div>'
                    + '<div class="table-td cell-inline"><span aria-hidden="true">&#128197;</span> ' + lastDonation + '</div>'
                    + '<div class="table-td"><span class="badge ' + badge + '">' + badgeLabel + '</span></div>'
                    + '<div class="table-td cell-center">' + donationCount + '</div>'
                    + '<div class="table-td actions">' + renderActionButtons(donorId, isActive) + '</div>'
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
            if (deferredDonorsEl) {
                deferredDonorsEl.textContent = formatNumber(stats.temporary_deferred_donors || 0);
            }
            if (reviewDonorsEl) {
                reviewDonorsEl.textContent = formatNumber(stats.for_review_donors || 0);
            }
            if (totalDonationsEl) {
                totalDonationsEl.textContent = formatNumber(stats.total_donations || 0);
            }
        }

        function renderPagination(meta) {
            if (!paginationInfo || !paginationControls) {
                return;
            }

            state.meta = {
                current_page: Number(meta.current_page || 1),
                last_page: Number(meta.last_page || 1),
                total: Number(meta.total || 0),
                from: Number(meta.from || 0),
                to: Number(meta.to || 0)
            };

            if (window.eDonateAdminPagination) {
                window.eDonateAdminPagination.render(paginationControls, state.meta, null, {
                    infoElement: paginationInfo
                });
            }
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

        function populateViewModal(donor) {
            var donorPayload = donor || {};
            var fullName = displayValue(donorPayload.full_name || donorPayload.donor_code || 'Donor record');
            var verificationStatus = String(donorPayload.verification_status || 'unverified').toLowerCase();

            if (viewModalSubtitle) {
                viewModalSubtitle.textContent = fullName;
            }
            if (viewVerificationBadge) {
                viewVerificationBadge.textContent = verificationLabel(verificationStatus);
                viewVerificationBadge.className = 'digital-id-card__status digital-id-card__status--' + verificationStatus;
            }
            if (viewCardAvatar) {
                var profilePhotoUrl = String(donorPayload.profile_photo_url || '');
                var safePhotoUrl = '';
                try {
                    var parsedPhotoUrl = new URL(profilePhotoUrl, window.location.origin);
                    if (profilePhotoUrl && parsedPhotoUrl.origin === window.location.origin) safePhotoUrl = parsedPhotoUrl.toString();
                } catch (error) {
                    safePhotoUrl = '';
                }

                viewCardAvatar.replaceChildren();
                if (safePhotoUrl) {
                    var photo = document.createElement('img');
                    photo.src = safePhotoUrl;
                    photo.alt = fullName + ' profile photo';
                    photo.className = 'h-full w-full object-cover';
                    photo.addEventListener('error', function () {
                        var initials = document.createElement('span');
                        initials.textContent = donorInitials(donorPayload.full_name);
                        initials.setAttribute('aria-hidden', 'true');
                        viewCardAvatar.replaceChildren(initials);
                    }, { once: true });
                    viewCardAvatar.appendChild(photo);
                } else {
                    var initials = document.createElement('span');
                    initials.textContent = donorInitials(donorPayload.full_name);
                    initials.setAttribute('aria-hidden', 'true');
                    viewCardAvatar.appendChild(initials);
                }
            }
            if (viewCardName) {
                viewCardName.textContent = fullName;
            }
            if (viewCardCode) {
                viewCardCode.textContent = displayValue(donorPayload.donor_code);
            }
            if (viewCardBloodType) {
                viewCardBloodType.textContent = displayValue(donorPayload.blood_type, 'Not yet determined');
            }
            if (viewCardIdentity) {
                viewCardIdentity.textContent = verificationText(verificationStatus);
            }
            if (viewCardEligibility) {
                viewCardEligibility.textContent = statusLabel(donorPayload.eligibility_status);
            }
            if (viewCardAddress) {
                viewCardAddress.textContent = displayValue(donorPayload.full_address, 'Not recorded');
            }
            if (viewCardContactNumber) {
                viewCardContactNumber.textContent = displayValue(donorPayload.contact_number, 'Not recorded');
            }
            if (viewCardLastDonationDate) {
                viewCardLastDonationDate.textContent = formatDate(donorPayload.last_donation_date, 'Not recorded');
            }
            if (viewCardNextEligibleDate) {
                var eligibilityStatus = normalizeStatus(donorPayload.eligibility_status);
                var fallbackDateLabel = eligibilityStatus === 'eligible'
                    ? 'Eligible now'
                    : (eligibilityStatus === 'temporary_deferred' ? 'Date not set' : 'See eligibility status');
                viewCardNextEligibleDate.textContent = formatDate(donorPayload.next_eligible_date, fallbackDateLabel);
                var hasFutureEligibilityDate = false;
                if (donorPayload.next_eligible_date) {
                    var nextEligibleDate = new Date(String(donorPayload.next_eligible_date) + 'T00:00:00');
                    var today = new Date();
                    today.setHours(0, 0, 0, 0);
                    hasFutureEligibilityDate = !Number.isNaN(nextEligibleDate.getTime()) && nextEligibleDate > today;
                }
                viewCardNextEligibleDate.classList.remove(
                    'digital-id-card__field-value--waiting',
                    'digital-id-card__field-value--eligible',
                    'digital-id-card__field-value--unknown'
                );
                viewCardNextEligibleDate.classList.add(hasFutureEligibilityDate
                    ? 'digital-id-card__field-value--waiting'
                    : (eligibilityStatus === 'eligible' ? 'digital-id-card__field-value--eligible' : 'digital-id-card__field-value--unknown'));
            }
            if (viewCardAccountStatus) {
                viewCardAccountStatus.textContent = donorPayload.is_active === false ? 'INACTIVE ACCOUNT' : 'ACTIVE ACCOUNT';
                viewCardAccountStatus.classList.toggle('digital-id-card__account--inactive', donorPayload.is_active === false);
            }

            setValue(viewDonorCode, donorPayload.donor_code);
            setValue(viewFullName, donorPayload.full_name);
            setValue(viewEmail, donorPayload.email);
            setValue(viewContactNumber, donorPayload.contact_number);
            setValue(viewGender, donorPayload.gender);
            setValue(viewBirthdate, formatDate(donorPayload.birthdate));
            setValue(viewBloodType, donorPayload.blood_type);
            setValue(viewBloodTypeStatus, String(donorPayload.blood_type_status || 'not_yet_determined').replace(/_/g, ' '));
            setValue(viewBloodTypeVerifiedAt, formatDateTime(donorPayload.blood_type_verified_at));
            if (viewSelfReportedWarning) {
                viewSelfReportedWarning.classList.toggle('d-none', donorPayload.blood_type_status !== 'self_reported');
            }
            setValue(viewEligibilityStatus, statusLabel(donorPayload.eligibility_status));
            setValue(viewAddress, donorPayload.full_address);
            setValue(viewLatitude, donorPayload.latitude);
            setValue(viewLongitude, donorPayload.longitude);
            setValue(viewLastDonationDate, formatDate(donorPayload.last_donation_date));
            setValue(viewNextEligibleDate, formatDate(donorPayload.next_eligible_date));
            setValue(viewTotalDonations, formatNumber(donorPayload.total_donations || 0));
            setValue(viewDateRegistered, formatDateTime(donorPayload.date_registered));
            setValue(viewAccountCreated, formatDateTime(donorPayload.auth_created_at));
            setValue(viewUpdatedAt, formatDateTime(donorPayload.updated_at));
        }

        function populateEditModal(donor, options) {
            var donorPayload = donor || {};
            var responseOptions = options || {};

            clearInlineFeedback(editFeedback);
            if (editProfilePhotoInput) editProfilePhotoInput.value = '';

            if (editModalSubtitle) {
                editModalSubtitle.textContent = displayValue(donorPayload.donor_code || donorPayload.full_name || 'Update donor information');
            }

            if (editDonorIdInput) {
                editDonorIdInput.value = String(donorPayload.donor_id || '');
            }
            if (editFirstNameInput) {
                editFirstNameInput.value = String(donorPayload.first_name || '');
            }
            if (editLastNameInput) {
                editLastNameInput.value = String(donorPayload.last_name || '');
            }
            if (editEmailInput) {
                editEmailInput.value = String(donorPayload.email || '');
            }
            if (editContactNumberInput) {
                editContactNumberInput.value = String(donorPayload.contact_number || '');
            }
            if (editGenderSelect) {
                editGenderSelect.value = String(donorPayload.gender || '');
            }
            if (editBirthdateInput) {
                editBirthdateInput.value = donorPayload.birthdate ? String(donorPayload.birthdate) : '';
            }
            if (editStreetAddressInput) {
                editStreetAddressInput.value = String(donorPayload.street_address || '');
            }
            if (editBarangayInput) {
                editBarangayInput.value = String(donorPayload.barangay_name || '');
            }
            if (editCityInput) {
                editCityInput.value = String(donorPayload.city || '');
            }
            if (editProvinceInput) {
                editProvinceInput.value = String(donorPayload.province || '');
            }
            if (editLatitudeInput) {
                editLatitudeInput.value = donorPayload.latitude != null ? String(donorPayload.latitude) : '';
            }
            if (editLongitudeInput) {
                editLongitudeInput.value = donorPayload.longitude != null ? String(donorPayload.longitude) : '';
            }

            setSelectOptions(editBloodTypeSelect, responseOptions.blood_types || [], 'Select blood type', donorPayload.blood_type_id);
            setSelectOptions(editEligibilityStatusSelect, responseOptions.statuses || [], 'Select status', donorPayload.eligibility_status);
        }

        function buildDeactivatePrompt(target) {
            var donorName = target && target.name ? String(target.name) : 'this donor';
            return target && target.reactivate
                ? 'Reactivate ' + donorName + '? This restores access to active donor services and preserves historical records.'
                : 'Deactivate ' + donorName + '? This will prevent active donor services while preserving historical records.';
        }

        function loadDonorDetails(donorId) {
            var requestUrl = buildDonorActionUrl(showUrlTemplate, donorId);

            if (!requestUrl) {
                return Promise.reject(new Error('User detail route is not configured.'));
            }

            return requestJson(requestUrl, {
                method: 'GET'
            });
        }

        function openViewModal(donorId, triggerButton) {
            setActionButtonLoading(triggerButton, true);

            loadDonorDetails(donorId)
                .then(function (responsePayload) {
                    populateViewModal(responsePayload.donor || {});
                    viewModal = ensureModal(viewModalElement, viewModal);
                    if (viewModal) {
                        viewModal.show();
                    } else {
                        throw new Error('The Digital Donor ID viewer is still loading. Please try again.');
                    }
                })
                .catch(function (error) {
                    showAlert('danger', error.message || 'Unable to load donor details.');
                })
                .finally(function () {
                    setActionButtonLoading(triggerButton, false);
                });
        }

        function openEditModal(donorId, triggerButton) {
            setActionButtonLoading(triggerButton, true);

            loadDonorDetails(donorId)
                .then(function (responsePayload) {
                    populateEditModal(responsePayload.donor || {}, responsePayload.options || {});
                    editModal = ensureModal(editModalElement, editModal);
                    if (editModal) {
                        editModal.show();
                    } else {
                        throw new Error('The edit form is still loading. Please try again.');
                    }
                })
                .catch(function (error) {
                    showAlert('danger', error.message || 'Unable to load donor information for editing.');
                })
                .finally(function () {
                    setActionButtonLoading(triggerButton, false);
                });
        }

        function openDeactivateModal(donorId, donorName, reactivate) {
            state.deactivateTarget = {
                id: donorId,
                name: donorName,
                reactivate: !!reactivate
            };

            clearInlineFeedback(deactivateFeedback);

            if (deactivatePrompt) {
                deactivatePrompt.textContent = buildDeactivatePrompt(state.deactivateTarget);
            }
            var title = document.getElementById('userManagementDeactivateModalLabel');
            if (title) title.textContent = reactivate ? 'Reactivate Donor?' : 'Deactivate Donor?';
            if (deactivateConfirmButton) {
                deactivateConfirmButton.textContent = reactivate ? 'Reactivate Donor' : 'Deactivate Donor';
                deactivateConfirmButton.classList.toggle('btn-success', !!reactivate);
                deactivateConfirmButton.classList.toggle('btn-danger', !reactivate);
            }

            deactivateModal = ensureModal(deactivateModalElement, deactivateModal);
            if (deactivateModal) {
                deactivateModal.show();
            } else {
                showAlert('danger', 'The deactivation dialog is still loading. Please try again.');
            }
        }

        function loadUsers() {
            if (!listUrl) {
                renderRows([]);
                return;
            }

            setLoadingState();

            requestJson(buildRequestUrl(), {
                method: 'GET'
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

                var actionButton = target.closest('.btn-action[data-action]');
                if (!actionButton) {
                    return;
                }

                event.preventDefault();

                var donorId = Number(actionButton.getAttribute('data-donor-id') || '0');
                var action = String(actionButton.getAttribute('data-action') || '');
                var row = actionButton.closest('.table-row');
                var donorName = row ? String(row.getAttribute('data-donor-name') || '') : '';

                if (!donorId) {
                    showAlert('warning', 'Please select a valid donor record.');
                    return;
                }

                if (action === 'view') {
                    openViewModal(donorId, actionButton);
                    return;
                }

                if (action === 'edit') {
                    openEditModal(donorId, actionButton);
                    return;
                }

                if (action === 'toggle-active') {
                    openDeactivateModal(donorId, donorName, actionButton.getAttribute('data-is-active') === 'false');
                }
            });
        }

        if (editForm) {
            editForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var donorId = Number(editDonorIdInput ? editDonorIdInput.value : '0');
                var requestUrl = buildDonorActionUrl(updateUrlTemplate, donorId);

                if (!donorId || !requestUrl) {
                    showInlineFeedback(editFeedback, 'danger', 'User update route is not configured correctly.');
                    return;
                }

                var payloadBody = {
                    first_name: String((editFirstNameInput && editFirstNameInput.value) || '').trim(),
                    last_name: String((editLastNameInput && editLastNameInput.value) || '').trim(),
                    email: String((editEmailInput && editEmailInput.value) || '').trim(),
                    contact_number: String((editContactNumberInput && editContactNumberInput.value) || '').trim() || null,
                    gender: String((editGenderSelect && editGenderSelect.value) || '').trim() || null,
                    birthdate: String((editBirthdateInput && editBirthdateInput.value) || '').trim() || null,
                    blood_type_id: String((editBloodTypeSelect && editBloodTypeSelect.value) || '').trim() !== ''
                        ? Number(editBloodTypeSelect.value)
                        : null,
                    street_address: String((editStreetAddressInput && editStreetAddressInput.value) || '').trim() || null,
                    barangay_name: String((editBarangayInput && editBarangayInput.value) || '').trim() || null,
                    city: String((editCityInput && editCityInput.value) || '').trim() || null,
                    province: String((editProvinceInput && editProvinceInput.value) || '').trim() || null,
                    latitude: String((editLatitudeInput && editLatitudeInput.value) || '').trim() || null,
                    longitude: String((editLongitudeInput && editLongitudeInput.value) || '').trim() || null,
                    eligibility_status: String((editEligibilityStatusSelect && editEligibilityStatusSelect.value) || '').trim() || null
                };
                var selectedPhoto = editProfilePhotoInput && editProfilePhotoInput.files
                    ? editProfilePhotoInput.files[0]
                    : null;

                clearInlineFeedback(editFeedback);
                setTextButtonLoading(editSaveButton, true, 'Saving...');

                requestJson(requestUrl, {
                    method: 'PUT',
                    body: JSON.stringify(payloadBody)
                })
                    .then(function (responsePayload) {
                        if (!selectedPhoto) {
                            return { profile: responsePayload, photo: null };
                        }

                        var photoUrl = buildDonorActionUrl(photoUploadUrlTemplate, donorId);
                        if (!photoUrl) {
                            var routeError = new Error('Profile saved, but the photo upload route is not configured.');
                            routeError.profileSaved = true;
                            throw routeError;
                        }

                        return uploadDonorPhoto(photoUrl, selectedPhoto)
                            .then(function (photoPayload) {
                                return { profile: responsePayload, photo: photoPayload };
                            })
                            .catch(function (error) {
                                error.profileSaved = true;
                                throw error;
                            });
                    })
                    .then(function (result) {
                        if (editModal) {
                            editModal.hide();
                        }

                        showAlert('success', result.profile.message || 'Donor updated successfully.');
                        loadUsers();
                    })
                    .catch(function (error) {
                        var message = error.message || 'Unable to update donor.';
                        showInlineFeedback(editFeedback, error.profileSaved ? 'warning' : 'danger', message);
                        if (error.profileSaved) {
                            loadUsers();
                        }
                    })
                    .finally(function () {
                        setTextButtonLoading(editSaveButton, false, 'Saving...');
                    });
            });
        }

        if (printDigitalIdButton && viewDigitalIdCard) {
            printDigitalIdButton.addEventListener('click', function () {
                document.body.classList.add('digital-id-printing');
                window.addEventListener('afterprint', function cleanupDigitalIdPrint() {
                    document.body.classList.remove('digital-id-printing');
                }, { once: true });
                window.print();
            });
        }

        if (deactivateConfirmButton) {
            deactivateConfirmButton.addEventListener('click', function () {
                var targetDonor = state.deactivateTarget;
                var donorId = targetDonor ? Number(targetDonor.id || 0) : 0;
                var isReactivate = !!(targetDonor && targetDonor.reactivate);
                var requestUrl = buildDonorActionUrl(isReactivate ? reactivateUrlTemplate : deactivateUrlTemplate, donorId);

                if (!donorId || !requestUrl) {
                    showInlineFeedback(deactivateFeedback, 'danger', 'Donor account-status route is not configured correctly.');
                    return;
                }

                clearInlineFeedback(deactivateFeedback);
                var loadingLabel = isReactivate ? 'Reactivating...' : 'Deactivating...';
                setTextButtonLoading(deactivateConfirmButton, true, loadingLabel);

                requestJson(requestUrl, {
                    method: 'PATCH'
                })
                    .then(function (responsePayload) {
                        if (deactivateModal) {
                            deactivateModal.hide();
                        }

                        showAlert('success', responsePayload.message || (isReactivate ? 'Donor account reactivated.' : 'Donor account deactivated.'));
                        state.deactivateTarget = null;
                        loadUsers();
                    })
                    .catch(function (error) {
                        showInlineFeedback(deactivateFeedback, 'danger', error.message || 'Unable to deactivate donor.');
                    })
                    .finally(function () {
                        setTextButtonLoading(deactivateConfirmButton, false, isReactivate ? 'Reactivate Donor' : 'Deactivate Donor');
                    });
            });
        }

        if (editModalElement) {
            editModalElement.addEventListener('hidden.bs.modal', function () {
                clearInlineFeedback(editFeedback);
            });
        }

        if (deactivateModalElement) {
            deactivateModalElement.addEventListener('hidden.bs.modal', function () {
                clearInlineFeedback(deactivateFeedback);
            });
        }

        if (exportButton) {
            exportButton.addEventListener('click', function (event) {
                event.preventDefault();

                var downloadUrl = buildExportUrl();
                if (!downloadUrl) {
                    showAlert('danger', 'Donor export is not available right now.');
                    return;
                }

                setTextButtonLoading(exportButton, true, 'Preparing Export...');
                window.location.assign(downloadUrl);
                window.setTimeout(function () {
                    setTextButtonLoading(exportButton, false, 'Preparing Export...');
                }, 1200);
            });
        }

        hydrateBloodTypeFilter((payload.filters && payload.filters.bloodTypes) || []);
        loadUsers();
    })();
</script>
@endpush
