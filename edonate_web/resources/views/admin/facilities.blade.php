@extends('layouts.admin')

@section('title', 'eDonate - Facility Management')
@section('admin_page_class', 'admin-facilities-page')
@section('header_title', 'Facility Management')
@section('header_subtitle', 'Manage hospitals, clinics, blood banks, and health centers')

@section('header_actions')
@if($canManage)<button class="btn btn-danger" type="button" id="createFacilityButton">Create Facility</button>@endif
@endsection

@section('main_content')
<main class="facility-content">
    <section class="facility-summary" aria-label="Facility summary">
        <div class="facility-stat"><span>Total Facilities</span><strong id="facilityTotal">-</strong></div>
        <div class="facility-stat"><span>Active</span><strong id="facilityActive">-</strong></div>
        <div class="facility-stat"><span>Inactive</span><strong id="facilityInactive">-</strong></div>
        <div class="facility-stat"><span>Mapped</span><strong id="facilityMapped">-</strong></div>
    </section>
    <section class="facility-panel">
        <div class="facility-toolbar">
            <div><label for="facilitySearch">Search</label><input class="form-control" id="facilitySearch" type="search" maxlength="150" placeholder="Facility or location"></div>
            <div><label for="facilityTypeFilter">Facility type</label><select class="form-select" id="facilityTypeFilter"><option value="">All types</option>@foreach($facilityTypes as $type)<option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>@endforeach</select></div>
            <div><label for="facilityStatusFilter">Status</label><select class="form-select" id="facilityStatusFilter"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <button class="btn btn-outline-secondary" id="facilityClear" type="button">Clear</button>
        </div>
        <div class="facility-table-wrap">
            <table class="table table-hover facility-table"><thead><tr><th>Facility</th><th>Type</th><th>Location</th><th>Status</th><th>Available Units</th><th>Low / Out Types</th><th>Map</th><th>Actions</th></tr></thead><tbody id="facilityRows"><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody></table>
        </div>
        <div class="d-flex justify-content-between align-items-center gap-3"><small class="text-muted" id="facilityMeta"></small><div class="btn-group"><button class="btn btn-sm btn-outline-secondary" id="facilityPrev" type="button">Previous</button><button class="btn btn-sm btn-outline-secondary" id="facilityNext" type="button">Next</button></div></div>
    </section>
</main>

@if($canManage)
<div class="modal fade" id="facilityModal" tabindex="-1" aria-labelledby="facilityModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" id="facilityForm"><div class="modal-header"><h2 class="modal-title fs-5" id="facilityModalTitle">Create Facility</h2><button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button></div><div class="modal-body"><div class="row g-3">
    <div class="col-md-8"><label class="form-label">Facility name</label><input class="form-control" name="facility_name" maxlength="150" required></div>
    <div class="col-md-4"><label class="form-label">Type</label><select class="form-select" name="facility_type" required>@foreach($facilityTypes as $type)<option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>@endforeach</select></div>
    <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"></textarea></div>
    <div class="col-md-4"><label class="form-label">Barangay</label><input class="form-control" name="barangay_name" maxlength="100"></div>
    <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" maxlength="100" value="Lipa City" required></div>
    <div class="col-md-4"><label class="form-label">Province</label><input class="form-control" name="province" maxlength="100" value="Batangas" required></div>
    <div class="col-md-4"><label class="form-label">Latitude</label><input class="form-control" name="latitude" type="number" step="any" min="-90" max="90"></div>
    <div class="col-md-4"><label class="form-label">Longitude</label><input class="form-control" name="longitude" type="number" step="any" min="-180" max="180"></div>
    <div class="col-md-4"><label class="form-label">Contact number</label><input class="form-control" name="contact_number" maxlength="30"></div>
    <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    <div class="col-12"><div class="alert alert-danger d-none mb-0" id="facilityErrors"></div></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button><button class="btn btn-danger" type="submit" id="facilitySave">Save Facility</button></div></form></div></div>
@endif
@endsection

