@extends('layouts.admin')

@section('title', 'eDonate - Donor Verification')
@section('admin_page_class', 'admin-donor-verifications-page')
@section('header_title', 'Donor Verification')
@section('header_subtitle', 'Review identity documents and verify legitimate donor accounts')

@section('main_content')
@php
    $statusBadge = static function (?string $status): string {
        return match ($status) {
            'pending' => 'bg-warning text-dark',
            'verified' => 'bg-success text-white',
            'rejected' => 'bg-danger text-white',
            default => 'bg-secondary text-white',
        };
    };

    $statusLabel = static fn (?string $status): string => \Illuminate\Support\Str::headline((string) ($status ?: 'unverified'));
@endphp

<main class="main container-fluid px-0">
    <section class="content container-fluid py-3" aria-label="Donor verification content">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please correct the following:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (($focusedVerificationId ?? 0) > 0)
            <div class="alert alert-info" role="status">
                Opened verification record #{{ $focusedVerificationId }} from the dashboard.
                <a class="alert-link ms-1" href="{{ route('admin.donor-verifications.index') }}">Return to all verification records</a>.
            </div>
        @endif

        <div class="row g-3" aria-label="Verification summary">
            <div class="col-6 col-xl-3">
                <article class="stat-card h-100">
                    <p class="mb-1 text-muted small">Total Requests</p>
                    <p class="mb-0 fs-4 fw-bold">{{ number_format($stats['total'] ?? 0) }}</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card h-100 border-warning">
                    <p class="mb-1 text-muted small">Pending</p>
                    <p class="mb-0 fs-4 fw-bold">{{ number_format($stats['pending'] ?? 0) }}</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card h-100 border-success">
                    <p class="mb-1 text-muted small">Verified</p>
                    <p class="mb-0 fs-4 fw-bold">{{ number_format($stats['verified'] ?? 0) }}</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card h-100 border-danger">
                    <p class="mb-1 text-muted small">Rejected</p>
                    <p class="mb-0 fs-4 fw-bold">{{ number_format($stats['rejected'] ?? 0) }}</p>
                </article>
            </div>
        </div>

        <form class="row g-3 align-items-center mt-3" method="GET" action="{{ route('admin.donor-verifications.index') }}" role="search">
            <div class="col-12 col-lg">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Search donor name, email, or contact..." aria-label="Search donor verification requests">
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <select name="status" class="form-select" aria-label="Filter by verification status">
                    <option value="">All Statuses</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="verified" @selected(($filters['status'] ?? '') === 'verified')>Verified</option>
                    <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
            </div>
            <div class="col-12 col-md-auto">
                <a href="{{ route('admin.donor-verifications.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive mt-4">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Donor Name</th>
                        <th>Contact / Email</th>
                        <th>Document Type</th>
                        <th>Files Received</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th>Reviewed By</th>
                        <th>Reviewed At</th>
                        <th class="text-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($verifications as $verification)
                        @php
                            $historyItems = $histories[(int) $verification->donor_id] ?? collect();
                            $documentLabel = $documentTypes[$verification->document_type] ?? \Illuminate\Support\Str::headline($verification->document_type);
                            $hasDonorRecord = is_numeric($verification->linked_donor_id ?? null) && (int) $verification->linked_donor_id > 0;
                            $hasRecordedDonorId = is_numeric($verification->donor_id ?? null) && (int) $verification->donor_id > 0;
                        @endphp
                        <tr id="verification-row-{{ $verification->verification_id }}" tabindex="-1" @class(['dashboard-record-highlight' => (int) ($focusedVerificationId ?? 0) === (int) $verification->verification_id])>
                            <td>
                                <div class="fw-semibold">{{ $hasDonorRecord ? (trim($verification->donor_name) ?: 'Unknown Donor') : 'Unlinked submission' }}</div>
                                @if ($hasDonorRecord)
                                    <small class="text-muted">D{{ str_pad((string) $verification->linked_donor_id, 3, '0', STR_PAD_LEFT) }}</small>
                                @elseif ($hasRecordedDonorId)
                                    <small class="d-block text-danger">Recorded donor ID D{{ str_pad((string) $verification->donor_id, 3, '0', STR_PAD_LEFT) }} has no matching donor account</small>
                                @else
                                    <small class="d-block text-danger">Donor ID unavailable</small>
                                @endif
                                <small class="text-muted d-block">Verification #{{ $verification->verification_id }}</small>
                            </td>
                            <td>
                                <div>{{ $verification->contact_number ?: '-' }}</div>
                                <small class="text-muted">{{ $verification->donor_email ?: '-' }}</small>
                            </td>
                            <td>{{ $documentLabel }}</td>
                            <td class="text-nowrap">
                                @php
                                    $frontFileExists = (bool) ($verification->front_document_available ?? false);
                                    $backFileExists = (bool) ($verification->back_document_available ?? false);
                                    $frontFileLabel = ! filled($verification->document_path) ? 'Not recorded' : ($frontFileExists ? 'Available' : 'File missing');
                                    $backFileLabel = ! filled($verification->document_back_path) ? 'Not recorded' : ($backFileExists ? 'Available' : 'File missing');
                                @endphp
                                <span class="badge {{ $frontFileExists ? 'bg-success' : (filled($verification->document_path) ? 'bg-danger' : 'bg-secondary') }}">Front: {{ $frontFileLabel }}</span>
                                <span class="badge {{ $backFileExists ? 'bg-success' : (filled($verification->document_back_path) ? 'bg-danger' : 'bg-secondary') }}">Back: {{ $backFileLabel }}</span>
                            </td>
                            <td><span class="badge {{ $statusBadge($verification->status) }}">{{ $statusLabel($verification->status) }}</span></td>
                            <td class="text-nowrap">{{ $verification->created_at ? \Carbon\Carbon::parse($verification->created_at)->format('M j, Y g:i A') : '-' }}</td>
                            <td>{{ $verification->reviewed_by_name ?: '-' }}</td>
                            <td class="text-nowrap">{{ $verification->reviewed_at ? \Carbon\Carbon::parse($verification->reviewed_at)->format('M j, Y g:i A') : '-' }}</td>
                            <td>
                                <div class="table-actions d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#documentModal{{ $verification->verification_id }}">View Document</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#historyModal{{ $verification->verification_id }}">History</button>

                                    @if ($verification->status === 'pending' && $hasDonorRecord)
                                        <form method="POST" action="{{ route('admin.donor-verifications.approve', $verification->verification_id) }}" class="approve-form">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $verification->verification_id }}">Reject</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">{{ ($focusedVerificationId ?? 0) > 0 ? 'The selected verification record could not be found.' : 'No donor verification requests found.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin-pagination :paginator="$verifications" class="mt-3" />
    </section>
</main>

@foreach ($verifications as $verification)
    @php
        $historyItems = $histories[(int) $verification->donor_id] ?? collect();
    @endphp

    <div class="modal fade" id="documentModal{{ $verification->verification_id }}" tabindex="-1" aria-labelledby="documentModalLabel{{ $verification->verification_id }}" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentModalLabel{{ $verification->verification_id }}">Verification Document - {{ trim($verification->donor_name) ?: 'Unknown Donor' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="background: #f8f9fa;">
                    <div class="row g-3">
                        @foreach ([
                            ['side' => 'front', 'label' => 'Front of ID', 'path' => $verification->document_path],
                            ['side' => 'back', 'label' => 'Back of ID', 'path' => $verification->document_back_path],
                        ] as $document)
                            @php
                                $documentExtension = strtolower(pathinfo((string) $document['path'], PATHINFO_EXTENSION));
                                $documentUrl = route('admin.donor-verifications.document', [
                                    'verification' => $verification->verification_id,
                                    'side' => $document['side'],
                                    'rendition' => 'preview',
                                ]);
                                $originalUrl = route('admin.donor-verifications.document', [
                                    'verification' => $verification->verification_id,
                                    'side' => $document['side'],
                                    'rendition' => 'original',
                                ]);
                            @endphp
                            <div class="col-12 col-lg-6">
                                <section class="h-100 rounded border bg-white p-3" aria-label="{{ $document['label'] }}">
                                    <h6 class="mb-3 fw-semibold">{{ $document['label'] }}</h6>
                                    @if (filled($document['path']))
                                        @if (in_array($documentExtension, ['jpg', 'jpeg', 'png'], true))
                                            <img src="{{ $documentUrl }}" alt="{{ $document['label'] }} optimized preview" loading="lazy" class="img-fluid d-block mx-auto rounded verification-document-preview" style="max-height: 65vh; object-fit: contain;">
                                            <div class="alert alert-warning mt-3 d-none verification-document-fallback" role="status">
                                                The optimized preview could not be loaded. Try the original file or retry the preview.
                                                <a href="{{ $documentUrl }}" class="alert-link verification-document-retry">Retry preview</a>.
                                            </div>
                                            <p class="small text-muted mt-2 mb-0">Use the original file to inspect fine details.</p>
                                        @else
                                            <iframe src="{{ $documentUrl }}" title="{{ $document['label'] }} submitted by donor" loading="lazy" class="w-100 border rounded" style="height: 65vh;"></iframe>
                                        @endif
                                        <a href="{{ $originalUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-3">Open original {{ strtolower($document['side']) }}</a>
                                    @else
                                        <div class="alert alert-secondary mb-0" role="status">
                                            No {{ strtolower($document['side']) }} document path is recorded for this submission. Confirm with the donor before treating it as not submitted.
                                        </div>
                                    @endif
                                </section>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal{{ $verification->verification_id }}" tabindex="-1" aria-labelledby="rejectModalLabel{{ $verification->verification_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('admin.donor-verifications.reject', $verification->verification_id) }}" class="modal-content">
                @csrf
                @method('PATCH')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectModalLabel{{ $verification->verification_id }}">Reject Verification</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Provide a clear reason so the donor can re-upload the correct document.</p>
                    <label for="rejectionReason{{ $verification->verification_id }}" class="form-label fw-semibold">Rejection Reason</label>
                    <textarea id="rejectionReason{{ $verification->verification_id }}" name="rejection_reason" class="form-control" rows="4" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="historyModal{{ $verification->verification_id }}" tabindex="-1" aria-labelledby="historyModalLabel{{ $verification->verification_id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel{{ $verification->verification_id }}">Verification History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-3">{{ trim($verification->donor_name) ?: 'Unknown Donor' }}</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Reviewed By</th>
                                    <th>Reviewed At</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($historyItems as $history)
                                    <tr>
                                        <td>{{ $documentTypes[$history->document_type] ?? \Illuminate\Support\Str::headline($history->document_type) }}</td>
                                        <td><span class="badge {{ $statusBadge($history->status) }}">{{ $statusLabel($history->status) }}</span></td>
                                        <td>{{ $history->created_at ? \Carbon\Carbon::parse($history->created_at)->format('M j, Y') : '-' }}</td>
                                        <td>{{ $history->reviewed_by_name ?: '-' }}</td>
                                        <td>{{ $history->reviewed_at ? \Carbon\Carbon::parse($history->reviewed_at)->format('M j, Y') : '-' }}</td>
                                        <td>{{ $history->rejection_reason ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">No history found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

@push('admin_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const focusedRow = document.getElementById('verification-row-{{ (int) ($focusedVerificationId ?? 0) }}');
        if (focusedRow) {
            focusedRow.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            focusedRow.focus({ preventScroll: true });
            window.setTimeout(function() { focusedRow.classList.remove('dashboard-record-highlight'); }, 2800);
        }

        const approveForms = document.querySelectorAll('.approve-form');
        approveForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Approve Donor Verification?',
                    text: 'Are you sure you want to approve this donor?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Approve',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        document.querySelectorAll('.verification-document-preview').forEach(function(image) {
            image.addEventListener('error', function() {
                image.classList.add('d-none');
                const fallback = image.parentElement.querySelector('.verification-document-fallback');
                if (fallback) fallback.classList.remove('d-none');
            });
        });
    });
</script>
@endpush

@endsection
