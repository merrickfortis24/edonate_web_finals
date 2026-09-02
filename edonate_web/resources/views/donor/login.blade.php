<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>Donor Login | Blood Donation Management System</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<style>
		:root {
			--health-red: #c62f3c;
			--ink-900: #1f2937;
			--ink-500: #6b7280;
			--line-soft: #e5e7eb;
			--surface: #ffffff;
			--focus-ring: rgba(198, 47, 60, 0.28);
			--shadow-soft: 0 16px 40px rgba(17, 24, 39, 0.1);
		}

		body {
			margin: 0;
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
			background:
				radial-gradient(circle at 10% -10%, #ffe6e9 0, #ffe6e9 18%, transparent 40%),
				radial-gradient(circle at 90% -20%, #fff5f5 0, #fff5f5 15%, transparent 35%),
				#f8fafc;
			color: var(--ink-900);
			min-height: 100vh;
			overflow-x: hidden;
		}

		.login-wrapper {
			min-height: auto;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 0 0.8rem 1.25rem;
		}

		.login-card {
			width: 100%;
			max-width: 30rem;
			border-radius: 1rem;
			border: 1px solid rgba(198, 47, 60, 0.12);
			box-shadow: var(--shadow-soft);
			background: var(--surface);
			overflow: hidden;
		}

		.login-head {
			padding: 1.4rem 1.25rem;
			border-bottom: 1px solid var(--line-soft);
			background: linear-gradient(135deg, #fff 0%, #fff7f8 100%);
		}

		.login-head h1 {
			margin: 0;
			font-size: 1.55rem;
			font-weight: 700;
		}

		.login-head p {
			margin: 0.4rem 0 0;
			color: var(--ink-500);
		}

		.login-body {
			padding: 1.2rem;
		}

		.form-control {
			min-height: 2.75rem;
			border-color: #d1d5db;
		}

		.form-control:focus {
			border-color: var(--health-red);
			box-shadow: 0 0 0 0.2rem var(--focus-ring);
		}

		.btn-login {
			min-height: 2.75rem;
			background: var(--health-red);
			border: none;
			font-weight: 600;
		}

		.btn-login:hover,
		.btn-login:focus {
			background: #ab2431;
		}

		.btn-google {
			min-height: 2.75rem;
			background: #ffffff;
			border: 1px solid #d1d5db;
			color: #1f2937;
			font-weight: 600;
		}

		.btn-google:hover,
		.btn-google:focus {
			background: #f9fafb;
			border-color: #cbd5e1;
		}

		.agreement-box {
			background: #fff5f6;
			border: 1px solid rgba(198, 47, 60, 0.2);
			border-radius: 0.7rem;
			padding: 0.8rem 0.9rem;
		}

		.separator {
			display: flex;
			align-items: center;
			gap: 0.75rem;
			color: var(--ink-500);
			font-size: 0.9rem;
		}

		.separator::before,
		.separator::after {
			content: "";
			flex: 1;
			height: 1px;
			background: var(--line-soft);
		}

		@media (min-width: 768px) {
			.login-head {
				padding: 1.6rem 1.7rem;
			}

			.login-body {
				padding: 1.5rem 1.7rem 1.7rem;
			}
		}

		/* Mobile-style header + rounded card layout to match design */
		.hero-header {
			background: linear-gradient(180deg,#8a0f12 0%, #c62f3c 55%, #d94b47 100%);
			padding: 3.5rem 1rem 5.5rem;
			color: #fff;
			text-align: center;
		}

		.hero-logo {
			display: flex;
			flex-direction: column;
			align-items: center;
			font-weight: 800;
			font-size: 1.5rem;
		}

		.hero-logo__image {
			display: block;
			overflow: hidden;
			width: min(100%, 24rem);
			/* Leave a little vertical breathing room so the source artwork is not clipped. */
			aspect-ratio: 4.2 / 1;
		}

		.hero-logo__image img {
			display: block;
			height: 100%;
			filter: drop-shadow(0 0 2px rgba(255, 255, 255, .85));
			object-fit: cover;
			object-position: center 47%;
			width: 100%;
		}

		.hero-logo__subtitle {
			font-size: .85rem;
			font-weight: 600;
			margin-top: .55rem;
		}

		.card-overlay {
			background: linear-gradient(180deg, #f5f5f7 0%, #ececef 100%);
			border-radius: 1.25rem;
			border: 1px solid rgba(31, 41, 55, 0.08);
			max-width: 420px;
			margin: -3.4rem auto 0;
			box-shadow: 0 18px 40px rgba(17,24,39,0.14);
			padding: 1.4rem 1.25rem;
		}

		.welcome-title { color: #6b0f12; font-size: 1.5rem; font-weight: 800; margin: 0 0 .25rem; }
		.welcome-sub { color: #6b7280; margin: 0 0 1rem; }

		.form-control { border-radius: 0.75rem; padding-left: 3.25rem; }
		.input-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); width: 1.6rem; height: 1.6rem; color: #6b7280; }
		.input-group { position: relative; }
		.password-input-group .form-control { padding-right: 3.25rem; }
		.password-toggle {
			position: absolute;
			right: 0.7rem;
			top: 50%;
			z-index: 5;
			width: 2.25rem;
			height: 2.25rem;
			transform: translateY(-50%);
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0;
			border: 0;
			border-radius: 50%;
			background: transparent;
			color: #6b7280;
			cursor: pointer;
		}
		.password-toggle:hover { background: rgba(198, 47, 60, 0.08); color: #9b1a1f; }
		.password-toggle:focus-visible { outline: 2px solid #c62f3c; outline-offset: 2px; }
		.password-toggle svg { width: 1.1rem; height: 1.1rem; }

		.btn-signin { background: #6b0f12; border-radius: .75rem; color: #fff; padding: .85rem 1rem; font-weight:700; }
		.btn-signin:hover { background: #50090a; }

		.btn-create { border-radius: .75rem; border: 2px solid #c62f3c; color: #6b0f12; background: transparent; padding: .7rem 0.9rem; }

		.info-box { background: #fde8e8; border-radius: .75rem; padding: .9rem 1rem; color: #6b0f12; margin-top: 1rem; display:flex; gap:.75rem; align-items:center; }
		.info-box .heart { width: 1.5rem; height:1.5rem; border-radius:.35rem; display:inline-flex; align-items:center; justify-content:center; border:2px solid #f1a6a6; color:#c62f3c; background:#fff0f0; }

		.terms-box {
			background: #fff5f6;
			border: 1px solid rgba(198, 47, 60, 0.2);
			border-radius: 0.75rem;
			padding: 0.75rem 0.9rem;
		}

		.btn-google {
			border-radius: 0.75rem;
		}

		.separator {
			display: flex;
			align-items: center;
			gap: 0.75rem;
			color: var(--ink-500);
			font-size: 0.9rem;
		}

		.separator::before,
		.separator::after {
			content: "";
			flex: 1;
			height: 1px;
			background: var(--line-soft);
		}
	</style>
</head>
<body>
<header class="hero-header">
	<div class="hero-logo">
		<span class="hero-logo__image">
			<img src="{{ asset('images/edonate-logo.png') }}" alt="eDonate">
		</span>
		<div class="hero-logo__subtitle">Blood Donation App</div>
	</div>
</header>

<main class="login-wrapper">
	<section class="card-overlay" aria-labelledby="login-title">
		<div class="text-center mb-2">
			<h1 class="welcome-title" id="login-title">Welcome Back</h1>
			<p class="welcome-sub">Sign in to continue saving lives</p>
		</div>

		<div class="login-body p-0">
			@if (session('success'))
				<div class="alert alert-success" role="status">{{ session('success') }}</div>
			@endif

			@if ($errors->any())
				<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
			@endif

			<form method="POST" action="{{ route('donor.login.store') }}" novalidate>
				@csrf

				<div class="mb-3 input-group password-input-group">
					<span class="input-icon">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
							<path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v.217l-8 5.333-8-5.333V4z"/>
							<path d="M0 4.697v7.104l5.803-3.868L0 4.697zM6.761 8.83 16 12.5V4.697l-9.239 4.133z"/>
						</svg>
					</span>
					<input
						type="email"
						class="form-control @error('email') is-invalid @enderror"
						id="email"
						name="email"
						value="{{ old('email') }}"
						required
						maxlength="150"
						autocomplete="email"
						placeholder="your.email@gmail.com"
					>
					@error('email')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>

				<div class="mb-3 input-group">
					<span class="input-icon">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-lock" viewBox="0 0 16 16">
							<path d="M8 1a3 3 0 0 0-3 3v3h6V4a3 3 0 0 0-3-3z"/>
							<path d="M3 8a1 1 0 0 0-1 1v4a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V9a1 1 0 0 0-1-1H3z"/>
						</svg>
					</span>
					<input
						type="password"
						class="form-control @error('password') is-invalid @enderror"
						id="password"
						name="password"
						required
						autocomplete="current-password"
						placeholder="Enter your password"
					>
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
					@error('password')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>

				<div class="d-grid mb-3">
					<button type="submit" class="btn btn-signin">Sign In</button>
				</div>

				<div class="terms-box mb-3">
					<div class="form-check">
						<input
							class="form-check-input @error('terms') is-invalid @enderror"
							type="checkbox"
							value="1"
							id="terms"
							name="terms"
							{{ old('terms') ? 'checked' : '' }}
							required
						>
						<label class="form-check-label" for="terms">
							I agree to the
							<a href="#" role="button" class="btn btn-link p-0 align-baseline link-danger" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a>
							and
							<a href="#" role="button" class="btn btn-link p-0 align-baseline link-danger" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>.
						</label>
						@error('terms')
							<div class="invalid-feedback d-block">{{ $message }}</div>
						@else
							<div class="invalid-feedback">You must agree before continuing.</div>
						@enderror
					</div>
				</div>

				<div class="separator mb-3">or</div>

				<div class="d-grid mb-3">
					<button type="button" id="googleSignInBtn" class="btn btn-google">Sign in with Google</button>
				</div>

			<div class="d-flex align-items-center justify-content-between mb-2">
				<div class="form-check">
					<input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
					<label class="form-check-label" for="remember">Remember me</label>
				</div>
				<div>
					@if (Route::has('password.request'))
						<a href="{{ route('password.request') }}" class="text-decoration-none" style="color:#6b0f12">Forgot Password?</a>
					@else
						<a href="{{ url('/password/reset') }}" class="text-decoration-none" style="color:#6b0f12">Forgot Password?</a>
					@endif
				</div>
			</div>

			<div class="text-center mb-3"> 
				<div style="color:#9b1a1f;font-weight:600;margin-bottom:.5rem">New to eDonate?</div>
				<a href="{{ route('donor.signup') }}" class="btn btn-create w-100">Create an Account</a>
			</div>

			<div class="info-box">
				<div class="heart">❤</div>
				<div>
					<div style="font-weight:700">Join thousands of donors making a difference.</div>
					<div style="font-size:.92rem;color:#7a4b4b">One donation can save up to three lives</div>
				</div>
			</div>
			</form>
		</div>
	</section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.5/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.5/firebase-auth-compat.js"></script>
<script>
	(function () {
		const config = {
			apiKey: @json(env('FIREBASE_WEB_API_KEY')),
			authDomain: @json(env('FIREBASE_WEB_AUTH_DOMAIN')),
			projectId: @json(env('FIREBASE_WEB_PROJECT_ID')),
			appId: @json(env('FIREBASE_WEB_APP_ID')),
		};

		const hasConfig = config.apiKey && config.authDomain && config.projectId && config.appId;
		const button = document.getElementById('googleSignInBtn');
		const termsCheckbox = document.getElementById('terms');
		if (!button) {
			return;
		}

		if (!hasConfig) {
			button.disabled = true;
			button.textContent = 'Google Sign-In not configured';
			return;
		}

		if (!firebase.apps.length) {
			firebase.initializeApp(config);
		}

		button.addEventListener('click', async function () {
			if (!termsCheckbox || !termsCheckbox.checked) {
				alert('Please agree to the Terms of Service and Privacy Policy before signing in.');
				return;
			}

			button.disabled = true;
			const original = button.textContent;
			button.textContent = 'Signing in...';

			try {
				const provider = new firebase.auth.GoogleAuthProvider();
				const result = await firebase.auth().signInWithPopup(provider);
				const user = result.user;

				if (!user) {
					throw new Error('No Google user returned.');
				}

				const idToken = await user.getIdToken();
				const response = await fetch(@json(route('auth.google')), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
						'Accept': 'application/json',
					},
					credentials: 'same-origin',
					body: JSON.stringify({
						id_token: idToken,
						uid: user.uid,
						email: user.email,
						full_name: user.displayName,
						terms_accepted: true,
					}),
				});

				const data = await response.json();
				if (!response.ok) {
					throw new Error(data.message || 'Google login failed.');
				}

				window.location.href = data.redirect_url;
			} catch (error) {
				alert(error.message || 'Google sign-in failed. Please try again.');
			} finally {
				button.disabled = false;
				button.textContent = original;
			}
		});
	})();
</script>
<x-password-toggle-script />
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
