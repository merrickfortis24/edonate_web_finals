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
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ trim($verification->donor_name) ?: 'Unknown Donor' }}</div>
                                <small class="text-muted">D{{ str_pad((string) $verification->donor_id, 3, '0', STR_PAD_LEFT) }}</small>
                            </td>
                            <td>
                                <div>{{ $verification->contact_number ?: '-' }}</div>
                                <small class="text-muted">{{ $verification->donor_email ?: '-' }}</small>
                            </td>
                            <td>{{ $documentLabel }}</td>
                            <td><span class="badge {{ $statusBadge($verification->status) }}">{{ $statusLabel($verification->status) }}</span></td>
                            <td class="text-nowrap">{{ $verification->created_at ? \Carbon\Carbon::parse($verification->created_at)->format('M j, Y g:i A') : '-' }}</td>
                            <td>{{ $verification->reviewed_by_name ?: '-' }}</td>
                            <td class="text-nowrap">{{ $verification->reviewed_at ? \Carbon\Carbon::parse($verification->reviewed_at)->format('M j, Y g:i A') : '-' }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('admin.donor-verifications.document', $verification->verification_id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View Document</a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#historyModal{{ $verification->verification_id }}">History</button>

                                    @if ($verification->status === 'pending')
                                        <form method="POST" action="{{ route('admin.donor-verifications.approve', $verification->verification_id) }}" onsubmit="return confirm('Approve this donor verification?');">
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
                            <td colspan="8" class="text-center text-muted py-4">No donor verification requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $verifications->links() }}
        </div>
    </section>
</main>

@foreach ($verifications as $verification)
    @php
        $historyItems = $histories[(int) $verification->donor_id] ?? collect();
    @endphp

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
@endsection
