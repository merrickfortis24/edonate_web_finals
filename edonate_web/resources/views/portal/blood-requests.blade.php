@extends('layouts.donor-portal')

@section('title', 'Blood Requests')
@section('page_heading', 'Blood Requests')
@section('page_subheading', 'Donation requests sent to your donor account.')

@section('content')
<x-dashboard.card title="Donation Requests" subtitle="Only requests sent to you are shown here.">
    <div class="row g-3">
        @forelse($invitations as $invitation)
            @php($request = $invitation->request)
            <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100">
                    <div class="d-flex justify-content-between gap-2">
                        <strong>{{ $request?->request_reference ?: 'Blood Request' }}</strong>
                        <span class="badge bg-{{ $request?->urgency === 'emergency' ? 'danger' : ($request?->urgency === 'urgent' ? 'warning text-dark' : 'secondary') }}">{{ Str::headline($request?->urgency ?? 'normal') }}</span>
                    </div>
                    <p class="mb-1 mt-2">{{ $request?->facility?->facility_name }}</p>
                    <p class="text-muted small mb-2">{{ collect([$request?->facility?->barangay_name, $request?->facility?->city])->filter()->join(', ') }}</p>
                    <p class="mb-2">Blood type needed: <strong>{{ $request?->bloodType?->blood_type ?? 'Any' }}</strong></p>
                    <p class="mb-3">Your response: <strong>{{ Str::headline($invitation->status) }}</strong></p>
                    <a class="btn btn-sm btn-danger" href="{{ route('donor.blood-requests.show', $request) }}">View Request</a>
                </div>
            </div>
        @empty
            <div class="col-12">
                <p class="text-muted mb-0">No blood requests have been sent to your account.</p>
            </div>
        @endforelse
    </div>
</x-dashboard.card>
@endsection
