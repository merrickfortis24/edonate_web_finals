<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin 2FA Verification | eDonate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(160deg, #7a0909, #e34d4d);
            font-family: "Poppins", sans-serif;
        }

        .challenge-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .challenge-card {
            width: 100%;
            max-width: 480px;
            border: 0;
            border-radius: 18px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.2);
        }

        .challenge-card .card-header {
            background: #b80f0f;
            color: #fff;
            border: 0;
            border-radius: 18px 18px 0 0;
            padding: 20px 24px;
        }

        .challenge-card .card-body {
            padding: 24px;
        }
    </style>
</head>
<body>
<main class="challenge-shell">
    <section class="card challenge-card" aria-label="Admin two-factor verification">
        <header class="card-header">
            <h1 class="h5 mb-1">Two-Factor Verification</h1>
            <p class="mb-0 small">Use your Google Authenticator code to continue.</p>
        </header>

        <div class="card-body">
            @if (session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif

            @if (session('success'))
                <div class="alert alert-success" role="alert">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <p class="text-secondary small mb-3">
                Verification code is requested for <strong>{{ $maskedEmail ?? 'your account' }}</strong>.
                @if (!empty($remainingSeconds))
                    Session expires in about {{ max(1, (int) ceil($remainingSeconds / 60)) }} minute(s).
                @endif
            </p>

            <form method="POST" action="{{ route('admin.2fa.verify') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="code" class="form-label">Authenticator Code</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        class="form-control"
                        value="{{ old('code') }}"
                        placeholder="Enter 6-digit code"
                        autocomplete="one-time-code"
                    >
                </div>

                <div class="mb-3">
                    <label for="recovery_code" class="form-label">Recovery Code (optional)</label>
                    <input
                        id="recovery_code"
                        name="recovery_code"
                        type="text"
                        class="form-control"
                        value="{{ old('recovery_code') }}"
                        placeholder="Use only if authenticator is unavailable"
                        autocomplete="off"
                    >
                    <div class="form-text">Enter either authenticator code or one recovery code.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger flex-grow-1">Verify and Continue</button>
                    <a href="{{ route('admin.login') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </form>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
