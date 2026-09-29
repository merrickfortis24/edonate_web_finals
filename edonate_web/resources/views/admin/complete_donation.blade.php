@extends('layouts.admin')

@section('title', data_get($completionPayload ?? [], 'mode') === 'verify_blood_type' ? 'eDonate - Verify Donation Blood Type' : 'eDonate - Complete Donation')
@section('admin_page_class', 'admin-complete-donation-page')
@section('header_title', data_get($completionPayload ?? [], 'mode') === 'verify_blood_type' ? 'Verify Donation Blood Type' : 'Complete Donation')
@section('header_subtitle', data_get($completionPayload ?? [], 'mode') === 'verify_blood_type' ? 'Add the lab-confirmed type to an existing completed donation' : 'Securely record the checked-in donor\'s completed donation')

@section('header_actions')
    <a class="btn btn-outline-secondary" href="{{ route('admin.donation-records') }}">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
        Back to Donation Processing
    </a>
@endsection

@section('admin_page_data')
{!! json_encode([
    'page' => 'donation-completion',
    'donationCompletion' => $completionPayload ?? [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
<main class="completion-shell container-fluid px-0">
    <div id="completionStatus" class="alert d-none" role="status" aria-live="polite"></div>

    <section class="completion-context-bar" aria-label="Donation completion context">
        <div class="completion-context-bar__identity">
            <span class="completion-kicker">Checked-in appointment</span>
            <h2 id="completionContextTitle">Finish the donation record</h2>
            <p id="completionContextDescription">Review the donor's Digital ID and save the verified donation outcome.</p>
        </div>
        <div class="completion-context-bar__meta">
            <span class="completion-appointment-code" id="completionAppointmentCode">-</span>
            <span class="completion-context-bar__secure"><i class="bi bi-shield-check" aria-hidden="true"></i> Secure admin workspace</span>
        </div>
    </section>

    <div class="completion-steps" aria-label="Donation completion steps">
        <div class="completion-step completion-step--active">
            <span class="completion-step__number">01</span>
            <span><small>IDENTITY</small><strong>Review ID</strong></span>
        </div>
        <div class="completion-step__line" aria-hidden="true"></div>
        <div class="completion-step">
            <span class="completion-step__number">02</span>
            <span><small>RECORD</small><strong>Complete</strong></span>
        </div>
    </div>

    <div class="completion-layout completion-layout--single">
        <section class="completion-panel completion-id-panel" aria-labelledby="digitalIdTitle">
            <div class="completion-panel__heading">
                <div class="completion-section-heading">
                    <span class="completion-section-number">01</span>
                    <div>
                        <span class="completion-kicker">Identity preview</span>
                        <h2 id="digitalIdTitle" class="h5 mb-1">Digital Donor ID</h2>
                        <p class="text-body-secondary small mb-0">Verify the saved donor details before completing this record.</p>
                    </div>
                </div>
                <span class="completion-panel-tag"><i class="bi bi-eye" aria-hidden="true"></i> Live preview</span>
            </div>

            <section class="digital-donor-id-card completion-digital-id-card" aria-label="Digital Donor ID card">
                <div class="completion-id-card__header">
                    <div>
                        <span class="digital-donor-id-card__brand"><span aria-hidden="true">♥</span> eDonate</span>
                        <span class="completion-id-card__title">DIGITAL DONOR ID</span>
                    </div>
                    <span class="digital-donor-id-card__badge" id="completionVerificationBadge">UNVERIFIED DONOR</span>
                </div>

                <div class="digital-donor-id-card__identity completion-id-card__identity">
                    <div class="completion-id-card__photo-frame">
                        @if (!empty(data_get($completionPayload ?? [], 'donor.profile_photo_url')))
                            <img class="completion-id-card__photo" src="{{ data_get($completionPayload, 'donor.profile_photo_url') }}" alt="Donor photo on Digital Donor ID" onerror="this.remove()">
                        @else
                            <div class="digital-donor-id-card__avatar edonate-user-avatar" id="completionPhotoFallback" aria-hidden="true">--</div>
                        @endif
                    </div>
                    <div class="completion-id-card__identity-copy">
                        <span class="completion-id-card__label">Donor name</span>
                        <div class="digital-donor-id-card__name" id="completionDonorName">-</div>
                        <div class="digital-donor-id-card__code"><i class="bi bi-person-badge me-1" aria-hidden="true"></i><span id="completionDonorCode">-</span></div>
                    </div>
                </div>

                <div class="digital-donor-id-card__fields completion-digital-id-card__fields">
                    <div>
                        <i class="bi bi-droplet-half" aria-hidden="true"></i><span>Blood Type</span>
                        <strong id="completionBloodType">Not Yet Determined</strong>
                    </div>
                    <div>
                        <i class="bi bi-person-vcard" aria-hidden="true"></i><span>Donor ID</span>
                        <strong id="completionDonorId">-</strong>
                    </div>
                    <div class="completion-id-card__field--wide">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i><span>Address</span>
                        <strong id="completionAddress">-</strong>
                    </div>
                    <div>
                        <i class="bi bi-telephone" aria-hidden="true"></i><span>Contact Number</span>
                        <strong id="completionContactNumber">-</strong>
                    </div>
                    <div>
                        <i class="bi bi-calendar3" aria-hidden="true"></i><span>Last Donation Date</span>
                        <strong id="completionLastDonationDate">Not recorded</strong>
                    </div>
                    <div>
                        <i class="bi bi-calendar-check" aria-hidden="true"></i><span>Next Eligible Donation Date</span>
                        <strong id="completionNextEligibleDate">Not set</strong>
                    </div>
                </div>
                <div class="completion-id-card__footer"><i class="bi bi-shield-lock" aria-hidden="true"></i> For authorized eDonate staff use</div>
            </section>
        </section>
    </div>

    <section class="completion-panel completion-form-panel" aria-labelledby="completionFormTitle">
        <div class="completion-panel__heading">
            <div class="completion-section-heading">
                <span class="completion-section-number">03</span>
                <div>
                    <span class="completion-kicker">Donation record</span>
                    <h2 id="completionFormTitle" class="h5 mb-1">Record donation outcome</h2>
                    <p id="completionFormDescription" class="text-body-secondary small mb-0">Confirm the details below to finish this checked-in appointment.</p>
                </div>
            </div>
            <span class="completion-status-chip" id="completionCurrentStatus"><span></span> Checked In</span>
        </div>

        <form id="completeDonationWindowForm">
            <div class="completion-form-grid">
                <div class="completion-form-field" id="completionBloodUnitsField">
                    <label class="form-label" for="completionBloodUnits">Blood Units</label>
                    <input class="form-control" id="completionBloodUnits" type="number" min="1" max="10" value="1" required>
                </div>
                <div class="completion-form-field" id="completionDonationDateField">
                    <label class="form-label" for="completionDonationDate">Donation Date</label>
                    <input class="form-control" id="completionDonationDate" type="date" required>
                </div>
                <div class="completion-form-field completion-form-field--current-type">
                    <span class="form-label">Current Blood Type</span>
                    <div class="completion-current-type" id="completionCurrentBloodType">Not Yet Determined</div>
                </div>
                <div class="completion-form-field">
                    <label class="form-label" for="completionVerifiedBloodType">Verified Blood Type</label>
                    <select class="form-select" id="completionVerifiedBloodType">
                        <option value="">Not yet determined</option>
                    </select>
                    <div class="form-text" id="completionBloodTypeHelp">Only an administrator may record a verified blood type.</div>
                </div>
                <div class="completion-form-field--wide">
                    <div class="alert alert-info d-none mb-0" id="completionMapReadiness" role="status" aria-live="polite"></div>
                </div>
                <div class="completion-form-field--wide d-none" id="completionBloodTypeChangeFields">
                    <div class="alert alert-warning py-2 small mb-2">This result differs from the donor's current verified blood type. Confirm the correction and record its reason.</div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="completionConfirmBloodTypeChange" type="checkbox" value="1">
                        <label class="form-check-label" for="completionConfirmBloodTypeChange">I confirm this verified blood type correction.</label>
                    </div>
                    <label class="form-label" for="completionBloodTypeChangeReason">Reason for change</label>
                    <textarea class="form-control" id="completionBloodTypeChangeReason" rows="2" maxlength="1000"></textarea>
                </div>
                <div class="completion-form-field--wide" id="completionRemarksField">
                    <label class="form-label" for="completionRemarks">Remarks</label>
                    <textarea class="form-control" id="completionRemarks" rows="3" maxlength="1000"></textarea>
                </div>
            </div>

            <div class="completion-form__actions">
                <a class="btn btn-outline-secondary" href="{{ route('admin.donation-records') }}">Cancel</a>
                <button class="btn btn-success" id="completeDonationSubmit" type="submit">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                    Complete Donation
                </button>
            </div>
        </form>
    </section>
</main>
@endsection

@push('admin_scripts')
<script>
    (function () {
        'use strict';

        var config = (window.AdminPageData && window.AdminPageData.donationCompletion) || {};
        var donor = config.donor || {};
        var appointment = config.appointment || {};
        var api = config.api || {};
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var token = csrf ? csrf.getAttribute('content') : '';
        var completionStatus = document.getElementById('completionStatus');
        var form = document.getElementById('completeDonationWindowForm');
        var submitButton = document.getElementById('completeDonationSubmit');
        var bloodTypeSelect = document.getElementById('completionVerifiedBloodType');
        var changeFields = document.getElementById('completionBloodTypeChangeFields');
        var mapReadiness = document.getElementById('completionMapReadiness');
        var verificationOnly = config.mode === 'verify_blood_type';

        function text(id, value, fallback) {
            var element = document.getElementById(id);
            if (element) element.textContent = value === null || typeof value === 'undefined' || String(value).trim() === '' ? (fallback || '-') : String(value);
        }

        function displayDate(value, fallback) {
            if (!value) return fallback || 'Not recorded';
            var date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return String(value);
            return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function dateInputValue(value) {
            if (!value) return new Date().toISOString().slice(0, 10);
            return String(value).slice(0, 10);
        }

        function initials(name) {
            var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
            return parts.length ? parts.slice(0, 2).map(function (part) { return part.charAt(0); }).join('').toUpperCase() : '--';
        }

        function bloodTypeLabel() {
            var type = String(donor.blood_type || '').trim();
            if (!type) return 'Not Yet Determined';
            return String(donor.blood_type_status || '').toLowerCase() === 'verified' ? 'Verified ' + type : 'Self-Reported ' + type;
        }

        function verificationLabel() {
            var status = String(donor.verification_status || 'unverified').toLowerCase();
            return {
                verified: 'VERIFIED DONOR',
                pending: 'PENDING VERIFICATION',
                rejected: 'REJECTED',
                unverified: 'UNVERIFIED DONOR'
            }[status] || 'UNVERIFIED DONOR';
        }

        function renderDonor() {
            var name = donor.full_name || ('Donor #' + (donor.donor_id || ''));
            var donorCode = donor.donor_code || ('D' + String(donor.donor_id || '').padStart(3, '0'));
            var address = donor.full_address || [donor.street_address, donor.barangay_name, donor.city, donor.province].filter(Boolean).join(', ');
            var currentType = donor.blood_type || 'Not Yet Determined';

            text('completionAppointmentCode', appointment.appointment_code, 'Appointment');
            text('completionDonorName', name, 'Donor record');
            text('completionDonorCode', donorCode, '-');
            text('completionPhotoFallback', initials(name), '--');
            text('completionBloodType', bloodTypeLabel(), 'Not Yet Determined');
            text('completionDonorId', donorCode, '-');
            text('completionAddress', address, 'Address not recorded');
            text('completionContactNumber', donor.contact_number, 'Not recorded');
            text('completionLastDonationDate', displayDate(donor.last_donation_date), 'Not recorded');
            text('completionNextEligibleDate', displayDate(donor.next_eligible_date, 'Not set'), 'Not set');
            text('completionCurrentBloodType', currentType + ' (' + String(donor.blood_type_status || 'not yet determined').replace(/_/g, ' ') + ')', 'Not Yet Determined');

            var badge = document.getElementById('completionVerificationBadge');
            if (badge) {
                var status = String(donor.verification_status || 'unverified').toLowerCase();
                badge.textContent = verificationLabel();
                badge.className = 'digital-donor-id-card__badge digital-donor-id-card__badge--' + status;
            }

            bloodTypeSelect.setAttribute('data-current-id', donor.blood_type_id || '');
            bloodTypeSelect.setAttribute('data-current-status', donor.blood_type_status || 'not_yet_determined');
            updateMapReadiness();
        }

        function updateMapReadiness() {
            var reasons = [];
            if (String(donor.verification_status || 'unverified').toLowerCase() !== 'verified') reasons.push('identity verification must be approved');
            if (String(donor.blood_type_status || 'not_yet_determined').toLowerCase() !== 'verified') reasons.push('a laboratory-confirmed blood type must be recorded');
            if (!String(donor.barangay_name || '').trim()) reasons.push('a barangay must be present in the donor profile');
            if (donor.is_active === false || donor.is_active === 0) reasons.push('the donor account must be active');

            if (!mapReadiness) return;
            mapReadiness.classList.remove('d-none', 'alert-info', 'alert-warning', 'alert-success');
            if (reasons.length) {
                mapReadiness.classList.add('alert-warning');
                mapReadiness.textContent = verificationOnly
                    ? 'This completed donation will appear in donor availability only after ' + reasons.join(', ') + '. Select only the type shown by the laboratory result.'
                    : 'Map readiness: ' + reasons.join(', ') + '. A self-reported profile blood type alone is not used for the map. If a lab result is available, record it below; otherwise it can be verified later from the completed donation row.';
                return;
            }

            mapReadiness.classList.add('alert-success');
            mapReadiness.textContent = 'This donor meets the map’s identity, blood type, account, and barangay checks. The open map refreshes automatically.';
        }

        function populateBloodTypes() {
            var options = config.verificationBloodTypes || [];
            options.forEach(function (option) {
                var element = document.createElement('option');
                element.value = option.id;
                element.textContent = option.label;
                bloodTypeSelect.appendChild(element);
            });
            bloodTypeSelect.disabled = config.canVerifyBloodType !== true;
            document.getElementById('completionBloodTypeHelp').textContent = config.canVerifyBloodType === true
                ? 'Use the laboratory-confirmed result, not an unverified profile value.'
                : 'Only an administrator may record a verified blood type.';
        }

        function configureMode() {
            if (!verificationOnly) return;

            document.getElementById('completionContextTitle').textContent = 'Verify blood type for completed donation';
            document.getElementById('completionContextDescription').textContent = 'This donation is already saved. Record its laboratory-confirmed blood type without creating a duplicate donation.';
            document.getElementById('completionFormTitle').textContent = 'Record laboratory result';
            document.getElementById('completionFormDescription').textContent = 'This updates the donor’s verified blood type and map eligibility only; donation history and inventory will not be changed.';
            document.getElementById('completionCurrentStatus').innerHTML = '<span></span> Donation Completed';
            document.getElementById('completionBloodUnitsField').classList.add('d-none');
            document.getElementById('completionDonationDateField').classList.add('d-none');
            document.getElementById('completionRemarksField').classList.add('d-none');
            document.getElementById('completionBloodUnits').disabled = true;
            document.getElementById('completionBloodUnits').required = false;
            document.getElementById('completionDonationDate').disabled = true;
            document.getElementById('completionDonationDate').required = false;
            bloodTypeSelect.required = true;
            bloodTypeSelect.setAttribute('aria-describedby', 'completionBloodTypeHelp completionMapReadiness');
            document.getElementById('completionBloodTypeHelp').textContent = 'Required: select the result confirmed by the laboratory.';
            submitButton.innerHTML = '<i class="bi bi-shield-check me-1" aria-hidden="true"></i> Save Verified Blood Type';
        }

        function showStatus(message, kind) {
            completionStatus.className = 'alert alert-' + (kind || 'info');
            completionStatus.textContent = message;
            completionStatus.classList.remove('d-none');
        }

        function updateBloodTypeCorrectionFields() {
            var isDifferent = bloodTypeSelect.value !== ''
                && bloodTypeSelect.getAttribute('data-current-status') === 'verified'
                && bloodTypeSelect.value !== bloodTypeSelect.getAttribute('data-current-id');
            changeFields.classList.toggle('d-none', !isDifferent);
            if (!isDifferent) {
                document.getElementById('completionConfirmBloodTypeChange').checked = false;
                document.getElementById('completionBloodTypeChangeReason').value = '';
            }
        }

        function returnToProcessing(flag) {
            if (!api.returnUrl) return;

            var separator = api.returnUrl.indexOf('?') === -1 ? '?' : '&';
            window.location.replace(api.returnUrl + separator + encodeURIComponent(flag) + '=1');
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitButton.disabled = true;
            showStatus(verificationOnly ? 'Saving laboratory-confirmed blood type...' : 'Saving donation completion...', 'info');

            var requestBody = verificationOnly
                ? {
                    verified_blood_type_id: bloodTypeSelect.value || null,
                    confirm_blood_type_change: document.getElementById('completionConfirmBloodTypeChange').checked,
                    blood_type_change_reason: document.getElementById('completionBloodTypeChangeReason').value
                }
                : {
                    blood_units: document.getElementById('completionBloodUnits').value,
                    donation_date: document.getElementById('completionDonationDate').value,
                    verified_blood_type_id: bloodTypeSelect.value || null,
                    confirm_blood_type_change: document.getElementById('completionConfirmBloodTypeChange').checked,
                    blood_type_change_reason: document.getElementById('completionBloodTypeChangeReason').value,
                    remarks: document.getElementById('completionRemarks').value
                };

            fetch(verificationOnly ? api.verifyBloodTypeUrl : api.completeUrl, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify(requestBody)
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        if (!response.ok) throw body;
                        return body;
                    });
                })
                .then(function (body) {
                    if (body.donor) {
                        donor = body.donor;
                        renderDonor();
                    }
                    showStatus(body.message || (verificationOnly ? 'Verified blood type saved.' : 'Donation completed successfully.'), 'success');
                    submitButton.innerHTML = verificationOnly
                        ? '<i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Blood Type Verified'
                        : '<i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Donation Completed';
                    window.setTimeout(function () { returnToProcessing(verificationOnly ? 'verified' : 'completed'); }, 650);
                })
                .catch(function (error) {
                    var message = error && error.message ? error.message : 'The donation could not be completed.';
                    if (error && error.errors) {
                        var firstKey = Object.keys(error.errors)[0];
                        if (firstKey && error.errors[firstKey][0]) message = error.errors[firstKey][0];
                    }
                    showStatus(message, 'danger');
                    submitButton.disabled = false;
                });
        });

        bloodTypeSelect.addEventListener('change', updateBloodTypeCorrectionFields);

        document.getElementById('completionDonationDate').value = dateInputValue(appointment.appointment_date);
        configureMode();
        renderDonor();
        populateBloodTypes();
    }());
</script>
@endpush
