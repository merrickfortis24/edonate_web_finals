@extends('layouts.admin')

@section('title', 'eDonate - Blood Availability Map')
@section('admin_page_class', 'admin-blood-availability-page')
@section('header_title', 'Blood Availability Map')
@section('header_subtitle', 'Completed donor coverage and recorded facility inventory')

@push('admin_head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.min.css') }}">
<style>
    .availability-content{padding:24px 32px 40px}.availability-tabs{display:inline-flex;border:1px solid var(--bs-border-color);border-radius:8px;overflow:hidden;margin-bottom:18px}.availability-tab{background:var(--edonate-card-bg);border:0;border-right:1px solid var(--bs-border-color);color:var(--bs-body-color);font-weight:600;padding:10px 16px}.availability-tab:last-child{border-right:0}.availability-tab.active{background:#9f1010;color:#fff}.availability-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:18px}.availability-stat,.availability-panel{background:var(--edonate-card-bg);border:1px solid var(--bs-border-color);border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);color:var(--bs-body-color)}.availability-stat{min-height:100px;padding:16px}.availability-stat__label{color:var(--bs-secondary-color);font-size:12px;font-weight:600}.availability-stat__value{color:#9f1010;font-size:26px;font-weight:700;margin-top:7px}.availability-panel{margin-bottom:18px;padding:18px}.availability-filter-row{align-items:end;display:grid;gap:12px;grid-template-columns:180px minmax(200px,1fr) 180px 180px auto}.availability-filter-row label{display:block;font-size:12px;font-weight:600;margin-bottom:5px}.availability-quality,.availability-panel__hint,.availability-legend{color:var(--bs-secondary-color);font-size:12px;margin:10px 0 0}.availability-panel__title{color:var(--c-primary-dark);font-size:18px;font-weight:700;margin:0 0 4px}#availabilityMap{background:var(--bs-tertiary-bg);border-radius:8px;height:500px;min-height:360px;margin-top:14px}.availability-table-wrap{overflow-x:auto}.availability-table{margin:0;min-width:980px}.availability-table td,.availability-table th{font-size:12px;vertical-align:middle;white-space:nowrap}.availability-badge{border-radius:999px;display:inline-block;font-size:11px;font-weight:700;padding:4px 8px}.availability-badge--high,.availability-badge--available{background:#dff3e4;color:#17642c}.availability-badge--moderate,.availability-badge--low{background:#fff0bd;color:#725500}.availability-badge--none,.availability-badge--out_of_stock{background:#f3d8d8;color:#851616}.availability-marker{align-items:center;background:#a90f0f;border:3px solid #fff;border-radius:50%;box-shadow:0 1px 5px rgba(0,0,0,.35);color:#fff;display:flex;font-size:11px;font-weight:700;height:38px;justify-content:center;width:38px}.availability-marker--moderate,.availability-marker--low{background:#bd8300}.availability-marker--none,.availability-marker--out_of_stock{background:#777}@media(max-width:1100px){.availability-summary{grid-template-columns:repeat(3,1fr)}.availability-filter-row{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.availability-content{padding:16px}.availability-summary,.availability-filter-row{grid-template-columns:1fr}#availabilityMap{height:420px}.availability-tabs{display:flex}.availability-tab{flex:1}}
</style>
@endpush

@section('main_content')
<main class="availability-content">
    <div class="availability-tabs" role="group" aria-label="Availability map layer"><button class="availability-tab active" id="donorLayer" aria-pressed="true" type="button">Donor Availability</button><button class="availability-tab" id="facilityLayer" aria-pressed="false" type="button">Facility Inventory</button></div>
    <section class="availability-summary" aria-label="Availability summary">
        <div class="availability-stat"><div class="availability-stat__label" id="summaryLabel1">Completed Donors</div><div class="availability-stat__value" id="summaryValue1">-</div></div>
        <div class="availability-stat"><div class="availability-stat__label" id="summaryLabel2">Mapped Completed Donors</div><div class="availability-stat__value" id="summaryValue2">-</div></div>
        <div class="availability-stat"><div class="availability-stat__label" id="summaryLabel3">Barangays With Donors</div><div class="availability-stat__value" id="summaryValue3">-</div></div>
        <div class="availability-stat"><div class="availability-stat__label" id="summaryLabel4">Most Common Type</div><div class="availability-stat__value" id="summaryValue4">-</div></div>
        <div class="availability-stat" id="summaryCard5"><div class="availability-stat__label" id="summaryLabel5">Unmapped Completed Donors</div><div class="availability-stat__value" id="summaryValue5">-</div></div>
    </section>
    <section class="availability-panel" aria-label="Availability filters"><div class="availability-filter-row">
        <div><label for="availabilityBloodType">Blood type</label><select class="form-select" id="availabilityBloodType"><option value="">All blood types</option>@foreach($bloodTypes as $bloodType)<option value="{{ $bloodType }}">{{ $bloodType }}</option>@endforeach<option value="Unknown">Unknown</option></select></div>
        <div><label for="availabilitySearch" id="availabilitySearchLabel">Barangay search</label><input class="form-control" id="availabilitySearch" type="search" maxlength="150" placeholder="Search barangay"></div>
        <div><label for="availabilityCity" id="availabilityScopeLabel">City filter</label><input class="form-control" id="availabilityCity" type="search" maxlength="150" placeholder="Any city"><select class="form-select d-none" id="availabilityFacilityType"><option value="">All facility types</option>@foreach($facilityTypes as $type)<option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>@endforeach</select></div>
        <div id="availabilitySortWrap" class="d-none"><label for="availabilitySort">Sort facilities</label><select class="form-select" id="availabilitySort"><option value="name">Facility name</option><option value="units_desc">Units: high to low</option><option value="units_asc">Units: low to high</option><option value="updated_desc">Recently updated</option></select></div>
        <button class="btn btn-outline-secondary" id="clearAvailabilityFilters" type="button">Clear</button>
    </div><p class="availability-quality" id="availabilityQuality">Every distinct donor with a completed donation is counted, regardless of current eligibility or blood-type verification. Laboratory-confirmed types take precedence; otherwise profile types are labelled unconfirmed and missing types are shown as Unknown. Donor identities and exact home coordinates are not exposed.</p></section>
    <section class="availability-panel"><h2 class="availability-panel__title" id="mapTitle">Completed Donor Coverage</h2><p class="availability-panel__hint" id="mapHint">One aggregate marker represents one barangay. This is not physical blood-bank inventory.</p><p class="availability-legend" id="mapLegend">Legend: marker values are distinct donors with completed donation history.</p><p id="mapPrivacyNotice">External map tiles are blocked until you allow maps in <button type="button" class="btn btn-outline-secondary" data-privacy-open>Privacy choices</button>. The table below remains available.</p><div id="availabilityMap" role="region" aria-label="Optional completed-donor coverage map; equivalent information is in the table below"></div><p class="text-muted small mt-2 mb-0" id="mapStatus" aria-live="polite"></p></section>
    <section class="availability-panel"><h2 class="availability-panel__title" id="tableTitle">Barangay Completed Donors</h2><div class="availability-table-wrap" role="region" aria-label="Blood availability table, scroll horizontally if needed" tabindex="0"><table class="table table-hover availability-table"><thead id="availabilityTableHead"></thead><tbody id="availabilityTableBody"></tbody></table></div><div class="admin-pagination admin-pagination--js d-none" id="facilityPagination" aria-label="Table pagination"><span class="admin-pagination__info" id="facilityPageMeta">Showing 0 to 0 of 0 entries</span><nav class="admin-pagination__links" id="facilityPaginationLinks" aria-label="Pagination links"></nav></div></section>
    <section class="availability-panel" id="unmappedCompletedPanel" hidden aria-labelledby="unmappedCompletedTitle"><h2 class="availability-panel__title" id="unmappedCompletedTitle">Unmapped completed donors</h2><p class="availability-panel__hint">These completed donors remain in the total, but their registered barangay or a usable barangay center needs correction. Only donor references and missing fields are shown.</p><div class="availability-table-wrap" role="region" aria-label="Unmapped completed donors" tabindex="0"><table class="table table-hover availability-table"><thead><tr><th>Donor reference</th><th>Barangay</th><th>City</th><th>Blood type</th><th>Type status</th><th>Missing field</th></tr></thead><tbody id="unmappedCompletedBody"></tbody></table></div></section>
</main>
@endsection

@push('admin_scripts')
@include('admin.partials.facility-status-script')
<script src="{{ asset('vendor/leaflet/leaflet.min.js') }}"></script>
<script>
(function(){
    'use strict';
    const donorUrl=@json(route('admin.map.data')),facilityUrl=@json(route('admin.facilities.map-data')),bloodTypes=@json(array_values($bloodTypes));
    const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const blood=document.getElementById('availabilityBloodType'),search=document.getElementById('availabilitySearch'),city=document.getElementById('availabilityCity'),facilityType=document.getElementById('availabilityFacilityType'),sort=document.getElementById('availabilitySort'),body=document.getElementById('availabilityTableBody'),head=document.getElementById('availabilityTableHead'),status=document.getElementById('mapStatus');
    let layer='donors',timer=null,map=null,markers=window.L?L.layerGroup():null,facilityRows=[],facilityPage=1,refreshRevision=0;
    function syncMapConsent(){
        const allowed=window.eDonatePrivacy?.allowed('maps')===true;
        document.getElementById('mapPrivacyNotice').hidden=allowed;
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
    const markerIcon=(level,label)=>L.divIcon({className:'',html:`<span class="availability-marker availability-marker--${esc(level)}">${esc(label)}</span>`,iconSize:[38,38],iconAnchor:[19,19]});
    function query(){const q=new URLSearchParams();if(blood.value)q.set('blood_type',blood.value);if(layer==='donors'){if(search.value.trim())q.set('barangay',search.value.trim());if(city.value.trim())q.set('city',city.value.trim());}else{if(search.value.trim())q.set('search',search.value.trim());if(facilityType.value)q.set('facility_type',facilityType.value);}return q.toString();}
    function summary(labels,values){for(let i=0;i<5;i++){const card=i===4?document.getElementById('summaryCard5'):null;if(card)card.classList.toggle('d-none',!labels[i]);if(labels[i]){document.getElementById(`summaryLabel${i+1}`).textContent=labels[i];document.getElementById(`summaryValue${i+1}`).textContent=values[i]??0;}}}
    const bloodTypeColumns=[...bloodTypes,'Unknown'];
    const typeCell=(row,type)=>{
        const count=Number(row.blood_types?.[type]||0),quality=row.blood_type_confidence_by_type?.[type]||{};
        const notes=[];
        if(Number(quality.unconfirmed||0))notes.push(`${Number(quality.unconfirmed)} Unconfirmed`);
        if(Number(quality.unknown||0))notes.push(`${Number(quality.unknown)} Unknown type`);
        return '<td>'+esc(count)+(notes.length?'<small class="d-block text-muted">'+esc(notes.join(' · '))+'</small>':'')+'</td>';
    };
    const confidenceLabel=value=>({confirmed:'Confirmed',unconfirmed:'Unconfirmed',unknown:'Unknown'})[value]||'Unknown';
    function renderDonors(data){
        const summaryData=data.summary||{},quality=data.data_quality||{};
        const formatType=item=>item&&item.blood_type?item.blood_type+' ('+item.count+')':'-';
        summary(
            ['Completed Donors','Mapped Completed Donors','Barangays With Donors','Most Common Type','Unmapped Completed Donors'],
            [
                summaryData.completed_donors??summaryData.available_donors??0,
                summaryData.mapped_completed_donors??quality.mapped_completed_donors??0,
                summaryData.barangays??0,
                formatType(summaryData.most_common_blood_type??summaryData.most_available_blood_type),
                quality.unmapped_completed_donor_count??0
            ]
        );
        document.getElementById('availabilityQuality').textContent=
            'Completed donors: '+(summaryData.completed_donors??0)+
            '. Mapped at barangay level: '+(quality.mapped_completed_donors??0)+
            '. Unmapped and listed below: '+(quality.unmapped_completed_donor_count??0)+
            '. Completed history is independent of current eligibility or waiting periods. Confirmed types take precedence; profile-only types are unconfirmed and missing types are Unknown. Exact home coordinates and personal identities are not exposed.';
        head.innerHTML='<tr><th>Barangay</th><th>City</th><th>Completed donors</th><th>Scheduled</th>'+
            bloodTypeColumns.map(type=>'<th>'+esc(type)+'</th>').join('')+
            '<th>Unconfirmed</th><th>Level</th></tr>';
        const rows=data.barangays||[];
        body.innerHTML=rows.length?rows.map(row=>
            '<tr><td><strong>'+esc(row.barangay_name)+'</strong></td>'+
            '<td>'+esc(row.city)+'</td>'+
            '<td>'+esc(row.completed_donors??row.available_donors??0)+'</td>'+
            '<td>'+esc(row.scheduled_donors??0)+'</td>'+
            bloodTypeColumns.map(type=>typeCell(row,type)).join('')+
            '<td>'+esc(row.blood_type_confidence?.unconfirmed??0)+'</td>'+
            '<td><span class="availability-badge availability-badge--'+esc(row.availability_level)+'">'+esc(row.availability_level)+'</span></td></tr>'
        ).join(''):'<tr><td colspan="'+(6+bloodTypeColumns.length)+'" class="text-center text-muted py-4">No completed donor data found.</td></tr>';

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
        renderDonorMarkers(data.map_points||[]);
    }
    function renderDonorMarkers(points){
        if(!map)return;
        markers.clearLayers();
        const bounds=[];
        points.forEach(point=>{
            if(!valid(point.latitude,point.longitude))return;
            const pos=[Number(point.latitude),Number(point.longitude)];
            bounds.push(pos);
            const count=point.completed_donors??point.available_donors??0;
            const types=bloodTypeColumns.map(type=>{
                const count=Number(point.blood_types?.[type]||0),quality=point.blood_type_confidence_by_type?.[type]||{};
                const unconfirmed=Number(quality.unconfirmed||0),unknown=Number(quality.unknown||0);
                const qualifier=unconfirmed?` <small>(${unconfirmed} Unconfirmed)</small>`:unknown?` <small>(${unknown} Unknown type)</small>`:'';
                return '<div>'+esc(type)+': <strong>'+esc(count)+'</strong>'+qualifier+'</div>';
            }).join('');
            const confidence=point.blood_type_confidence||{};
            const popup='<strong>'+esc(point.barangay_name)+'</strong><br>'+esc(point.city)+
                '<hr class="my-1"><div>Completed donors: <strong>'+esc(count)+'</strong></div>'+
                '<div>Scheduled: <strong>'+esc(point.scheduled_donors??0)+'</strong></div>'+
                '<div>Unconfirmed type: <strong>'+esc(confidence.unconfirmed??0)+'</strong></div>'+
                '<div>Unknown type: <strong>'+esc(confidence.unknown??0)+'</strong></div>'+types;
            L.marker(pos,{icon:markerIcon(point.availability_level,count)}).bindPopup(popup).addTo(markers);
        });
        fit(bounds);
        status.textContent=points.length?points.length+' aggregate barangay marker(s).':'No mapped barangay coordinates match the filters.';
    }
    function renderFacilities(data){document.getElementById('unmappedCompletedPanel').hidden=true;const s=data.summary||{},selected=blood.value;summary(selected?['Active Facilities',`Total ${selected} Units`,`${selected} Available`,`Low ${selected}`,`Out of ${selected}`]:['Total Facilities','Mapped Facilities','Total Blood Units','Low Stock Facilities','Out-of-Stock Facilities'],selected?[s.facilities,s.total_units,s.facilities_with_available,s.facilities_with_low_stock,s.facilities_with_out_of_stock]:[s.facilities,s.mapped_facilities,s.total_units,s.facilities_with_low_stock,s.facilities_with_out_of_stock]);document.getElementById('availabilityQuality').textContent=`${data.freshness_notice||'Inventory reflects the latest recorded update.'} Mapped facilities: ${s.mapped_facilities??0}.`;head.innerHTML=`<tr><th>Facility</th><th>Type</th><th>Location</th>${bloodTypes.map(type=>`<th>${esc(type)}</th>`).join('')}<th>Last Updated</th></tr>`;facilityRows=data.facilities||[];facilityPage=1;renderFacilityTable();renderFacilityMarkers(data.map_points||[]);}
    function rowUnits(row){return blood.value?Number(row.blood_types?.[blood.value]?.units||0):Object.values(row.blood_types||{}).reduce((sum,item)=>sum+Number(item.units||0),0);}
    function renderFacilityTable(){let rows=[...facilityRows];if(sort.value==='units_desc')rows.sort((a,b)=>rowUnits(b)-rowUnits(a));else if(sort.value==='units_asc')rows.sort((a,b)=>rowUnits(a)-rowUnits(b));else if(sort.value==='updated_desc')rows.sort((a,b)=>new Date(b.last_updated||0)-new Date(a.last_updated||0));else rows.sort((a,b)=>a.facility_name.localeCompare(b.facility_name));const perPage=10,last=Math.max(1,Math.ceil(rows.length/perPage));facilityPage=Math.min(facilityPage,last);const pageRows=rows.slice((facilityPage-1)*perPage,facilityPage*perPage);body.innerHTML=pageRows.length?pageRows.map(row=>`<tr><td><strong>${esc(row.facility_name)}</strong><div class="mt-1">${window.eDonateFacilityStatus(row.status)}</div></td><td>${esc(String(row.facility_type).replaceAll('_',' '))}</td><td>${esc([row.barangay_name,row.city,row.province].filter(Boolean).join(', '))}${row.mapped?'':'<br><small class="text-muted">Location not mapped</small>'}</td>${bloodTypes.map(type=>{const item=row.blood_types?.[type]||{units:0,status:'out_of_stock'};return `<td><span class="availability-badge availability-badge--${esc(item.status)}">${esc(item.units)}</span></td>`;}).join('')}<td>${row.last_updated?esc(new Date(row.last_updated).toLocaleString()):'Never'}</td></tr>`).join(''):`<tr><td colspan="${4+bloodTypes.length}" class="text-center text-muted py-4">No active facilities match the filters.</td></tr>`;const meta={current_page:facilityPage,last_page:last,per_page:perPage,total:rows.length,from:rows.length?((facilityPage-1)*perPage)+1:0,to:rows.length?Math.min(rows.length,facilityPage*perPage):0};const pagination=document.getElementById('facilityPagination');pagination.classList.toggle('d-none',layer!=='facilities');if(window.eDonateAdminPagination)window.eDonateAdminPagination.render(document.getElementById('facilityPaginationLinks'),meta,nextPage=>{facilityPage=nextPage;renderFacilityTable();},{infoElement:document.getElementById('facilityPageMeta')});}
    function renderFacilityMarkers(points){
        if(!map)return;markers.clearLayers();const bounds=[];points.forEach(point=>{if(!valid(point.latitude,point.longitude))return;const pos=[Number(point.latitude),Number(point.longitude)];bounds.push(pos);const units=rowUnits(point);L.marker(pos,{icon:markerIcon(units>0?'available':'out_of_stock',units)}).bindPopup(`<strong>${esc(point.facility_name)}</strong><br>${window.eDonateFacilityStatus(point.status)}<br>${esc([point.address,point.barangay_name,point.city,point.province].filter(Boolean).join(', '))}`).addTo(markers);});fit(bounds);
        status.textContent=points.length?`${points.length} facility marker(s).`:'No mapped facilities match the filters.';
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
        document.getElementById('mapTitle').textContent=next==='donors'?'Completed Donor Coverage':'Facility Blood Inventory';
        document.getElementById('mapHint').textContent=next==='donors'?'One aggregate marker represents one barangay. This is not physical blood-bank inventory.':'One marker represents one active facility and its latest recorded stock.';
        document.getElementById('mapLegend').textContent=next==='donors'?'Legend: marker values are distinct donors with completed donation history.':'Legend: marker values are recorded inventory units; green is available, amber is low, and gray is out of stock.';
        document.getElementById('tableTitle').textContent=next==='donors'?'Barangay Completed Donors':'Facility Inventory';
        search.value='';
        city.value='';
        facilityType.value='';
        history.replaceState(null,'',next==='facilities'?'?layer=facilities':location.pathname);
        document.getElementById('availabilityMap').setAttribute('aria-label',next==='donors'?'Optional completed-donor coverage map; equivalent information is in the table below':'Optional facility inventory map; equivalent information is in the table below');
        setTimeout(()=>map?.invalidateSize(),0);
        syncMapConsent();
        refresh();
    }
    document.getElementById('donorLayer').onclick=()=>setLayer('donors');document.getElementById('facilityLayer').onclick=()=>setLayer('facilities');blood.onchange=refresh;[search,city].forEach(input=>input.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(refresh,300);}));facilityType.onchange=refresh;sort.onchange=()=>{facilityPage=1;renderFacilityTable();};document.getElementById('clearAvailabilityFilters').onclick=()=>{blood.value='';search.value='';city.value='';facilityType.value='';sort.value='name';refresh();};
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
    window.addEventListener('pageshow',()=>{if(!document.hidden)refresh();});
    window.setInterval(()=>{if(!document.hidden)refresh();},30000);
    setLayer(new URLSearchParams(location.search).get('layer')==='facilities'?'facilities':'donors');
}());
</script>
@endpush
