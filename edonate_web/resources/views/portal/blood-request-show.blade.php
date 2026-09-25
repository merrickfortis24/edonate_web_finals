@extends('layouts.donor-portal')

@section('title', 'Blood Request Details')
@section('page_heading', $bloodRequest->request_reference ?: 'Blood Request')
@section('page_subheading', 'Review the request and respond if you are available.')

@section('content')
<x-dashboard.card title="Request Details" subtitle="No patient private information is shown.">
    <div class="row g-3">
        <div class="col-md-6"><strong>Facility</strong><p>{{ $bloodRequest->facility?->facility_name }}</p></div>
        <div class="col-md-6"><strong>Location</strong><p>{{ collect([$bloodRequest->facility?->barangay_name, $bloodRequest->facility?->city, $bloodRequest->facility?->province])->filter()->join(', ') }}</p></div>
        <div class="col-md-4"><strong>Blood Type Needed</strong><p>{{ $bloodRequest->bloodType?->blood_type ?? 'Any' }}</p></div>
        <div class="col-md-4"><strong>Urgency</strong><p>{{ Str::headline($bloodRequest->urgency) }}</p></div>
        <div class="col-md-4"><strong>Status</strong><p>{{ Str::headline($bloodRequest->status) }}</p></div>
        @if($bloodRequest->notes)
            <div class="col-12"><strong>Notes</strong><p>{{ $bloodRequest->notes }}</p></div>
        @endif
    </div>

    <div class="d-flex gap-2 mt-3 flex-wrap">
        @if(in_array($bloodRequest->status, ['open', 'in_progress'], true))
            <form method="POST" action="{{ route('donor.blood-requests.interested', $bloodRequest) }}">
                @csrf
                    <x-privacy-acknowledgment purpose="blood-request" />
                <button class="btn btn-danger" type="submit">I'm Interested</button>
            </form>
            <form method="POST" action="{{ route('donor.blood-requests.decline', $bloodRequest) }}">
                @csrf
                <button class="btn btn-outline-secondary" type="submit">Decline</button>
            </form>
        @else
            <span class="badge bg-secondary">This request is no longer active.</span>
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('donor.blood-requests.index') }}">Back</a>
    </div>
</x-dashboard.card>
@endsection
