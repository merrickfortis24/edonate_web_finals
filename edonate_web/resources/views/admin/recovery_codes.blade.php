@extends('layouts.admin')

@section('title', 'eDonate - Save Recovery Codes')
@section('admin_page_class', 'admin-settings-page admin-recovery-codes-page')
@section('header_title', 'Save Your Recovery Codes')
@section('header_subtitle', 'These codes are shown only once')

@section('main_content')
    <main class="main container-fluid px-0">
        <div class="container-fluid py-3">
            <section class="card border-warning shadow-sm mx-auto" style="max-width: 900px;">
                <header class="card-header bg-warning-subtle text-warning-emphasis">
                    <h2 class="h5 mb-0"><i class="fas fa-triangle-exclamation me-2" aria-hidden="true"></i>Store these codes somewhere secure</h2>
                </header>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <strong>Save these recovery codes now. You will need one if you reset your password, and they will not be shown again.</strong>
                    </div>

                    <p class="text-body-secondary">Each code can be used once. Keep them private and do not save them on a shared computer.</p>

                    <div class="row g-2 mb-3" aria-label="One-time recovery codes">
                        @foreach ($recoveryCodes as $code)
                            <div class="col-12 col-sm-6">
                                <code class="d-block rounded border bg-body-secondary px-3 py-2 fw-bold text-center fs-5">{{ $code }}</code>
                            </div>
                        @endforeach
                    </div>

                    <textarea id="recoveryCodesCopySource" class="visually-hidden" aria-hidden="true" tabindex="-1">{{ implode("\n", $recoveryCodes) }}</textarea>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <button type="button" class="btn btn-outline-secondary" id="copyRecoveryCodes">
                            <i class="fas fa-copy me-1" aria-hidden="true"></i>Copy Codes
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="downloadRecoveryCodes">
                            <i class="fas fa-download me-1" aria-hidden="true"></i>Download Codes
                        </button>
                        <span id="recoveryCodeActionStatus" class="align-self-center small text-body-secondary" aria-live="polite"></span>
                    </div>

                    <form method="POST" action="{{ route('admin.2fa.recovery-codes.acknowledge') }}">
                        @csrf
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="saved_codes" id="savedRecoveryCodes" value="1" required>
                            <label class="form-check-label fw-semibold" for="savedRecoveryCodes">I have saved these recovery codes in a secure place.</label>
                        </div>
                        @error('saved_codes')
                            <div class="text-danger small mb-3" role="alert">{{ $message }}</div>
                        @enderror
                        <button type="submit" class="btn btn-danger">I’ve Saved Them — Continue</button>
                    </form>
                </div>
            </section>
        </div>
    </main>
@endsection

@push('admin_scripts')
<script>
    (function () {
        var source = document.getElementById('recoveryCodesCopySource');
        var status = document.getElementById('recoveryCodeActionStatus');
        var codes = source ? source.value : '';

        function fallbackCopy() {
            if (!source) return false;
            source.classList.remove('visually-hidden');
            source.select();
            var copied = document.execCommand('copy');
            source.classList.add('visually-hidden');
            return copied;
        }

        document.getElementById('copyRecoveryCodes')?.addEventListener('click', function () {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(codes).then(function () {
                    status.textContent = 'Recovery codes copied.';
                }).catch(function () {
                    status.textContent = fallbackCopy() ? 'Recovery codes copied.' : 'Copy was unavailable. Select and copy the codes manually.';
                });
                return;
            }
            status.textContent = fallbackCopy() ? 'Recovery codes copied.' : 'Copy was unavailable. Select and copy the codes manually.';
        });

        document.getElementById('downloadRecoveryCodes')?.addEventListener('click', function () {
            var blob = new Blob([codes + '\n'], { type: 'text/plain;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = 'edonate-recovery-codes.txt';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
            status.textContent = 'Recovery codes downloaded.';
        });
    })();
</script>
@endpush
