<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Forgot Password | eDonate Admin</title>
	<x-edonate-favicon />
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<style>
		:root {
			--bg-from: #750000;
			--bg-to: #ff4e4e;
			--panel: #ffffff;
			--panel-muted: #f6f6f6;
			--primary: #b60c0c;
			--primary-dark: #850000;
			--text-main: #1d1d1d;
			--text-sub: #5d5d5d;
			--input-bg: #eaeaea;
			--shadow-card: 0 14px 28px rgba(0, 0, 0, 0.24);
			--shadow-soft: 0 4px 8px rgba(0, 0, 0, 0.15);
		}

		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			font-family: 'Poppins', sans-serif;
			background: linear-gradient(160deg, var(--bg-from), var(--bg-to));
			min-height: 100vh;
			color: var(--text-main);
		}

		.page {
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px 16px;
		}

		.module {
			width: 100%;
			max-width: 660px;
			background: var(--panel);
			border-radius: 24px;
			box-shadow: var(--shadow-card);
			overflow: hidden;
		}

		.module__header {
			padding: 24px 26px;
			background: linear-gradient(150deg, #ffefef, #ffd8d8);
			border-bottom: 1px solid rgba(0, 0, 0, 0.08);
		}

		.module__brand {
			font-size: 14px;
			font-weight: 600;
			color: var(--primary);
			margin: 0;
		}

		.module__title {
			font-size: 28px;
			font-weight: 700;
			color: var(--primary);
			line-height: 1.15;
			margin: 8px 0 10px;
		}

		.module__subtitle {
			font-size: 15px;
			line-height: 1.55;
			color: var(--text-sub);
			margin: 0;
			max-width: 500px;
		}

		.module__body {
			padding: 24px 26px 28px;
			background: var(--panel-muted);
		}

		.form-label {
			font-size: 14px;
			font-weight: 500;
			margin-bottom: 6px;
			color: #434343;
		}

		.form-control {
			height: 44px;
			border: none;
			border-radius: 10px;
			background: var(--input-bg);
			box-shadow: inset 0 4px 4px rgba(0, 0, 0, 0.15);
			font-size: 14px;
		}

		.form-control:focus {
			box-shadow: 0 0 0 0.2rem rgba(182, 12, 12, 0.18);
		}

		.btn-reset {
			height: 44px;
			width: 100%;
			border: none;
			border-radius: 10px;
			color: #fff;
			font-size: 15px;
			font-weight: 600;
			background: linear-gradient(120deg, var(--primary), var(--primary-dark));
			box-shadow: var(--shadow-soft);
		}

		.btn-reset:hover {
			opacity: 0.92;
		}

		.module__links {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 12px;
			margin-top: 14px;
		}

		.module__links a {
			font-size: 13px;
			font-weight: 500;
			color: var(--primary);
			text-decoration: none;
		}

		.module__links a:hover {
			text-decoration: underline;
		}

		.alert {
			border-radius: 10px;
			font-size: 13px;
			margin-bottom: 14px;
		}
	</style>
</head>
<body>
<main class="page" role="main">
	<section class="module" aria-label="Forgot password module">
		<header class="module__header">
			<p class="module__brand">eDonate Admin Portal</p>
			<h1 class="module__title">Find your account</h1>
			<p class="module__subtitle">Enter your email. We'll send you a link to reset your password.</p>
		</header>

		<div class="module__body">
			@if (session('success'))
				<div class="alert alert-success" role="alert">{{ session('success') }}</div>
			@endif

			@if ($errors->any())
				<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
			@endif

			<form method="POST" action="{{ route('admin.password.email') }}" novalidate>
				@csrf
				<label for="email" class="form-label">Email Address</label>
				<input
					type="email"
					id="email"
					name="email"
					class="form-control"
					value="{{ old('email') }}"
					placeholder="name@example.com"
					autocomplete="email"
					required
				>

				<div class="mt-3">
					<button type="submit" class="btn-reset btn">Send Reset Link</button>
				</div>
			</form>

			<div class="module__links">
				<a href="{{ route('admin.login') }}">Back to Admin Login</a>
				<a href="{{ route('donor.login') }}">Donor Login</a>
			</div>
		</div>
	</section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
