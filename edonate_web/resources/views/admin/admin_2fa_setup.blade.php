@extends('layouts.admin')

@section('title', 'eDonate - Admin Two-Factor Authentication')
@section('admin_page_class', 'admin-settings-page admin-two-factor-page')
@section('header_title', 'Two-Factor Authentication')
@section('header_subtitle', 'Manage Google Authenticator for your admin account')

@section('header_actions')
    <a href="{{ route('admin.settings') }}" class="btn btn-outline-danger btn-sm">Back to Settings</a>
@endsection

@section('main_content')
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

                        <div class="d-flex justify-content-center align-items-center p-3 bg-body-secondary rounded border mb-3" style="min-height: 250px;">
                            @if (!empty($qrSvg))
                                {!! $qrSvg !!}
                            @else
                                <p class="text-danger mb-0">QR code is not available right now. Refresh the page and try again.</p>
                            @endif
                        </div>

                        @if (!empty($secret))
                            <div class="mb-2">
                                <label class="form-label fw-semibold mb-1">Manual Secret Key</label>
                                <div class="form-control bg-body-secondary">{{ $secret }}</div>
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
                <section class="card border-warning shadow-sm mb-3">
                    <header class="card-header fw-semibold">Regenerate Recovery Codes</header>
                    <div class="card-body">
                        <p class="text-body-secondary">Regeneration invalidates every previously issued recovery code. Verify your identity with a fresh Google Authenticator code to continue.</p>
                        <form method="POST" action="{{ route('admin.2fa.recovery-codes.regenerate') }}" novalidate>
                            @csrf
                            <div class="row align-items-end g-3">
                                <div class="col-12 col-md-6">
                                    <label for="regenerate_recovery_otp" class="form-label">6-digit Authenticator Code</label>
                                    <input id="regenerate_recovery_otp" name="otp" type="text" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" class="form-control" autocomplete="one-time-code" required>
                                </div>
                                <div class="col-12 col-md-auto">
                                    <button type="submit" class="btn btn-outline-danger">Verify &amp; Regenerate Codes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>

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
                                    <label for="disable_otp" class="form-label">6-digit Authenticator Code</label>
                                    <input
                                        id="disable_otp"
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

            <section class="card shadow-sm mt-3">
                <header class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold">Trusted Devices</span>
                    @if (($activeTrustedDeviceCount ?? 0) > 0)
                        <form method="POST" action="{{ route('admin.2fa.trusted-devices.revoke-all') }}" class="m-0"
                            onsubmit="return confirm('Revoke all trusted devices? They will need to complete two-factor authentication the next time they sign in.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">Revoke All Trusted Devices</button>
                        </form>
                    @endif
                </header>
                <div class="card-body">
                    <p class="text-body-secondary small">
                        These browser-specific credentials can skip 2FA only after a valid password sign-in. Each expires 30 days after it was trusted.
                    </p>

                    @if (!($trustedDevicesAvailable ?? false))
                        <div class="alert alert-warning mb-0" role="alert">Trusted-device storage is not available. Run the application migrations to enable this feature.</div>
                    @elseif (($trustedDevices ?? collect())->isEmpty())
                        <p class="text-body-secondary mb-0">No trusted devices have been registered for this account.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Device</th>
                                        <th scope="col">Browser</th>
                                        <th scope="col">Platform</th>
                                        <th scope="col">IP Address</th>
                                        <th scope="col">Trusted Since</th>
                                        <th scope="col">Last Used</th>
                                        <th scope="col">Expires</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trustedDevices as $device)
                                        @php
                                            $deviceExpired = \Illuminate\Support\Carbon::parse($device->expires_at)->lessThanOrEqualTo(now());
                                            $deviceRevoked = !empty($device->revoked_at);
                                            $deviceActive = !$deviceRevoked && !$deviceExpired;
                                        @endphp
                                        <tr>
                                            <td>{{ $device->device_name ?: 'Unknown device' }}</td>
                                            <td>{{ $device->browser ?: 'Unknown' }}</td>
                                            <td>{{ $device->platform ?: 'Unknown' }}</td>
                                            <td>{{ $device->ip_address ?: '—' }}</td>
                                            <td>{{ \Illuminate\Support\Carbon::parse($device->trusted_at)->format('M d, Y h:i A') }}</td>
                                            <td>{{ $device->last_used_at ? \Illuminate\Support\Carbon::parse($device->last_used_at)->format('M d, Y h:i A') : '—' }}</td>
                                            <td>{{ \Illuminate\Support\Carbon::parse($device->expires_at)->format('M d, Y h:i A') }}</td>
                                            <td>
                                                @if ($deviceRevoked)
                                                    <span class="badge text-bg-secondary">Revoked</span>
                                                @elseif ($deviceExpired)
                                                    <span class="badge text-bg-warning">Expired</span>
                                                @else
                                                    <span class="badge text-bg-success">Active</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($deviceActive)
                                                    <form method="POST" action="{{ route('admin.2fa.trusted-devices.revoke', $device->id) }}" class="m-0"
                                                        onsubmit="return confirm('Revoke this trusted device? It will need to complete two-factor authentication the next time it signs in.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Revoke</button>
                                                    </form>
                                                @else
                                                    <span class="text-body-secondary">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </main>
@endsection
