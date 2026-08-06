@php
    $twoFactorChallengeModal = is_array($twoFactorChallengeModal ?? null) ? $twoFactorChallengeModal : [];
    $isPendingChallenge = (bool) ($twoFactorChallengeModal['show'] ?? false);
    $showChallengeErrors = $errors->has('code') || $errors->has('recovery_code');
    $showChallengeModal = $isPendingChallenge || $showChallengeErrors;

    $maskedEmail = trim((string) ($twoFactorChallengeModal['maskedEmail'] ?? ''));
    $remainingSeconds = (int) ($twoFactorChallengeModal['remainingSeconds'] ?? 0);
@endphp

@if ($showChallengeModal)
    <div class="modal fade" id="twoFactorChallengeModal" tabindex="-1" aria-labelledby="twoFactorChallengeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger-subtle">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="twoFactorChallengeModalLabel">Two-Factor Verification</h5>
                </div>

                <div class="modal-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                    @endif

                    @if ($showChallengeErrors)
                        <div class="alert alert-danger" role="alert">{{ $errors->first('code') ?: $errors->first('recovery_code') }}</div>
                    @endif

                    <p class="text-secondary small mb-3">
                        Verification code is requested for <strong>{{ $maskedEmail !== '' ? $maskedEmail : 'your account' }}</strong>.
                        @if ($remainingSeconds > 0)
                            Session expires in about {{ max(1, (int) ceil($remainingSeconds / 60)) }} minute(s).
                        @endif
                    </p>

                    <form method="POST" action="{{ route('admin.2fa.verify') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="loginChallengeCode" class="form-label">Authenticator Code</label>
                            <input
                                id="loginChallengeCode"
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
                            <label for="loginChallengeRecoveryCode" class="form-label">Recovery Code (optional)</label>
                            <input
                                id="loginChallengeRecoveryCode"
                                name="recovery_code"
                                type="text"
                                class="form-control"
                                value="{{ old('recovery_code') }}"
                                placeholder="Use only if authenticator is unavailable"
                                autocomplete="off"
                            >
                            <div class="form-text">Enter either authenticator code or one recovery code.</div>
                        </div>

                        <button type="submit" class="btn btn-danger w-100">Verify and Continue</button>
                    </form>
                </div>

                <div class="modal-footer justify-content-start">
                    <form method="POST" action="{{ route('admin.2fa.cancel') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Back</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

@if ($showChallengeModal)
    @push('admin_scripts')
    <script>
        (function () {
            var modalElement = document.getElementById('twoFactorChallengeModal');
            if (!modalElement || typeof bootstrap === 'undefined') {
                return;
            }

            var modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
                backdrop: {!! $isPendingChallenge ? "'static'" : 'true' !!},
                keyboard: {!! $isPendingChallenge ? 'false' : 'true' !!}
            });

            modal.show();
        })();
    </script>
    @endpush
@endif
