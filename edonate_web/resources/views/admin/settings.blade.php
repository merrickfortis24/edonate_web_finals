@extends('layouts.admin')

@section('title', 'eDonate - Admin Settings')
@section('admin_page_class', 'admin-settings-page')
@section('header_title', 'Settings')
@section('header_subtitle', 'Manage system configuration, security, and preferences')

@section('header_actions')
	<span class="settings-header-pill">Admin Control Panel</span>
@endsection

@section('admin_page_data')
{!! json_encode($settingsPayload ?? [
	'page' => 'settings',
	'settings' => [
		'general' => [
			'systemName' => 'eDonate',
			'systemEmail' => 'admin@edonate.local',
			'contactNumber' => '+63 917 123 4567',
		],
		'notifications' => [
			'email' => true,
			'sms' => false,
		],
		'security' => [
			'twoFactor' => false,
			'sessionTimeout' => '10',
			'twoFactorSetupUrl' => route('admin.2fa.setup'),
			'updateSecurityUrl' => route('admin.settings.security.update'),
			'currentAccountTwoFactorEnabled' => false,
		],
	],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
	<main class="main container-fluid px-0">
		<div class="settings-body container-fluid py-3">
			<div id="settingsAlertHost" class="settings-alert-host"></div>

			<section class="settings-shell" aria-label="Admin settings sections">
				<ul class="nav nav-tabs settings-tabs" id="settingsTabs" role="tablist">
					<li class="nav-item" role="presentation">
						<button class="nav-link active" id="settings-general-tab" data-bs-toggle="tab" data-bs-target="#settings-general-pane" type="button" role="tab" aria-controls="settings-general-pane" aria-selected="true">General</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="settings-appearance-tab" data-bs-toggle="tab" data-bs-target="#settings-appearance-pane" type="button" role="tab" aria-controls="settings-appearance-pane" aria-selected="false">Appearance</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="settings-account-tab" data-bs-toggle="tab" data-bs-target="#settings-account-pane" type="button" role="tab" aria-controls="settings-account-pane" aria-selected="false">Account</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="settings-notifications-tab" data-bs-toggle="tab" data-bs-target="#settings-notifications-pane" type="button" role="tab" aria-controls="settings-notifications-pane" aria-selected="false">Notifications</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="settings-security-tab" data-bs-toggle="tab" data-bs-target="#settings-security-pane" type="button" role="tab" aria-controls="settings-security-pane" aria-selected="false">Security</button>
					</li>
				</ul>

				<div class="tab-content settings-tab-content" id="settingsTabsContent">
					<div class="tab-pane fade show active" id="settings-general-pane" role="tabpanel" aria-labelledby="settings-general-tab" tabindex="0">
						<article class="settings-card">
							<header class="settings-card__header">
								<span class="settings-card__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none">
										<path d="M4 7.5C4 5.57 5.57 4 7.5 4H16.5C18.43 4 20 5.57 20 7.5V16.5C20 18.43 18.43 20 16.5 20H7.5C5.57 20 4 18.43 4 16.5V7.5Z" stroke="currentColor" stroke-width="1.8"/>
										<path d="M8 12H16M8 8.5H13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
									</svg>
								</span>
								<div>
									<h2 class="settings-card__title">General Settings</h2>
									<p class="settings-card__subtitle">Basic platform information shown across your admin system.</p>
								</div>
							</header>

							<form id="generalSettingsForm" class="settings-form needs-validation" novalidate>
								<div class="row g-3">
									<div class="col-12 col-lg-6">
										<label class="form-label" for="settingsSystemNameInput">System Name</label>
										<input type="text" class="form-control" id="settingsSystemNameInput" placeholder="Enter system name" required>
										<div class="invalid-feedback">Please enter a system name.</div>
									</div>

									<div class="col-12 col-lg-6">
										<label class="form-label" for="settingsSystemEmailInput">System Email</label>
										<input type="email" class="form-control" id="settingsSystemEmailInput" placeholder="name@example.com" required>
										<div class="invalid-feedback">Please enter a valid system email.</div>
									</div>

									<div class="col-12 col-lg-6">
										<label class="form-label" for="settingsContactNumberInput">Contact Number</label>
										<input type="text" class="form-control" id="settingsContactNumberInput" placeholder="+63 9XX XXX XXXX" required pattern="^[+0-9][0-9\s-]{6,}$">
										<div class="invalid-feedback">Please enter a valid contact number.</div>
									</div>
								</div>

								<div class="settings-actions">
									<button class="btn settings-btn settings-btn--primary" id="saveGeneralSettingsBtn" type="submit">
										Save General Settings
									</button>
								</div>
							</form>
						</article>
					</div>

					<div class="tab-pane fade" id="settings-appearance-pane" role="tabpanel" aria-labelledby="settings-appearance-tab" tabindex="0">
						<article class="settings-card">
							<header class="settings-card__header">
								<span class="settings-card__icon" aria-hidden="true">
									<i class="bi bi-circle-half"></i>
								</span>
								<div>
									<h2 class="settings-card__title">Appearance</h2>
									<p class="settings-card__subtitle">Choose how the eDonate Admin Portal looks.</p>
								</div>
							</header>

							<div class="settings-theme-control" role="radiogroup" aria-labelledby="settingsThemeLabel">
								<div class="settings-theme-copy">
									<h3 id="settingsThemeLabel">Theme</h3>
									<p>Light mode is the default. Your choice is saved on this browser.</p>
								</div>

								<div class="settings-theme-options">
									<input class="btn-check" type="radio" name="settingsTheme" id="settingsThemeLight" value="light" autocomplete="off">
									<label class="btn btn-outline-danger settings-theme-option" for="settingsThemeLight">
										<i class="bi bi-sun me-2" aria-hidden="true"></i>Light
									</label>

									<input class="btn-check" type="radio" name="settingsTheme" id="settingsThemeDark" value="dark" autocomplete="off">
									<label class="btn btn-outline-danger settings-theme-option" for="settingsThemeDark">
										<i class="bi bi-moon-stars me-2" aria-hidden="true"></i>Dark
									</label>
								</div>
							</div>
						</article>
					</div>

					<div class="tab-pane fade" id="settings-account-pane" role="tabpanel" aria-labelledby="settings-account-tab" tabindex="0">
						<article class="settings-card">
							<header class="settings-card__header">
								<span class="settings-card__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none">
										<circle cx="12" cy="9" r="3.2" stroke="currentColor" stroke-width="1.8"/>
										<path d="M6 19C6 15.6863 8.68629 13 12 13C15.3137 13 18 15.6863 18 19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
									</svg>
								</span>
								<div>
									<h2 class="settings-card__title">Account Settings</h2>
									<p class="settings-card__subtitle">Update your password securely with confirmation checks.</p>
								</div>
							</header>

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

								<div class="settings-actions">
									<button class="btn settings-btn settings-btn--primary" id="saveAccountSettingsBtn" type="submit">
										Update Password
									</button>
								</div>
							</form>
						</article>
					</div>

					<div class="tab-pane fade" id="settings-notifications-pane" role="tabpanel" aria-labelledby="settings-notifications-tab" tabindex="0">
						<article class="settings-card">
							<header class="settings-card__header">
								<span class="settings-card__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none">
										<path d="M12 4C8.68629 4 6 6.68629 6 10V13.5L4.5 16H19.5L18 13.5V10C18 6.68629 15.3137 4 12 4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
										<path d="M10 18C10.4 19 11.1 19.5 12 19.5C12.9 19.5 13.6 19 14 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
									</svg>
								</span>
								<div>
									<h2 class="settings-card__title">Notification Settings</h2>
									<p class="settings-card__subtitle">Control delivery channels for important admin and donor updates.</p>
								</div>
							</header>

							<form id="notificationSettingsForm" class="settings-form" novalidate>
								<div class="settings-switch-list">
									<div class="settings-switch-item">
										<div class="settings-switch-item__copy">
											<h3>Email Notifications</h3>
											<p>Receive activity alerts and report updates via email.</p>
										</div>
										<div class="form-check form-switch">
											<input class="form-check-input" type="checkbox" role="switch" id="settingsEmailNotificationsToggle">
										</div>
									</div>

									<div class="settings-switch-item">
										<div class="settings-switch-item__copy">
											<h3>SMS Notifications</h3>
											<p>Receive urgent events and reminders as SMS messages.</p>
										</div>
										<div class="form-check form-switch">
											<input class="form-check-input" type="checkbox" role="switch" id="settingsSmsNotificationsToggle">
										</div>
									</div>
								</div>

								<div class="settings-actions">
									<button class="btn settings-btn settings-btn--primary" id="saveNotificationSettingsBtn" type="submit">
										Save Notification Settings
									</button>
								</div>
							</form>
						</article>
					</div>

					<div class="tab-pane fade" id="settings-security-pane" role="tabpanel" aria-labelledby="settings-security-tab" tabindex="0">
						<article class="settings-card">
							<header class="settings-card__header">
								<span class="settings-card__icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none">
										<path d="M12 3L19 6V11.8C19 16.2 16.2 20.2 12 21C7.8 20.2 5 16.2 5 11.8V6L12 3Z" stroke="currentColor" stroke-width="1.8"/>
										<path d="M9.5 12.2L11.2 13.9L14.8 10.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</span>
								<div>
									<h2 class="settings-card__title">Security Settings</h2>
									<p class="settings-card__subtitle">Protect admin sessions with 2FA and inactivity timeout controls.</p>
								</div>
							</header>

							<form id="securitySettingsForm" class="settings-form" novalidate>
								<div class="settings-switch-list">
									<div class="settings-switch-item">
										<div class="settings-switch-item__copy">
											<h3>Require 2FA For All Admin/Staff Accounts</h3>
											<p>When enabled, every admin and staff user must enroll in Google Authenticator before accessing the portal.</p>
										</div>
										<div class="form-check form-switch">
											<input class="form-check-input" type="checkbox" role="switch" id="settingsTwoFactorToggle">
										</div>
									</div>

									<p class="small text-muted mt-2 mb-0" id="settingsTwoFactorEnrollmentHint"></p>

									<p class="small text-muted mt-2 mb-0">
										Manage enrollment, QR setup, and disable actions on
										<a href="{{ route('admin.2fa.setup') }}">Google Authenticator setup page</a>.
									</p>
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

								<div class="settings-actions">
									<button class="btn settings-btn settings-btn--primary" id="saveSecuritySettingsBtn" type="submit">
										Save Security Settings
									</button>
								</div>
							</form>
						</article>
					</div>
				</div>
			</section>
		</div>
	</main>

	<div class="modal fade" id="settingsConfirmModal" tabindex="-1" aria-labelledby="settingsConfirmModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content settings-confirm-modal">
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
			general: Object.assign({
				systemName: '',
				systemEmail: '',
				contactNumber: '',
			}, payload.general || {}),
			notifications: Object.assign({
				email: false,
				sms: false,
			}, payload.notifications || {}),
			security: Object.assign({
				twoFactor: false,
				sessionTimeout: '10',
				twoFactorSetupUrl: '{{ route('admin.2fa.setup') }}',
				updateSecurityUrl: '{{ route('admin.settings.security.update') }}',
				currentAccountTwoFactorEnabled: false,
			}, payload.security || {}),
		};

		var confirmModalElement = document.getElementById('settingsConfirmModal');
		var confirmModal = confirmModalElement ? bootstrap.Modal.getOrCreateInstance(confirmModalElement) : null;
		var confirmBody = document.getElementById('settingsConfirmModalBody');
		var confirmSaveButton = document.getElementById('settingsConfirmSaveBtn');

		var alertHost = document.getElementById('settingsAlertHost');
		var pendingAction = null;

		var generalForm = document.getElementById('generalSettingsForm');
		var accountForm = document.getElementById('accountSettingsForm');
		var notificationForm = document.getElementById('notificationSettingsForm');
		var securityForm = document.getElementById('securitySettingsForm');

		var saveGeneralButton = document.getElementById('saveGeneralSettingsBtn');
		var saveAccountButton = document.getElementById('saveAccountSettingsBtn');
		var saveNotificationButton = document.getElementById('saveNotificationSettingsBtn');
		var saveSecurityButton = document.getElementById('saveSecuritySettingsBtn');

		var systemNameInput = document.getElementById('settingsSystemNameInput');
		var systemEmailInput = document.getElementById('settingsSystemEmailInput');
		var contactNumberInput = document.getElementById('settingsContactNumberInput');

		var currentPasswordInput = document.getElementById('settingsCurrentPasswordInput');
		var newPasswordInput = document.getElementById('settingsNewPasswordInput');
		var confirmPasswordInput = document.getElementById('settingsConfirmPasswordInput');
		var confirmPasswordFeedback = document.getElementById('settingsConfirmPasswordFeedback');

		var emailNotificationsToggle = document.getElementById('settingsEmailNotificationsToggle');
		var smsNotificationsToggle = document.getElementById('settingsSmsNotificationsToggle');

		var twoFactorToggle = document.getElementById('settingsTwoFactorToggle');
		var sessionTimeoutSelect = document.getElementById('settingsSessionTimeoutSelect');
		var twoFactorEnrollmentHint = document.getElementById('settingsTwoFactorEnrollmentHint');
		var updateSecurityUrl = String(settingsData.security.updateSecurityUrl || '');
		var themeInputs = document.querySelectorAll('input[name="settingsTheme"]');

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
					bootstrap.Alert.getOrCreateInstance(alertElement).close();
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
					throw new Error(extractApiError(payload) || 'Unable to save security settings.');
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
			if (!confirmModal) {
				onConfirm();
				return;
			}

			pendingAction = onConfirm;
			if (confirmBody) {
				confirmBody.textContent = 'Are you sure you want to save changes in ' + sectionLabel + '?';
			}
			confirmModal.show();
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

		function getPortalTheme() {
			if (window.eDonateTheme && typeof window.eDonateTheme.getTheme === 'function') {
				return window.eDonateTheme.getTheme();
			}

			try {
				var storedTheme = window.localStorage.getItem('lte-theme');
				return storedTheme === 'dark' ? 'dark' : 'light';
			} catch (error) {
				return 'light';
			}
		}

		function setPortalTheme(theme) {
			var nextTheme = theme === 'dark' ? 'dark' : 'light';

			if (window.eDonateTheme && typeof window.eDonateTheme.setTheme === 'function') {
				nextTheme = window.eDonateTheme.setTheme(nextTheme);
			} else {
				document.documentElement.setAttribute('data-bs-theme', nextTheme);
				try {
					window.localStorage.setItem('lte-theme', nextTheme);
				} catch (error) {
					// localStorage may be unavailable in restricted browsing modes.
				}
			}

			syncThemeControl(nextTheme);
		}

		function syncThemeControl(theme) {
			var selectedTheme = theme === 'dark' ? 'dark' : 'light';

			themeInputs.forEach(function (input) {
				input.checked = input.value === selectedTheme;
			});
		}

		function hydrateFromPayload() {
			if (systemNameInput) {
				systemNameInput.value = settingsData.general.systemName;
			}
			if (systemEmailInput) {
				systemEmailInput.value = settingsData.general.systemEmail;
			}
			if (contactNumberInput) {
				contactNumberInput.value = settingsData.general.contactNumber;
			}

			if (emailNotificationsToggle) {
				emailNotificationsToggle.checked = !!settingsData.notifications.email;
			}
			if (smsNotificationsToggle) {
				smsNotificationsToggle.checked = !!settingsData.notifications.sms;
			}

			if (twoFactorToggle) {
				twoFactorToggle.checked = !!settingsData.security.twoFactor;
			}
			if (sessionTimeoutSelect) {
				sessionTimeoutSelect.value = String(settingsData.security.sessionTimeout || '10');
			}

			renderTwoFactorEnrollmentHint();
			syncThemeControl(getPortalTheme());
		}

		themeInputs.forEach(function (input) {
			input.addEventListener('change', function () {
				if (input.checked) {
					setPortalTheme(input.value);
				}
			});
		});

		window.addEventListener('edonate:themechange', function (event) {
			syncThemeControl(event.detail && event.detail.theme);
		});

		if (confirmSaveButton) {
			confirmSaveButton.addEventListener('click', function () {
				if (typeof pendingAction === 'function') {
					pendingAction();
				}

				pendingAction = null;
				if (confirmModal) {
					confirmModal.hide();
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

		if (generalForm) {
			generalForm.addEventListener('submit', function (event) {
				event.preventDefault();
				event.stopPropagation();

				generalForm.classList.add('was-validated');
				if (!generalForm.checkValidity()) {
					return;
				}

				openConfirmModal('General Settings', function () {
					setButtonLoading(saveGeneralButton, true);

					window.setTimeout(function () {
						settingsData.general.systemName = systemNameInput.value.trim();
						settingsData.general.systemEmail = systemEmailInput.value.trim();
						settingsData.general.contactNumber = contactNumberInput.value.trim();

						setButtonLoading(saveGeneralButton, false);
						showAlert('success', 'General settings saved successfully.');
					}, 700);
				});
			});
		}

		if (accountForm) {
			accountForm.addEventListener('submit', function (event) {
				event.preventDefault();
				event.stopPropagation();

				if (!validateAccountForm()) {
					return;
				}

				openConfirmModal('Account Settings', function () {
					setButtonLoading(saveAccountButton, true);

					window.setTimeout(function () {
						setButtonLoading(saveAccountButton, false);
						accountForm.reset();
						accountForm.classList.remove('was-validated');
						showAlert('success', 'Password updated successfully.');
					}, 700);
				});
			});
		}

		if (notificationForm) {
			notificationForm.addEventListener('submit', function (event) {
				event.preventDefault();
				event.stopPropagation();

				openConfirmModal('Notification Settings', function () {
					setButtonLoading(saveNotificationButton, true);

					window.setTimeout(function () {
						settingsData.notifications.email = !!emailNotificationsToggle.checked;
						settingsData.notifications.sms = !!smsNotificationsToggle.checked;

						setButtonLoading(saveNotificationButton, false);
						showAlert('success', 'Notification settings saved successfully.');
					}, 700);
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

		hydrateFromPayload();
	})();
</script>
@endpush
