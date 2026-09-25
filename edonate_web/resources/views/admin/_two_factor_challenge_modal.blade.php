@php
    $twoFactorChallengeModal = is_array($twoFactorChallengeModal ?? null) ? $twoFactorChallengeModal : [];
    $isPendingChallenge = (bool) ($twoFactorChallengeModal['show'] ?? false);
    $showChallengeErrors = $errors->has('code') || $errors->has('recovery_code');
    $showChallengeModal = $isPendingChallenge || $showChallengeErrors;

    $maskedEmail = trim((string) ($twoFactorChallengeModal['maskedEmail'] ?? ''));
@endphp

@if ($showChallengeModal)
    <style>
        #twoFactorChallengeModal .modal-content { overflow: hidden; border: 0; border-radius: 1rem; box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, .22); }
        #twoFactorChallengeModal .modal-header { padding: 1rem 1.2rem; border: 0; background: linear-gradient(135deg, #850000, #c4121c); }
        #twoFactorChallengeModal .modal-title { font-weight: 750; }
        #twoFactorChallengeModal .modal-body { padding: 1.35rem; }
        @media (max-width: 575.98px) { #twoFactorChallengeModal .modal-dialog { margin: .75rem; } }
    </style>

    <div class="modal fade" id="twoFactorChallengeModal" tabindex="-1" aria-labelledby="twoFactorChallengeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger-subtle">
                <div class="modal-header text-white">
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
                        <div class="alert alert-danger" role="alert">
                            {{ $errors->first('code') ?: $errors->first('recovery_code') }}
                        </div>
                    @endif

                    <p class="mb-3">
                        Enter your Google Authenticator code to continue.
                    </p>

                    @if ($maskedEmail !== '')
                        <p class="text-secondary small mb-3">
                            Enter a fresh code for <strong>{{ $maskedEmail }}</strong>.
                        </p>
                    @endif

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
                                autofocus
                            >
                        </div>

                        <div class="d-flex align-items-center gap-2 mb-3 text-secondary" aria-hidden="true">
                            <span class="flex-grow-1 border-top"></span>
                            <span class="small">or</span>
                            <span class="flex-grow-1 border-top"></span>
                        </div>

                        <div class="mb-3">
                            <label for="loginChallengeRecoveryCode" class="form-label">Recovery Code</label>
                            <input
                                id="loginChallengeRecoveryCode"
                                name="recovery_code"
                                type="text"
                                maxlength="64"
                                class="form-control"
                                value="{{ old('recovery_code') }}"
                                placeholder="Enter a recovery code"
                                autocomplete="off"
                            >
                            <div class="form-text">Use one recovery code if you cannot access Google Authenticator. Each code works once.</div>
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
                backdrop: {!! $isPendingChallenge ? "'static'" : "'true'" !!},
                keyboard: {!! $isPendingChallenge ? 'false' : 'true' !!}
            });

            modalElement.addEventListener('shown.bs.modal', function () {
                var input = document.getElementById('loginChallengeCode');
                if (input) {
                    input.focus();
                }
            });

            modal.show();
        })();
    </script>
    @endpush
@endif
