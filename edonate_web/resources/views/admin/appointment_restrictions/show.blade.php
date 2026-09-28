@extends('layouts.admin')

@section('title', 'eDonate - Restriction Review')
@section('admin_page_class', 'admin-appointment-restriction-detail-page')
@section('header_title', 'Appointment Restriction Review')
@section('header_subtitle', 'Review donor-submitted reasons and historical appointment cancellations.')
@section('header_actions')
    <a class="btn btn-outline-secondary" href="{{ route('admin.appointment-restrictions.index') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Restrictions</a>
@endsection

@section('main_content')
<main class="main container-fluid px-0">
    <section class="content container-fluid py-3" aria-label="Donor restriction details">
        @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><strong>Please review the following:</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <h2 class="card-title mb-0">Donor Information</h2>
                <span class="badge {{ $donor->appointment_restricted ? 'text-bg-danger' : 'text-bg-success' }}">{{ $donor->appointment_restricted ? 'Restricted' : 'Access Restored' }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-xl-3"><div class="small text-muted">Donor ID</div><div class="fw-semibold">DN-{{ str_pad((string) $donor->donor_id, 6, '0', STR_PAD_LEFT) }}</div></div>
                    <div class="col-12 col-md-6 col-xl-3"><div class="small text-muted">Name</div><div class="fw-semibold">{{ trim(($donor->first_name ?? '').' '.($donor->last_name ?? '')) ?: 'Donor #'.$donor->donor_id }}</div></div>
                    <div class="col-12 col-md-6 col-xl-3"><div class="small text-muted">Email</div><div class="fw-semibold text-break">{{ $donor->email ?: '—' }}</div></div>
                    <div class="col-12 col-md-6 col-xl-3"><div class="small text-muted">Phone</div><div class="fw-semibold">{{ $donor->contact_number ?: '—' }}</div></div>
                    <div class="col-12 col-md-6 col-xl-3"><div class="small text-muted">Consecutive Cancellations</div><div class="fw-semibold">{{ (int) $donor->consecutive_cancellations }}</div></div>
                    <div class="col-12 col-md-6 col-xl-3"><div class="small text-muted">Restriction Date</div><div class="fw-semibold">{{ $activeRestriction?->restricted_at?->format('M j, Y g:i A') ?? $donor->restricted_at?->format('M j, Y g:i A') ?? '—' }}</div></div>
                    <div class="col-12"><div class="small text-muted">Restriction Reason</div><div>{{ $activeRestriction?->restriction_reason ?? $donor->restriction_reason ?? '—' }}</div></div>
                </div>
                @if ($activeRestriction)
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#liftRestrictionModal"><i class="bi bi-unlock me-1" aria-hidden="true"></i>Lift Restriction</button>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title mb-0">Appointment Cancellation History</h2></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Cancelled At</th><th>Appointment Date</th><th>Event / Facility</th><th>Reason</th><th>Consecutive Count</th><th>Cancelled By</th></tr></thead>
                        <tbody>
                            @forelse ($cancellations as $cancellation)
                                <tr>
                                    <td>{{ $cancellation->cancelled_at?->format('M j, Y g:i A') ?? '—' }}</td>
                                    <td>{{ $cancellation->appointment?->appointment_date?->format('M j, Y') ?? '—' }}</td>
                                    <td>{{ $cancellation->appointment?->event?->title ?? 'Legacy appointment' }}@if($cancellation->appointment?->event?->location_name) — {{ $cancellation->appointment->event->location_name }}@elseif($cancellation->appointment?->donation_center) — {{ $cancellation->appointment->donation_center }}@endif</td>
                                    <td class="text-break" style="min-width: 16rem">{{ $cancellation->reason }}</td>
                                    <td><span class="badge text-bg-warning">{{ (int) $cancellation->consecutive_count }}</span></td>
                                    <td>{{ \Illuminate\Support\Str::headline($cancellation->cancelled_by) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-4 text-center text-muted">No donor-initiated cancellation records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($cancellations->hasPages())<div class="card-footer">{{ $cancellations->links() }}</div>@endif
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title mb-0">Donor Justifications / Appeals</h2></div>
            <div class="card-body">
                @php($appeals = $restrictions->flatMap(fn ($restriction) => $restriction->appeals)->sortByDesc('submitted_at'))
                @forelse ($appeals as $appeal)
                    <article class="border rounded p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <div><span class="fw-semibold">Submitted:</span> {{ $appeal->submitted_at?->format('M j, Y g:i A') ?? '—' }}</div>
                            <span class="badge {{ $appeal->status === 'pending' ? 'text-bg-warning' : ($appeal->status === 'approved' ? 'text-bg-success' : 'text-bg-danger') }}">{{ \Illuminate\Support\Str::headline($appeal->status) }}</span>
                        </div>
                        <p class="mb-3 text-break">{{ $appeal->justification }}</p>
                        @if ($appeal->reviewed_at)
                            <p class="small text-muted mb-2">Reviewed {{ $appeal->reviewed_at->format('M j, Y g:i A') }}{{ $appeal->reviews->first()?->admin?->full_name ? ' by '.$appeal->reviews->first()->admin->full_name : '' }}</p>
                        @endif
                        @if ($appeal->admin_notes)<p class="mb-3"><span class="fw-semibold">Admin notes:</span> {{ $appeal->admin_notes }}</p>@endif
                        @if ($appeal->status === 'pending' && $activeRestriction && (int) $appeal->restriction_id === (int) $activeRestriction->restriction_id)
                            <div class="row g-3">
                                <div class="col-12 col-xl-6">
                                    <form method="POST" action="{{ route('admin.appointment-restrictions.appeals.approve', $appeal->appeal_id) }}" class="border rounded p-3">
                                        @csrf
                                        <label class="form-label" for="approveNotes{{ $appeal->appeal_id }}">Admin notes (optional)</label>
                                        <textarea class="form-control mb-2" id="approveNotes{{ $appeal->appeal_id }}" name="admin_notes" rows="2" maxlength="1000"></textarea>
                                        <button class="btn btn-success" type="submit" onclick="return confirm('Approve this appeal and restore appointment privileges?')">Approve &amp; Lift Restriction</button>
                                    </form>
                                </div>
                                <div class="col-12 col-xl-6">
                                    <form method="POST" action="{{ route('admin.appointment-restrictions.appeals.reject', $appeal->appeal_id) }}" class="border rounded p-3">
                                        @csrf
                                        <label class="form-label" for="rejectNotes{{ $appeal->appeal_id }}">Reason / admin notes <span class="text-danger">*</span></label>
                                        <textarea class="form-control mb-2" id="rejectNotes{{ $appeal->appeal_id }}" name="admin_notes" rows="2" minlength="8" maxlength="1000" required></textarea>
                                        <button class="btn btn-outline-danger" type="submit" onclick="return confirm('Reject this appeal? The donor will remain restricted.')">Reject Appeal / Keep Restricted</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="mb-0 text-muted">No justification has been submitted for this donor.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title mb-0">Admin Review History</h2></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Reviewed At</th><th>Admin</th><th>Action</th><th>Notes</th></tr></thead>
                        <tbody>
                            @php($reviews = $restrictions->flatMap(fn ($restriction) => $restriction->reviews)->sortByDesc('reviewed_at'))
                            @forelse ($reviews as $review)
                                <tr><td>{{ $review->reviewed_at?->format('M j, Y g:i A') ?? '—' }}</td><td>{{ $review->admin?->full_name ?? 'Administrator' }}</td><td><span class="badge {{ str_contains($review->action, 'lift') || str_contains($review->action, 'approved') ? 'text-bg-success' : 'text-bg-secondary' }}">{{ \Illuminate\Support\Str::headline($review->action) }}</span></td><td>{{ $review->notes ?: '—' }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-4 text-center text-muted">No administrator reviews recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($activeRestriction)
            <div class="card border-warning mt-3">
                <div class="card-header"><h2 class="card-title mb-0">Keep Appointment Access Restricted</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.appointment-restrictions.keep', $donor->donor_id) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-12"><label for="keepNotes" class="form-label">Review notes / reason <span class="text-danger">*</span></label><textarea id="keepNotes" name="admin_notes" class="form-control" rows="2" minlength="8" maxlength="1000" required></textarea></div>
                        <div class="col-12"><button type="submit" class="btn btn-outline-warning" onclick="return confirm('Keep appointment privileges restricted and record this review?')">Keep Restricted</button></div>
                    </form>
                </div>
            </div>
        @endif
    </section>
</main>

@if ($activeRestriction)
    <div class="modal fade" id="liftRestrictionModal" tabindex="-1" aria-labelledby="liftRestrictionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.appointment-restrictions.lift', $donor->donor_id) }}">
                @csrf
                @method('PATCH')
                <div class="modal-header"><h2 class="modal-title fs-5" id="liftRestrictionModalLabel">Lift Appointment Restriction?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <p>You are about to restore appointment privileges for <strong>{{ trim(($donor->first_name ?? '').' '.($donor->last_name ?? '')) ?: 'Donor #'.$donor->donor_id }}</strong>.</p>
                    <label for="liftNotes" class="form-label">Admin notes / reason <span class="text-danger">*</span></label>
                    <textarea id="liftNotes" name="admin_notes" class="form-control" rows="3" minlength="8" maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Confirm &amp; Restore</button></div>
            </form>
        </div>
    </div>
@endif
@endsection
