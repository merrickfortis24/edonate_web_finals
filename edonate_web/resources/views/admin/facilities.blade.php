@extends('layouts.admin')

@section('title', 'eDonate - Facility Management')
@section('admin_page_class', 'admin-facilities-page')
@section('header_title', 'Facility Management')
@section('header_subtitle', 'Manage hospitals, clinics, blood banks, and health centers')

@section('header_actions')
@if($canManage)<button class="btn btn-danger" type="button" id="createFacilityButton">Create Facility</button>@endif
@endsection

@push('admin_head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.min.css') }}">
@endpush

@section('main_content')
@php
    $facilityConfig = [
        'data' => route('admin.facilities.data'),
        'store' => route('admin.facilities.store'),
        'canManage' => $canManage,
        'tiles' => 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png?key='.urlencode((string) config('services.carto.basemap_key')),
    ];
@endphp
<main class="facility-content" id="facilityManagement" data-config='@json($facilityConfig)'>
    <section class="facility-summary" aria-label="Facility summary">
        <div class="facility-stat"><span>Total Facilities</span><strong id="facilityTotal">-</strong></div>
        <div class="facility-stat"><span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Active</span><strong id="facilityActive">-</strong></div>
        <div class="facility-stat"><span><i class="bi bi-slash-circle" aria-hidden="true"></i> Inactive</span><strong id="facilityInactive">-</strong></div>
        <div class="facility-stat"><span>Mapped</span><strong id="facilityMapped">-</strong></div>
    </section>
    <section class="card facility-panel">
        <div class="alert alert-success d-none" id="facilityNotice" role="status"></div>
        <div class="alert alert-danger d-none" id="facilityListError" role="alert"></div>
        <div class="facility-toolbar">
            <div><label for="facilitySearch">Search</label><input class="form-control" id="facilitySearch" type="search" maxlength="150" placeholder="Facility or location"></div>
            <div><label for="facilityTypeFilter">Facility type</label><select class="form-select" id="facilityTypeFilter"><option value="">All types</option>@foreach($facilityTypes as $type)<option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>@endforeach</select></div>
            <div><label for="facilityStatusFilter">Status</label><select class="form-select" id="facilityStatusFilter"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <button class="btn btn-outline-secondary" id="facilityClear" type="button">Clear</button>
        </div>
        <div class="table-responsive facility-table-wrap" role="region" aria-label="Facilities table, scroll horizontally if needed" tabindex="0">
            <table class="table table-hover facility-table"><thead><tr><th>Facility</th><th>Type</th><th>Location</th><th>Status</th><th>Available Units</th><th>Low / Out Types</th><th>Map</th><th>Actions</th></tr></thead><tbody id="facilityRows"><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody></table>
        </div>
        <div class="admin-pagination admin-pagination--js" aria-label="Table pagination">
            <span class="admin-pagination__info" id="facilityMeta">Showing 0 to 0 of 0 entries</span>
            <nav class="admin-pagination__links" id="facilityPaginationLinks" aria-label="Pagination links"></nav>
        </div>
    </section>
</main>

@if($canManage)
<div class="modal fade" id="facilityModal" tabindex="-1" aria-labelledby="facilityModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="facilityForm" novalidate>@csrf<div class="modal-header"><h2 class="modal-title fs-5" id="facilityModalTitle">Create Facility</h2><button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button></div><div class="modal-body"><div class="row g-3">
    <div class="col-12"><div class="alert alert-danger d-none mb-0" id="facilityErrors" role="alert" tabindex="-1"></div></div>
    <div class="col-md-8"><label class="form-label" for="facilities-facility_name">Facility name</label><input id="facilities-facility_name" class="form-control" name="facility_name" maxlength="150" required></div>
    <div class="col-md-4"><label class="form-label" for="facilities-facility_type">Type</label><select id="facilities-facility_type" class="form-select" name="facility_type" required>@foreach($facilityTypes as $type)<option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>@endforeach</select></div>
    <div class="col-12"><label class="form-label" for="facilities-address">Address</label><textarea id="facilities-address" class="form-control" name="address" rows="2" maxlength="5000" autocomplete="street-address"></textarea></div>
    <div class="col-md-3"><label class="form-label" for="facilities-barangay_name">Barangay</label><input id="facilities-barangay_name" class="form-control" name="barangay_name" maxlength="100" autocomplete="address-level3"></div>
    <div class="col-md-5"><label class="form-label" for="facilities-city">City / municipality</label><input id="facilities-city" class="form-control" name="city" maxlength="100" autocomplete="address-level2" required></div>
    <div class="col-md-4"><label class="form-label" for="facilities-province">Province</label><input id="facilities-province" class="form-control" name="province" maxlength="100" autocomplete="address-level1" required></div>
    <div class="col-12">
        <label class="form-label" for="facilityPinMap">Facility map position</label>
        <p class="form-text mb-2" id="facilityPinHelp">Click the map to place the pin. Drag the pin to fine-tune its position. The coordinates are saved with this facility.</p>
        <div class="alert alert-info py-2 mb-2" id="facilityMapPrivacyNotice" role="status">External map tiles are off. Allow maps in <button type="button" class="btn btn-link p-0 align-baseline" data-privacy-open>Privacy choices</button> to use the pin picker.</div>
        <div id="facilityPinMap" class="facility-pin-map" role="region" aria-label="Facility location pin map" aria-describedby="facilityPinHelp facilityPinStatus" hidden></div>
        <div id="facilityPinStatus" class="small mt-2" role="status" aria-live="polite">Choose a pin position on the map.</div>
        <input type="hidden" name="location_pin_selected" value="0">
        <input type="hidden" name="latitude">
        <input type="hidden" name="longitude">
    </div>
    <div class="col-12"><p class="form-text mb-0">Enter the address details manually. Correct missing or incomplete address information; it is not filled or inferred from the map.</p></div>
    <div class="col-md-4"><label class="form-label" for="facilities-contact_number">Contact number</label><input id="facilities-contact_number" class="form-control" name="contact_number" maxlength="30"></div>
    <div class="col-md-4"><label class="form-label" for="facilities-status">Status</label><select id="facilities-status" class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select><div class="mt-2" id="facilityCurrentStatus"></div></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="location_confirmed" id="facilities-location_confirmed" value="1" required><label class="form-check-label" for="facilities-location_confirmed">I have reviewed and confirmed the facility address and pin position.</label></div></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button><button class="btn btn-danger" type="submit" id="facilitySave">Save Facility</button></div></form></div></div>
@endif
@endsection

@push('admin_scripts')
@include('admin.partials.facility-status-script')
<script src="{{ asset('vendor/leaflet/leaflet.min.js') }}"></script>
@vite('resources/js/admin-facilities.js')
@endpush
