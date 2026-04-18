<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin Login | eDonate</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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

		*, *::before, *::after {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		html, body {
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

		.card__left__icon {
			width: 154px;
			height: 154px;
			border-radius: 50%;
			background: rgba(255, 255, 255, 0.1);
			border: 1px solid rgba(255, 255, 255, 0.3);
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.card__left__icon svg {
			width: 86px;
			height: 86px;
		}

		.card__left__brand {
			font-size: var(--fs-md);
			font-weight: 700;
			margin-top: 8px;
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

			.card__left__icon {
				width: 110px;
				height: 110px;
			}

			.card__left__icon svg {
				width: 62px;
				height: 62px;
			}

			.card__left__brand {
				font-size: 17px;
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

			.card__left__icon {
				width: 90px;
				height: 90px;
			}

			.card__left__icon svg {
				width: 50px;
				height: 50px;
			}

			.card__left__brand {
				font-size: 16px;
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
			<div class="card__left__icon" aria-hidden="true">
				<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M32 6C32 6 15 24 15 37.5C15 47.7173 23.2827 56 33.5 56C43.7173 56 52 47.7173 52 37.5C52 24 32 6 32 6Z" fill="white"/>
					<path d="M39.5 37.5C39.5 41.6421 36.1421 45 32 45" stroke="#b60c0c" stroke-width="4" stroke-linecap="round"/>
				</svg>
			</div>

			<p class="card__left__brand">eDonate</p>

			<p class="card__left__tagline">
				Give blood, save lives.<br>
				Join our community of lifesavers today.
			</p>

			<div class="card__left__stats" aria-label="Statistics">
				<div class="stat">
					<span class="stat__number">12k+</span>
					<span class="stat__label">Donors</span>
				</div>
				<div class="stat">
					<span class="stat__number">9k+</span>
					<span class="stat__label">Donations</span>
				</div>
				<div class="stat">
					<span class="stat__number">27k+</span>
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
				<input
					class="form__input form-control"
					id="email"
					type="email"
					name="email"
					value="{{ old('email') }}"
					autocomplete="email"
					required
				>

				<label class="form__label" for="password">Password</label>
				<input
					class="form__input form-control"
					id="password"
					type="password"
					name="password"
					autocomplete="current-password"
					required
				>

				<div class="form__meta">
					<label class="form__remember" for="remember">
						<input
							type="checkbox"
							id="remember"
							name="remember"
							value="1"
							{{ old('remember') ? 'checked' : '' }}
						>
						<span>Remember Me</span>
					</label>

					<a href="{{ route('admin.password.request') }}" class="form__forgot">Forgot Password?</a>
				</div>

				<button type="submit" class="form__submit btn">Log In</button>
			</form>
		</section>
	</div>
</main>

@include('admin._two_factor_setup_modal')
@include('admin._two_factor_challenge_modal')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
@stack('admin_scripts')
</body>
</html>
