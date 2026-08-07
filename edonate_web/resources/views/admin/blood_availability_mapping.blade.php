@extends('layouts.admin')

@section('title', 'eDonate - Blood Availability Map')
@section('admin_page_class', 'admin-blood-availability-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('sidebar_open_class', 'is-open')
@section('render_default_hamburger', 'false')
@section('header_title', 'Blood Availability Map')
@section('header_subtitle', 'Aggregated eligible donors by barangay and verified blood type')

@section('header_slot')
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
    </button>
@endsection

@push('admin_head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.min.css') }}">
    <style>
        .availability-content { padding: 24px 32px 40px; }
        .availability-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 18px; }
        .availability-stat, .availability-panel { background: #fff; border: 1px solid rgba(0,0,0,.12); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .availability-stat { padding: 18px; min-height: 112px; }
        .availability-stat__label { color: #666; font-size: 13px; font-weight: 600; }
        .availability-stat__value { color: #b60c0c; font-size: 28px; font-weight: 700; margin-top: 8px; }
        .availability-panel { padding: 18px; margin-bottom: 18px; }
        .availability-panel__title { color: #850000; font-size: 18px; font-weight: 700; margin: 0 0 4px; }
        .availability-panel__hint { color: #666; font-size: 13px; margin: 0 0 16px; }
        #availability-map { height: 500px; min-height: 360px; border-radius: 8px; background: #eef2f5; }
        .availability-table-wrap { overflow-x: auto; }
        .availability-table { min-width: 920px; margin-bottom: 0; }
        .availability-table th { white-space: nowrap; font-size: 12px; }
        .availability-table td { font-size: 13px; vertical-align: middle; }
        .availability-badge { border-radius: 999px; display: inline-block; font-size: 11px; font-weight: 700; padding: 4px 8px; }
        .availability-badge--high { background: #d8f5df; color: #146c2e; }
        .availability-badge--moderate { background: #fff1bf; color: #795900; }
        .availability-badge--low { background: #ffe0c2; color: #874000; }
        .availability-badge--none { background: #ececec; color: #666; }
        .availability-map-marker { align-items: center; background: #b60c0c; border: 3px solid #fff; border-radius: 50%; box-shadow: 0 1px 5px rgba(0,0,0,.35); color: #fff; display: flex; font-size: 12px; font-weight: 700; height: 34px; justify-content: center; width: 34px; }
        .availability-map-marker--moderate { background: #c28b00; }
        .availability-map-marker--low { background: #d56c18; }
        .availability-map-marker--none { background: #777; }
        .availability-filter-row { align-items: end; display: grid; gap: 12px; grid-template-columns: 180px minmax(180px, 1fr) 180px auto; }
        .availability-filter-row label { color: #444; display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; }
        .availability-quality { color: #666; font-size: 12px; margin: 12px 0 0; }
        @media (max-width: 900px) { .availability-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } .availability-filter-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 600px) { .availability-content { padding: 16px; } .availability-summary { grid-template-columns: 1fr; } .availability-filter-row { grid-template-columns: 1fr; } #availability-map { height: 420px; } }
    </style>
@endpush

@section('main_content')
<main class="availability-content">
    <section class="availability-summary" aria-label="Availability summary">
        <div class="availability-stat"><div class="availability-stat__label">Available Verified Donors</div><div class="availability-stat__value" id="summaryAvailable">-</div></div>
        <div class="availability-stat"><div class="availability-stat__label">Barangays With Availability</div><div class="availability-stat__value" id="summaryBarangays">-</div></div>
        <div class="availability-stat"><div class="availability-stat__label">Most Available Type</div><div class="availability-stat__value" id="summaryMost">-</div></div>
        <div class="availability-stat"><div class="availability-stat__label">Lowest Available Type</div><div class="availability-stat__value" id="summaryLowest">-</div></div>
    </section>

    <section class="availability-panel" aria-label="Availability filters">
        <div class="availability-filter-row">
            <div><label for="availabilityBloodType">Blood type</label><select class="form-select" id="availabilityBloodType"><option value="">All Blood Types</option>@foreach ($bloodTypes as $bloodType)<option value="{{ $bloodType }}">{{ $bloodType }}</option>@endforeach</select></div>
            <div><label for="availabilityBarangay">Barangay search</label><input class="form-control" id="availabilityBarangay" type="search" maxlength="150" placeholder="Search barangay"></div>
            <div><label for="availabilityCity">City filter</label><input class="form-control" id="availabilityCity" type="search" maxlength="150" placeholder="Any city"></div>
            <button class="btn btn-outline-secondary" id="clearAvailabilityFilters" type="button">Clear</button>
        </div>
        <p class="availability-quality" id="availabilityQuality">Availability is calculated from current verified and eligible donor data.</p>
    </section>

    <section class="availability-panel" aria-label="Aggregated donor map">
        <h2 class="availability-panel__title">Eligible Verified Donor Availability</h2>
        <p class="availability-panel__hint">One aggregate marker represents one barangay. This is not physical blood-bank inventory.</p>
        <div id="availabilityMap" role="application" aria-label="Aggregated donor availability map"></div>
        <p class="text-muted small mt-2 mb-0" id="mapStatus" aria-live="polite"></p>
    </section>

    <section class="availability-panel" aria-label="Barangay availability table">
        <h2 class="availability-panel__title">Barangay Availability</h2>
        <div class="availability-table-wrap">
            <table class="table table-hover availability-table">
                <thead><tr><th>Barangay</th><th>City</th><th>Available</th><th>Scheduled</th>@foreach ($bloodTypes as $bloodType)<th>{{ $bloodType }}</th>@endforeach<th>Level</th></tr></thead>
                <tbody id="availabilityTableBody"><tr><td colspan="{{ 5 + count($bloodTypes) }}" class="text-center text-muted py-4">Loading...</td></tr></tbody>
            </table>
        </div>
    </section>
</main>
@endsection

@push('admin_scripts')
<script src="{{ asset('vendor/leaflet/leaflet.min.js') }}"></script>
<script>
(function () {
    'use strict';

    const dataUrl = @json(route('admin.map.data'));
    const bloodTypes = @json(array_values($bloodTypes));
    const mapElement = document.getElementById('availabilityMap');
    const tableBody = document.getElementById('availabilityTableBody');
    const mapStatus = document.getElementById('mapStatus');
    const bloodTypeInput = document.getElementById('availabilityBloodType');
    const barangayInput = document.getElementById('availabilityBarangay');
    const cityInput = document.getElementById('availabilityCity');
    const markerLayer = window.L ? L.layerGroup() : null;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    let map = null;
    let searchTimer = null;

    if (window.L) {
        map = L.map(mapElement, { zoomControl: true }).setView([13.9419, 121.1644], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' })
            .addTo(map)
            .on('tileerror', () => { mapStatus.textContent = 'Map tiles could not be loaded. The aggregate table remains available.'; });
        markerLayer.addTo(map);
    } else {
        mapStatus.textContent = 'Map visualization is unavailable. The aggregate table remains available.';
    }

    function params() {
        const query = new URLSearchParams();
        if (bloodTypeInput.value) query.set('blood_type', bloodTypeInput.value);
        if (barangayInput.value.trim()) query.set('barangay', barangayInput.value.trim());
        if (cityInput.value.trim()) query.set('city', cityInput.value.trim());
        return query.toString();
    }

    function validCoordinate(latitude, longitude) {
        return Number.isFinite(Number(latitude)) && Number.isFinite(Number(longitude)) && Number(latitude) !== 0 && Number(longitude) !== 0;
    }

    function markerIcon(level, count) {
        return L.divIcon({ className: '', html: `<span class="availability-map-marker availability-map-marker--${escapeHtml(level)}">${escapeHtml(count)}</span>`, iconSize: [34, 34], iconAnchor: [17, 17] });
    }

    function renderMap(points) {
        if (!map || !markerLayer) return;
        markerLayer.clearLayers();
        const bounds = [];
        points.forEach(point => {
            if (!validCoordinate(point.latitude, point.longitude)) return;
            const position = [Number(point.latitude), Number(point.longitude)];
            bounds.push(position);
            const bloodBreakdown = bloodTypes.map(type => `<div>${escapeHtml(type)}: <strong>${escapeHtml(point.blood_types?.[type] ?? 0)}</strong></div>`).join('');
            const popup = `<strong>${escapeHtml(point.barangay_name)}</strong><br><span>${escapeHtml(point.city)}</span><hr class="my-1"><div>Available verified donors: <strong>${escapeHtml(point.available_donors)}</strong></div><div>Scheduled: <strong>${escapeHtml(point.scheduled_donors)}</strong></div><div class="mt-1">${bloodBreakdown}</div>`;
            L.marker(position, { icon: markerIcon(point.availability_level, point.available_donors) }).bindPopup(popup).addTo(markerLayer);
        });
        if (bounds.length) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 14 });
        mapStatus.textContent = points.length ? `${points.length} aggregate barangay marker(s).` : 'No mapped barangay coordinates match the current filters.';
    }

    function renderSummary(data) {
        const summary = data.summary || {};
        const formatType = value => value?.blood_type ? `${value.blood_type} (${value.count})` : '-';
        document.getElementById('summaryAvailable').textContent = summary.available_donors ?? 0;
        document.getElementById('summaryBarangays').textContent = summary.barangays ?? 0;
        document.getElementById('summaryMost').textContent = formatType(summary.most_available_blood_type);
        document.getElementById('summaryLowest').textContent = formatType(summary.lowest_available_blood_type);
        const quality = data.data_quality || {};
        document.getElementById('availabilityQuality').textContent = `Mapped available donors: ${quality.mapped_available_donors ?? 0}. Missing usable coordinates: ${quality.available_donors_missing_coordinates ?? 0}. Scheduled donors are shown separately.`;
    }

    function renderTable(rows) {
        if (!rows.length) {
            tableBody.innerHTML = `<tr><td colspan="${5 + bloodTypes.length}" class="text-center text-muted py-4">No aggregate availability found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = rows.map(row => `<tr><td><strong>${escapeHtml(row.barangay_name)}</strong></td><td>${escapeHtml(row.city)}</td><td>${escapeHtml(row.available_donors)}</td><td>${escapeHtml(row.scheduled_donors)}</td>${bloodTypes.map(type => `<td>${escapeHtml(row.blood_types?.[type] ?? 0)}</td>`).join('')}<td><span class="availability-badge availability-badge--${escapeHtml(row.availability_level)}">${escapeHtml(row.availability_level)}</span></td></tr>`).join('');
    }

    async function refresh() {
        tableBody.innerHTML = `<tr><td colspan="${5 + bloodTypes.length}" class="text-center text-muted py-4">Loading...</td></tr>`;
        try {
            const response = await fetch(`${dataUrl}${params() ? '?' + params() : ''}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Availability request failed.');
            const data = await response.json();
            renderSummary(data);
            renderTable(Array.isArray(data.barangays) ? data.barangays : []);
            renderMap(Array.isArray(data.map_points) ? data.map_points : []);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="${5 + bloodTypes.length}" class="text-center text-danger py-4">Could not load aggregate availability.</td></tr>`;
            mapStatus.textContent = 'The map request failed. Please try again; no donor details are exposed by this page.';
        }
    }

    bloodTypeInput.addEventListener('change', refresh);
    [barangayInput, cityInput].forEach(input => input.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(refresh, 300); }));
    document.getElementById('clearAvailabilityFilters').addEventListener('click', () => { bloodTypeInput.value = ''; barangayInput.value = ''; cityInput.value = ''; refresh(); });
    refresh();
}());
</script>
@endpush
