@extends('layouts.admin')

@section('title', 'eDonate - Event Bookings')
@section('admin_page_class', 'admin-donation-event-show-page')
@section('header_title', 'Event Bookings')
@section('header_subtitle', $event->title)

@section('header_actions')
    <a class="btn btn-outline-secondary fw-semibold" href="{{ route('admin.donation-events.index') }}">Back to Events</a>
@endsection

@section('main_content')
@php
    $badgeClass = [
        'open' => 'bg-success',
        'closed' => 'bg-primary',
        'cancelled' => 'bg-danger',
        'completed' => 'bg-secondary',
    ][$eventPayload['status'] ?? 'closed'] ?? 'bg-secondary';
@endphp

<main class="container-fluid py-3">
    <section class="row g-3 mb-3">
        <div class="col-12 col-xl-3">
            <article class="stat-card h-100 p-3 bg-body border rounded">
                <p class="text-muted text-uppercase fw-bold small mb-1">Status</p>
                <span class="badge {{ $badgeClass }}">{{ \Illuminate\Support\Str::headline($eventPayload['status'] ?? 'closed') }}</span>
            </article>
        </div>
        <div class="col-12 col-xl-3">
            <article class="stat-card h-100 p-3 bg-body border rounded">
                <p class="text-muted text-uppercase fw-bold small mb-1">Confirmed Bookings</p>
                <p class="fs-4 fw-bold mb-0">{{ number_format($eventPayload['confirmed_count'] ?? 0) }}</p>
            </article>
        </div>
        <div class="col-12 col-xl-3">
            <article class="stat-card h-100 p-3 bg-body border rounded">
                <p class="text-muted text-uppercase fw-bold small mb-1">Remaining Slots</p>
                <p class="fs-4 fw-bold mb-0">{{ number_format($eventPayload['remaining_slots'] ?? 0) }}</p>
            </article>
        </div>
        <div class="col-12 col-xl-3">
            <article class="stat-card h-100 p-3 bg-body border rounded">
                <p class="text-muted text-uppercase fw-bold small mb-1">Capacity</p>
                <p class="fs-4 fw-bold mb-0">{{ number_format($eventPayload['max_capacity'] ?? 0) }}</p>
            </article>
        </div>
    </section>

    <section class="bg-body border rounded p-3 mb-3">
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <p class="text-muted small fw-bold text-uppercase mb-1">Date and Time</p>
                <p class="mb-0">
                    {{ $event->event_date ? \Carbon\Carbon::parse($event->event_date)->format('F j, Y') : '-' }}
                    {{ $event->start_time ? \Carbon\Carbon::parse($event->start_time)->format('g:i A') : '' }}
                    @if ($event->end_time)
                        - {{ \Carbon\Carbon::parse($event->end_time)->format('g:i A') }}
                    @endif
                </p>
            </div>
            <div class="col-12 col-lg-4">
                <p class="text-muted small fw-bold text-uppercase mb-1">Location</p>
                <p class="mb-0">{{ $event->location_name }}</p>
                @if ($event->address)
                    <p class="text-muted small mb-0">{{ $event->address }}</p>
                @endif
            </div>
            <div class="col-12 col-lg-4">
                <p class="text-muted small fw-bold text-uppercase mb-1">Created By</p>
                <p class="mb-0">{{ $event->creator?->full_name ?: $event->creator?->username ?: '-' }}</p>
            </div>
        </div>
    </section>

    <section class="bg-body border rounded p-3">
        <form method="GET" class="row g-3 align-items-center mb-3">
            <div class="col-12 col-md-4 col-xl-3">
                <select name="status" class="form-select">
                    <option value="">All appointment statuses</option>
                    @foreach (['confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-danger" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.donation-events.show', $event->event_id) }}">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Donor</th>
                        <th>Appointment Ref</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Eligibility</th>
                        <th>Booked</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($appointments as $appointment)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ trim(($appointment->first_name ?? '') . ' ' . ($appointment->last_name ?? '')) ?: 'Unknown Donor' }}</div>
                            <small class="text-muted">{{ $appointment->email ?: '-' }}</small>
                        </td>
                        <td>AP{{ str_pad((string) $appointment->appointment_id, 3, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $appointment->appointment_date ? \Carbon\Carbon::parse($appointment->appointment_date)->format('M j, Y') : '-' }}</td>
                        <td>{{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') : '-' }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($appointment->normalized_status ?? $appointment->status ?? '-') }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($appointment->verification_status ?? 'unverified') }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($appointment->latest_eligibility_status ?? 'none') }}</td>
                        <td>{{ $appointment->created_at ? \Carbon\Carbon::parse($appointment->created_at)->format('M j, Y g:i A') : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No booked donors found for this event.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-pagination mt-3">
            {{ $appointments->links('pagination::bootstrap-5') }}
        </div>
    </section>
</main>
@endsection
