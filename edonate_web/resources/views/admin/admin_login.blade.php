<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>Admin Login Page | eDonate</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
		rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
		integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<style>
		:root {
			--color-bg-from: #750000;
			--color-bg-to: #ff4e4e;

			--color-panel-dark: #b60c0c;
			--color-panel-light: #ffffff;

			--color-btn-grad-from: #850000;
			--color-btn-grad-to: #ad0000;

			--color-input-bg: #e7e7e7;
			--color-input-admin-bg: #f4f4f4;

			--color-text-white: #ffffff;
			--color-text-dark: #4f4f4f;
			--color-text-red: #b60c0c;

			--font-family: 'Poppins', sans-serif;

			--radius-card: 30px;
			--radius-input: 10px;
			--radius-admin-btn: 20px;

			--shadow-card: 0 4px 10px rgba(0, 0, 0, 0.69);
			--shadow-panel-white: 0 4px 4px rgba(0, 0, 0, 0.25);
			--shadow-input-inset: inset 0 4px 4px rgba(0, 0, 0, 0.25);
			--shadow-login-btn: 0 4px 4px rgba(0, 0, 0, 0.25);
			--shadow-admin-btn: 0 4px 4px rgba(0, 0, 0, 0.25);

			--spacing-xs: 8px;
			--spacing-sm: 12px;
			--spacing-md: 20px;
			--spacing-lg: 40px;
			--spacing-xl: 54px;

			--fs-xs: 12px;
			--fs-sm: 13px;
			--fs-base: 16px;
			--fs-md: 20px;
			--fs-lg: 48px;
		}

		*,
		*::before,
		*::after {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		html,
		body {
			min-height: 100%;
			font-family: var(--font-family);
		}

		body {
			background: linear-gradient(to bottom, var(--color-bg-from), var(--color-bg-to));
		}

		.page {
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 40px 24px;
		}

		.login-card {
			width: 100%;
			max-width: 1197px;
			display: flex;
			flex-direction: row;
			border-radius: var(--radius-card);
			overflow: hidden;
			box-shadow: var(--shadow-card);
		}

		.card__left {
			flex: 0 0 46.8%;
			background: var(--color-panel-dark);
			color: var(--color-text-white);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 60px 36px;
		}

		.card__left__logo {
			display: block;
			overflow: hidden;
			width: min(100%, 360px);
			/* Leave a little vertical breathing room so the source artwork is not clipped. */
			aspect-ratio: 4.2 / 1;
		}

		.card__left__logo img {
			display: block;
			height: 100%;
			filter: drop-shadow(0 0 2px rgba(255, 255, 255, .85));
			object-fit: cover;
			object-position: center 47%;
			width: 100%;
		}

		.card__left__tagline {
			margin-top: 28px;
			max-width: 420px;
			text-align: center;
			font-size: var(--fs-md);
			line-height: 1.5;
			font-weight: 400;
		}

		.card__left__stats {
			margin-top: 40px;
			width: 100%;
			max-width: 500px;
			display: flex;
			justify-content: space-around;
			gap: 16px;
		}

		.stat {
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 2px;
		}

		.stat__number {
			font-size: var(--fs-lg);
			line-height: 1;
			font-weight: 700;
		}

		.stat__label {
			font-size: var(--fs-md);
			text-align: center;
		}

		.card__right {
			flex: 1;
			background: var(--color-panel-light);
			box-shadow: var(--shadow-panel-white);
			display: flex;
			flex-direction: column;
			justify-content: center;
			padding: 52px var(--spacing-xl);
		}

		.form__heading {
			color: var(--color-text-red);
			font-size: var(--fs-md);
			font-weight: 700;
			margin-bottom: 6px;
		}

		.form__subheading {
			color: var(--color-text-dark);
			font-size: var(--fs-md);
			font-weight: 400;
			margin-bottom: 24px;
		}

		.form__admin-btn {
			width: 100%;
			height: 39px;
			border: 0.5px solid #000;
			border-radius: var(--radius-admin-btn);
			background: var(--color-input-admin-bg);
			box-shadow: var(--shadow-admin-btn);
			color: var(--color-text-red);
			font-family: var(--font-family);
			font-size: var(--fs-sm);
			font-weight: 600;
			cursor: pointer;
			margin-bottom: 24px;
			transition: opacity 0.2s ease;
		}

		.form__admin-btn:hover {
			opacity: 0.85;
		}

		.form__label {
			display: block;
			color: var(--color-text-dark);
			font-size: var(--fs-base);
			font-weight: 400;
			margin-bottom: 6px;
		}

		.form__input {
			width: 100%;
			height: 39px;
			border: none;
			border-radius: var(--radius-input);
			background: var(--color-input-bg);
			box-shadow: var(--shadow-input-inset);
			color: var(--color-text-dark);
			font-family: var(--font-family);
			font-size: var(--fs-xs);
			font-weight: 300;
			padding: 0 14px;
			outline: none;
			margin-bottom: 16px;
		}

		.password-field {
			position: relative;
			margin-bottom: 16px;
		}

		.password-field .form__input {
			margin-bottom: 0;
			padding-right: 48px;
		}

		.password-toggle {
			position: absolute;
			top: 50%;
			right: 8px;
			width: 32px;
			height: 32px;
			transform: translateY(-50%);
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0;
			border: 0;
			border-radius: 50%;
			background: transparent;
			color: var(--color-text-dark);
			cursor: pointer;
		}

		.password-toggle:hover {
			background: rgba(182, 12, 12, 0.08);
			color: var(--color-text-red);
		}

		.password-toggle:focus-visible {
			outline: 2px solid var(--color-text-red);
			outline-offset: 2px;
		}

		.password-toggle svg {
			width: 18px;
			height: 18px;
		}

		.form__input::placeholder {
			color: var(--color-text-dark);
		}

		.form__forgot {
			display: block;
			text-align: right;
			color: var(--color-text-red);
			font-size: var(--fs-sm);
			font-weight: 500;
			text-decoration: none;
			margin-top: -8px;
			margin-bottom: 20px;
		}

		.form__forgot:hover {
			text-decoration: underline;
		}

		.form__meta {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin-top: -8px;
			margin-bottom: 20px;
		}

		.form__remember {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			font-size: var(--fs-sm);
			color: var(--color-text-dark);
			font-weight: 500;
			margin: 0;
			cursor: pointer;
			user-select: none;
		}

		.form__remember input[type="checkbox"] {
			width: 16px;
			height: 16px;
			border-radius: 4px;
			cursor: pointer;
		}

		.form__submit {
			width: 100%;
			height: 39px;
			border: none;
			border-radius: var(--radius-input);
			background: linear-gradient(to left, var(--color-btn-grad-from), var(--color-btn-grad-to));
			box-shadow: var(--shadow-login-btn);
			color: var(--color-text-white);
			font-family: var(--font-family);
			font-size: var(--fs-base);
			font-weight: 600;
			cursor: pointer;
			transition: opacity 0.2s ease;
		}

		.form__submit:hover {
			opacity: 0.9;
		}

		.form__social-divider {
			display: flex;
			align-items: center;
			gap: 12px;
			margin: 22px 0 14px;
			color: #777;
			font-size: var(--fs-sm);
		}

		.form__social-divider::before,
		.form__social-divider::after {
			content: '';
			flex: 1;
			height: 1px;
			background: #dedede;
		}

		.form__google-btn {
			width: 100%;
			min-height: 42px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 10px;
			border: 1px solid #d0d5dd;
			border-radius: var(--radius-input);
			background: #fff;
			box-shadow: 0 2px 5px rgba(0, 0, 0, .08);
			color: #303030;
			font-family: var(--font-family);
			font-size: var(--fs-sm);
			font-weight: 600;
			cursor: pointer;
			transition: background .2s ease, box-shadow .2s ease, opacity .2s ease;
		}

		.form__google-btn:hover:not(:disabled) {
			background: #fafafa;
			box-shadow: 0 3px 8px rgba(0, 0, 0, .12);
		}

		.form__google-btn:focus-visible {
			outline: 2px solid var(--color-text-red);
			outline-offset: 2px;
		}

		.form__google-btn:disabled {
			cursor: not-allowed;
			opacity: .62;
		}

		.form__google-btn svg {
			width: 18px;
			height: 18px;
			flex: 0 0 auto;
		}

		.form__google-help {
			margin-top: 9px;
			color: #777;
			font-size: 11px;
			line-height: 1.4;
			text-align: center;
		}

		.form__google-status {
			min-height: 18px;
			margin-top: 9px;
			color: var(--color-text-red);
			font-size: 12px;
			line-height: 1.45;
			text-align: center;
		}

		.form__alert {
			border-radius: 10px;
			padding: 10px 12px;
			font-size: 13px;
			margin-bottom: 14px;
		}

		.form__alert--error {
			background: #fee2e2;
			color: #991b1b;
			border: 1px solid #fecaca;
		}

		.form__alert--success {
			background: #dcfce7;
			color: #166534;
			border: 1px solid #bbf7d0;
		}

		@media (max-width: 1024px) {
			.login-card {
				max-width: 860px;
			}

			.card__left {
				padding: 40px 24px;
			}

			.card__left__tagline {
				font-size: 16px;
				margin-top: 20px;
			}

			.card__left__stats {
				margin-top: 28px;
			}

			.stat__number {
				font-size: 36px;
			}

			.stat__label {
				font-size: 15px;
			}

			.card__right {
				padding: 40px 36px;
			}

			.form__heading {
				font-size: 18px;
			}

			.form__subheading {
				font-size: 15px;
			}
		}

		@media (max-width: 768px) {
			.page {
				padding: 24px 16px;
			}

			.login-card {
				flex-direction: column;
				max-width: 480px;
			}

			.card__left {
				padding: 48px 32px 40px;
			}

			.card__left__tagline {
				font-size: 14px;
				margin-top: 16px;
			}

			.card__left__stats {
				margin-top: 24px;
				gap: 8px;
			}

			.stat__number {
				font-size: 30px;
			}

			.stat__label {
				font-size: 13px;
			}

			.card__right {
				padding: 36px 28px;
			}

			.form__heading {
				font-size: 18px;
			}

			.form__subheading {
				font-size: 14px;
			}

			.form__admin-btn {
				font-size: 12px;
			}

			.form__label {
				font-size: 14px;
			}

			.form__submit {
				font-size: 15px;
			}

			.form__meta {
				flex-direction: column;
				align-items: flex-start;
				gap: 10px;
			}

			.form__forgot {
				margin: 0;
			}
		}
	</style>
</head>

<body>
	<main class="page" role="main">
		<div class="login-card">
			<section class="card__left" aria-label="eDonate branding">
				<div class="card__left__logo">
					<img src="{{ asset('images/edonate-logo.png') }}" alt="eDonate">
				</div>

				<p class="card__left__tagline">
					Give blood, save lives.<br>
					Join our community of lifesavers today.
				</p>

				<div class="card__left__stats" aria-label="Statistics">
					<div class="stat">
						<span class="stat__number">{{ $loginStats['donors'] ?? '0' }}</span>
						<span class="stat__label">Donors</span>
					</div>
					<div class="stat">
						<span class="stat__number">{{ $loginStats['donations'] ?? '0' }}</span>
						<span class="stat__label">Donations</span>
					</div>
					<div class="stat">
						<span class="stat__number">{{ $loginStats['lives_saved'] ?? '0' }}</span>
						<span class="stat__label">Lives Saved</span>
					</div>
				</div>
			</section>

			<section class="card__right" aria-label="Login form">
				@php
					$setupModalPayload = is_array($twoFactorSetupModal ?? null) ? $twoFactorSetupModal : [];
					$challengeModalPayload = is_array($twoFactorChallengeModal ?? null) ? $twoFactorChallengeModal : [];

					$setupModalActive = (bool) ($setupModalPayload['required'] ?? false)
						|| (is_array($setupModalPayload['recoveryCodes'] ?? null) && ($setupModalPayload['recoveryCodes'] ?? []) !== [])
						|| $errors->has('otp');

					$challengeModalActive = (bool) ($challengeModalPayload['show'] ?? false)
						|| $errors->has('code')
						|| $errors->has('recovery_code');

					$suppressLoginAlerts = $setupModalActive || $challengeModalActive;
				@endphp

				<h1 class="form__heading">Welcome Back</h1>
				<p class="form__subheading">Log in to continue to your admin account</p>

				@if (session('error') && !$suppressLoginAlerts)
					<div class="form__alert form__alert--error">{{ session('error') }}</div>
				@endif

				@if (session('success') && !$suppressLoginAlerts)
					<div class="form__alert form__alert--success">{{ session('success') }}</div>
				@endif

				@if (isset($errors) && $errors->any() && !$suppressLoginAlerts)
					<div class="form__alert form__alert--error">{{ $errors->first() }}</div>
				@endif

				<form id="adminLoginForm" method="POST" action="{{ route('admin.login.store') }}" novalidate>
					@csrf
					<label class="form__label" for="email">Email Address</label>
					<input class="form__input form-control" id="email" type="email" name="email"
						value="{{ old('email') }}" autocomplete="email" required>

					<label class="form__label" for="password">Password</label>
					<div class="password-field">
						<input class="form__input form-control" id="password" type="password" name="password"
							autocomplete="current-password" required>
						<button type="button" class="password-toggle" data-password-toggle
							data-password-toggle-target="password" aria-controls="password" aria-label="Show password"
							title="Show password" aria-pressed="false">
							<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" data-password-icon="show">
								<path fill="currentColor" d="M12 5c-5 0-8.8 3.1-10.5 7C3.2 15.9 7 19 12 19s8.8-3.1 10.5-7C20.8 8.1 17 5 12 5Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2.2A1.8 1.8 0 1 0 12 10a1.8 1.8 0 0 0 0 3.6Z" />
							</svg>
							<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" data-password-icon="hide" hidden>
								<path fill="currentColor" d="m3.3 2.3 18.4 18.4-1.4 1.4-3.1-3.1A11.8 11.8 0 0 1 12 20C7 20 3.2 16.9 1.5 13a12.8 12.8 0 0 1 4.1-5.1L1.9 3.7l1.4-1.4ZM7 9.3A10.8 10.8 0 0 0 3.7 13c1.7 2.9 4.7 5 8.3 5 1.1 0 2.1-.2 3-.5l-2.1-2.1A4.5 4.5 0 0 1 7 9.3Zm5-3.3c5 0 8.8 3.1 10.5 7a12.8 12.8 0 0 1-3.7 4.7l-1.5-1.5a10.8 10.8 0 0 0 3.1-3.2c-1.7-2.9-4.7-5-8.3-5-.7 0-1.4.1-2 .2L8.5 6.7c1.1-.4 2.3-.7 3.5-.7Zm0 3a3 3 0 0 1 3 3c0 .4-.1.8-.2 1.1l-3.9-3.9c.3-.1.7-.2 1.1-.2Z" />
							</svg>
						</button>
					</div>

					<div class="form__meta">
						<label class="form__remember" for="remember">
							<input type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
							<span>Remember Me</span>
						</label>

						<a href="{{ route('admin.password.request') }}" class="form__forgot">Forgot Password?</a>
					</div>

					<button type="submit" class="form__submit btn">Log In</button>
				</form>

				@if (config('services.firebase.admin_google_login_enabled', true))
					<div class="form__social-divider" aria-hidden="true"><span>or</span></div>
					<button type="button" class="form__google-btn" id="adminGoogleSignInBtn">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
							<path fill="#4285F4" d="M21.35 12.27c0-.73-.07-1.43-.2-2.1H12v3.98h5.24a4.48 4.48 0 0 1-1.94 2.94v2.45h3.14c1.84-1.69 2.91-4.18 2.91-7.27Z" />
							<path fill="#34A853" d="M12 21.7c2.63 0 4.84-.87 6.45-2.36l-3.14-2.45c-.87.58-1.98.92-3.31.92-2.55 0-4.71-1.72-5.49-4.03H3.27v2.53A9.74 9.74 0 0 0 12 21.7Z" />
							<path fill="#FBBC05" d="M6.51 13.78A5.86 5.86 0 0 1 6.2 12c0-.62.11-1.22.31-1.78V7.69H3.27A9.74 9.74 0 0 0 2.25 12c0 1.56.37 3.04 1.02 4.31l3.24-2.53Z" />
							<path fill="#EA4335" d="M12 6.19c1.43 0 2.71.49 3.72 1.45l2.79-2.79C16.84 3.27 14.63 2.3 12 2.3a9.74 9.74 0 0 0-8.73 5.39l3.24 2.53C7.29 7.91 9.45 6.19 12 6.19Z" />
						</svg>
						<span id="adminGoogleSignInLabel">Continue with Google</span>
					</button>
					<p class="form__google-help">Google may ask you to confirm this sign-in on your phone.</p>
					<p class="form__google-status" id="adminGoogleSignInStatus" role="status" aria-live="polite"></p>
				@endif
			</section>
		</div>
	</main>

	@include('admin._two_factor_setup_modal')
	@include('admin._two_factor_challenge_modal')
	<x-password-toggle-script />

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
		integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
		crossorigin="anonymous"></script>
	@stack('admin_scripts')

	@if (config('services.firebase.admin_google_login_enabled', true))
		<script src="https://www.gstatic.com/firebasejs/10.12.5/firebase-app-compat.js"></script>
		<script src="https://www.gstatic.com/firebasejs/10.12.5/firebase-auth-compat.js"></script>
		<script>
			(function () {
				const button = document.getElementById('adminGoogleSignInBtn');
				const label = document.getElementById('adminGoogleSignInLabel');
				const status = document.getElementById('adminGoogleSignInStatus');
				const remember = document.getElementById('remember');
				if (!button) {
					return;
				}

				const firebaseConfig = {
					apiKey: @json(config('services.firebase.web.api_key')),
					authDomain: @json(config('services.firebase.web.auth_domain')),
					projectId: @json(config('services.firebase.web.project_id')),
					appId: @json(config('services.firebase.web.app_id')),
				};
				const hasConfig = Object.values(firebaseConfig).every(function (value) {
					return typeof value === 'string' && value.trim() !== '';
				});

				function setStatus(message) {
					if (status) {
						status.textContent = message || '';
					}
				}

				if (!hasConfig || typeof firebase === 'undefined') {
					button.disabled = true;
					setStatus('Google sign-in is not configured for this environment.');
					return;
				}

				try {
					if (!firebase.apps.length) {
						firebase.initializeApp(firebaseConfig);
					}
				} catch (error) {
					button.disabled = true;
					setStatus('Google sign-in could not be initialized.');
					return;
				}

				button.addEventListener('click', async function () {
					button.disabled = true;
					const originalLabel = label ? label.textContent : 'Continue with Google';
					if (label) label.textContent = 'Connecting to Google...';
					setStatus('');

					try {
						const provider = new firebase.auth.GoogleAuthProvider();
						provider.setCustomParameters({ prompt: 'select_account' });
						const result = await firebase.auth().signInWithPopup(provider);
						const user = result && result.user;
						if (!user) {
							throw new Error('Google did not return an account.');
						}

						const idToken = await user.getIdToken(true);
						const response = await fetch(@json(route('admin.login.google')), {
							method: 'POST',
							credentials: 'same-origin',
							headers: {
								'Content-Type': 'application/json',
								'Accept': 'application/json',
								'X-Requested-With': 'XMLHttpRequest',
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
							},
							body: JSON.stringify({
								id_token: idToken,
								remember: !!(remember && remember.checked),
							}),
						});
						const data = await response.json().catch(function () { return {}; });
						if (!response.ok) {
							throw new Error(data.message || 'This Google account cannot access the admin portal.');
						}

						window.location.assign(data.redirect_url || @json(route('admin.login')));
					} catch (error) {
						setStatus(error && error.message ? error.message : 'Google sign-in failed. Please try again.');
						if (firebase.auth && firebase.auth().currentUser) {
							await firebase.auth().signOut().catch(function () {});
						}
					} finally {
						button.disabled = false;
						if (label) label.textContent = originalLabel;
					}
				});
			})();
		</script>
	@endif
</body>

</html>
