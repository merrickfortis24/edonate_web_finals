@extends('layouts.admin')

@section('title', 'eDonate - Appointment Restrictions')
@section('admin_page_class', 'admin-appointment-restrictions-page')
@section('header_title', 'Appointment Restrictions')
@section('header_subtitle', 'Review donor cancellation histories and restore appointment access when appropriate.')

@section('main_content')
<main class="main container-fluid px-0">
    <section class="content container-fluid py-3" aria-label="Appointment restriction management">
        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Please review the following:</strong>
                <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if (($appealStatus ?? '') === 'pending')
            <div class="alert alert-warning d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2" role="status">
                <span>Showing restricted donors with a pending appeal.</span>
                <a class="alert-link" href="{{ route('admin.appointment-restrictions.index') }}">View all restricted donors</a>
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <a href="{{ route('admin.appointment-restrictions.index') }}" class="card border-danger text-decoration-none h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div><div class="text-muted small">Restricted Donors</div><div class="fs-3 fw-bold text-danger">{{ number_format($stats['restricted_donors'] ?? 0) }}</div></div>
                        <i class="bi bi-person-lock fs-2 text-danger" aria-hidden="true"></i>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-6">
                <div class="card border-warning h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div><div class="text-muted small">Pending Appeals</div><div class="fs-3 fw-bold text-warning-emphasis">{{ number_format($stats['pending_appeals'] ?? 0) }}</div></div>
                        <i class="bi bi-hourglass-split fs-2 text-warning" aria-hidden="true"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <h2 class="card-title mb-0">Currently Restricted Donors</h2>
                <form class="d-flex gap-2" method="GET" action="{{ route('admin.appointment-restrictions.index') }}" role="search">
                    @if (($appealStatus ?? '') !== '')<input type="hidden" name="appeal_status" value="{{ $appealStatus }}">@endif
                    <label class="visually-hidden" for="restrictionSearch">Search donors</label>
                    <input id="restrictionSearch" class="form-control" type="search" name="search" value="{{ $search }}" placeholder="Name, email, phone, or ID">
                    <button class="btn btn-primary" type="submit">Search</button>
                    @if ($search !== '')<a class="btn btn-outline-secondary" href="{{ route('admin.appointment-restrictions.index', ($appealStatus ?? '') !== '' ? ['appeal_status' => $appealStatus] : []) }}">Clear</a>@endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Donor ID</th>
                                <th scope="col">Donor Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Cancellation Count</th>
                                <th scope="col">Latest Cancellation</th>
                                <th scope="col">Restriction Date</th>
                                <th scope="col">Appeal</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($donors as $record)
                                <tr>
                                    <td>DN-{{ str_pad((string) $record->donor_id, 6, '0', STR_PAD_LEFT) }}</td>
                                    <td class="fw-semibold">{{ trim(($record->first_name ?? '').' '.($record->last_name ?? '')) ?: 'Donor #'.$record->donor_id }}</td>
                                    <td>{{ $record->email ?: '—' }}</td>
                                    <td>{{ $record->contact_number ?: '—' }}</td>
                                    <td><span class="badge text-bg-danger">{{ (int) $record->consecutive_cancellations }}</span></td>
                                    <td>{{ $record->latest_cancellation_at ? \Carbon\Carbon::parse($record->latest_cancellation_at)->format('M j, Y g:i A') : '—' }}</td>
                                    <td>{{ $record->restricted_at ? \Carbon\Carbon::parse($record->restricted_at)->format('M j, Y g:i A') : '—' }}</td>
                                    <td>@if ($record->has_pending_appeal)<span class="badge text-bg-warning">Pending Appeal</span>@else<span class="text-muted">No pending appeal</span>@endif</td>
                                    <td><span class="badge text-bg-danger">Restricted</span></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.appointment-restrictions.show', $record->donor_id) }}"><i class="bi bi-eye me-1" aria-hidden="true"></i>View Details</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="py-5 text-center text-muted">{{ ($appealStatus ?? '') === 'pending' ? 'No restricted donors have a pending appeal.' : 'No donors currently have appointment restrictions.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($donors->hasPages())<div class="card-footer">{{ $donors->links() }}</div>@endif
        </div>
    </section>
</main>
@endsection
