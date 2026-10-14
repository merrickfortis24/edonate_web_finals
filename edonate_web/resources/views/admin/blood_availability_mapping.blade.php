@extends('layouts.admin')

@section('title', 'eDonate - Blood Availability Map')
@section('admin_page_class', 'admin-blood-availability-page')
@section('header_title', 'Blood Availability Map')
@section('header_subtitle', 'Blood-type donor availability and recorded facility inventory')

@push('admin_head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/leaflet-control-geocoder/Control.Geocoder.css') }}">
<style>
    .availability-content{padding:24px 32px 40px}.availability-tabs{display:inline-flex;border:1px solid var(--bs-border-color);border-radius:8px;overflow:hidden;margin-bottom:18px}.availability-tab{background:var(--edonate-card-bg);border:0;border-right:1px solid var(--bs-border-color);color:var(--bs-body-color);font-weight:600;padding:10px 16px}.availability-tab:last-child{border-right:0}.availability-tab.active{background:#9f1010;color:#fff}.availability-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:18px}.availability-stat,.availability-panel{background:var(--edonate-card-bg);border:1px solid var(--bs-border-color);border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);color:var(--bs-body-color)}.availability-stat{min-height:100px;padding:16px}.availability-stat__label{color:var(--bs-secondary-color);font-size:12px;font-weight:600}.availability-stat__value{color:#9f1010;font-size:26px;font-weight:700;margin-top:7px}.availability-panel{margin-bottom:18px;padding:18px}.availability-filter-row{align-items:end;display:grid;gap:12px;grid-template-columns:180px minmax(200px,1fr) 180px 180px auto}.availability-filter-row label{display:block;font-size:12px;font-weight:600;margin-bottom:5px}.availability-quality,.availability-panel__hint,.availability-legend{color:var(--bs-secondary-color);font-size:12px;margin:10px 0 0}.availability-panel__title{color:var(--c-primary-dark);font-size:18px;font-weight:700;margin:0 0 4px}#availabilityMap{background:var(--bs-tertiary-bg);border-radius:8px;height:500px;min-height:360px;margin-top:14px}.availability-table-wrap{overflow-x:auto}.availability-table{margin:0;min-width:980px}.availability-table td,.availability-table th{font-size:12px;vertical-align:middle;white-space:nowrap}.availability-badge{border-radius:999px;display:inline-block;font-size:11px;font-weight:700;padding:4px 8px}.availability-badge--high,.availability-badge--available{background:#dff3e4;color:#17642c}.availability-badge--moderate,.availability-badge--low{background:#fff0bd;color:#725500}.availability-badge--none,.availability-badge--out_of_stock{background:#f3d8d8;color:#851616}.availability-marker{align-items:center;background:#a90f0f;border:3px solid #fff;border-radius:50%;box-shadow:0 1px 5px rgba(0,0,0,.35);color:#fff;display:flex;font-size:11px;font-weight:700;height:38px;justify-content:center;width:38px}.availability-marker--moderate,.availability-marker--low{background:#bd8300}.availability-marker--none,.availability-marker--out_of_stock{background:#777}@media(max-width:1100px){.availability-summary{grid-template-columns:repeat(3,1fr)}.availability-filter-row{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.availability-content{padding:16px}.availability-summary,.availability-filter-row{grid-template-columns:1fr}#availabilityMap{height:420px}.availability-tabs{display:flex}.availability-tab{flex:1}}
</style>
<style>
    .availability-marker--approximate{border-style:dashed}
    .availability-marker--donor-available{background:#16823b}
    .availability-marker--donor-temporarily_deferred{background:#e0b000;color:#241b00}
    .availability-marker--donor-not_available{background:#b52c2c}
    .availability-status{border-radius:999px;display:inline-block;font-size:11px;font-weight:700;padding:4px 8px;white-space:nowrap}
    .availability-status--available{background:#dff3e4;color:#17642c}
    .availability-status--temporarily_deferred{background:#fff0bd;color:#725500}
    .availability-status--not_available{background:#f3d8d8;color:#851616}
    .availability-type-detail{min-width:210px;white-space:normal!important}
    .availability-type-detail small{display:block;line-height:1.45;margin-top:4px;white-space:normal}
    .availability-popup-types{display:grid;gap:5px;grid-template-columns:1fr 1fr;min-width:320px}
    .availability-popup-type{border-bottom:1px solid #ddd;padding:3px 0}
    .availability-popup-type small{display:block;line-height:1.35;margin-top:3px}
    .availability-legend-items{display:flex;flex-wrap:wrap;gap:8px 16px;margin-top:8px}
    .availability-legend-item{align-items:center;display:inline-flex;gap:6px}
    .availability-legend-swatch{border:1px solid rgba(0,0,0,.2);border-radius:50%;display:inline-flex;font-size:11px;font-weight:800;height:22px;justify-content:center;line-height:20px;width:22px}
    .availability-legend-swatch--available{background:#16823b;color:#fff}
    .availability-legend-swatch--temporarily_deferred{background:#e0b000;color:#241b00}
    .availability-legend-swatch--not_available{background:#b52c2c;color:#fff}
</style>
@endpush

@section('main_content')
<main class="availability-content">
    <div class="availability-tabs" role="group" aria-label="Availability map layer"><button class="availability-tab active" id="donorLayer" aria-pressed="true" type="button">Donor Availability</button><button class="availability-tab" id="facilityLayer" aria-pressed="false" type="button">Facility Inventory</button></div>
    <section class="availability-summary" aria-label="Availability summary">
        <div class="availability-stat"><div class="availability-stat__label" id="summaryLabel1">Completed Donors</div><div class="availability-stat__value" id="summaryValue1">-</div></div>
        <div class="availability-stat"><div class="availability-stat__label" id="summaryLabel2">Available Donors</div><div class="availability-stat__value" id="summaryValue2">-</div></div>
        <div class="availability-stat"><div class="availability-stat__label" id="summaryLabel3">Temporarily Deferred</div><div class="availability-stat__value" id="summaryValue3">-</div></div>
        <div class="availability-stat"><div class="availability-stat__label" id="summaryLabel4">Barangays With Completed Donors</div><div class="availability-stat__value" id="summaryValue4">-</div></div>
        <div class="availability-stat" id="summaryCard5"><div class="availability-stat__label" id="summaryLabel5">Unmapped Completed Donors</div><div class="availability-stat__value" id="summaryValue5">-</div></div>
    </section>
    <section class="availability-panel" aria-label="Availability filters"><div class="availability-filter-row">
        <div><label for="availabilityBloodType">Blood type</label><select class="form-select" id="availabilityBloodType"><option value="">All blood types</option>@foreach($bloodTypes as $bloodType)<option value="{{ $bloodType }}">{{ $bloodType }}</option>@endforeach<option value="Unknown">Unknown</option></select></div>
        <div><label for="availabilitySearch" id="availabilitySearchLabel">Barangay search</label><input class="form-control" id="availabilitySearch" type="search" maxlength="150" placeholder="Search barangay"></div>
        <div><label for="availabilityCity" id="availabilityScopeLabel">City filter</label><input class="form-control" id="availabilityCity" type="search" maxlength="150" placeholder="Any city"><select class="form-select d-none" id="availabilityFacilityType"><option value="">All facility types</option>@foreach($facilityTypes as $type)<option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>@endforeach</select></div>
        <div id="availabilitySortWrap" class="d-none"><label for="availabilitySort">Sort facilities</label><select class="form-select" id="availabilitySort"><option value="name">Facility name</option><option value="units_desc">Units: high to low</option><option value="units_asc">Units: low to high</option><option value="updated_desc">Recently updated</option></select></div>
        <button class="btn btn-outline-secondary" id="clearAvailabilityFilters" type="button">Clear</button>
    </div><p class="availability-quality" id="availabilityQuality">The total counts distinct donors with completed donation history. Per-type status additionally requires an active account, verified donor identity, and current eligibility; profile-only blood types remain labelled unconfirmed. Exact identities and home coordinates are not exposed.</p></section>
    <section class="availability-panel"><h2 class="availability-panel__title" id="mapTitle">Blood-Type Donor Availability</h2><p class="availability-panel__hint" id="mapHint">One aggregate marker represents one barangay with completed donation history. This is not physical blood-bank inventory.</p><div class="availability-legend" id="mapLegend" aria-label="Map status legend"></div><p id="mapPrivacyNotice">External map tiles are blocked until you allow maps in <button type="button" class="btn btn-outline-secondary" data-privacy-open>Privacy choices</button>. The table below remains available.</p><div id="availabilityMap" role="region" aria-label="Optional blood-type donor availability map; equivalent information is in the table below"></div><p class="text-muted small mt-2 mb-0" id="mapStatus" aria-live="polite"></p></section>
    <section class="availability-panel"><h2 class="availability-panel__title" id="tableTitle">Barangay Blood-Type Availability</h2><div class="availability-table-wrap" role="region" aria-label="Blood availability table, scroll horizontally if needed" tabindex="0"><table class="table table-hover availability-table"><thead id="availabilityTableHead"></thead><tbody id="availabilityTableBody"></tbody></table></div><div class="admin-pagination admin-pagination--js d-none" id="facilityPagination" aria-label="Table pagination"><span class="admin-pagination__info" id="facilityPageMeta">Showing 0 to 0 of 0 entries</span><nav class="admin-pagination__links" id="facilityPaginationLinks" aria-label="Pagination links"></nav></div></section>
    <section class="availability-panel" id="unmappedCompletedPanel" hidden aria-labelledby="unmappedCompletedTitle"><h2 class="availability-panel__title" id="unmappedCompletedTitle">Unmapped completed donors</h2><p class="availability-panel__hint">These completed donors remain in the total, but their registered barangay or municipality could not be matched to a usable center. Only donor references and missing fields are shown.</p><p class="availability-panel__hint">The map first uses local barangay polygon centers from Faeldon’s 2019 Philippines boundary dataset. If a location is not in that dataset, an admin may optionally use the external resolver; unmatched records stay visible and no donor identity or exact home coordinates are sent.</p><div class="d-flex flex-wrap align-items-center gap-2 mb-3"><button class="btn btn-outline-primary" id="resolveBarangayCenters" type="button" disabled><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Resolve locations outside the dataset</button><span class="small text-muted" id="geocodeQueueStatus" role="status" aria-live="polite"></span></div><div class="availability-table-wrap" role="region" aria-label="Unmapped completed donors" tabindex="0"><table class="table table-hover availability-table"><thead><tr><th>Donor reference</th><th>Barangay</th><th>City</th><th>Blood type</th><th>Type status</th><th>Missing field</th></tr></thead><tbody id="unmappedCompletedBody"></tbody></table></div></section>
</main>
@endsection

@push('admin_scripts')
@include('admin.partials.facility-status-script')
<script src="{{ asset('vendor/leaflet/leaflet.min.js') }}"></script>
<script src="{{ asset('vendor/leaflet-control-geocoder/Control.Geocoder.js') }}"></script>
<script>
(function(){
    'use strict';
    const donorUrl=@json(route('admin.map.data')),facilityUrl=@json(route('admin.facilities.map-data')),geocoderSearchUrl=@json(route('admin.map.locations.geocoder-search',['location'=>'__LOCATION__'])),coordinatesUrl=@json(route('admin.map.locations.coordinates',['location'=>'__LOCATION__'])),bloodTypes=@json(array_values($bloodTypes)),csrfToken=document.querySelector('meta[name="csrf-token"]')?.content||'';
    const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const blood=document.getElementById('availabilityBloodType'),search=document.getElementById('availabilitySearch'),city=document.getElementById('availabilityCity'),facilityType=document.getElementById('availabilityFacilityType'),sort=document.getElementById('availabilitySort'),body=document.getElementById('availabilityTableBody'),head=document.getElementById('availabilityTableHead'),status=document.getElementById('mapStatus');
    const resolveCentersButton=document.getElementById('resolveBarangayCenters'),geocodeQueueStatus=document.getElementById('geocodeQueueStatus');
    const geocoderAvailable=Boolean(window.L?.Control?.Geocoder?.nominatim);
    let layer='donors',timer=null,map=null,markers=window.L?L.layerGroup():null,facilityRows=[],facilityPage=1,refreshRevision=0,geocodeJobs=[],geocodingActive=false;
    const mapsAllowed=()=>window.eDonatePrivacy?.allowed('maps')===true;
    const delay=milliseconds=>new Promise(resolve=>window.setTimeout(resolve,milliseconds));
    const normalizePlace=value=>String(value??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/^\s*city\s+of\s+/,'').replace(/^\s*(?:barangay|brgy)\.?\s+/,'').replace(/[^a-z0-9]+/g,' ').trim();
    function updateGeocodeControls(){
        const ready=Boolean(geocoderAvailable&&mapsAllowed()&&geocodeJobs.length&&!geocodingActive);
        resolveCentersButton.disabled=!ready;
        if(geocodingActive)return;
        if(!geocoderAvailable)geocodeQueueStatus.textContent='The local geocoder plugin is unavailable.';
        else if(!mapsAllowed())geocodeQueueStatus.textContent='Allow external maps in Privacy choices before sending a barangay/city query.';
        else if(!geocodeJobs.length)geocodeQueueStatus.textContent='No unmapped completed-donor location has both a barangay and city to geocode.';
        else geocodeQueueStatus.textContent=`${geocodeJobs.length} unique barangay center(s) can be resolved; requests run one at a time.`;
    }
    function syncMapConsent(){
        const allowed=window.eDonatePrivacy?.allowed('maps')===true;
        document.getElementById('mapPrivacyNotice').hidden=allowed;
        updateGeocodeControls();
        if(!allowed){if(map){map.remove();map=null;}return;}
        if(!window.L){status.textContent='Map visualization is unavailable. The table remains available.';return;}
        if(map)return;
        map=L.map('availabilityMap',{zoomControl:true}).setView([13.9419,121.1644],12);
        try {
            L.tileLayer(
                'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png?key={{ urlencode(config("services.carto.basemap_key")) }}',
                {
                    subdomains: 'abcd',
                    maxZoom: 20,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
                }
            ).addTo(map).on('tileerror',()=>status.textContent='Map tiles could not be loaded. The table remains available.');
        } catch (_) {
            status.textContent='Map tiles are unavailable. The table remains available.';
        }
        markers.addTo(map);refresh();
    }
    document.addEventListener('edonate:privacy-changed',syncMapConsent);
    document.addEventListener('DOMContentLoaded',syncMapConsent);
    const valid=(lat,lng)=>Number.isFinite(Number(lat))&&Number.isFinite(Number(lng))&&Number(lat)!==0&&Number(lng)!==0;
    const markerIcon=(level,label,approximate=false,donorStatus=false)=>L.divIcon({className:'',html:`<span class="availability-marker availability-marker--${donorStatus?'donor-':''}${esc(level)}${approximate?' availability-marker--approximate':''}">${esc(label)}</span>`,iconSize:[38,38],iconAnchor:[19,19]});
    function query(){const q=new URLSearchParams();if(blood.value)q.set('blood_type',blood.value);if(layer==='donors'){if(search.value.trim())q.set('barangay',search.value.trim());if(city.value.trim())q.set('city',city.value.trim());}else{if(search.value.trim())q.set('search',search.value.trim());if(facilityType.value)q.set('facility_type',facilityType.value);}return q.toString();}
    function summary(labels,values){for(let i=0;i<5;i++){const card=i===4?document.getElementById('summaryCard5'):null;if(card)card.classList.toggle('d-none',!labels[i]);if(labels[i]){document.getElementById(`summaryLabel${i+1}`).textContent=labels[i];document.getElementById(`summaryValue${i+1}`).textContent=values[i]??0;}}}
    const bloodTypeColumns=[...bloodTypes,'Unknown'];
    const confidenceLabel=value=>({confirmed:'Confirmed',unconfirmed:'Unconfirmed',unknown:'Unknown'})[value]||'Unknown';
    const statusText=value=>({available:'Available',temporarily_deferred:'Temporarily deferred',not_available:'Not available'})[value]||'Not available';
    const statusSymbol=value=>({available:'✓',temporarily_deferred:'◷',not_available:'×'})[value]||'×';
    const statusBadge=(value,count)=>'<span class="availability-status availability-status--'+esc(value)+'">'+esc(statusSymbol(value)+' '+statusText(value))+'</span>'+(count===undefined?'':' <strong>'+esc(count)+'</strong>');
    const typeCell=(row,type)=>{
        const detail=row.blood_type_availability?.[type]||{};
        const counts=detail.counts||{};
        const completed=Number(detail.completed_donors||0);
        const quality=row.blood_type_confidence_by_type?.[type]||{};
        const unconfirmed=Number(quality.unconfirmed||0);
        const notes=[`Completed ${completed}`,`Available ${Number(counts.available||0)}`,`Deferred ${Number(counts.temporarily_deferred||0)}`,`Not available ${Number(counts.not_available||0)}`];
        if(unconfirmed)notes.push(`Profile-only ${unconfirmed}`);
        if(detail.earliest_next_eligible_date)notes.push(`Next eligible ${detail.earliest_next_eligible_date}`);
        return '<td class="availability-type-detail">'+statusBadge(detail.status||'not_available')+'<small>'+esc(notes.join(' · '))+'</small></td>';
    };
    function renderDonors(data){
        const summaryData=data.summary||{},quality=data.data_quality||{};
        summary(
            ['Completed Donors','Available Donors','Temporarily Deferred','Barangays With Completed Donors','Unmapped Completed Donors'],
            [
                summaryData.completed_donors??0,
                summaryData.available_donors??0,
                summaryData.temporarily_deferred_donors??0,
                summaryData.barangays??0,
                quality.unmapped_completed_donor_count??0
            ]
        );
        const mapFilterNote=blood.value?` The map marker color is focused on ${blood.value}; the table and totals still show all blood types.`:' Select a blood type to color markers for that group; All blood types uses the best status across the eight groups.';
        document.getElementById('availabilityQuality').textContent=
            'Distinct completed-history donors: '+(summaryData.completed_donors??0)+
            '; currently available: '+(summaryData.available_donors??0)+
            '; temporarily deferred: '+(summaryData.temporarily_deferred_donors??0)+
            '. Barangays with completed-history donors: '+(summaryData.barangays??0)+
            '. Unmapped and listed below: '+(quality.unmapped_completed_donor_count??0)+
            '. Green/yellow availability requires an active account, verified identity, and current eligibility; a future next-eligible date or temporary deferral is yellow. Confirmed donation blood type takes precedence; profile-only types are labelled unconfirmed, and missing types are Unknown. Only completed-history barangays are shown; exact home coordinates and identities are not exposed.'+mapFilterNote;
        head.innerHTML='<tr><th>Barangay</th><th>City</th><th>Completed donors</th>'+
            bloodTypeColumns.map(type=>'<th>'+esc(type)+'</th>').join('')+
            '<th>Map status</th></tr>';
        const rows=data.barangays||[];
        body.innerHTML=rows.length?rows.map(row=>
            '<tr><td><strong>'+esc(row.barangay_name)+'</strong>'+(row.boundary_match_name&&normalizePlace(row.boundary_match_name)!==normalizePlace(row.barangay_name)?'<small class="d-block text-muted">Boundary match: '+esc(row.boundary_match_name)+'</small>':'')+'</td>'+
            '<td>'+esc(row.city)+'</td>'+
            '<td>'+esc(row.completed_history_donors??row.completed_donors??0)+'</td>'+
            bloodTypeColumns.map(type=>typeCell(row,type)).join('')+
            '<td>'+statusBadge(row.availability_level||'not_available')+'</td></tr>'
        ).join(''):'<tr><td colspan="'+(4+bloodTypeColumns.length)+'" class="text-center text-muted py-4">No completed donor data found.</td></tr>';

        const unmapped=data.data_quality?.unmapped_completed_donors||[];
        const unmappedPanel=document.getElementById('unmappedCompletedPanel');
        const unmappedBody=document.getElementById('unmappedCompletedBody');
        unmappedPanel.hidden=unmapped.length===0;
        unmappedBody.innerHTML=unmapped.map(donor=>
            '<tr><td>'+esc(donor.donor_reference)+'</td>'+
            '<td>'+esc(donor.barangay_name)+'</td>'+
            '<td>'+esc(donor.city)+'</td>'+
            '<td>'+esc(donor.blood_type)+'</td>'+
            '<td>'+esc(confidenceLabel(donor.blood_type_confidence))+'</td>'+
            '<td>'+esc((donor.missing_fields||[]).join(' '))+'</td></tr>'
        ).join('');
        const locations=new Map();
        unmapped.forEach(donor=>{
            const locationId=Number(donor.location_id),barangay=String(donor.barangay_name||'').trim(),cityName=String(donor.city||'').trim();
            if(!Number.isInteger(locationId)||locationId<1||!barangay||!cityName||barangay==='Not recorded'||cityName==='Not recorded')return;
            if(!locations.has(locationId))locations.set(locationId,{locationId,barangay,city:cityName});
        });
        geocodeJobs=[...locations.values()];
        updateGeocodeControls();
        renderDonorMarkers(data.map_points||[]);
    }
    function geocoderFor(locationId){
        if(!geocoderAvailable)return null;
        const searchUrl=geocoderSearchUrl.replace('__LOCATION__',encodeURIComponent(locationId));
        return L.Control.Geocoder.nominatim({
            serviceUrl:searchUrl.replace(/search$/,''),
            geocodingQueryParams:{countrycodes:'ph',limit:1,addressdetails:1,format:'jsonv2'}
        });
    }
    async function geocodeQuery(query,geocoder){
        try{return await geocoder.geocode(query);}catch(_){return [];}
    }
    function matchingGeocodeResult(results,job){
        const barangay=normalizePlace(job.barangay),cityName=normalizePlace(job.city);
        return (results||[]).find(result=>{
            const center=result?.center,lat=Number(center?.lat),lng=Number(center?.lng);
            if(!Number.isFinite(lat)||!Number.isFinite(lng)||lat<4||lat>22||lng<116||lng>127)return false;
            const address=result?.properties?.address||{};
            const searchable=normalizePlace([result?.name,...Object.values(address)].join(' '));
            return barangay.length>1&&cityName.length>1&&searchable.includes(barangay)&&searchable.includes(cityName);
        })||null;
    }
    async function resolveBarangayCenters(){
        if(geocodingActive||!geocoderAvailable||!mapsAllowed()||!geocodeJobs.length)return;
        const jobs=[...geocodeJobs];
        geocodingActive=true;
        updateGeocodeControls();
        resolveCentersButton.innerHTML='<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Resolving centers…';
        let saved=0,skipped=0,failed=0;
        for(let index=0;index<jobs.length;index++){
            if(!mapsAllowed()){skipped+=jobs.length-index;break;}
            if(index>0)await delay(1200);
            const job=jobs[index],geocoder=geocoderFor(job.locationId),baseQuery=`${job.barangay}, ${job.city}, Philippines`;
            if(!geocoder){failed++;continue;}
            geocodeQueueStatus.textContent=`Resolving ${index+1} of ${jobs.length}: ${job.barangay}, ${job.city}`;
            let resultQuery=baseQuery;
            let result=matchingGeocodeResult(await geocodeQuery(baseQuery,geocoder),job);
            if(!result&&/^\s*City of\s+/i.test(job.city)){
                const fallbackCity=job.city.replace(/^\s*City of\s+/i,'').trim();
                await delay(1200);
                resultQuery=`${job.barangay}, ${fallbackCity}, Philippines`;
                result=matchingGeocodeResult(await geocodeQuery(resultQuery,geocoder),{...job,city:fallbackCity});
            }
            if(!result){skipped++;continue;}
            const latitude=Number(result.center.lat),longitude=Number(result.center.lng);
            try{
                const response=await fetch(coordinatesUrl.replace('__LOCATION__',encodeURIComponent(job.locationId)),{
                    method:'POST',
                    headers:{Accept:'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrfToken},
                    body:JSON.stringify({latitude,longitude,query:resultQuery})
                });
                const payload=await response.json().catch(()=>({}));
                if(response.status===409){skipped++;continue;}
                if(!response.ok||payload.saved!==true){failed++;continue;}
                saved++;
                if(map&&markers)L.marker([latitude,longitude],{icon:markerIcon('low','•')})
                    .bindPopup(`<strong>${esc(job.barangay)}</strong><br>${esc(job.city)}`).addTo(markers);
            }catch(_){failed++;}
        }
        geocodingActive=false;
        resolveCentersButton.innerHTML='<i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Resolve missing barangay centers';
        await refresh();
        geocodeQueueStatus.textContent=mapsAllowed()
            ?`Finished: ${saved} center(s) saved, ${skipped} unmatched or skipped, ${failed} error(s). Unmatched locations remain listed for correction.`
            :'Stopped because external map permission was withdrawn. No further geocoding queries were sent.';
    }
    function renderDonorMarkers(points){
        if(!map)return;
        markers.clearLayers();
        const bounds=[];
        points.forEach(point=>{
            if(!valid(point.latitude,point.longitude))return;
            const pos=[Number(point.latitude),Number(point.longitude)];
            bounds.push(pos);
            const count=point.completed_history_donors??point.completed_donors??0;
            const statusForType=type=>point.blood_type_availability?.[type]?.status||'not_available';
            let markerStatus='not_available';
            if(blood.value){
                markerStatus=statusForType(blood.value==='Unknown'?'Unknown':blood.value);
            }else{
                const statuses=bloodTypes.map(statusForType);
                markerStatus=statuses.includes('available')?'available':statuses.includes('temporarily_deferred')?'temporarily_deferred':'not_available';
            }
            const typeDetails=bloodTypeColumns.map(type=>{
                const detail=point.blood_type_availability?.[type]||{};
                const counts=detail.counts||{};
                const confidence=point.blood_type_confidence_by_type?.[type]||{};
                const date=detail.earliest_next_eligible_date?'<small>Earliest next eligible: '+esc(detail.earliest_next_eligible_date)+'</small>':'';
                return '<div class="availability-popup-type"><strong>'+esc(type)+'</strong> '+statusBadge(detail.status||'not_available')+
                    '<small>Completed: '+esc(detail.completed_donors||0)+' · Available: '+esc(counts.available||0)+' · Temporarily deferred: '+esc(counts.temporarily_deferred||0)+' · Not available: '+esc(counts.not_available||0)+'</small>'+
                    '<small>Type source: '+esc(confidence.confirmed||0)+' confirmed, '+esc(confidence.unconfirmed||0)+' profile-only, '+esc(confidence.unknown||0)+' unknown</small>'+date+'</div>';
            }).join('');
            const boundaryNote=point.boundary_match_name&&normalizePlace(point.boundary_match_name)!==normalizePlace(point.barangay_name)?'<div><small>Boundary dataset match: '+esc(point.boundary_match_name)+'</small></div>':'';
            const markerMeaning=blood.value?`Status for ${blood.value}`:'Best status across the eight blood types';
            const popup='<strong>'+esc(point.barangay_name)+'</strong><br>'+esc(point.city)+boundaryNote+
                '<hr class="my-1"><div>'+esc(markerMeaning)+': '+statusBadge(markerStatus)+'</div>'+
                '<div>Distinct completed-history donors: <strong>'+esc(count)+'</strong></div>'+
                '<hr class="my-1"><div class="availability-popup-types">'+typeDetails+'</div>';
            const markerTitle=point.barangay_name+': '+markerMeaning+' — '+statusText(markerStatus);
            L.marker(pos,{icon:markerIcon(markerStatus,statusSymbol(markerStatus),false,true),title:markerTitle,alt:markerTitle})
                .bindPopup(popup).addTo(markers);
        });
        fit(bounds);
        status.textContent=points.length?`${points.length} aggregate barangay marker(s). Markers show status text/icons as well as color.`:'No mapped barangay coordinates match the filters.';
    }
    function renderFacilities(data){
        document.getElementById('unmappedCompletedPanel').hidden=true;
        const s=data.summary||{},selected=blood.value,approximate=Number(s.approximate_mapped_facilities||0),unmapped=Number(s.unmapped_facilities||0);
        summary(selected?['Active Facilities',`Total ${selected} Units`,`${selected} Available`,`Low ${selected}`,`Out of ${selected}`]:['Total Facilities','Mapped Facilities','Total Blood Units','Low Stock Facilities','Out-of-Stock Facilities'],selected?[s.facilities,s.total_units,s.facilities_with_available,s.facilities_with_low_stock,s.facilities_with_out_of_stock]:[s.facilities,s.mapped_facilities,s.total_units,s.facilities_with_low_stock,s.facilities_with_out_of_stock]);
        document.getElementById('availabilityQuality').textContent=`${data.freshness_notice||'Inventory reflects the latest recorded update.'} Mapped facilities: ${s.mapped_facilities??0}; approximate barangay centers: ${approximate}; still unmapped: ${unmapped}.`;
        head.innerHTML=`<tr><th>Facility</th><th>Type</th><th>Location</th>${bloodTypes.map(type=>`<th>${esc(type)}</th>`).join('')}<th>Last Updated</th></tr>`;
        facilityRows=data.facilities||[];facilityPage=1;renderFacilityTable();renderFacilityMarkers(data.map_points||[]);
    }
    function rowUnits(row){return blood.value?Number(row.blood_types?.[blood.value]?.units||0):Object.values(row.blood_types||{}).reduce((sum,item)=>sum+Number(item.units||0),0);}
    function facilityMapNote(row){
        if(row.map_location_source==='barangay_center')return `<br><small class="text-warning-emphasis">Approximate barangay center: ${esc(row.matched_barangay_name||row.barangay_name||'matched area')}</small>`;
        return row.mapped?'':'<br><small class="text-muted">Location not mapped</small>';
    }
    function renderFacilityTable(){let rows=[...facilityRows];if(sort.value==='units_desc')rows.sort((a,b)=>rowUnits(b)-rowUnits(a));else if(sort.value==='units_asc')rows.sort((a,b)=>rowUnits(a)-rowUnits(b));else if(sort.value==='updated_desc')rows.sort((a,b)=>new Date(b.last_updated||0)-new Date(a.last_updated||0));else rows.sort((a,b)=>a.facility_name.localeCompare(b.facility_name));const perPage=10,last=Math.max(1,Math.ceil(rows.length/perPage));facilityPage=Math.min(facilityPage,last);const pageRows=rows.slice((facilityPage-1)*perPage,facilityPage*perPage);body.innerHTML=pageRows.length?pageRows.map(row=>`<tr><td><strong>${esc(row.facility_name)}</strong><div class="mt-1">${window.eDonateFacilityStatus(row.status)}</div></td><td>${esc(String(row.facility_type).replaceAll('_',' '))}</td><td>${esc([row.barangay_name,row.city,row.province].filter(Boolean).join(', '))}${facilityMapNote(row)}</td>${bloodTypes.map(type=>{const item=row.blood_types?.[type]||{units:0,status:'out_of_stock'};return `<td><span class="availability-badge availability-badge--${esc(item.status)}">${esc(item.units)}</span></td>`;}).join('')}<td>${row.last_updated?esc(new Date(row.last_updated).toLocaleString()):'Never'}</td></tr>`).join(''):`<tr><td colspan="${4+bloodTypes.length}" class="text-center text-muted py-4">No active facilities match the filters.</td></tr>`;const meta={current_page:facilityPage,last_page:last,per_page:perPage,total:rows.length,from:rows.length?((facilityPage-1)*perPage)+1:0,to:rows.length?Math.min(rows.length,facilityPage*perPage):0};const pagination=document.getElementById('facilityPagination');pagination.classList.toggle('d-none',layer!=='facilities');if(window.eDonateAdminPagination)window.eDonateAdminPagination.render(document.getElementById('facilityPaginationLinks'),meta,nextPage=>{facilityPage=nextPage;renderFacilityTable();},{infoElement:document.getElementById('facilityPageMeta')});}
    function renderFacilityMarkers(points){
        if(!map)return;markers.clearLayers();const bounds=[];let approximateCount=0;
        points.forEach(point=>{
            if(!valid(point.latitude,point.longitude))return;
            const pos=[Number(point.latitude),Number(point.longitude)];bounds.push(pos);const units=rowUnits(point),approximate=point.map_location_source==='barangay_center';
            if(approximate)approximateCount++;
            const locationNote=approximate?`<br><em>Approximate barangay center from Faeldon 2019 boundary data; this is not the facility's exact pin.</em>`:'';
            L.marker(pos,{icon:markerIcon(units>0?'available':'out_of_stock',units,approximate)}).bindPopup(`<strong>${esc(point.facility_name)}</strong><br>${window.eDonateFacilityStatus(point.status)}<br>${esc([point.address,point.barangay_name,point.city,point.province].filter(Boolean).join(', '))}${locationNote}`).addTo(markers);
        });
        fit(bounds);
        status.textContent=bounds.length?`${bounds.length} facility marker(s). ${approximateCount?`${approximateCount} use approximate barangay centers.`:''}`:'No mapped facilities match the filters.';
    }
    function fit(bounds){if(bounds.length)map.fitBounds(bounds,{padding:[24,24],maxZoom:14});}
    async function refresh(){const revision=++refreshRevision,requestedLayer=layer;body.innerHTML='<tr><td class="text-center text-muted py-4">Loading...</td></tr>';try{const response=await fetch(`${layer==='donors'?donorUrl:facilityUrl}${query()?'?'+query():''}`,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});if(!response.ok)throw new Error();const data=await response.json();if(revision!==refreshRevision||requestedLayer!==layer)return;layer==='donors'?renderDonors(data):renderFacilities(data);}catch(e){body.innerHTML='<tr><td class="text-center text-danger py-4">Could not load availability data.</td></tr>';status.textContent='The map request failed. Please try again.';}}
    function setLayer(next){
        layer=next;
        if(next==='facilities'&&blood.value==='Unknown')blood.value='';
        markers?.clearLayers();
        document.getElementById('donorLayer').classList.toggle('active',next==='donors');
        document.getElementById('facilityLayer').classList.toggle('active',next==='facilities');
        document.getElementById('donorLayer').setAttribute('aria-pressed',next==='donors');
        document.getElementById('facilityLayer').setAttribute('aria-pressed',next==='facilities');
        document.getElementById('availabilitySearchLabel').textContent=next==='donors'?'Barangay search':'Facility or location';
        search.placeholder=next==='donors'?'Search barangay':'Search facility or location';
        document.getElementById('availabilityScopeLabel').textContent=next==='donors'?'City filter':'Facility type';
        document.getElementById('availabilityScopeLabel').htmlFor=next==='donors'?'availabilityCity':'availabilityFacilityType';
        city.classList.toggle('d-none',next!=='donors');
        facilityType.classList.toggle('d-none',next==='donors');
        document.getElementById('availabilitySortWrap').classList.toggle('d-none',next==='donors');
        document.getElementById('facilityPagination').classList.toggle('d-none',next!=='facilities');
        document.getElementById('unmappedCompletedPanel').hidden=true;
        document.getElementById('mapTitle').textContent=next==='donors'?'Blood-Type Donor Availability':'Facility Blood Inventory';
        document.getElementById('mapHint').textContent=next==='donors'?'One aggregate marker represents one barangay with completed donation history. This is not physical blood-bank inventory. With a selected type, its status controls the marker; All blood types uses the best status across the eight groups.':'One marker represents one active facility; if no facility pin is saved, its barangay center is used as an approximate location.';
        document.getElementById('mapLegend').innerHTML=next==='donors'
            ?'<div class="availability-legend-items"><span class="availability-legend-item"><span class="availability-legend-swatch availability-legend-swatch--available" aria-hidden="true">✓</span>Green: Available</span><span class="availability-legend-item"><span class="availability-legend-swatch availability-legend-swatch--temporarily_deferred" aria-hidden="true">◷</span>Yellow: Temporarily deferred</span><span class="availability-legend-item"><span class="availability-legend-swatch availability-legend-swatch--not_available" aria-hidden="true">×</span>Red: Not available</span></div>'
            :'Legend: marker values are recorded inventory units; dashed markers use approximate Faeldon 2019 barangay centers; green is available, amber is low, and gray is out of stock.';
        document.getElementById('tableTitle').textContent=next==='donors'?'Barangay Blood-Type Availability':'Facility Inventory';
        search.value='';
        city.value='';
        facilityType.value='';
        history.replaceState(null,'',next==='facilities'?'?layer=facilities':location.pathname);
        document.getElementById('availabilityMap').setAttribute('aria-label',next==='donors'?'Optional blood-type donor availability map; marker icon and color show status, with all status counts in the popup and table below':'Optional facility inventory map; equivalent information is in the table below');
        setTimeout(()=>map?.invalidateSize(),0);
        syncMapConsent();
        refresh();
    }
    resolveCentersButton.addEventListener('click',resolveBarangayCenters);
    document.getElementById('donorLayer').onclick=()=>setLayer('donors');document.getElementById('facilityLayer').onclick=()=>setLayer('facilities');blood.onchange=refresh;[search,city].forEach(input=>input.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(refresh,300);}));facilityType.onchange=refresh;sort.onchange=()=>{facilityPage=1;renderFacilityTable();};document.getElementById('clearAvailabilityFilters').onclick=()=>{blood.value='';search.value='';city.value='';facilityType.value='';sort.value='name';refresh();};
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
    window.addEventListener('pageshow',()=>{if(!document.hidden)refresh();});
    window.setInterval(()=>{if(!document.hidden)refresh();},30000);
    setLayer(new URLSearchParams(location.search).get('layer')==='facilities'?'facilities':'donors');
}());
</script>
@endpush
