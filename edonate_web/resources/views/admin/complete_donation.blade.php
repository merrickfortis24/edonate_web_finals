@extends('layouts.admin')

@section('title', 'eDonate - Complete Donation')
@section('admin_page_class', 'admin-complete-donation-page')
@section('header_title', 'Complete Donation')
@section('header_subtitle', 'Securely record the checked-in donor\'s completed donation')

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
            <h2>Finish the donation record</h2>
            <p>Capture a clear donor photo, review the Digital ID, then save the donation outcome.</p>
        </div>
        <div class="completion-context-bar__meta">
            <span class="completion-appointment-code" id="completionAppointmentCode">-</span>
            <span class="completion-context-bar__secure"><i class="bi bi-shield-check" aria-hidden="true"></i> Secure admin workspace</span>
        </div>
    </section>

    <div class="completion-steps" aria-label="Donation completion steps">
        <div class="completion-step completion-step--active">
            <span class="completion-step__number">01</span>
            <span><small>PHOTO</small><strong>Capture</strong></span>
        </div>
        <div class="completion-step__line" aria-hidden="true"></div>
        <div class="completion-step">
            <span class="completion-step__number">02</span>
            <span><small>IDENTITY</small><strong>Review ID</strong></span>
        </div>
        <div class="completion-step__line" aria-hidden="true"></div>
        <div class="completion-step">
            <span class="completion-step__number">03</span>
            <span><small>RECORD</small><strong>Complete</strong></span>
        </div>
    </div>

    <div class="completion-layout">
        <section class="completion-panel completion-camera-panel" aria-labelledby="cameraPanelTitle">
            <div class="completion-panel__heading">
                <div class="completion-section-heading">
                    <span class="completion-section-number">01</span>
                    <div>
                        <span class="completion-kicker">Photo capture</span>
                        <h2 id="cameraPanelTitle" class="h5 mb-1">Capture donor photo</h2>
                        <p class="text-body-secondary small mb-0">Use a front-facing photo with the donor centered in the frame.</p>
                    </div>
                </div>
                <span class="completion-panel-tag"><i class="bi bi-camera" aria-hidden="true"></i> Camera</span>
            </div>

            <div class="completion-camera__preview" id="donorCameraPreviewShell">
                <div class="completion-camera__frame" aria-hidden="true"></div>
                <video id="donorCameraVideo" class="d-none" autoplay playsinline muted aria-label="Donor camera preview"></video>
                <img id="donorCameraImage" class="d-none" alt="Captured donor photo preview">
                <div id="donorCameraPlaceholder" class="completion-camera__placeholder">
                    <span class="completion-camera__placeholder-icon"><i class="bi bi-person-bounding-box" aria-hidden="true"></i></span>
                    <strong>Ready for a donor photo</strong>
                    <span>Enable the camera to begin</span>
                </div>
            </div>

            <div class="completion-camera__controls" aria-label="Camera controls">
                <button class="btn btn-danger" id="startDonorCamera" type="button">
                    <i class="bi bi-camera-video me-1" aria-hidden="true"></i>
                    Enable Camera
                </button>
                <button class="btn btn-outline-danger" id="captureDonorPhoto" type="button" disabled>
                    <i class="bi bi-camera me-1" aria-hidden="true"></i>
                    Take Photo
                </button>
                <button class="btn btn-outline-secondary d-none" id="retakeDonorPhoto" type="button">
                    Retake Photo
                </button>
            </div>
            <div class="completion-camera__status" id="cameraStatus" role="status" aria-live="polite">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>Camera access requires permission and works on HTTPS or localhost.</span>
            </div>
        </section>

        <section class="completion-panel completion-id-panel" aria-labelledby="digitalIdTitle">
            <div class="completion-panel__heading">
                <div class="completion-section-heading">
                    <span class="completion-section-number">02</span>
                    <div>
                        <span class="completion-kicker">Identity preview</span>
                        <h2 id="digitalIdTitle" class="h5 mb-1">Digital Donor ID</h2>
                        <p class="text-body-secondary small mb-0">The photo and donor details appear here before saving.</p>
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
                        <div class="digital-donor-id-card__avatar edonate-user-avatar" id="completionPhotoFallback" aria-hidden="true">--</div>
                        <img class="completion-id-card__photo d-none" id="completionPhotoCard" alt="Donor photo on Digital Donor ID">
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
                    <p class="text-body-secondary small mb-0">Confirm the details below to finish this checked-in appointment.</p>
                </div>
            </div>
            <span class="completion-status-chip" id="completionCurrentStatus"><span></span> Checked In</span>
        </div>

        <form id="completeDonationWindowForm">
            <div class="completion-form-grid">
                <div class="completion-form-field">
                    <label class="form-label" for="completionBloodUnits">Blood Units</label>
                    <input class="form-control" id="completionBloodUnits" type="number" min="1" max="10" value="1" required>
                </div>
                <div class="completion-form-field">
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
                <div class="completion-form-field--wide d-none" id="completionBloodTypeChangeFields">
                    <div class="alert alert-warning py-2 small mb-2">This result differs from the donor's current verified blood type. Confirm the correction and record its reason.</div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="completionConfirmBloodTypeChange" type="checkbox" value="1">
                        <label class="form-check-label" for="completionConfirmBloodTypeChange">I confirm this verified blood type correction.</label>
                    </div>
                    <label class="form-label" for="completionBloodTypeChangeReason">Reason for change</label>
                    <textarea class="form-control" id="completionBloodTypeChangeReason" rows="2" maxlength="1000"></textarea>
                </div>
                <div class="completion-form-field--wide">
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
        var stream = null;
        var capturedPhoto = '';

        var video = document.getElementById('donorCameraVideo');
        var cameraImage = document.getElementById('donorCameraImage');
        var cameraPlaceholder = document.getElementById('donorCameraPlaceholder');
        var cameraStatus = document.getElementById('cameraStatus');
        var startCameraButton = document.getElementById('startDonorCamera');
        var captureButton = document.getElementById('captureDonorPhoto');
        var retakeButton = document.getElementById('retakeDonorPhoto');
        var cardPhoto = document.getElementById('completionPhotoCard');
        var cardPhotoFallback = document.getElementById('completionPhotoFallback');
        var completionStatus = document.getElementById('completionStatus');
        var form = document.getElementById('completeDonationWindowForm');
        var submitButton = document.getElementById('completeDonationSubmit');
        var bloodTypeSelect = document.getElementById('completionVerifiedBloodType');
        var changeFields = document.getElementById('completionBloodTypeChangeFields');

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
                ? 'Only enter a confirmed laboratory result.'
                : 'Only an administrator may record a verified blood type.';
        }

        function showStatus(message, kind) {
            completionStatus.className = 'alert alert-' + (kind || 'info');
            completionStatus.textContent = message;
            completionStatus.classList.remove('d-none');
        }

        function setCameraStatus(message) {
            var messageElement = cameraStatus ? cameraStatus.querySelector('span') : null;
            if (messageElement) {
                messageElement.textContent = message;
            } else if (cameraStatus) {
                cameraStatus.textContent = message;
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
            }
            if (video) video.srcObject = null;
            captureButton.disabled = true;
        }

        function startCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                setCameraStatus('This browser does not support camera access. Use the initials placeholder instead.');
                return;
            }

            stopCamera();
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
                .then(function (mediaStream) {
                    stream = mediaStream;
                    video.srcObject = mediaStream;
                    video.classList.remove('d-none');
                    cameraImage.classList.add('d-none');
                    cameraPlaceholder.classList.add('d-none');
                    captureButton.disabled = false;
                    retakeButton.classList.add('d-none');
                    startCameraButton.innerHTML = '<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Restart Camera';
                    setCameraStatus('Camera is ready. Center the donor and take a photo.');
                })
                .catch(function () {
                    setCameraStatus('Camera permission was unavailable. You can continue with the initials placeholder.');
                });
        }

        function capturePhoto() {
            if (!stream || !video.videoWidth) return;
            var canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            capturedPhoto = canvas.toDataURL('image/jpeg', 0.88);
            cameraImage.src = capturedPhoto;
            cardPhoto.src = capturedPhoto;
            cameraImage.classList.remove('d-none');
            cardPhoto.classList.remove('d-none');
            cardPhotoFallback.classList.add('d-none');
            video.classList.add('d-none');
            cameraPlaceholder.classList.add('d-none');
            retakeButton.classList.remove('d-none');
            setCameraStatus('Photo captured for this Digital ID window.');
            stopCamera();
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

        function returnToProcessing() {
            if (!api.returnUrl) return;

            var separator = api.returnUrl.indexOf('?') === -1 ? '?' : '&';
            window.location.replace(api.returnUrl + separator + 'completed=1');
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitButton.disabled = true;
            showStatus('Saving donation completion...', 'info');

            fetch(api.completeUrl, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({
                    blood_units: document.getElementById('completionBloodUnits').value,
                    donation_date: document.getElementById('completionDonationDate').value,
                    verified_blood_type_id: bloodTypeSelect.value || null,
                    confirm_blood_type_change: document.getElementById('completionConfirmBloodTypeChange').checked,
                    blood_type_change_reason: document.getElementById('completionBloodTypeChangeReason').value,
                    remarks: document.getElementById('completionRemarks').value
                })
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
                    showStatus(body.message || 'Donation completed successfully.', 'success');
                    submitButton.innerHTML = '<i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Donation Completed';
                    window.setTimeout(returnToProcessing, 650);
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

        startCameraButton.addEventListener('click', startCamera);
        captureButton.addEventListener('click', capturePhoto);
        retakeButton.addEventListener('click', function () {
            capturedPhoto = '';
            cameraImage.removeAttribute('src');
            cardPhoto.removeAttribute('src');
            cameraImage.classList.add('d-none');
            cardPhoto.classList.add('d-none');
            cardPhotoFallback.classList.remove('d-none');
            startCamera();
        });
        bloodTypeSelect.addEventListener('change', updateBloodTypeCorrectionFields);
        window.addEventListener('beforeunload', stopCamera);

        document.getElementById('completionDonationDate').value = dateInputValue(appointment.appointment_date);
        renderDonor();
        populateBloodTypes();
    }());
</script>
@endpush
