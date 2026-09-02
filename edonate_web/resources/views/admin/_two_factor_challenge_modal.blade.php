@php
    $twoFactorChallengeModal = is_array($twoFactorChallengeModal ?? null) ? $twoFactorChallengeModal : [];
    $isPendingChallenge = (bool) ($twoFactorChallengeModal['show'] ?? false);
    $showChallengeErrors = $errors->has('code') || $errors->has('recovery_code');
    $showChallengeModal = $isPendingChallenge || $showChallengeErrors;

    $maskedEmail = trim((string) ($twoFactorChallengeModal['maskedEmail'] ?? ''));
    $remainingSeconds = (int) ($twoFactorChallengeModal['remainingSeconds'] ?? 0);
    $promptNumber = trim((string) ($twoFactorChallengeModal['promptNumber'] ?? ''));
    $promptAvailable = (bool) ($twoFactorChallengeModal['promptAvailable'] ?? false);
    $challengeId = trim((string) ($twoFactorChallengeModal['challengeId'] ?? ''));
    $statusUrl = trim((string) ($twoFactorChallengeModal['statusUrl'] ?? ''));
    $triggerUrl = trim((string) ($twoFactorChallengeModal['triggerUrl'] ?? ''));
    $completeUrl = trim((string) ($twoFactorChallengeModal['completeUrl'] ?? ''));
    $channelName = trim((string) ($twoFactorChallengeModal['channelName'] ?? ''));
    $hasPromptView = $challengeId !== '' && preg_match('/^\d{2}$/', $promptNumber) === 1;
    // Keep the fallback visible after a failed TOTP/recovery submission so
    // the validation message and the next input are immediately available.
    $showPromptByDefault = $hasPromptView && ! $showChallengeErrors;
@endphp

