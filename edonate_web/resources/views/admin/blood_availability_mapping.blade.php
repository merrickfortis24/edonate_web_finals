@extends('layouts.admin')

@section('title', 'eDonate - Geographic Blood Availability')
@section('admin_page_class', 'admin-blood-availability-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('sidebar_aria_label', 'Main navigation')
@section('sidebar_nav_aria_label', 'Main navigation')
@section('sidebar_open_class', 'is-open')
@section('render_default_hamburger', 'false')

@section('header_title', 'Geographic Blood Availability')
@section('header_subtitle', 'Monitor blood type availability across different locations in real-time')

@section('header_slot')
	<button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
		<span class="hamburger__bar"></span>
	</button>
@endsection

@section('header_actions')
	<p class="page-header__date-label">Today's Date</p>
	<p class="page-header__date-value" id="todayDate">-</p>
@endsection

@section('admin_page_data')
{!! json_encode([
	'page'            => 'blood-availability-mapping',
	'donorsUrl'       => route('admin.map.donors'),
	'barangaysUrl'    => route('admin.map.barangays'),
	'summaryUrl'      => route('admin.map.summary'),
	'geocodeMissingUrl' => route('admin.map.geocode-missing'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@push('admin_head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.min.css') }}"/>
@endpush

@section('main_content')
<main class="main container-fluid px-0">

	{{-- ── Top row: Filter panel + Map panel ── --}}
	<section class="panels-row" aria-label="Filters and Location Map">

		{{-- ── Filter / Legend panel ── --}}
		<aside class="filter-panel" aria-label="Map filters">
			<div class="filter-panel__header">
				<svg class="filter-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
					<path d="M4 6H20M7 12H17M10 18H14" stroke="#b60c0c" stroke-width="2" stroke-linecap="round"/>
				</svg>
				<span class="filter-panel__title">Filters</span>
			</div>

			{{-- Blood type filter --}}
			<p class="filter-panel__label">Blood Type</p>
			<div class="filter-panel__select-wrap">
				<select id="filterBloodType" class="filter-panel__select form-select" aria-label="Filter by blood type">
					<option value="">All Blood Types</option>
					<option>A+</option><option>A-</option>
					<option>B+</option><option>B-</option>
					<option>AB+</option><option>AB-</option>
					<option>O+</option><option>O-</option>
				</select>
				<span class="filter-panel__select-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</span>
			</div>

			{{-- Barangay filter --}}
			<p class="filter-panel__label">Barangay</p>
			<div class="filter-panel__select-wrap">
				<select id="filterBarangay" class="filter-panel__select form-select" aria-label="Filter by barangay">
					<option value="">All Barangays</option>
					{{-- populated dynamically from /admin/map/barangays --}}
				</select>
				<span class="filter-panel__select-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</span>
			</div>

			<button id="clearFiltersBtn" class="filter-panel__clear-btn btn btn-outline-secondary" type="button">
				Clear Filters
			</button>

			<hr class="filter-panel__divider"/>

			{{-- Active filter chips --}}
			<p class="filter-panel__section-label">Active Filters</p>
			<div class="filter-chips" id="activeFilterChips">
				<span class="filter-chip" style="color:#888;font-size:12px;">None</span>
			</div>

			<hr class="filter-panel__divider"/>

			{{-- Quick stats --}}
			<p class="filter-panel__section-label">Quick Stats</p>
			<div class="quick-stats__grid">
				<div class="quick-stats__row">
					<span class="quick-stats__key">Total Donors:</span>
					<span class="quick-stats__val" id="statTotalDonors">—</span>
				</div>
				<div class="quick-stats__row">
					<span class="quick-stats__key">Mapped Locations:</span>
					<span class="quick-stats__val" id="statTotalLocations">—</span>
				</div>
				<div class="quick-stats__row">
					<span class="quick-stats__key">Visible Pins:</span>
					<span class="quick-stats__val" id="statVisiblePins">—</span>
				</div>
				<div class="quick-stats__row">
					<span class="quick-stats__key">Unmapped Donors:</span>
					<span class="quick-stats__val" id="statUnmappedLocations">—</span>
				</div>
			</div>

			<button id="geocodeMissingBtn" class="filter-panel__clear-btn btn btn-outline-primary mt-2" type="button">
				Geocode Missing Locations
			</button>

			<hr class="filter-panel__divider"/>

			{{-- Blood type colour legend --}}
			<p class="filter-panel__section-label">Blood Type Colours</p>
			<div class="bt-legend" id="btLegend">
				{{-- built by JS --}}
			</div>
		</aside>

		{{-- ── Map panel ── --}}
		<div class="map-panel">
			<p class="map-panel__title">Location Map</p>
			<p class="map-panel__subtitle">Click a marker to see detailed blood availability for that area</p>

			{{-- Layer toggles --}}
			<div class="map-toolbar" role="group" aria-label="Map layer toggles">
				<button class="map-toolbar__btn is-active" id="btnMarkers" type="button">
					<span class="map-toolbar__dot" style="background:#e53e3e;"></span> Donor Pins
				</button>
				<button class="map-toolbar__btn" id="btnHeatmap" type="button">
					<span class="map-toolbar__dot" style="background:#fd8d3c;"></span> Heatmap
				</button>
				<button class="map-toolbar__btn" id="btnBarangay" type="button">
					<span class="map-toolbar__dot" style="background:#3182ce;"></span> Barangay View
				</button>
			</div>

			{{-- Map container --}}
			<div class="map-panel__map-wrap">
				{{-- Leaflet renders here --}}
				<div id="blood-map" role="application" aria-label="Interactive blood donor map"></div>

				{{-- Loading overlay --}}
				<div class="map-loading" id="mapLoading" aria-live="polite">
					<div class="map-spinner"></div>
					<span>Loading map data…</span>
				</div>

				{{-- Availability legend (bottom-right, overlaid on map) --}}
				<div class="map-legend" aria-label="Availability level legend">
					<p class="map-legend__title">Availability Level</p>
					<div class="map-legend__row"><span class="map-legend__dot map-legend__dot--high"></span><span class="map-legend__text">High (≥10 donors)</span></div>
					<div class="map-legend__row"><span class="map-legend__dot map-legend__dot--medium"></span><span class="map-legend__text">Medium (5–9)</span></div>
					<div class="map-legend__row"><span class="map-legend__dot map-legend__dot--low"></span><span class="map-legend__text">Low (2–4)</span></div>
					<div class="map-legend__row"><span class="map-legend__dot map-legend__dot--critical"></span><span class="map-legend__text">Critical (&lt;2)</span></div>
				</div>
			</div>

			{{-- Summary bar below the map --}}
			<div class="map-summary-bar" id="mapSummaryBar" aria-label="Map summary statistics">
				{{-- built by JS --}}
			</div>
		</div>
	</section>

	{{-- ── Bottom: Selected-location detail panel ── --}}
	<section class="location-panel is-empty" id="locationPanel" aria-label="Selected location blood availability" aria-live="polite">
		<div class="location-panel__empty-msg">
			Click a barangay marker on the map to see detailed blood availability here.
		</div>
	</section>

</main>
@endsection

@push('admin_scripts')
<script src="{{ asset('vendor/leaflet/leaflet.min.js') }}"></script>
<script src="{{ asset('vendor/leaflet/leaflet-heat.js') }}"></script>

<script>
(function () {
	'use strict';

	/*
	 * Leaflet resolves marker-icon URLs relative to its JS file location.
	 * When served locally the path would be wrong, so we set it explicitly.
	 */
	L.Icon.Default.imagePath = '{{ asset('vendor/leaflet/images') }}/';

	/* ── Constants ── */
	const URLS = window.AdminPageData;

	const BT_COLORS = {
		'A+':  '#3182ce',
		'A-':  '#00b5d8',
		'B+':  '#38a169',
		'B-':  '#276749',
		'AB+': '#805ad5',
		'AB-': '#d53f8c',
		'O+':  '#e53e3e',
		'O-':  '#dd6b20',
	};

	const LEVEL_COLORS = {
		high:     '#10a44b',
		medium:   '#ffb400',
		low:      '#ff8f2a',
		critical: '#df2020',
	};

	const LEVEL_LABELS = {
		high:     'High',
		medium:   'Medium',
		low:      'Low',
		critical: 'Critical',
	};

	/* ── State ── */
	let allDonors    = [];
	let allBarangays = [];
	let activeLayer  = 'markers'; // 'markers' | 'heatmap' | 'barangay'

	/* ── Leaflet layers ── */
	let markerLayer   = null;
	let heatLayer     = null;
	let barangayLayer = null;

	/* ── Map init ── */
	const map = L.map('blood-map', { zoomControl: true }).setView([13.9419, 121.1644], 12);

	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		maxZoom: 19,
	}).addTo(map);

	/* ── DOM refs ── */
	const filterBT        = document.getElementById('filterBloodType');
	const filterBarangay  = document.getElementById('filterBarangay');
	const clearBtn        = document.getElementById('clearFiltersBtn');
	const chipsWrap       = document.getElementById('activeFilterChips');
	const mapLoading      = document.getElementById('mapLoading');
	const locationPanel   = document.getElementById('locationPanel');
	const statDonors      = document.getElementById('statTotalDonors');
	const statLocations   = document.getElementById('statTotalLocations');
	const statPins        = document.getElementById('statVisiblePins');
	const statUnmapped    = document.getElementById('statUnmappedLocations');
	const summaryBar      = document.getElementById('mapSummaryBar');
	const btLegend        = document.getElementById('btLegend');
	const geocodeMissingBtn = document.getElementById('geocodeMissingBtn');

	const btnMarkers  = document.getElementById('btnMarkers');
	const btnHeatmap  = document.getElementById('btnHeatmap');
	const btnBarangay = document.getElementById('btnBarangay');

	/* ── Build blood-type colour legend in sidebar ── */
	function buildBtLegend() {
		btLegend.innerHTML = Object.entries(BT_COLORS).map(([bt, color]) =>
			`<div class="bt-legend__row">
				<span class="bt-legend__swatch" style="background:${color};"></span>
				<span>${bt}</span>
			</div>`
		).join('');
	}

	/* ── Helpers ── */
	function showLoading()  { mapLoading.classList.remove('hidden'); }
	function hideLoading()  { mapLoading.classList.add('hidden'); }

	function getFilters() {
		return {
			blood_type: filterBT.value.trim(),
			barangay:   filterBarangay.value.trim(),
		};
	}

	function buildUrl(base, params) {
		const url = new URL(base, window.location.origin);
		Object.entries(params).forEach(([k, v]) => { if (v) url.searchParams.set(k, v); });
		return url.toString();
	}

	function csrfToken() {
		return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
	}

	function validCoordinate(lat, lng) {
		const latitude = Number(lat);
		const longitude = Number(lng);

		return Number.isFinite(latitude)
			&& Number.isFinite(longitude)
			&& latitude >= -90
			&& latitude <= 90
			&& longitude >= -180
			&& longitude <= 180
			&& !(latitude === 0 && longitude === 0);
	}

	function normalizeDonorCoordinates(donors) {
		return (Array.isArray(donors) ? donors : [])
			.map(function (donor) {
				const lat = donor.lat ?? donor.latitude;
				const lng = donor.lng ?? donor.longitude;

				return {
					...donor,
					lat: Number(lat),
					lng: Number(lng),
				};
			})
			.filter(function (donor) {
				return validCoordinate(donor.lat, donor.lng);
			});
	}

	function normalizeBarangayCoordinates(barangays) {
		return (Array.isArray(barangays) ? barangays : [])
			.map(function (barangay) {
				return {
					...barangay,
					centroid_lat: Number(barangay.centroid_lat ?? barangay.lat ?? barangay.latitude),
					centroid_lng: Number(barangay.centroid_lng ?? barangay.lng ?? barangay.longitude),
				};
			})
			.filter(function (barangay) {
				return validCoordinate(barangay.centroid_lat, barangay.centroid_lng);
			});
	}

	async function postJson(url, payload = {}) {
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': csrfToken(),
			},
			body: JSON.stringify(payload),
		});

		if (!response.ok) {
			throw new Error('Request failed.');
		}

		return response.json();
	}

	function updateFilterChips(filters) {
		const chips = [];
		if (filters.blood_type) chips.push(`<span class="filter-chip filter-chip--type">${filters.blood_type}</span>`);
		if (filters.barangay)   chips.push(`<span class="filter-chip filter-chip--barangay">${filters.barangay}</span>`);
		chipsWrap.innerHTML = chips.length
			? chips.join('')
			: '<span style="color:#888;font-size:12px;">None</span>';
	}

	/* ── Remove all dynamic layers from map ── */
	function clearLayers() {
		if (markerLayer)   { map.removeLayer(markerLayer);   markerLayer   = null; }
		if (heatLayer)     { map.removeLayer(heatLayer);     heatLayer     = null; }
		if (barangayLayer) { map.removeLayer(barangayLayer); barangayLayer = null; }
	}

	/* ── Render circle-marker layer ── */
	function renderMarkers(donors) {
		markerLayer = L.layerGroup();

		donors.forEach(function (d) {
			const color = BT_COLORS[d.blood_type] ?? '#718096';

			const circle = L.circleMarker([d.lat, d.lng], {
				radius:      9,
				fillColor:   color,
				color:       '#fff',
				weight:      2,
				opacity:     1,
				fillOpacity: 0.88,
			});

			circle.bindPopup(
				`<div style="font-family:'Poppins',sans-serif;min-width:160px;">
					<strong style="font-size:14px;">${d.name}</strong><br>
					<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};margin-right:4px;vertical-align:middle;"></span>
					<b>${d.blood_type}</b><br>
					<span style="font-size:12px;color:#555;">📍 ${d.barangay}, ${d.city}</span>
				</div>`,
				{ maxWidth: 240 }
			);

			markerLayer.addLayer(circle);
		});

		markerLayer.addTo(map);
		statPins.textContent = donors.length;
	}

	/* ── Render heatmap layer ── */
	function renderHeatmap(donors) {
		const points = donors.map(d => [d.lat, d.lng, 1.0]);

		heatLayer = L.heatLayer(points, {
			radius:   32,
			blur:     22,
			maxZoom:  14,
			gradient: { 0.2: '#ffffd4', 0.5: '#fd8d3c', 0.8: '#bd0026' },
		});

		heatLayer.addTo(map);
		statPins.textContent = donors.length;
	}

	/* ── Render barangay-badge layer ── */
	function renderBarangayLayer(barangays) {
		barangayLayer = L.layerGroup();

		barangays.forEach(function (b) {
			if (!b.centroid_lat || !b.centroid_lng) return;

			const levelColor = LEVEL_COLORS[b.availability_level] ?? '#718096';
			const levelLabel = LEVEL_LABELS[b.availability_level] ?? b.availability_level;

			const icon = L.divIcon({
				html: `<div class="leaflet-barangay-badge level-${b.availability_level}">
							${b.barangay}
							<span class="badge-count">${b.donor_count}</span>
						</div>`,
				className: '',
				iconAnchor: [0, 0],
			});

			const marker = L.marker([b.centroid_lat, b.centroid_lng], { icon });

			marker.bindPopup(
				`<div style="font-family:'Poppins',sans-serif;min-width:180px;">
					<strong style="font-size:14px;">${b.barangay}</strong><br>
					<span style="font-size:12px;color:#555;">
						Donors: <b>${b.donor_count}</b><br>
						Level: <b style="color:${levelColor};">${levelLabel}</b><br>
						${b.dominant_type ? `Dominant: <b>${b.dominant_type}</b>${b.is_surplus ? ' ⚠ surplus' : ''}` : ''}
						${b.types_present.length ? `<br>Types: ${b.types_present.join(', ')}` : ''}
					</span>
				</div>`,
				{ maxWidth: 260 }
			);

			marker.on('click', function () {
				renderLocationPanel(b);
			});

			barangayLayer.addLayer(marker);
		});

		barangayLayer.addTo(map);
		statPins.textContent = barangays.length + ' barangays';
	}

	/* ── Populate the bottom location-detail panel ── */
	function renderLocationPanel(b) {
		const levelColor = LEVEL_COLORS[b.availability_level] ?? '#718096';
		const levelLabel = LEVEL_LABELS[b.availability_level] ?? b.availability_level;

		const typesPresent = new Set(b.types_present ?? []);
		const allTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

		const cards = allTypes.map(function (bt) {
			const present   = typesPresent.has(bt);
			const color     = BT_COLORS[bt] ?? '#718096';
			const cardClass = present ? (bt === b.dominant_type ? 'blood-card--green' : 'blood-card--yellow') : 'blood-card--red';
			const dotClass  = present ? (bt === b.dominant_type ? 'blood-card__dot--high' : 'blood-card__dot--medium') : 'blood-card__dot--critical';
			const statusLbl = present ? (bt === b.dominant_type ? 'Dominant' : 'Present') : 'None';

			return `<div class="blood-card ${cardClass}">
				<div class="blood-card__top">
					<span class="blood-card__type">${bt}</span>
					<span style="width:18px;height:18px;border-radius:50%;background:${color};display:inline-block;flex-shrink:0;"></span>
				</div>
				<div class="blood-card__status-row" style="margin-top:auto;">
					<span class="blood-card__dot ${dotClass}"></span>
					<span class="blood-card__status-label">${statusLbl}</span>
				</div>
			</div>`;
		}).join('');

		locationPanel.classList.remove('is-empty');
		locationPanel.innerHTML = `
			<div class="location-panel__header">
				<h2 class="location-panel__name">${b.barangay}</h2>
				<span class="location-panel__badge" style="background:${levelColor}20;color:${levelColor};">
					${levelLabel}${b.is_surplus ? ' — Surplus' : ''}
				</span>
			</div>
			<p class="location-panel__address">${b.donor_count} registered donor(s) · ${b.types_present.length} blood type(s) present</p>
			<div class="blood-grid">${cards}</div>
			<p class="location-panel__footer">Last refreshed: ${new Date().toLocaleString()}</p>
		`;

		locationPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	/* ── Populate summary bar ── */
	function renderSummaryBar(summary) {
		const mappedLocations = summary.mapped_locations ?? summary.total_locations ?? '—';
		const unmappedDonors = summary.unmapped_donors ?? summary.unmapped_locations ?? '—';

		statDonors.textContent    = summary.total_donors ?? '—';
		statLocations.textContent = mappedLocations;
		if (statUnmapped) {
			statUnmapped.textContent = unmappedDonors;
		}

		const btBreakdown = (summary.blood_type_breakdown ?? [])
			.map(b => `<div class="summary-stat">
				<span class="summary-stat__val" style="color:${BT_COLORS[b.blood_type] ?? '#b60c0c'};">${b.count}</span>
				<span class="summary-stat__label">${b.blood_type}</span>
			</div>`).join('');

		summaryBar.innerHTML = `
			<div class="summary-stat">
				<span class="summary-stat__val">${summary.total_donors}</span>
				<span class="summary-stat__label">Total Donors</span>
			</div>
			${btBreakdown}
			<div class="summary-stat" style="margin-left:auto;">
				<span style="font-size:10px;color:#999;">Updated ${summary.last_updated}</span>
			</div>`;
	}

	/* ── Populate barangay dropdown from live data ── */
	function populateBarangayDropdown(barangays) {
		const existing = Array.from(filterBarangay.options).map(o => o.value);
		barangays.forEach(function (b) {
			if (!existing.includes(b.barangay)) {
				const opt = document.createElement('option');
				opt.value = b.barangay;
				opt.textContent = b.barangay;
				filterBarangay.appendChild(opt);
			}
		});
	}

	/* ── Set active toolbar button ── */
	function setActiveLayerBtn(id) {
		[btnMarkers, btnHeatmap, btnBarangay].forEach(function (btn) {
			btn.classList.toggle('is-active', btn.id === id);
		});
	}

	/* ── Main data-fetch + render cycle ── */
	async function refresh() {
		showLoading();
		clearLayers();

		const filters = getFilters();
		updateFilterChips(filters);

		try {
			const [donorResp, barangayResp, summaryResp] = await Promise.all([
				fetch(buildUrl(URLS.donorsUrl,    filters)),
				fetch(buildUrl(URLS.barangaysUrl, {})),
				fetch(buildUrl(URLS.summaryUrl,   { blood_type: filters.blood_type })),
			]);

			if (!donorResp.ok || !barangayResp.ok || !summaryResp.ok) {
				throw new Error('One or more API calls failed.');
			}

			allDonors    = normalizeDonorCoordinates(await donorResp.json());
			allBarangays = normalizeBarangayCoordinates(await barangayResp.json());
			const summary = await summaryResp.json();

			populateBarangayDropdown(allBarangays);
			renderSummaryBar(summary);
			applyLayer();

		} catch (err) {
			console.error('Map refresh error:', err);
			summaryBar.innerHTML = '<span style="color:#e53e3e;font-size:13px;">Could not load map data. Check your connection.</span>';
		} finally {
			hideLoading();
		}
	}

	/* ── Apply whichever layer is currently active ── */
	function applyLayer() {
		clearLayers();

		if (activeLayer === 'markers') {
			renderMarkers(allDonors);
		} else if (activeLayer === 'heatmap') {
			if (allDonors.length === 0) {
				statPins.textContent = '0';
			} else {
				renderHeatmap(allDonors);
			}
		} else if (activeLayer === 'barangay') {
			renderBarangayLayer(allBarangays);
		}
	}

	/* ── Layer toggle handlers ── */
	btnMarkers.addEventListener('click', function () {
		activeLayer = 'markers';
		setActiveLayerBtn('btnMarkers');
		applyLayer();
	});

	btnHeatmap.addEventListener('click', function () {
		activeLayer = 'heatmap';
		setActiveLayerBtn('btnHeatmap');
		applyLayer();
	});

	btnBarangay.addEventListener('click', function () {
		activeLayer = 'barangay';
		setActiveLayerBtn('btnBarangay');
		applyLayer();
	});

	/* ── Filter change handlers ── */
	filterBT.addEventListener('change', refresh);
	filterBarangay.addEventListener('change', refresh);

	clearBtn.addEventListener('click', function () {
		filterBT.value       = '';
		filterBarangay.value = '';
		refresh();
	});

	/* ── Today's date ── */
	if (geocodeMissingBtn && URLS.geocodeMissingUrl) {
		geocodeMissingBtn.addEventListener('click', async function () {
			if (!window.confirm('Try to geocode donor locations that are missing map coordinates?')) {
				return;
			}

			const defaultLabel = 'Geocode Missing Locations';
			geocodeMissingBtn.disabled = true;
			geocodeMissingBtn.textContent = 'Geocoding...';

			try {
				const result = await postJson(URLS.geocodeMissingUrl, { limit: 5 });
				summaryBar.innerHTML = `<span style="color:#276749;font-size:13px;">Geocoding complete: ${result.succeeded ?? 0} saved, ${result.failed ?? 0} failed.</span>`;
				await refresh();
			} catch (err) {
				console.error('Geocode missing locations error:', err);
				summaryBar.innerHTML = '<span style="color:#e53e3e;font-size:13px;">Could not geocode missing locations. Please try again.</span>';
			} finally {
				geocodeMissingBtn.disabled = false;
				geocodeMissingBtn.textContent = defaultLabel;
			}
		});
	}

	const dateEl = document.getElementById('todayDate');
	if (dateEl) {
		dateEl.textContent = new Date().toLocaleDateString('en-US', {
			year: 'numeric', month: 'long', day: 'numeric',
		});
	}

	/* ── Bootstrap ── */
	buildBtLegend();
	refresh();

})();
</script>
@endpush