@push('admin_scripts')
<script>
(function(){
    'use strict';
    const urls={data:@json(route('admin.facilities.data')),store:@json(route('admin.facilities.store'))};
    const canManage=@json($canManage); const csrf=document.querySelector('meta[name="csrf-token"]').content;
    const rows=document.getElementById('facilityRows'); let page=1,lastPage=1,timer=null,editing=null,cache=[];
    const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const headline=v=>String(v??'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
    const request=async(url,options={})=>{const response=await fetch(url,{...options,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'Content-Type':'application/json',...(options.headers||{})}});const data=await response.json().catch(()=>({}));if(!response.ok)throw data;return data;};
    function params(){const q=new URLSearchParams({page});const search=document.getElementById('facilitySearch').value.trim(),type=document.getElementById('facilityTypeFilter').value,status=document.getElementById('facilityStatusFilter').value;if(search)q.set('search',search);if(type)q.set('facility_type',type);if(status)q.set('status',status);return q;}
    function actions(row,index){let html=`<a class="btn btn-sm btn-outline-danger" href="${esc(row.inventory_url)}">Inventory</a>`;if(canManage)html+=`<button class="btn btn-sm btn-outline-secondary" data-edit="${index}" type="button">Edit</button><button class="btn btn-sm btn-outline-secondary" data-status="${index}" type="button">${row.status==='active'?'Deactivate':'Activate'}</button>`;return html;}
    async function load(){rows.innerHTML='<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';try{const data=await request(`${urls.data}?${params()}`);cache=data.data||[];lastPage=data.meta?.last_page||1;const s=data.summary||{};document.getElementById('facilityTotal').textContent=s.total??0;document.getElementById('facilityActive').textContent=s.active??0;document.getElementById('facilityInactive').textContent=s.inactive??0;document.getElementById('facilityMapped').textContent=s.mapped??0;document.getElementById('facilityMeta').textContent=`${data.meta?.from??0}-${data.meta?.to??0} of ${data.meta?.total??0}`;document.getElementById('facilityPrev').disabled=page<=1;document.getElementById('facilityNext').disabled=page>=lastPage;rows.innerHTML=cache.length?cache.map((row,index)=>`<tr><td><strong>${esc(row.facility_name)}</strong><br><small class="text-muted">${esc(row.contact_number||'No contact')}</small></td><td>${esc(headline(row.facility_type))}</td><td>${esc([row.barangay_name,row.city,row.province].filter(Boolean).join(', '))}</td><td><span class="facility-badge facility-badge--${esc(row.status)}">${esc(headline(row.status))}</span></td><td>${esc(row.total_available_units)}</td><td>${esc(row.low_or_out_types)}</td><td>${row.mapped?'Mapped':'No coordinates'}</td><td><div class="facility-actions">${actions(row,index)}</div></td></tr>`).join(''):'<tr><td colspan="8" class="text-center text-muted py-4">No facilities found.</td></tr>';}catch(e){rows.innerHTML='<tr><td colspan="8" class="text-center text-danger py-4">Could not load facilities.</td></tr>';}}
    document.getElementById('facilityPrev').onclick=()=>{if(page>1){page--;load();}};document.getElementById('facilityNext').onclick=()=>{if(page<lastPage){page++;load();}};
    ['facilitySearch','facilityTypeFilter','facilityStatusFilter'].forEach(id=>document.getElementById(id).addEventListener(id==='facilitySearch'?'input':'change',()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load();},250);}));
    document.getElementById('facilityClear').onclick=()=>{document.getElementById('facilitySearch').value='';document.getElementById('facilityTypeFilter').value='';document.getElementById('facilityStatusFilter').value='';page=1;load();};
    if(canManage){const modal=new bootstrap.Modal('#facilityModal'),form=document.getElementById('facilityForm'),errors=document.getElementById('facilityErrors');const open=row=>{editing=row||null;form.reset();form.city.value='Lipa City';form.province.value='Batangas';form.status.value='active';document.getElementById('facilityModalTitle').textContent=row?'Edit Facility':'Create Facility';if(row)Object.keys(row).forEach(key=>{if(form.elements[key]&&row[key]!==null)form.elements[key].value=row[key];});errors.classList.add('d-none');modal.show();};document.getElementById('createFacilityButton').onclick=()=>open(null);rows.addEventListener('click',async event=>{const edit=event.target.closest('[data-edit]'),status=event.target.closest('[data-status]');if(edit)open(cache[Number(edit.dataset.edit)]);if(status){const row=cache[Number(status.dataset.status)],next=row.status==='active'?'inactive':'active';if(!confirm(`Set ${row.facility_name} as ${next}?`))return;await request(`/admin/facilities/${row.facility_id}/status`,{method:'PATCH',body:JSON.stringify({status:next})});load();}});form.onsubmit=async event=>{event.preventDefault();const payload=Object.fromEntries(new FormData(form));['address','barangay_name','latitude','longitude','contact_number'].forEach(k=>{if(payload[k]==='')payload[k]=null;});document.getElementById('facilitySave').disabled=true;try{await request(editing?`/admin/facilities/${editing.facility_id}`:urls.store,{method:editing?'PUT':'POST',body:JSON.stringify(payload)});modal.hide();load();}catch(e){errors.textContent=e.message||Object.values(e.errors||{}).flat().join(' ');errors.classList.remove('d-none');}finally{document.getElementById('facilitySave').disabled=false;}};}
    load();
}());
</script>
@endpush
