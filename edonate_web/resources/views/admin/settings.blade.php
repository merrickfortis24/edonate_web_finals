@extends('layouts.admin')

@section('title', 'eDonate - Admin Settings')
@section('admin_page_class', 'admin-settings-page')
@section('header_title', 'Settings')
@section('header_subtitle', 'Manage system configuration, security, and preferences')

@section('header_actions')
	<div class="mt-2 mt-md-0">
		<span class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-danger-subtle text-danger-emphasis fw-semibold text-nowrap">
			<i class="bi bi-sliders" aria-hidden="true"></i>
			<span>Admin Control Panel</span>
		</span>
	</div>
@endsection

@section('admin_page_data')
{!! json_encode($settingsPayload ?? [
	'page' => 'settings',
	'settings' => [
		'account' => [
			'updateUrl' => route('admin.settings.account.update'),
		],
		'notifications' => [
			'email' => false,
			'emailAddress' => '',
			'updateUrl' => route('admin.settings.notifications.update'),
		],
		'security' => [
			'twoFactor' => false,
			'sessionTimeout' => '10',
			'twoFactorSetupUrl' => route('admin.2fa.setup'),
			'updateSecurityUrl' => route('admin.settings.security.update'),
			'currentAccountTwoFactorEnabled' => false,
		],
		'privacyLegal' => [
			'privacyPolicy' => null,
			'termsAndConditions' => null,
			'cookiePolicy' => null,
			'enforceCookieConsentBanner' => true,
			'updateUrl' => route('admin.settings.privacy-legal.update'),
		],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	@php
		$errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
		$privacyLegalHasValidationErrors = $errors->hasAny([
				'privacy_policy',
				'terms_and_conditions',
				'cookie_policy',
				'enforce_cookie_consent_banner',
			]);
		$privacyLegalTabActive = session('settings_tab') === 'privacy-legal'
			|| $privacyLegalHasValidationErrors;
	@endphp
	<div class="container-fluid px-0">
		<div id="settingsAlertHost" class="mb-3" aria-live="polite">
			@if (session('success'))
				<div class="alert alert-success" role="status">{{ session('success') }}</div>
			@elseif (session('error'))
				<div class="alert alert-danger" role="alert">{{ session('error') }}</div>
			@endif
		</div>

		<section class="card settings-shell border-0 shadow-sm" aria-label="Admin settings sections">
			<div class="card-header bg-transparent border-bottom p-0">
				<ul class="nav nav-tabs card-header-tabs settings-tabs flex-nowrap overflow-auto px-3 pt-3" id="settingsTabs" role="tablist">
					<li class="nav-item" role="presentation">
						<button class="nav-link{{ $privacyLegalTabActive ? '' : ' active' }}" id="settings-account-tab" data-bs-toggle="tab" data-bs-target="#settings-account-pane" type="button" role="tab" aria-controls="settings-account-pane" aria-selected="{{ $privacyLegalTabActive ? 'false' : 'true' }}">Account</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="settings-notifications-tab" data-bs-toggle="tab" data-bs-target="#settings-notifications-pane" type="button" role="tab" aria-controls="settings-notifications-pane" aria-selected="false">Notifications</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="settings-security-tab" data-bs-toggle="tab" data-bs-target="#settings-security-pane" type="button" role="tab" aria-controls="settings-security-pane" aria-selected="false">Security</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link{{ $privacyLegalTabActive ? ' active' : '' }}" id="settings-privacy-legal-tab" data-bs-toggle="tab" data-bs-target="#settings-privacy-legal-pane" type="button" role="tab" aria-controls="settings-privacy-legal-pane" aria-selected="{{ $privacyLegalTabActive ? 'true' : 'false' }}">Privacy &amp; Legal</button>
					</li>
				</ul>
			</div>

			<div class="card-body p-3 p-lg-4">
				<div class="tab-content settings-tab-content" id="settingsTabsContent">
					<div class="tab-pane fade{{ $privacyLegalTabActive ? '' : ' show active' }}" id="settings-account-pane" role="tabpanel" aria-labelledby="settings-account-tab" tabindex="0">
						<article class="card settings-card border-0 shadow-none">
							<header class="card-header settings-card__header bg-transparent border-0 px-0 d-flex align-items-start gap-3">
								<span class="settings-card__icon d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-danger-subtle text-danger-emphasis" style="width: 2.5rem; height: 2.5rem;" aria-hidden="true">
									<i class="bi bi-person fs-5"></i>
								</span>
								<div>
									<h2 class="h5 mb-1 settings-card__title">Account Settings</h2>
									<p class="small text-body-secondary mb-0 settings-card__subtitle">Update your password securely with confirmation checks.</p>
								</div>
							</header>

							<div class="card-body px-0 pb-0">
							<form id="accountSettingsForm" class="settings-form needs-validation" novalidate>
								<div class="row g-3">
									<div class="col-12 col-lg-6">
										<label class="form-label" for="settingsCurrentPasswordInput">Current Password</label>
										<div class="input-group">
											<input type="password" class="form-control" id="settingsCurrentPasswordInput" required>
											<button class="btn btn-outline-secondary settings-password-toggle" type="button" data-target="settingsCurrentPasswordInput" aria-label="Show or hide current password">Show</button>
											<div class="invalid-feedback">Current password is required.</div>
										</div>
									</div>

									<div class="col-12 col-lg-6">
										<label class="form-label" for="settingsNewPasswordInput">New Password</label>
										<div class="input-group">
											<input type="password" class="form-control" id="settingsNewPasswordInput" required minlength="8">
											<button class="btn btn-outline-secondary settings-password-toggle" type="button" data-target="settingsNewPasswordInput" aria-label="Show or hide new password">Show</button>
											<div class="invalid-feedback">New password must be at least 8 characters.</div>
										</div>
									</div>

									<div class="col-12 col-lg-6">
										<label class="form-label" for="settingsConfirmPasswordInput">Confirm New Password</label>
										<div class="input-group">
											<input type="password" class="form-control" id="settingsConfirmPasswordInput" required minlength="8">
											<button class="btn btn-outline-secondary settings-password-toggle" type="button" data-target="settingsConfirmPasswordInput" aria-label="Show or hide confirm password">Show</button>
											<div class="invalid-feedback" id="settingsConfirmPasswordFeedback">Please confirm your new password.</div>
										</div>
									</div>
								</div>

								<div class="settings-actions d-flex justify-content-end mt-4 pt-3 border-top">
									<button class="btn settings-btn settings-btn--primary" id="saveAccountSettingsBtn" type="submit">
										Update Password
									</button>
								</div>
							</form>
							</div>
						</article>
					</div>

					<div class="tab-pane fade" id="settings-notifications-pane" role="tabpanel" aria-labelledby="settings-notifications-tab" tabindex="0">
						<article class="card settings-card border-0 shadow-none">
							<header class="card-header settings-card__header bg-transparent border-0 px-0 d-flex align-items-start gap-3">
								<span class="settings-card__icon d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-danger-subtle text-danger-emphasis" style="width: 2.5rem; height: 2.5rem;" aria-hidden="true">
									<i class="bi bi-bell fs-5"></i>
								</span>
								<div>
									<h2 class="h5 mb-1 settings-card__title">Notification Settings</h2>
									<p class="small text-body-secondary mb-0 settings-card__subtitle">Control email delivery for important admin updates.</p>
								</div>
							</header>

							<div class="card-body px-0 pb-0">
							<form id="notificationSettingsForm" class="settings-form" novalidate>
								<div class="settings-switch-list vstack gap-3">
									<div class="settings-switch-item card border bg-body-tertiary p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
										<div class="settings-switch-item__copy">
											<h3>Email Notifications</h3>
											<p>Receive activity alerts and report updates at <span id="settingsNotificationEmailAddress">your admin email address</span>.</p>
										</div>
										<div class="form-check form-switch">
											<input class="form-check-input" type="checkbox" role="switch" id="settingsEmailNotificationsToggle">
										</div>
									</div>
								</div>

								<div class="settings-actions d-flex justify-content-end mt-4 pt-3 border-top">
									<button class="btn settings-btn settings-btn--primary" id="saveNotificationSettingsBtn" type="submit">
										Save Notification Settings
									</button>
								</div>
							</form>
							</div>
						</article>
					</div>

					<div class="tab-pane fade" id="settings-security-pane" role="tabpanel" aria-labelledby="settings-security-tab" tabindex="0">
						<article class="card settings-card border-0 shadow-none">
							<header class="card-header settings-card__header bg-transparent border-0 px-0 d-flex align-items-start gap-3">
								<span class="settings-card__icon d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-danger-subtle text-danger-emphasis" style="width: 2.5rem; height: 2.5rem;" aria-hidden="true">
									<i class="bi bi-shield-check fs-5"></i>
								</span>
								<div>
									<h2 class="h5 mb-1 settings-card__title">Security Settings</h2>
									<p class="small text-body-secondary mb-0 settings-card__subtitle">Protect admin sessions with 2FA and inactivity timeout controls.</p>
								</div>
							</header>

							<div class="card-body px-0 pb-0">
							<form id="securitySettingsForm" class="settings-form" novalidate>
								<div class="settings-switch-list vstack gap-3">
									<div class="settings-switch-item card border bg-body-tertiary p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
										<div class="settings-switch-item__copy">
											<h3>Require 2FA For All Admin/Staff Accounts</h3>
											<p>When enabled, every admin and staff user must enroll in Google Authenticator before accessing the portal.</p>
										</div>
										<div class="form-check form-switch">
											<input class="form-check-input" type="checkbox" role="switch" id="settingsTwoFactorToggle">
										</div>
									</div>

									<p class="small text-body-secondary mt-2 mb-0" id="settingsTwoFactorEnrollmentHint"></p>

									<div class="card border bg-body-tertiary mt-3">
										<div class="card-body p-3 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
											<div>
															<h3 class="h6 mb-1">Google Authenticator</h3>
															<p class="small text-body-secondary mb-0">
																Manage your Google Authenticator enrollment and QR setup.
												</p>
											</div>
											<a
												href="{{ route('admin.2fa.setup') }}"
												class="btn btn-outline-danger text-nowrap"
															aria-label="Open Google Authenticator setup"
											>
												<i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
																Manage Google Authenticator
											</a>
										</div>
									</div>
								</div>

								<div class="row g-3 mt-1">
									<div class="col-12 col-lg-5">
										<label class="form-label" for="settingsSessionTimeoutSelect">Session Timeout</label>
										<select class="form-select" id="settingsSessionTimeoutSelect" required>
											<option value="5">5 minutes</option>
											<option value="10">10 minutes</option>
											<option value="30">30 minutes</option>
										</select>
										<div class="invalid-feedback">Please select a session timeout.</div>
									</div>
								</div>

								<div class="settings-actions d-flex justify-content-end mt-4 pt-3 border-top">
									<button class="btn settings-btn settings-btn--primary" id="saveSecuritySettingsBtn" type="submit">
										Save Security Settings
									</button>
								</div>
							</form>
							</div>
						</article>
					</div>

					<div class="tab-pane fade{{ $privacyLegalTabActive ? ' show active' : '' }}" id="settings-privacy-legal-pane" role="tabpanel" aria-labelledby="settings-privacy-legal-tab" tabindex="0">
						<article class="card settings-card border-0 shadow-none">
							<header class="card-header settings-card__header bg-transparent border-0 px-0 d-flex align-items-start gap-3">
								<span class="settings-card__icon d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-danger-subtle text-danger-emphasis" style="width: 2.5rem; height: 2.5rem;" aria-hidden="true">
									<i class="bi bi-file-earmark-lock fs-5"></i>
								</span>
								<div>
									<h2 class="h5 mb-1 settings-card__title">Privacy &amp; Legal</h2>
									<p class="small text-body-secondary mb-0 settings-card__subtitle">Manage the public compliance documents and cookie-consent prompt.</p>
								</div>
							</header>

							<div class="card-body px-0 pb-0">
								<div class="alert alert-info" role="note">
									Policy overrides are published as plain text. Leave a policy blank to keep the reviewed built-in version.
								</div>

								<form
									id="privacyLegalSettingsForm"
									class="settings-form needs-validation"
									action="{{ route('admin.settings.privacy-legal.update') }}"
									method="POST"
									novalidate
								>
									@csrf

									<div class="mb-4">
										<label class="form-label fw-semibold" for="settingsPrivacyPolicyInput">Privacy Policy</label>
										<textarea
											class="form-control @error('privacy_policy') is-invalid @enderror"
											id="settingsPrivacyPolicyInput"
											name="privacy_policy"
											rows="10"
											minlength="50"
											maxlength="{{ \App\Services\PrivacyLegalSettings::POLICY_MAX_LENGTH }}"
											aria-describedby="settingsPrivacyPolicyHelp settingsPrivacyPolicyError"
											aria-invalid="{{ $errors->has('privacy_policy') ? 'true' : 'false' }}"
										>{{ old('privacy_policy', $privacyLegalSettings['privacyPolicy'] ?? '') }}</textarea>
										<div class="form-text" id="settingsPrivacyPolicyHelp">Enter at least 50 characters to publish an override, or leave this blank to use the built-in Privacy Policy.</div>
										<div class="invalid-feedback" id="settingsPrivacyPolicyError">
											@error('privacy_policy') {{ $message }} @else A policy override must contain at least 50 characters. @enderror
										</div>
									</div>

									<div class="mb-4">
										<label class="form-label fw-semibold" for="settingsTermsConditionsInput">Terms and Conditions</label>
										<textarea
											class="form-control @error('terms_and_conditions') is-invalid @enderror"
											id="settingsTermsConditionsInput"
											name="terms_and_conditions"
											rows="10"
											minlength="50"
											maxlength="{{ \App\Services\PrivacyLegalSettings::POLICY_MAX_LENGTH }}"
											aria-describedby="settingsTermsConditionsHelp settingsTermsConditionsError"
											aria-invalid="{{ $errors->has('terms_and_conditions') ? 'true' : 'false' }}"
										>{{ old('terms_and_conditions', $privacyLegalSettings['termsAndConditions'] ?? '') }}</textarea>
										<div class="form-text" id="settingsTermsConditionsHelp">Enter at least 50 characters to publish an override, or leave this blank to use the built-in Terms and Conditions.</div>
										<div class="invalid-feedback" id="settingsTermsConditionsError">
											@error('terms_and_conditions') {{ $message }} @else A terms override must contain at least 50 characters. @enderror
										</div>
									</div>

									<div class="mb-4">
										<label class="form-label fw-semibold" for="settingsCookiePolicyInput">Cookie Policy</label>
										<textarea
											class="form-control @error('cookie_policy') is-invalid @enderror"
											id="settingsCookiePolicyInput"
											name="cookie_policy"
											rows="10"
											minlength="50"
											maxlength="{{ \App\Services\PrivacyLegalSettings::POLICY_MAX_LENGTH }}"
											aria-describedby="settingsCookiePolicyHelp settingsCookiePolicyError"
											aria-invalid="{{ $errors->has('cookie_policy') ? 'true' : 'false' }}"
										>{{ old('cookie_policy', $privacyLegalSettings['cookiePolicy'] ?? '') }}</textarea>
										<div class="form-text" id="settingsCookiePolicyHelp">Enter at least 50 characters to publish an override, or leave this blank to use the built-in Cookie Policy.</div>
										<div class="invalid-feedback" id="settingsCookiePolicyError">
											@error('cookie_policy') {{ $message }} @else A cookie policy override must contain at least 50 characters. @enderror
										</div>
									</div>

									<div class="settings-switch-item card border bg-body-tertiary p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
										<div class="settings-switch-item__copy">
											<label class="form-label fw-semibold mb-1" for="settingsCookieConsentBannerToggle">Enforce Explicit Cookie Consent Banner</label>
											<p class="small text-body-secondary mb-0" id="settingsCookieConsentBannerHelp">Show the choices panel until a visitor makes an explicit choice. Optional services always remain blocked until consent is recorded.</p>
										</div>
										<div class="form-check form-switch flex-shrink-0">
											<input type="hidden" name="enforce_cookie_consent_banner" value="0">
											<input
												class="form-check-input @error('enforce_cookie_consent_banner') is-invalid @enderror"
												type="checkbox"
												role="switch"
												id="settingsCookieConsentBannerToggle"
												name="enforce_cookie_consent_banner"
												value="1"
												aria-describedby="settingsCookieConsentBannerHelp{{ $errors->has('enforce_cookie_consent_banner') ? ' settingsCookieConsentBannerError' : '' }}"
												aria-invalid="{{ $errors->has('enforce_cookie_consent_banner') ? 'true' : 'false' }}"
												@checked((bool) old('enforce_cookie_consent_banner', $privacyLegalSettings['enforceCookieConsentBanner'] ?? true))
											>
										</div>
									</div>

									@error('enforce_cookie_consent_banner')
										<div class="text-danger small mt-2" id="settingsCookieConsentBannerError" role="alert">{{ $message }}</div>
									@enderror

									<div class="settings-actions d-flex justify-content-end mt-4 pt-3 border-top">
										<button class="btn settings-btn settings-btn--primary" id="savePrivacyLegalSettingsBtn" type="submit">
											Save Privacy &amp; Legal Settings
										</button>
									</div>
								</form>
							</div>
						</article>
					</div>
				</div>
			</div>
		</section>
	</div>

	<div class="modal fade" id="settingsConfirmModal" tabindex="-1" aria-labelledby="settingsConfirmModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="settingsConfirmModalLabel">Confirm Changes</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="settingsConfirmModalBody">
					Are you sure you want to save these changes?
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger" id="settingsConfirmSaveBtn">Yes, Save Changes</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('admin_scripts')
<script>
	(function () {
		var payload = (window.AdminPageData && window.AdminPageData.settings) ? window.AdminPageData.settings : {};
		var csrfToken = '{{ csrf_token() }}';
		var settingsData = {
			account: Object.assign({
				updateUrl: '{{ route('admin.settings.account.update') }}',
			}, payload.account || {}),
			notifications: Object.assign({
				email: false,
				emailAddress: '',
				updateUrl: '{{ route('admin.settings.notifications.update') }}',
			}, payload.notifications || {}),
			security: Object.assign({
				twoFactor: false,
				sessionTimeout: '10',
				twoFactorSetupUrl: '{{ route('admin.2fa.setup') }}',
				updateSecurityUrl: '{{ route('admin.settings.security.update') }}',
				currentAccountTwoFactorEnabled: false,
			}, payload.security || {}),
			privacyLegal: Object.assign({
				privacyPolicy: '',
				termsAndConditions: '',
				cookiePolicy: '',
				enforceCookieConsentBanner: true,
				updateUrl: '{{ route('admin.settings.privacy-legal.update') }}',
			}, payload.privacyLegal || {}),
		};
		var hasPrivacyLegalValidationErrors = {{ $privacyLegalHasValidationErrors ? 'true' : 'false' }};

		var confirmModalElement = document.getElementById('settingsConfirmModal');
		var confirmBody = document.getElementById('settingsConfirmModalBody');
		var confirmSaveButton = document.getElementById('settingsConfirmSaveBtn');

		var alertHost = document.getElementById('settingsAlertHost');
		var pendingAction = null;

		var accountForm = document.getElementById('accountSettingsForm');
		var notificationForm = document.getElementById('notificationSettingsForm');
		var securityForm = document.getElementById('securitySettingsForm');
		var privacyLegalForm = document.getElementById('privacyLegalSettingsForm');

		var saveAccountButton = document.getElementById('saveAccountSettingsBtn');
		var saveNotificationButton = document.getElementById('saveNotificationSettingsBtn');
		var saveSecurityButton = document.getElementById('saveSecuritySettingsBtn');
		var savePrivacyLegalButton = document.getElementById('savePrivacyLegalSettingsBtn');

		var currentPasswordInput = document.getElementById('settingsCurrentPasswordInput');
		var newPasswordInput = document.getElementById('settingsNewPasswordInput');
		var confirmPasswordInput = document.getElementById('settingsConfirmPasswordInput');
		var confirmPasswordFeedback = document.getElementById('settingsConfirmPasswordFeedback');

		var emailNotificationsToggle = document.getElementById('settingsEmailNotificationsToggle');
		var notificationEmailAddress = document.getElementById('settingsNotificationEmailAddress');

		var twoFactorToggle = document.getElementById('settingsTwoFactorToggle');
		var sessionTimeoutSelect = document.getElementById('settingsSessionTimeoutSelect');
		var twoFactorEnrollmentHint = document.getElementById('settingsTwoFactorEnrollmentHint');
		var privacyPolicyInput = document.getElementById('settingsPrivacyPolicyInput');
		var termsConditionsInput = document.getElementById('settingsTermsConditionsInput');
		var cookiePolicyInput = document.getElementById('settingsCookiePolicyInput');
		var cookieConsentBannerToggle = document.getElementById('settingsCookieConsentBannerToggle');
		var updateSecurityUrl = String(settingsData.security.updateSecurityUrl || '');
		var updateNotificationUrl = String(settingsData.notifications.updateUrl || '');
		var updateAccountUrl = String(settingsData.account.updateUrl || '');
		var updatePrivacyLegalUrl = String(settingsData.privacyLegal.updateUrl || '');
		var confirmModal = null;

		function getConfirmModal() {
			if (!confirmModal && confirmModalElement && window.bootstrap && window.bootstrap.Modal) {
				confirmModal = window.bootstrap.Modal.getOrCreateInstance(confirmModalElement);
			}

			return confirmModal;
		}

		function escapeHtml(value) {
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		function showAlert(type, message) {
			if (!alertHost) {
				return;
			}

			var toneMap = {
				success: 'alert-success',
				danger: 'alert-danger',
				warning: 'alert-warning',
				info: 'alert-info',
			};

			var alertElement = document.createElement('div');
			alertElement.className = 'alert ' + (toneMap[type] || 'alert-info') + ' alert-dismissible fade show';
			alertElement.setAttribute('role', 'alert');
			alertElement.innerHTML = escapeHtml(message) + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';

			alertHost.innerHTML = '';
			alertHost.appendChild(alertElement);

			window.setTimeout(function () {
				if (alertElement && alertElement.parentNode) {
					if (window.bootstrap && window.bootstrap.Alert) {
						window.bootstrap.Alert.getOrCreateInstance(alertElement).close();
					} else {
						alertElement.remove();
					}
				}
			}, 2800);
		}

		function extractApiError(payload) {
			if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
				return payload.message;
			}

			if (payload && payload.errors && typeof payload.errors === 'object') {
				var keys = Object.keys(payload.errors);
				if (keys.length > 0 && Array.isArray(payload.errors[keys[0]]) && payload.errors[keys[0]].length > 0) {
					return String(payload.errors[keys[0]][0]);
				}
			}

			return '';
		}

		function parseApiResponse(response) {
			return response.json().catch(function () {
				return {};
			}).then(function (payload) {
				if (!response.ok) {
					var message = response.status === 429
						? 'Too many requests. Please try again shortly.'
						: extractApiError(payload);
					var error = new Error(message || 'Unable to save settings.');
					error.status = response.status;
					throw error;
				}

				return payload;
			});
		}

		function setButtonLoading(button, isLoading) {
			if (!button) {
				return;
			}

			if (isLoading) {
				button.disabled = true;
				button.dataset.originalText = button.textContent;
				button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving...';
			} else {
				button.disabled = false;
				if (button.dataset.originalText) {
					button.textContent = button.dataset.originalText;
				}
			}
		}

		function openConfirmModal(sectionLabel, onConfirm) {
			var modal = getConfirmModal();

			if (!modal) {
				onConfirm();
				return;
			}

			pendingAction = onConfirm;
			if (confirmBody) {
				confirmBody.textContent = 'Are you sure you want to save changes in ' + sectionLabel + '?';
			}
			modal.show();
		}

		function validateAccountForm() {
			var isValid = accountForm.checkValidity();
			var passwordsMatch = newPasswordInput.value === confirmPasswordInput.value;

			accountForm.classList.add('was-validated');

			if (!passwordsMatch) {
				confirmPasswordInput.setCustomValidity('Passwords do not match.');
				if (confirmPasswordFeedback) {
					confirmPasswordFeedback.textContent = 'New password and confirmation do not match.';
				}
				isValid = false;
			} else {
				confirmPasswordInput.setCustomValidity('');
				if (confirmPasswordFeedback) {
					confirmPasswordFeedback.textContent = 'Please confirm your new password.';
				}
			}

			return isValid;
		}

		function renderTwoFactorEnrollmentHint() {
			if (!twoFactorEnrollmentHint) {
				return;
			}

			if (settingsData.security.currentAccountTwoFactorEnabled) {
				twoFactorEnrollmentHint.textContent = 'Current account: enrolled in Google Authenticator.';
				return;
			}

			twoFactorEnrollmentHint.textContent = 'Current account: not yet enrolled. If global 2FA is enabled, this account will be redirected to setup before dashboard access.';
		}

		function hydrateFromPayload() {
			if (emailNotificationsToggle) {
				emailNotificationsToggle.checked = !!settingsData.notifications.email;
			}
			if (notificationEmailAddress) {
				notificationEmailAddress.textContent = settingsData.notifications.emailAddress || 'your admin email address';
			}

			if (twoFactorToggle) {
				twoFactorToggle.checked = !!settingsData.security.twoFactor;
			}
			if (sessionTimeoutSelect) {
				sessionTimeoutSelect.value = String(settingsData.security.sessionTimeout || '10');
			}

			if (!hasPrivacyLegalValidationErrors) {
				if (privacyPolicyInput) {
					privacyPolicyInput.value = String(settingsData.privacyLegal.privacyPolicy || '');
				}
				if (termsConditionsInput) {
					termsConditionsInput.value = String(settingsData.privacyLegal.termsAndConditions || '');
				}
				if (cookiePolicyInput) {
					cookiePolicyInput.value = String(settingsData.privacyLegal.cookiePolicy || '');
				}
				if (cookieConsentBannerToggle) {
					cookieConsentBannerToggle.checked = !!settingsData.privacyLegal.enforceCookieConsentBanner;
				}
			}

			renderTwoFactorEnrollmentHint();
		}

		if (confirmSaveButton) {
			confirmSaveButton.addEventListener('click', function () {
				if (typeof pendingAction === 'function') {
					pendingAction();
				}

				pendingAction = null;
				var modal = getConfirmModal();
				if (modal) {
					modal.hide();
				}
			});
		}

		document.querySelectorAll('.settings-password-toggle').forEach(function (button) {
			button.addEventListener('click', function () {
				var targetId = button.getAttribute('data-target');
				var input = targetId ? document.getElementById(targetId) : null;
				if (!input) {
					return;
				}

				var isPassword = input.type === 'password';
				input.type = isPassword ? 'text' : 'password';
				button.textContent = isPassword ? 'Hide' : 'Show';
			});
		});

		if (accountForm) {
			accountForm.addEventListener('submit', function (event) {
				event.preventDefault();
				event.stopPropagation();

				if (!validateAccountForm()) {
					return;
				}

				openConfirmModal('Account Settings', function () {
					setButtonLoading(saveAccountButton, true);

					if (!updateAccountUrl) {
						setButtonLoading(saveAccountButton, false);
						showAlert('danger', 'Account settings endpoint is not configured.');
						return;
					}

					fetch(updateAccountUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'X-Requested-With': 'XMLHttpRequest',
						},
						credentials: 'same-origin',
						body: JSON.stringify({
							current_password: currentPasswordInput.value,
							new_password: newPasswordInput.value,
							new_password_confirmation: confirmPasswordInput.value,
						}),
					})
						.then(parseApiResponse)
						.then(function (responsePayload) {
							accountForm.reset();
							accountForm.classList.remove('was-validated');
							showAlert('success', String((responsePayload && responsePayload.message) || 'Password updated successfully.'));
						})
						.catch(function (error) {
							showAlert('danger', error.message || 'Unable to update password.');
						})
						.finally(function () {
							setButtonLoading(saveAccountButton, false);
						});
				});
			});
		}

		if (notificationForm) {
			notificationForm.addEventListener('submit', function (event) {
				event.preventDefault();
				event.stopPropagation();

				openConfirmModal('Notification Settings', function () {
					setButtonLoading(saveNotificationButton, true);

					if (!updateNotificationUrl) {
						setButtonLoading(saveNotificationButton, false);
						showAlert('danger', 'Email notification settings endpoint is not configured.');
						return;
					}

					fetch(updateNotificationUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'X-Requested-With': 'XMLHttpRequest',
						},
						credentials: 'same-origin',
						body: JSON.stringify({
							email_enabled: !!emailNotificationsToggle.checked,
						}),
					})
						.then(parseApiResponse)
						.then(function (payload) {
							var incomingNotifications = (payload && payload.notifications && typeof payload.notifications === 'object')
								? payload.notifications
								: {};

							settingsData.notifications.email = !!incomingNotifications.email;
							settingsData.notifications.emailAddress = String(incomingNotifications.emailAddress || settingsData.notifications.emailAddress || '');

							if (emailNotificationsToggle) {
								emailNotificationsToggle.checked = settingsData.notifications.email;
							}
							if (notificationEmailAddress) {
								notificationEmailAddress.textContent = settingsData.notifications.emailAddress || 'your admin email address';
							}

							setButtonLoading(saveNotificationButton, false);
							showAlert('success', String((payload && payload.message) || 'Email notification settings saved successfully.'));
						})
						.catch(function (error) {
							setButtonLoading(saveNotificationButton, false);
							showAlert('danger', error.message || 'Unable to save email notification settings.');
						});
				});
			});
		}

		if (securityForm) {
			securityForm.addEventListener('submit', function (event) {
				event.preventDefault();
				event.stopPropagation();

				securityForm.classList.add('was-validated');
				if (!securityForm.checkValidity()) {
					return;
				}

				openConfirmModal('Security Settings', function () {
					setButtonLoading(saveSecurityButton, true);

					if (!updateSecurityUrl) {
						setButtonLoading(saveSecurityButton, false);
						showAlert('danger', 'Security settings endpoint is not configured.');
						return;
					}

					fetch(updateSecurityUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'X-Requested-With': 'XMLHttpRequest',
						},
						credentials: 'same-origin',
						body: JSON.stringify({
							two_factor_required: !!twoFactorToggle.checked,
							session_timeout: Number(sessionTimeoutSelect.value || 10),
						}),
					})
						.then(parseApiResponse)
						.then(function (payload) {
							var incomingSecurity = (payload && payload.security && typeof payload.security === 'object')
								? payload.security
								: {};

							settingsData.security.twoFactor = !!incomingSecurity.twoFactor;
							settingsData.security.sessionTimeout = String(incomingSecurity.sessionTimeout || sessionTimeoutSelect.value || '10');
							settingsData.security.currentAccountTwoFactorEnabled = !!incomingSecurity.currentAccountTwoFactorEnabled;

							if (typeof incomingSecurity.twoFactorSetupUrl === 'string' && incomingSecurity.twoFactorSetupUrl !== '') {
								settingsData.security.twoFactorSetupUrl = incomingSecurity.twoFactorSetupUrl;
							}

							renderTwoFactorEnrollmentHint();
							setButtonLoading(saveSecurityButton, false);

							if (payload && payload.requiresTwoFactorEnrollment) {
								showAlert('warning', 'Global 2FA is enabled. Redirecting to Google Authenticator setup...');
								window.setTimeout(function () {
									window.location.href = String(settingsData.security.twoFactorSetupUrl || '{{ route('admin.2fa.setup') }}');
								}, 350);
								return;
							}

							showAlert('success', String((payload && payload.message) || 'Security settings saved successfully.'));
						})
						.catch(function (error) {
							setButtonLoading(saveSecurityButton, false);
							showAlert('danger', error.message || 'Unable to save security settings.');
						});
				});
			});
		}

		if (privacyLegalForm) {
			privacyLegalForm.addEventListener('submit', function (event) {
				event.preventDefault();
				event.stopPropagation();

				privacyLegalForm.classList.add('was-validated');
				[privacyPolicyInput, termsConditionsInput, cookiePolicyInput].forEach(function (input) {
					if (input) {
						input.setAttribute('aria-invalid', String(!input.checkValidity()));
					}
				});
				if (!privacyLegalForm.checkValidity()) {
					var firstInvalidInput = privacyLegalForm.querySelector(':invalid');
					if (firstInvalidInput) {
						firstInvalidInput.focus();
					}
					return;
				}

				openConfirmModal('Privacy & Legal Settings', function () {
					setButtonLoading(savePrivacyLegalButton, true);

					if (!updatePrivacyLegalUrl) {
						setButtonLoading(savePrivacyLegalButton, false);
						showAlert('danger', 'Privacy and legal settings endpoint is not configured.');
						return;
					}

					fetch(updatePrivacyLegalUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'X-Requested-With': 'XMLHttpRequest',
						},
						credentials: 'same-origin',
						body: JSON.stringify({
							privacy_policy: privacyPolicyInput.value.trim() || null,
							terms_and_conditions: termsConditionsInput.value.trim() || null,
							cookie_policy: cookiePolicyInput.value.trim() || null,
							enforce_cookie_consent_banner: !!cookieConsentBannerToggle.checked,
						}),
					})
						.then(parseApiResponse)
						.then(function (responsePayload) {
							if (responsePayload && responsePayload.privacyLegal) {
								settingsData.privacyLegal = Object.assign(settingsData.privacyLegal, responsePayload.privacyLegal);
								hasPrivacyLegalValidationErrors = false;
								hydrateFromPayload();
							}
							privacyLegalForm.classList.remove('was-validated');
							showAlert('success', String((responsePayload && responsePayload.message) || 'Privacy and legal settings saved successfully.'));
						})
						.catch(function (error) {
							showAlert('danger', error.message || 'Unable to save privacy and legal settings.');
						})
						.finally(function () {
							setButtonLoading(savePrivacyLegalButton, false);
						});
				});
			});

			[privacyPolicyInput, termsConditionsInput, cookiePolicyInput].forEach(function (input) {
				if (!input) {
					return;
				}

				input.addEventListener('input', function () {
					input.setAttribute('aria-invalid', String(!input.checkValidity()));
				});
			});
		}

		hydrateFromPayload();
	})();
</script>
@endpush
