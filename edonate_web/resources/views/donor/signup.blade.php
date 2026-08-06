<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Donor Sign Up | Blood Donation Management System</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<style>
		:root {
			--health-red: #c62f3c;
			--health-red-soft: #fef1f2;
			--ink-900: #1f2937;
			--ink-700: #374151;
			--ink-500: #6b7280;
			--surface: #ffffff;
			--line-soft: #e5e7eb;
			--focus-ring: rgba(198, 47, 60, 0.28);
			--shadow-soft: 0 16px 40px rgba(17, 24, 39, 0.1);
		}

		body {
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
			background:
				radial-gradient(circle at 10% -10%, #ffe6e9 0, #ffe6e9 18%, transparent 40%),
				radial-gradient(circle at 90% -20%, #fff5f5 0, #fff5f5 15%, transparent 35%),
				#f8fafc;
			color: var(--ink-900);
			min-height: 100vh;
		}

		.signup-wrapper {
			min-height: 100vh;
			padding: 1.25rem 0.75rem;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.signup-card {
			background: var(--surface);
			border: 1px solid rgba(198, 47, 60, 0.12);
			border-radius: 1rem;
			box-shadow: var(--shadow-soft);
			width: 100%;
			max-width: 62rem;
			overflow: hidden;
			animation: cardIn 380ms ease-out;
		}

		@keyframes cardIn {
			from {
				opacity: 0;
				transform: translateY(12px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.card-header-custom {
			background: linear-gradient(135deg, #fff 0%, #fff7f8 100%);
			border-bottom: 1px solid var(--line-soft);
			padding: 1.5rem 1.25rem;
		}

		.card-header-custom h1 {
			font-size: 1.6rem;
			margin: 0;
			letter-spacing: 0.01em;
		}

		.card-header-custom p {
			margin: 0.5rem 0 0;
			color: var(--ink-500);
		}

		.section-title {
			font-size: 0.95rem;
			letter-spacing: 0.02em;
			text-transform: uppercase;
			font-weight: 700;
			color: var(--health-red);
			margin-bottom: 0.95rem;
		}

		.form-control,
		.form-select {
			border-color: #d1d5db;
			min-height: 2.75rem;
		}

		.form-control:focus,
		.form-select:focus,
		.form-check-input:focus {
			border-color: var(--health-red);
			box-shadow: 0 0 0 0.2rem var(--focus-ring);
		}

		.form-control:focus-visible,
		.form-select:focus-visible,
		.btn:focus-visible,
		.form-check-input:focus-visible,
		.link-danger:focus-visible {
			outline: 2px solid var(--health-red);
			outline-offset: 2px;
		}

		.input-group .btn-outline-secondary {
			border-color: #d1d5db;
			color: var(--ink-700);
		}

		.input-group .btn-outline-secondary:hover {
			background: #f3f4f6;
			color: var(--ink-900);
		}

		.helper-text {
			color: var(--ink-500);
			font-size: 0.85rem;
		}

		.helper-text.status-success {
			color: #166534;
		}

		.helper-text.status-error {
			color: #b91c1c;
		}

		.otp-code-input {
			letter-spacing: 0.45rem;
			font-size: 1.25rem;
			text-align: center;
			font-weight: 700;
		}

		.btn-register {
			background: var(--health-red);
			border: none;
			padding: 0.75rem 1rem;
			font-weight: 600;
		}

		.btn-register:hover,
		.btn-register:focus {
			background: #ab2431;
		}

		.agreement-box {
			background: var(--health-red-soft);
			border: 1px solid rgba(198, 47, 60, 0.2);
			border-radius: 0.7rem;
			padding: 0.85rem 0.95rem;
		}

		.stepper {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 0.6rem;
			margin-bottom: 1rem;
		}

		.step-pill {
			border: 1px solid #e5e7eb;
			border-radius: 999px;
			padding: 0.45rem 0.6rem;
			font-size: 0.82rem;
			text-align: center;
			color: var(--ink-500);
			background: #fff;
			font-weight: 600;
		}

		.step-pill.active {
			border-color: rgba(198, 47, 60, 0.55);
			background: #fff5f6;
			color: var(--health-red);
		}

		.step-pill.done {
			border-color: rgba(198, 47, 60, 0.4);
			color: #8f1f2b;
		}

		.signup-step {
			display: none;
			animation: stepIn 260ms ease;
		}

		.signup-step.active {
			display: block;
		}

		@keyframes stepIn {
			from {
				opacity: 0;
				transform: translateX(8px);
			}
			to {
				opacity: 1;
				transform: translateX(0);
			}
		}

		.step-actions {
			display: flex;
			gap: 0.75rem;
			justify-content: space-between;
			margin-top: 1rem;
		}

		.step-actions .btn {
			min-width: 8.5rem;
		}

		@media (min-width: 768px) {
			.signup-wrapper {
				padding: 2rem 1rem;
			}

			.card-header-custom {
				padding: 1.75rem 2rem;
			}

			.card-body-custom {
				padding: 1.75rem 2rem 2rem;
			}
		}
	</style>
</head>
<body>
<main class="signup-wrapper">
	<section class="signup-card" aria-labelledby="signup-title">
		<header class="card-header-custom">
			<h1 id="signup-title" class="fw-bold">Donor Sign-Up</h1>
			<p>Create your donor account and help save lives through safe blood donation.</p>
		</header>

		<div class="card-body-custom p-3 p-md-4">
			@if (session('success'))
				<div class="alert alert-success" role="status" aria-live="polite">
					{{ session('success') }}
				</div>
			@endif

			@if (session('error'))
				<div class="alert alert-danger" role="alert" aria-live="assertive">
					{{ session('error') }}
				</div>
			@endif

			<form method="POST" action="{{ route('donor.signup.store') }}" id="donorSignupForm" class="needs-validation" novalidate>
				@csrf

				<div class="stepper" aria-label="Signup steps">
					<div class="step-pill active" data-step-pill="1">1. Personal</div>
					<div class="step-pill" data-step-pill="2">2. Medical & Address</div>
					<div class="step-pill" data-step-pill="3">3. Security</div>
				</div>

				<div class="signup-step active" data-step="1">
					<h2 class="section-title">Step 1: Personal Information</h2>
					<div class="row g-3 mb-4">
						<div class="col-12 col-md-6">
							<label for="first_name" class="form-label">First Name</label>
							<input
								type="text"
								class="form-control @error('first_name') is-invalid @enderror"
								id="first_name"
								name="first_name"
								value="{{ old('first_name') }}"
								required
								maxlength="100"
								autocomplete="given-name"
								aria-invalid="@error('first_name') true @else false @enderror"
								aria-describedby="first_name_error"
							>
							@error('first_name')
							<div class="invalid-feedback" id="first_name_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="first_name_error">Please enter your first name.</div>
							@enderror
						</div>

						<div class="col-12 col-md-6">
							<label for="last_name" class="form-label">Last Name</label>
							<input
								type="text"
								class="form-control @error('last_name') is-invalid @enderror"
								id="last_name"
								name="last_name"
								value="{{ old('last_name') }}"
								required
								maxlength="100"
								autocomplete="family-name"
								aria-invalid="@error('last_name') true @else false @enderror"
								aria-describedby="last_name_error"
							>
							@error('last_name')
							<div class="invalid-feedback" id="last_name_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="last_name_error">Please enter your last name.</div>
							@enderror
						</div>

						<div class="col-12 col-md-6">
							<label for="email" class="form-label">Email Address</label>
							<input
								type="email"
								class="form-control @error('email') is-invalid @enderror"
								id="email"
								name="email"
								value="{{ old('email') }}"
								required
								maxlength="150"
								autocomplete="email"
								aria-invalid="@error('email') true @else false @enderror"
								aria-describedby="email_error"
							>
							@error('email')
							<div class="invalid-feedback" id="email_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="email_error">Please provide a valid email address.</div>
							@enderror
							<div class="helper-text mt-1 d-none" id="email_availability" aria-live="polite"></div>
						</div>

						<div class="col-12 col-md-6">
							<label for="phone" class="form-label">Phone Number</label>
							<input
								type="tel"
								class="form-control @error('phone') is-invalid @enderror"
								id="phone"
								name="phone"
								value="{{ old('phone') }}"
								required
								pattern="^(\+63|0)\d{10}$"
								inputmode="numeric"
								maxlength="13"
								autocomplete="tel"
								placeholder="09171234567 or +639171234567"
								aria-invalid="@error('phone') true @else false @enderror"
								aria-describedby="phone_help phone_error"
							>
							<div class="helper-text mt-1" id="phone_help">Use 11-digit local format (09...) or +63 format.</div>
							@error('phone')
							<div class="invalid-feedback" id="phone_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="phone_error">Phone number format is invalid.</div>
							@enderror
						</div>
					</div>

					<div class="step-actions">
						<span></span>
						<button type="button" class="btn btn-danger" data-next-step="2">Next Step</button>
					</div>
				</div>

				<div class="signup-step" data-step="2">
					<h2 class="section-title">Step 2: Medical and Address Details</h2>
					<div class="row g-3 mb-4">
						<div class="col-12 col-md-6">
							<label for="birthdate" class="form-label">Date of Birth</label>
							<input
								type="date"
								class="form-control @error('birthdate') is-invalid @enderror"
								id="birthdate"
								name="birthdate"
								value="{{ old('birthdate') }}"
								required
								max="{{ now()->format('Y-m-d') }}"
								autocomplete="bday"
								aria-invalid="@error('birthdate') true @else false @enderror"
								aria-describedby="birthdate_error"
							>
							@error('birthdate')
							<div class="invalid-feedback" id="birthdate_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="birthdate_error">Please provide a valid date of birth.</div>
							@enderror
						</div>

						<div class="col-12 col-md-6">
							<label for="gender" class="form-label">Gender</label>
							<select
								class="form-select @error('gender') is-invalid @enderror"
								id="gender"
								name="gender"
								required
								aria-invalid="@error('gender') true @else false @enderror"
								aria-describedby="gender_error"
							>
								<option value="" disabled {{ old('gender') ? '' : 'selected' }}>Select gender</option>
								<option value="Male" {{ old('gender') === 'Male' ? 'selected' : '' }}>Male</option>
								<option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
								<option value="Other" {{ old('gender') === 'Other' ? 'selected' : '' }}>Other</option>
								<option value="Prefer not to say" {{ old('gender') === 'Prefer not to say' ? 'selected' : '' }}>Prefer not to say</option>
							</select>
							@error('gender')
							<div class="invalid-feedback" id="gender_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="gender_error">Please select a gender.</div>
							@enderror
						</div>

						<div class="col-12 col-md-6">
							<label for="blood_type" class="form-label">Blood Type</label>
							<select
								class="form-select @error('blood_type') is-invalid @enderror"
								id="blood_type"
								name="blood_type"
								required
								aria-invalid="@error('blood_type') true @else false @enderror"
								aria-describedby="blood_type_error"
							>
								<option value="" disabled {{ old('blood_type') ? '' : 'selected' }}>Select blood type</option>
								@foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type)
									<option value="{{ $type }}" {{ old('blood_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
								@endforeach
							</select>
							@error('blood_type')
							<div class="invalid-feedback" id="blood_type_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="blood_type_error">Please select your blood type.</div>
							@enderror
						</div>
					</div>

					<div class="row g-3 mb-4">
						<div class="col-12">
							<label for="street_address" class="form-label">Street Address</label>
							<input
								type="text"
								class="form-control @error('street_address') is-invalid @enderror"
								id="street_address"
								name="street_address"
								value="{{ old('street_address') }}"
								required
								maxlength="150"
								autocomplete="street-address"
								aria-invalid="@error('street_address') true @else false @enderror"
								aria-describedby="street_address_error"
							>
							@error('street_address')
							<div class="invalid-feedback" id="street_address_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="street_address_error">Please provide your street address.</div>
							@enderror
						</div>

						<div class="col-12 col-md-4">
							<label for="barangay" class="form-label">Barangay</label>
							<input
								type="text"
								class="form-control @error('barangay') is-invalid @enderror"
								id="barangay"
								name="barangay"
								value="{{ old('barangay') }}"
								required
								maxlength="100"
								autocomplete="address-level3"
								aria-invalid="@error('barangay') true @else false @enderror"
								aria-describedby="barangay_error"
							>
							@error('barangay')
							<div class="invalid-feedback" id="barangay_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="barangay_error">Please enter your barangay.</div>
							@enderror
						</div>

						<div class="col-12 col-md-4">
							<label for="city" class="form-label">Municipality/City</label>
							<input
								type="text"
								class="form-control @error('city') is-invalid @enderror"
								id="city"
								name="city"
								value="{{ old('city') }}"
								required
								maxlength="100"
								autocomplete="address-level2"
								aria-invalid="@error('city') true @else false @enderror"
								aria-describedby="city_error"
							>
							@error('city')
							<div class="invalid-feedback" id="city_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="city_error">Please enter your municipality or city.</div>
							@enderror
						</div>

						<div class="col-12 col-md-4">
							<label for="province" class="form-label">Province</label>
							<input
								type="text"
								class="form-control @error('province') is-invalid @enderror"
								id="province"
								name="province"
								value="{{ old('province') }}"
								required
								maxlength="100"
								autocomplete="address-level1"
								aria-invalid="@error('province') true @else false @enderror"
								aria-describedby="province_error"
							>
							@error('province')
							<div class="invalid-feedback" id="province_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="province_error">Please enter your province.</div>
							@enderror
						</div>
					</div>

					<div class="step-actions">
						<button type="button" class="btn btn-outline-secondary" data-prev-step="1">Back</button>
						<button type="button" class="btn btn-danger" data-next-step="3">Next Step</button>
					</div>
				</div>

				<div class="signup-step" data-step="3">
					<h2 class="section-title">Step 3: Security and Consent</h2>
					<div class="row g-3 mb-3">
						<div class="col-12 col-md-6">
							<label for="password" class="form-label">Password</label>
							<div class="input-group">
								<input
									type="password"
									class="form-control @error('password') is-invalid @enderror"
									id="password"
									name="password"
									required
									minlength="8"
									autocomplete="new-password"
									aria-invalid="@error('password') true @else false @enderror"
									aria-describedby="password_help password_error"
								>
								<button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password" aria-controls="password">
									Show
								</button>
								@error('password')
								<div class="invalid-feedback d-block" id="password_error">{{ $message }}</div>
								@else
								<div class="invalid-feedback" id="password_error">Password must be valid and at least 8 characters.</div>
								@enderror
							</div>
							<div class="helper-text mt-1" id="password_help">
								Must be at least 8 characters and include one uppercase letter and one number.
							</div>
						</div>

						<div class="col-12 col-md-6">
							<label for="password_confirmation" class="form-label">Confirm Password</label>
							<div class="input-group">
								<input
									type="password"
									class="form-control"
									id="password_confirmation"
									name="password_confirmation"
									required
									autocomplete="new-password"
									aria-describedby="password_confirmation_error"
								>
								<button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm" aria-label="Show confirm password" aria-controls="password_confirmation">
									Show
								</button>
								<div class="invalid-feedback" id="password_confirmation_error">Passwords do not match.</div>
							</div>
						</div>
					</div>

					<div class="agreement-box mb-4">
						<div class="form-check">
							<input
								class="form-check-input @error('terms') is-invalid @enderror"
								type="checkbox"
								value="1"
								id="terms"
								name="terms"
								{{ old('terms') ? 'checked' : '' }}
								required
								aria-invalid="@error('terms') true @else false @enderror"
								aria-describedby="terms_error"
							>
							<label class="form-check-label" for="terms">
								I agree to the
								<a href="#" role="button" class="btn btn-link p-0 align-baseline link-danger" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a>
								and
								<a href="#" role="button" class="btn btn-link p-0 align-baseline link-danger" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>.
							</label>
							@error('terms')
							<div class="invalid-feedback d-block" id="terms_error">{{ $message }}</div>
							@else
							<div class="invalid-feedback" id="terms_error">You must agree before continuing.</div>
							@enderror
						</div>
					</div>

					<div class="step-actions">
						<button type="button" class="btn btn-outline-secondary" data-prev-step="2">Back</button>
						<button type="submit" class="btn btn-danger btn-register" id="registerButton">Register</button>
					</div>
				</div>

				<div id="otpFlowFeedback" class="alert d-none" role="alert" aria-live="assertive"></div>

				<p class="mb-0 text-center text-secondary">
					Already have an account?
					<a href="{{ url('/login') }}" class="link-danger fw-semibold">Log in</a>
				</p>
			</form>
		</div>
	</section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
	document.addEventListener('DOMContentLoaded', function () {
		'use strict';

		var form = document.getElementById('donorSignupForm');
		var csrfToken = form.querySelector('input[name="_token"]').value;
		var registerButton = document.getElementById('registerButton');
		var feedbackBox = document.getElementById('otpFlowFeedback');
		var otpModalElement = document.getElementById('otpModal');
		var otpModal = new bootstrap.Modal(otpModalElement);
		var otpInput = document.getElementById('otp_code');
		var confirmOtpButton = document.getElementById('confirmOtpButton');
		var otpError = document.getElementById('otp_error');
		var otpSubmitFeedback = document.getElementById('otp_submit_feedback');
		var sendOtpUrl = "{{ route('donor.signup.send-otp') }}";
		var confirmOtpUrl = "{{ route('donor.signup.confirm-otp') }}";
		var checkEmailUrl = "{{ route('donor.signup.check-email') }}";
		var passwordInput = document.getElementById('password');
		var confirmPasswordInput = document.getElementById('password_confirmation');
		var phoneInput = document.getElementById('phone');
		var emailInput = document.getElementById('email');
		var emailError = document.getElementById('email_error');
		var emailAvailability = document.getElementById('email_availability');
		var stepPanels = Array.prototype.slice.call(document.querySelectorAll('.signup-step'));
		var stepPills = Array.prototype.slice.call(document.querySelectorAll('[data-step-pill]'));
		var nextStepButtons = Array.prototype.slice.call(document.querySelectorAll('[data-next-step]'));
		var prevStepButtons = Array.prototype.slice.call(document.querySelectorAll('[data-prev-step]'));
		var currentStep = 1;
		var emailCheckTimer = null;
		var lastCheckedEmail = '';
		var emailCheckState = 'idle';

		function stepFields(step) {
			if (step === 1) {
				return ['first_name', 'last_name', 'email', 'phone'];
			}

			if (step === 2) {
				return ['birthdate', 'gender', 'blood_type', 'street_address', 'barangay', 'city', 'province'];
			}

			return ['password', 'password_confirmation', 'terms'];
		}

		function getFieldElement(name) {
			return form.querySelector('[name="' + name + '"]');
		}

		function setActiveStep(step) {
			currentStep = step;

			stepPanels.forEach(function (panel) {
				var isActive = Number(panel.getAttribute('data-step')) === step;
				panel.classList.toggle('active', isActive);
			});

			stepPills.forEach(function (pill) {
				var pillStep = Number(pill.getAttribute('data-step-pill'));
				pill.classList.toggle('active', pillStep === step);
				pill.classList.toggle('done', pillStep < step);
			});
		}

		function setEmailAvailabilityState(type, message) {
			emailAvailability.classList.remove('d-none', 'status-success', 'status-error');
			emailAvailability.textContent = message || '';

			if (type === 'success') {
				emailAvailability.classList.add('status-success');
				return;
			}

			emailAvailability.classList.add('status-error');
		}

		function clearEmailAvailabilityState() {
			emailAvailability.classList.add('d-none');
			emailAvailability.classList.remove('status-success', 'status-error');
			emailAvailability.textContent = '';
		}

		function canCheckEmail() {
			if (!emailInput) {
				return false;
			}

			return emailInput.value.trim() !== '' && emailInput.checkValidity();
		}

		function runEmailAvailabilityCheck(force) {
			if (!emailInput) {
				return Promise.resolve(true);
			}

			var emailValue = emailInput.value.trim();

			if (!canCheckEmail()) {
				emailCheckState = 'idle';
				lastCheckedEmail = '';
				emailInput.setCustomValidity('');
				clearEmailAvailabilityState();
				return Promise.resolve(false);
			}

			if (!force && emailValue === lastCheckedEmail && emailCheckState === 'available') {
				return Promise.resolve(true);
			}

			if (!force && emailValue === lastCheckedEmail && emailCheckState === 'taken') {
				return Promise.resolve(false);
			}

			emailCheckState = 'checking';
			setEmailAvailabilityState('error', 'Checking email availability...');

			return fetch(checkEmailUrl + '?email=' + encodeURIComponent(emailValue), {
				method: 'GET',
				headers: {
					'Accept': 'application/json'
				}
			})
				.then(function (response) {
					return parseJsonSafely(response).then(function (data) {
						return {
							ok: response.ok,
							data: data
						};
					});
				})
				.then(function (result) {
					lastCheckedEmail = emailValue;

					if (!result.ok || !result.data.available) {
						emailCheckState = 'taken';
						emailInput.setCustomValidity('This email is already registered.');
						if (emailError) {
							emailError.textContent = result.data.message || 'This email is already registered.';
						}
						setEmailAvailabilityState('error', result.data.message || 'This email is already registered.');
						return false;
					}

					emailCheckState = 'available';
					emailInput.setCustomValidity('');
					setEmailAvailabilityState('success', result.data.message || 'Email is available.');
					return true;
				})
				.catch(function () {
					emailCheckState = 'idle';
					emailInput.setCustomValidity('');
					setEmailAvailabilityState('error', 'Could not verify email right now. You can still continue.');
					return true;
				});
		}

		function validateStep(step) {
			var valid = true;

			if (step === 1) {
				validatePhone();
			}

			if (step === 3) {
				validatePasswordRules();
				validateConfirmPassword();
			}

			stepFields(step).forEach(function (name) {
				var field = getFieldElement(name);

				if (!field) {
					return;
				}

				field.classList.add('was-validated');

				if (!field.checkValidity()) {
					valid = false;
				}
			});

			return valid;
		}

		function firstInvalidStep() {
			for (var step = 1; step <= 3; step += 1) {
				var hasInvalid = stepFields(step).some(function (name) {
					var field = getFieldElement(name);

					if (!field) {
						return false;
					}

					return !field.checkValidity() || field.classList.contains('is-invalid');
				});

				if (hasInvalid) {
					return step;
				}
			}

			return 1;
		}

		function showFeedback(type, message) {
			feedbackBox.className = 'alert alert-' + type;
			feedbackBox.textContent = message;
			feedbackBox.classList.remove('d-none');
		}

		function clearFeedback() {
			feedbackBox.className = 'alert d-none';
			feedbackBox.textContent = '';
		}

		function setRegisterButtonLoading(isLoading) {
			registerButton.disabled = isLoading;
			registerButton.textContent = isLoading ? 'Sending OTP...' : 'Register';
		}

		function clearServerFieldErrors() {
			Array.prototype.slice.call(form.querySelectorAll('.is-invalid')).forEach(function (field) {
				field.classList.remove('is-invalid');
			});
		}

		function applyServerFieldErrors(errors) {
			Object.keys(errors).forEach(function (name) {
				var field = form.querySelector('[name="' + name + '"]');

				if (!field) {
					return;
				}

				field.classList.add('is-invalid');
				field.classList.add('was-validated');

				var messageElement = document.getElementById(field.id + '_error');
				if (messageElement && Array.isArray(errors[name]) && errors[name].length > 0) {
					messageElement.textContent = errors[name][0];
				}
			});
		}

		function parseJsonSafely(response) {
			return response.json().catch(function () {
				return {};
			});
		}

		function validatePasswordRules() {
			var value = passwordInput.value;
			var meetsLength = value.length >= 8;
			var hasUppercase = /[A-Z]/.test(value);
			var hasNumber = /\d/.test(value);

			if (!meetsLength || !hasUppercase || !hasNumber) {
				passwordInput.setCustomValidity('Password must include at least 8 characters, one uppercase letter, and one number.');
			} else {
				passwordInput.setCustomValidity('');
			}
		}

		function validateConfirmPassword() {
			if (confirmPasswordInput.value !== passwordInput.value) {
				confirmPasswordInput.setCustomValidity('Passwords do not match.');
			} else {
				confirmPasswordInput.setCustomValidity('');
			}
		}

		function validatePhone() {
			var phonePattern = /^(\+63|0)\d{10}$/;

			if (!phonePattern.test(phoneInput.value.trim())) {
				phoneInput.setCustomValidity('Please enter a valid phone number format.');
			} else {
				phoneInput.setCustomValidity('');
			}
		}

		function togglePasswordVisibility(buttonId, inputId) {
			var button = document.getElementById(buttonId);
			var input = document.getElementById(inputId);

			button.addEventListener('click', function () {
				var isPassword = input.getAttribute('type') === 'password';
				input.setAttribute('type', isPassword ? 'text' : 'password');
				button.textContent = isPassword ? 'Hide' : 'Show';
				button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
			});
		}

		nextStepButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				var targetStep = Number(button.getAttribute('data-next-step'));

				if (!validateStep(currentStep)) {
					return;
				}

				if (currentStep === 1) {
					runEmailAvailabilityCheck(true).then(function (available) {
						if (!available) {
							emailInput.classList.add('was-validated');
							return;
						}

						setActiveStep(targetStep);
					});
					return;
				}

				setActiveStep(targetStep);
			});
		});

		prevStepButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				var targetStep = Number(button.getAttribute('data-prev-step'));
				setActiveStep(targetStep);
			});
		});

		passwordInput.addEventListener('input', function () {
			validatePasswordRules();
			validateConfirmPassword();
			passwordInput.classList.add('was-validated');
		});

		confirmPasswordInput.addEventListener('input', function () {
			validateConfirmPassword();
			confirmPasswordInput.classList.add('was-validated');
		});

		phoneInput.addEventListener('input', function () {
			validatePhone();
			phoneInput.classList.add('was-validated');
		});

		emailInput.addEventListener('input', function () {
			emailInput.setCustomValidity('');
			emailCheckState = 'idle';
			lastCheckedEmail = '';
			clearTimeout(emailCheckTimer);
			clearEmailAvailabilityState();

			if (!canCheckEmail()) {
				return;
			}

			emailCheckTimer = setTimeout(function () {
				runEmailAvailabilityCheck(false);
			}, 450);
		});

		Array.prototype.slice.call(form.elements).forEach(function (field) {
			if (!field || !field.addEventListener || field.type === 'hidden') {
				return;
			}

			field.addEventListener('blur', function () {
				field.classList.add('was-validated');
				validatePasswordRules();
				validateConfirmPassword();
				validatePhone();
			});
		});

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			event.stopPropagation();

			validatePasswordRules();
			validateConfirmPassword();
			validatePhone();
			clearFeedback();
			clearServerFieldErrors();

			runEmailAvailabilityCheck(true).then(function (emailAvailable) {
				if (!emailAvailable) {
					setActiveStep(1);
					form.classList.add('was-validated');
					return;
				}

				if (!form.checkValidity()) {
					setActiveStep(firstInvalidStep());
					form.classList.add('was-validated');
					return;
				}

				form.classList.add('was-validated');
				setRegisterButtonLoading(true);

				fetch(sendOtpUrl, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': csrfToken,
						'Accept': 'application/json'
					},
					body: new FormData(form)
				})
					.then(function (response) {
						return parseJsonSafely(response).then(function (data) {
							return {
								ok: response.ok,
								status: response.status,
								data: data
							};
						});
					})
					.then(function (result) {
						if (!result.ok) {
							if (result.data.errors) {
								applyServerFieldErrors(result.data.errors);
							}

							showFeedback('danger', result.data.message || 'Unable to send OTP. Please review the form and try again.');
							setActiveStep(firstInvalidStep());
							return;
						}

						showFeedback('success', result.data.message || 'OTP sent. Please check your email.');
						otpInput.value = '';
						otpInput.classList.remove('is-invalid');
						otpError.textContent = 'Please enter the 6-digit code.';
						otpSubmitFeedback.classList.add('d-none');
						otpSubmitFeedback.textContent = '';
						otpModal.show();
					})
					.catch(function () {
						showFeedback('danger', 'Unable to send OTP right now. Please try again.');
					})
					.finally(function () {
						setRegisterButtonLoading(false);
					});
			});
		}, false);

		confirmOtpButton.addEventListener('click', function () {
			var otpValue = otpInput.value.trim();

			otpInput.classList.remove('is-invalid');
			otpSubmitFeedback.classList.add('d-none');
			otpSubmitFeedback.textContent = '';

			if (!/^\d{6}$/.test(otpValue)) {
				otpInput.classList.add('is-invalid');
				otpError.textContent = 'OTP must be exactly 6 digits.';
				return;
			}

			confirmOtpButton.disabled = true;
			confirmOtpButton.textContent = 'Verifying...';

			fetch(confirmOtpUrl, {
				method: 'POST',
				headers: {
					'X-CSRF-TOKEN': csrfToken,
					'Accept': 'application/json',
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({ otp: otpValue })
			})
				.then(function (response) {
					return parseJsonSafely(response).then(function (data) {
						return {
							ok: response.ok,
							status: response.status,
							data: data
						};
					});
				})
				.then(function (result) {
					if (!result.ok) {
						otpInput.classList.add('is-invalid');
						otpError.textContent = result.data.message || 'OTP verification failed.';
						return;
					}

					otpModal.hide();
					showFeedback('success', result.data.message || 'Registration completed successfully. Redirecting...');

					setTimeout(function () {
						window.location.href = result.data.redirect_url || "{{ url('/login') }}";
					}, 700);
				})
				.catch(function () {
					otpInput.classList.add('is-invalid');
					otpError.textContent = 'Unable to verify OTP right now. Please try again.';
				})
				.finally(function () {
					confirmOtpButton.disabled = false;
					confirmOtpButton.textContent = 'Confirm OTP';
				});
		});

		togglePasswordVisibility('togglePassword', 'password');
		togglePasswordVisibility('togglePasswordConfirm', 'password_confirmation');
		setActiveStep(firstInvalidStep());
	});
</script>

<!-- OTP Modal -->
<div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title text-danger" id="otpModalLabel">Email Verification</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="text-secondary mb-3">Enter the 6-digit OTP sent to your email address to complete registration.</p>
				<label for="otp_code" class="form-label">One-Time Password</label>
				<input type="text" class="form-control otp-code-input" id="otp_code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" aria-describedby="otp_error otp_submit_feedback">
				<div class="invalid-feedback" id="otp_error">Please enter the 6-digit code.</div>
				<div class="alert alert-danger mt-3 d-none" id="otp_submit_feedback" role="alert"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger" id="confirmOtpButton">Confirm OTP</button>
			</div>
		</div>
	</div>
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title text-danger" id="termsModalLabel">Terms of Service</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="text-secondary">Please replace this placeholder with your official Terms of Service.</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title text-danger" id="privacyModalLabel">Privacy Policy</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="text-secondary">Please replace this placeholder with your official Privacy Policy.</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
</body>
</html>