@if ($showChallengeModal)
    <style>
        #twoFactorChallengeModal .modal-content { overflow: hidden; border: 0; border-radius: 1rem; box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, .22); }
        #twoFactorChallengeModal .modal-header { padding: 1rem 1.2rem; border: 0; background: linear-gradient(135deg, #850000, #c4121c); }
        #twoFactorChallengeModal .modal-title { font-weight: 750; }
        #twoFactorChallengeModal .modal-body { padding: 1.35rem; }
        #twoFactorChallengeModal .mfa-eyebrow { margin-bottom: .35rem; color: #b30a12; font-size: .72rem; font-weight: 800; letter-spacing: .12em; }
        #twoFactorChallengeModal .mfa-prompt-number { margin: .65rem auto 1rem; width: min(11rem, 55vw); aspect-ratio: 1.25; display: grid; place-items: center; color: #9e0710; background: linear-gradient(145deg, #fff, #fff1f1); border: 2px solid #efc4c7; border-radius: 1.1rem; box-shadow: inset 0 0 0 6px rgba(179, 10, 18, .04); font-size: clamp(3rem, 16vw, 5rem); font-weight: 850; letter-spacing: .08em; line-height: 1; }
        #twoFactorChallengeModal .mfa-prompt-copy { color: #637080; line-height: 1.5; }
        #twoFactorChallengeModal .mfa-prompt-copy strong { color: #27313e; }
        #twoFactorChallengeModal .mfa-status { min-height: 1.5rem; font-size: .85rem; }
        #twoFactorChallengeModal .mfa-choice-link { border: 0; background: transparent; color: #8b0911; text-decoration: underline; text-underline-offset: .18em; }
        #twoFactorChallengeModal .mfa-choice-link:hover { color: #5d050a; }
        #twoFactorChallengeModal .mfa-expiry { color: #6c757d; font-size: .78rem; }
        #twoFactorChallengeModal .mfa-resend { font-size: .82rem; }
        #twoFactorChallengeModal .mfa-totp-panel { padding-top: .15rem; }
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

                    <div id="adminMfaPromptPanel" class="{{ $showPromptByDefault ? '' : 'd-none' }}" data-challenge="{{ $challengeId }}">
                        <p class="mfa-eyebrow">GOOGLE PROMPT-STYLE APPROVAL</p>
                        <h6 class="h5 mb-2">Confirm this sign-in from your phone</h6>
                        <p class="mfa-prompt-copy mb-2">
                            Open the eDonate security notification on your registered phone/browser, then choose the number shown below.
                        </p>

                        <div class="mfa-prompt-number" id="adminMfaPromptNumber" aria-live="polite">{{ $promptNumber !== '' ? $promptNumber : '--' }}</div>

                        <p class="mfa-status text-center mb-2" id="adminMfaPromptStatus" role="status">
                            {{ $promptAvailable ? 'Waiting for approval from your registered device…' : 'Browser approval is unavailable on this device. You can use Google Authenticator below.' }}
                        </p>
                        <p class="mfa-expiry text-center mb-3" id="adminMfaPromptExpiry" data-remaining="{{ $remainingSeconds }}">
                            @if ($remainingSeconds > 0)
                                This verification session expires in about {{ max(1, (int) ceil($remainingSeconds / 60)) }} minute(s).
                            @else
                                This verification session may have expired.
                            @endif
                        </p>

                        <div class="d-flex flex-wrap justify-content-center align-items-center gap-2">
                            @if ($triggerUrl !== '')
                                <button type="button" class="btn btn-outline-danger mfa-resend" id="adminMfaResendButton">Send notification again</button>
                            @endif
                            <button type="button" class="mfa-choice-link" id="useAuthenticatorButton">Use Google Authenticator code instead</button>
                        </div>
                    </div>

                    <div id="adminMfaUnavailablePanel" class="alert alert-warning {{ $hasPromptView ? 'd-none' : '' }}" role="alert">
                        Number matching is not available for this login yet. Use your Google Authenticator code instead.
                    </div>

                    <div id="adminMfaTotpPanel" class="mfa-totp-panel {{ $showPromptByDefault ? 'd-none' : '' }}">
                        @if ($showChallengeErrors)
                            <div class="alert alert-danger" role="alert">{{ $errors->first('code') ?: $errors->first('recovery_code') }}</div>
                        @endif

                        <p class="text-secondary small mb-3">
                            Enter a fresh code for <strong>{{ $maskedEmail !== '' ? $maskedEmail : 'your account' }}</strong>.
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

                        @if ($hasPromptView)
                            <div class="text-center mt-3">
                                <button type="button" class="mfa-choice-link" id="useNumberMatchingButton">Use number matching instead</button>
                            </div>
                        @endif
                    </div>
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

            var state = {
                challengeId: @json($challengeId),
                statusUrl: @json($statusUrl),
                triggerUrl: @json($triggerUrl),
                completeUrl: @json($completeUrl),
                channelName: @json($channelName),
                promptAvailable: @json($promptAvailable),
                promptNumber: @json($promptNumber),
                remainingSeconds: @json($remainingSeconds)
            };
            var promptPanel = document.getElementById('adminMfaPromptPanel');
            var unavailablePanel = document.getElementById('adminMfaUnavailablePanel');
            var totpPanel = document.getElementById('adminMfaTotpPanel');
            var promptNumberElement = document.getElementById('adminMfaPromptNumber');
            var promptStatusElement = document.getElementById('adminMfaPromptStatus');
            var expiryElement = document.getElementById('adminMfaPromptExpiry');
            var resendButton = document.getElementById('adminMfaResendButton');
            var pollingTimer = null;
            var expiryTimer = null;
            var echoChannel = null;
            var completing = false;

            function csrfToken() {
                var token = document.querySelector('meta[name="csrf-token"]');
                if (token && token.getAttribute('content')) {
                    return token.getAttribute('content');
                }

                var formToken = document.querySelector('form input[name="_token"]');
                return formToken ? formToken.value : '';
            }

            function setPromptStatus(message, className) {
                if (!promptStatusElement) return;
                promptStatusElement.textContent = message;
                promptStatusElement.className = 'mfa-status text-center mb-2' + (className ? ' ' + className : '');
            }

            function showPromptView() {
                if (promptPanel) promptPanel.classList.remove('d-none');
                if (unavailablePanel) unavailablePanel.classList.add('d-none');
                if (totpPanel) totpPanel.classList.add('d-none');
            }

            function showTotpView() {
                if (promptPanel) promptPanel.classList.add('d-none');
                if (unavailablePanel) unavailablePanel.classList.add('d-none');
                if (totpPanel) totpPanel.classList.remove('d-none');
                var input = document.getElementById('loginChallengeCode');
                if (input) window.setTimeout(function () { input.focus(); }, 50);
            }

            function statusEndpoint() {
                return state.statusUrl + '?challenge=' + encodeURIComponent(state.challengeId);
            }

            function completePromptLogin() {
                if (completing || !state.completeUrl || !state.challengeId) return;
                completing = true;
                setPromptStatus('Approved. Finishing your admin sign-in…', 'text-success fw-semibold');
                if (resendButton) resendButton.disabled = true;

                var form = document.createElement('form');
                form.method = 'POST';
                form.action = state.completeUrl;
                var csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = csrfToken();
                var challenge = document.createElement('input');
                challenge.type = 'hidden';
                challenge.name = 'challenge';
                challenge.value = state.challengeId;
                form.appendChild(csrf);
                form.appendChild(challenge);
                document.body.appendChild(form);
                form.submit();
            }

            function approved() {
                completePromptLogin();
            }

            async function pollPromptStatus() {
                if (completing || !state.challengeId || !state.statusUrl) return;

                try {
                    var response = await fetch(statusEndpoint(), {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    if (response.status === 429) {
                        setPromptStatus('Too many status checks. Please wait a moment…', 'text-warning');
                        return;
                    }

                    var payload = await response.json();
                    if (payload.status === 'approved') {
                        approved();
                    } else if (payload.status === 'expired') {
                        setPromptStatus(payload.message || 'This verification session has expired. Please log in again.', 'text-danger');
                        stopPolling();
                    }
                } catch (error) {
                    // Polling is a fallback. A transient network error must not
                    // prevent the user from choosing TOTP instead.
                }
            }

            function startPolling() {
                stopPolling();
                if (!state.challengeId || !state.statusUrl) return;
                pollPromptStatus();
                pollingTimer = window.setInterval(pollPromptStatus, 2500);
            }

            function stopPolling() {
                if (pollingTimer) {
                    window.clearInterval(pollingTimer);
                    pollingTimer = null;
                }
            }

            function updateExpiry() {
                if (!expiryElement) return;
                state.remainingSeconds = Math.max(0, Number(state.remainingSeconds || 0) - 60);
                if (state.remainingSeconds <= 0) {
                    expiryElement.textContent = 'This verification session has expired. Please log in again.';
                    return;
                }

                var minutes = Math.ceil(state.remainingSeconds / 60);
                expiryElement.textContent = 'This verification session expires in about ' + minutes + ' minute(s).';
            }

            function subscribeEcho() {
                if (!window.Echo || !state.channelName || typeof window.Echo.private !== 'function') return;

                try {
                    echoChannel = window.Echo.private(state.channelName);
                    echoChannel.listen('.LoginApproved', approved);
                } catch (error) {
                    echoChannel = null;
                }
            }

            function unsubscribeEcho() {
                if (window.Echo && state.channelName && typeof window.Echo.leave === 'function') {
                    try { window.Echo.leave(state.channelName); } catch (error) { /* polling remains active */ }
                }
                echoChannel = null;
            }

            async function resendPrompt() {
                if (!resendButton || !state.triggerUrl || completing) return;
                resendButton.disabled = true;
                setPromptStatus('Sending a new approval notification…', 'text-secondary');

                try {
                    var response = await fetch(state.triggerUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: '{}'
                    });
                    var payload = await response.json();
                    if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to send the approval notification.');

                    unsubscribeEcho();
                    state.challengeId = payload.challenge || state.challengeId;
                    state.channelName = 'admin-mfa.' + state.challengeId;
                    state.promptNumber = String(payload.number || '--');
                    state.promptAvailable = !!payload.available;
                    state.remainingSeconds = Math.max(0, Number(payload.expiresAt || 0) - Math.floor(Date.now() / 1000));
                    if (promptPanel) promptPanel.dataset.challenge = state.challengeId;
                    if (promptNumberElement) promptNumberElement.textContent = state.promptNumber;
                    setPromptStatus(payload.message || 'Waiting for approval from your registered device…', payload.available ? 'text-secondary' : 'text-warning');
                    startPolling();
                    subscribeEcho();
                } catch (error) {
                    setPromptStatus(error && error.message ? error.message : 'Unable to send the approval notification.', 'text-danger');
                } finally {
                    resendButton.disabled = false;
                }
            }

            var modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
                backdrop: {!! $isPendingChallenge ? "'static'" : "'true'" !!},
                keyboard: {!! $isPendingChallenge ? 'false' : 'true' !!}
            });

            var useAuthenticatorButton = document.getElementById('useAuthenticatorButton');
            var useNumberMatchingButton = document.getElementById('useNumberMatchingButton');
            if (useAuthenticatorButton) useAuthenticatorButton.addEventListener('click', showTotpView);
            if (useNumberMatchingButton) useNumberMatchingButton.addEventListener('click', showPromptView);
            if (resendButton) resendButton.addEventListener('click', resendPrompt);

            modalElement.addEventListener('shown.bs.modal', function () {
                if (state.challengeId) {
                    startPolling();
                    subscribeEcho();
                }
                if (expiryTimer) window.clearInterval(expiryTimer);
                expiryTimer = window.setInterval(updateExpiry, 60000);
            });
            modalElement.addEventListener('hidden.bs.modal', function () {
                stopPolling();
                if (expiryTimer) window.clearInterval(expiryTimer);
                unsubscribeEcho();
            });

            modal.show();
        })();
    </script>
    @endpush
@endif
