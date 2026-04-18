@php
    $twoFactorSetupModal = is_array($twoFactorSetupModal ?? null) ? $twoFactorSetupModal : [];
    $requiresEnrollment = (bool) ($twoFactorSetupModal['required'] ?? false);
    $maskedEmail = trim((string) ($twoFactorSetupModal['maskedEmail'] ?? ''));
    $secret = trim((string) ($twoFactorSetupModal['secret'] ?? ''));
    $qrSvg = (string) ($twoFactorSetupModal['qrSvg'] ?? '');
    $recoveryCodes = is_array($twoFactorSetupModal['recoveryCodes'] ?? null)
        ? $twoFactorSetupModal['recoveryCodes']
        : [];

    $hasRecoveryCodes = $recoveryCodes !== [];
    $showOtpError = $errors->has('otp');
    $showModal = $requiresEnrollment || $hasRecoveryCodes || $showOtpError;
    $postLoginDashboardUrl = trim((string) ($postLoginDashboardUrl ?? ''));
@endphp

@if ($showModal)
    <div class="modal fade" id="twoFactorEnrollmentModal" tabindex="-1" aria-labelledby="twoFactorEnrollmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-danger-subtle">
                <div class="modal-header">
                    <h5 class="modal-title" id="twoFactorEnrollmentModalLabel">Google Authenticator Setup</h5>
                    @if (!$requiresEnrollment)
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    @endif
                </div>

                <div class="modal-body">
                    @if (session('warning'))
                        <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
                    @endif

                    @if ($showOtpError)
                        <div class="alert alert-danger" role="alert">{{ $errors->first('otp') }}</div>
                    @endif

                    @if (session('success'))
                        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
                    @endif

                    @if ($hasRecoveryCodes)
                        <section class="card border-success mb-3">
                            <header class="card-header bg-success-subtle text-success-emphasis fw-semibold">Recovery Codes (Save These Now)</header>
                            <div class="card-body">
                                <p class="mb-2">Each code can be used once if you lose access to Google Authenticator.</p>
                                <div class="row g-2">
                                    @foreach ($recoveryCodes as $code)
                                        <div class="col-12 col-md-6">
                                            <div class="form-control bg-light fw-semibold">{{ $code }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif

                    @if ($requiresEnrollment)
                        <p class="mb-2">Account: <strong>{{ $maskedEmail !== '' ? $maskedEmail : 'your account' }}</strong></p>
                        <p class="text-secondary mb-3">Scan the QR code using Google Authenticator, then enter the 6-digit code to complete enrollment.</p>

                        <div class="d-flex justify-content-center align-items-center p-3 bg-light rounded border mb-3" style="min-height: 250px;">
                            @if ($qrSvg !== '')
                                {!! $qrSvg !!}
                            @else
                                <p class="text-danger mb-0">QR code is not available right now. Refresh the page and try again.</p>
                            @endif
                        </div>

                        @if ($secret !== '')
                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1">Manual Secret Key</label>
                                <div class="form-control bg-light">{{ $secret }}</div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.2fa.enable') }}" novalidate>
                            @csrf
                            <input type="hidden" name="return_to_dashboard" value="1">

                            <div class="mb-3">
                                <label for="dashboardEnrollmentOtp" class="form-label">6-digit Authenticator Code</label>
                                <input
                                    id="dashboardEnrollmentOtp"
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
                    @endif
                </div>

                @if ($requiresEnrollment)
                    <div class="modal-footer justify-content-start">
                        <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">Log Out</button>
                        </form>
                    </div>
                @elseif ($postLoginDashboardUrl !== '')
                    <div class="modal-footer justify-content-between">
                        <a href="{{ $postLoginDashboardUrl }}" class="btn btn-danger">Continue to Dashboard</a>

                        <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">Log Out</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($showModal)
    @push('admin_scripts')
    <script>
        (function () {
            var modalElement = document.getElementById('twoFactorEnrollmentModal');
            if (!modalElement || typeof bootstrap === 'undefined') {
                return;
            }

            var modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
                backdrop: {!! $requiresEnrollment ? "'static'" : 'true' !!},
                keyboard: {!! $requiresEnrollment ? 'false' : 'true' !!}
            });

            modal.show();
        })();
    </script>
    @endpush
@endif
