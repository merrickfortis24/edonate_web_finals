<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
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
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
			background:
				radial-gradient(circle at 10% -10%, #ffe6e9 0, #ffe6e9 18%, transparent 40%),
				radial-gradient(circle at 90% -20%, #fff5f5 0, #fff5f5 15%, transparent 35%),
				#f8fafc;
			color: var(--ink-900);
			min-height: 100vh;
		}

		.login-wrapper {
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 1.2rem 0.8rem;
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

		@media (min-width: 768px) {
			.login-head {
				padding: 1.6rem 1.7rem;
			}

			.login-body {
				padding: 1.5rem 1.7rem 1.7rem;
			}
		}
	</style>
</head>
<body>
<main class="login-wrapper">
	<section class="login-card" aria-labelledby="login-title">
		<header class="login-head">
			<h1 id="login-title">Donor Login</h1>
			<p>Sign in to continue your blood donation journey.</p>
		</header>

		<div class="login-body">
			@if (session('success'))
				<div class="alert alert-success" role="status">{{ session('success') }}</div>
			@endif

			@if ($errors->any())
				<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
			@endif

			<form method="POST" action="{{ route('donor.login.store') }}" novalidate>
				@csrf

				<div class="mb-3">
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
					>
					@error('email')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>

				<div class="mb-4">
					<label for="password" class="form-label">Password</label>
					<input
						type="password"
						class="form-control @error('password') is-invalid @enderror"
						id="password"
						name="password"
						required
						autocomplete="current-password"
					>
					@error('password')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>

				<div class="d-grid mb-3">
					<button type="submit" class="btn btn-danger btn-login">Login</button>
				</div>

				<p class="mb-0 text-center text-secondary">
					No account yet?
					<a href="{{ route('donor.signup') }}" class="link-danger fw-semibold">Create one</a>
				</p>
			</form>
		</div>
	</section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
