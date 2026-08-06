@extends('layouts.app')

@section('title', 'eDonate - Admin Two-Factor Authentication')
@section('admin_page_class', 'admin-settings-page admin-two-factor-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('render_default_hamburger', 'false')

@section('header_title', 'Two-Factor Authentication')
@section('header_subtitle', 'Manage Google Authenticator for your admin account')

@section('header_slot')
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
    </button>
@endsection

@section('header_actions')
    <a href="{{ route('admin.settings') }}" class="btn btn-outline-danger btn-sm">Back to Settings</a>
@endsection

@section('content')
    <main class="main container-fluid px-0">
        <div class="container-fluid py-3">
            @if (session('success'))
                <div class="alert alert-success" role="alert">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            @php
                $codes = is_array($recoveryCodes ?? null) ? $recoveryCodes : [];
            @endphp

            @if ($codes !== [])
                <section class="card border-success mb-3">
                    <header class="card-header bg-success-subtle text-success-emphasis fw-semibold">Recovery Codes (Save These Now)</header>
                    <div class="card-body">
                        <p class="mb-2">Each code can be used once if you lose access to Google Authenticator.</p>
                        <div class="row g-2">
                            @foreach ($codes as $code)
                                <div class="col-12 col-md-6">
                                    <div class="form-control bg-light fw-semibold">{{ $code }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            <section class="card shadow-sm mb-3">
                <header class="card-header fw-semibold">Google Authenticator Status</header>
                <div class="card-body">
                    <p class="mb-2">
                        Account: <strong>{{ $maskedEmail ?? 'your account' }}</strong>
                    </p>

                    @if ($twoFactorEnabled)
                        <p class="mb-0 text-success fw-semibold">Two-factor authentication is currently enabled.</p>
                        @if (!empty($confirmedAt))
                            <p class="text-secondary small mt-1 mb-0">
                                Confirmed on {{ $confirmedAt->format('M d, Y h:i A') }}
                            </p>
                        @endif
                    @else
                        <p class="mb-0 text-warning-emphasis fw-semibold">Two-factor authentication is currently disabled.</p>
                    @endif
                </div>
            </section>

            @if (!$twoFactorEnabled)
                <section class="card shadow-sm mb-3">
                    <header class="card-header fw-semibold">Step 1: Scan QR Code</header>
                    <div class="card-body">
                        <p class="mb-3">
                            Open Google Authenticator, tap <strong>+</strong>, then scan this QR code.
                        </p>

                        <div class="d-flex justify-content-center align-items-center p-3 bg-light rounded border mb-3" style="min-height: 250px;">
                            @if (!empty($qrSvg))
                                {!! $qrSvg !!}
                            @else
                                <p class="text-danger mb-0">QR code is not available right now. Refresh the page and try again.</p>
                            @endif
                        </div>

                        @if (!empty($secret))
                            <div class="mb-2">
                                <label class="form-label fw-semibold mb-1">Manual Secret Key</label>
                                <div class="form-control bg-light">{{ $secret }}</div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="card shadow-sm">
                    <header class="card-header fw-semibold">Step 2: Confirm Setup</header>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.2fa.enable') }}" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label for="otp" class="form-label">6-digit Authenticator Code</label>
                                <input
                                    id="otp"
                                    name="otp"
                                    type="text"
                                    maxlength="6"
                                    inputmode="numeric"
                                    pattern="[0-9]{6}"
                                    class="form-control"
                                    value="{{ old('otp') }}"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-danger">Enable Two-Factor Authentication</button>
                        </form>
                    </div>
                </section>
            @else
                <section class="card shadow-sm">
                    <header class="card-header fw-semibold">Disable Two-Factor Authentication</header>
                    <div class="card-body">
                        <p class="text-secondary">
                            To disable 2FA, confirm your password and provide a fresh authenticator code.
                        </p>

                        <form method="POST" action="{{ route('admin.2fa.disable') }}" novalidate>
                            @csrf

                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input
                                        id="current_password"
                                        name="current_password"
                                        type="password"
                                        class="form-control"
                                        autocomplete="current-password"
                                        required
                                    >
                                </div>

                                <div class="col-12 col-lg-6">
                                    <label for="otp" class="form-label">6-digit Authenticator Code</label>
                                    <input
                                        id="otp"
                                        name="otp"
                                        type="text"
                                        maxlength="6"
                                        inputmode="numeric"
                                        pattern="[0-9]{6}"
                                        class="form-control"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-outline-danger">Disable Two-Factor Authentication</button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif
        </div>
    </main>
@endsection
