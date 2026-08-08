@extends('layouts.admin')

@section('title', 'eDonate - Blood Requests')
@section('admin_page_class', 'admin-blood-requests-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_link_mode', 'link')
@section('render_default_hamburger', 'false')
@section('header_title', 'Blood Requests')
@section('header_subtitle', 'Create and manage facility blood and replacement donor requests')

@section('header_slot')
<button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar"><span class="hamburger__bar"></span><span class="hamburger__bar"></span><span class="hamburger__bar"></span></button>
@endsection

@section('header_actions')
<button class="btn btn-danger" type="button" id="createRequestButton">Create Request</button>
@endsection

@section('main_content')
<main class="request-content">
    <section class="request-summary" aria-label="Blood request summary">
        <div class="request-stat"><span>Open Requests</span><strong id="requestOpen">-</strong></div>
        <div class="request-stat"><span>Emergency Requests</span><strong id="requestEmergency">-</strong></div>
        <div class="request-stat"><span>Fulfilled</span><strong id="requestFulfilled">-</strong></div>
        <div class="request-stat"><span>Cancelled</span><strong id="requestCancelled">-</strong></div>
    </section>
    <section class="request-panel">
        <div class="request-toolbar">
            <div><label for="requestSearch">Search</label><input class="form-control" id="requestSearch" type="search" maxlength="150" placeholder="Reference or facility"></div>
            <div><label for="requestStatus">Status</label><select class="form-select" id="requestStatus"><option value="">All</option><option value="open">Open</option><option value="in_progress">In progress</option><option value="fulfilled">Fulfilled</option><option value="cancelled">Cancelled</option><option value="expired">Expired</option></select></div>
            <div><label for="requestUrgency">Urgency</label><select class="form-select" id="requestUrgency"><option value="">All</option><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="emergency">Emergency</option></select></div>
            <div><label for="requestBloodType">Blood type</label><select class="form-select" id="requestBloodType"><option value="">All</option>@foreach($bloodTypes as $bloodType)<option value="{{ $bloodType->blood_type_id }}">{{ $bloodType->blood_type }}</option>@endforeach</select></div>
            <button class="btn btn-outline-secondary" id="requestClear" type="button">Clear</button>
        </div>
        <div class="request-table-wrap">
            <table class="table table-hover request-table"><thead><tr><th>Reference</th><th>Facility</th><th>Type</th><th>Blood Type</th><th>Required</th><th>Notified</th><th>Interested</th><th>Urgency</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody id="requestRows"><tr><td colspan="11" class="text-center text-muted py-4">Loading...</td></tr></tbody></table>
        </div>
        <div class="d-flex justify-content-between align-items-center gap-3"><small class="text-muted" id="requestMeta"></small><div class="btn-group"><button class="btn btn-sm btn-outline-secondary" id="requestPrev" type="button">Previous</button><button class="btn btn-sm btn-outline-secondary" id="requestNext" type="button">Next</button></div></div>
    </section>
</main>

<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" id="requestForm"><div class="modal-header"><h2 class="modal-title fs-5" id="requestModalTitle">Create Blood Request</h2><button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button></div><div class="modal-body"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">Facility</label><select class="form-select" name="facility_id" required>@foreach($facilities as $facility)<option value="{{ $facility->facility_id }}">{{ $facility->facility_name }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Request type</label><select class="form-select" name="request_type" required><option value="replacement_donor">Replacement Donor</option><option value="blood_request">Blood Request</option></select></div>
    <div class="col-md-4"><label class="form-label">Needed blood type</label><select class="form-select" name="needed_blood_type_id" required>@foreach($bloodTypes as $bloodType)<option value="{{ $bloodType->blood_type_id }}">{{ $bloodType->blood_type }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Required donors</label><input class="form-control" name="required_donors" type="number" min="1" max="1000" value="1" required></div>
    <div class="col-md-4"><label class="form-label">Specific match required</label><input class="form-control" name="specific_match_required" type="number" min="0" max="1000" value="1"></div>
    <div class="col-md-4"><label class="form-label">Allow other blood types</label><select class="form-select" name="allow_other_blood_types"><option value="1">Yes</option><option value="0">No</option></select></div>
    <div class="col-md-4"><label class="form-label">Urgency</label><select class="form-select" name="urgency"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="emergency">Emergency</option></select></div>
    <div class="col-md-4"><label class="form-label">Expires at</label><input class="form-control" name="expires_at" type="datetime-local"></div>
    <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" maxlength="2000"></textarea></div>
    <div class="col-12"><div class="alert alert-danger d-none mb-0" id="requestErrors"></div></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button><button class="btn btn-danger" type="submit" id="requestSave">Save Request</button></div></form></div></div>
@endsection

@push('admin_scripts')
<script>
(function(){'use strict';
const urls={data:@json(route('admin.blood-requests.data')),store:@json(route('admin.blood-requests.store'))},csrf=document.querySelector('meta[name="csrf-token"]').content,rows=document.getElementById('requestRows');let page=1,lastPage=1,timer=null;
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),head=v=>String(v??'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
async function req(url,opt={}){const res=await fetch(url,{...opt,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'Content-Type':'application/json',...(opt.headers||{})}}),data=await res.json().catch(()=>({}));if(!res.ok)throw data;return data}
function params(){const q=new URLSearchParams({page});[['search','requestSearch'],['status','requestStatus'],['urgency','requestUrgency'],['blood_type_id','requestBloodType']].forEach(([k,id])=>{const v=document.getElementById(id).value.trim();if(v)q.set(k,v)});return q}
async function load(){rows.innerHTML='<tr><td colspan="11" class="text-center text-muted py-4">Loading...</td></tr>';try{const data=await req(`${urls.data}?${params()}`),s=data.summary||{},meta=data.meta||{};document.getElementById('requestOpen').textContent=s.open??0;document.getElementById('requestEmergency').textContent=s.emergency??0;document.getElementById('requestFulfilled').textContent=s.fulfilled??0;document.getElementById('requestCancelled').textContent=s.cancelled??0;lastPage=meta.last_page||1;document.getElementById('requestMeta').textContent=`${meta.from??0}-${meta.to??0} of ${meta.total??0}`;document.getElementById('requestPrev').disabled=page<=1;document.getElementById('requestNext').disabled=page>=lastPage;rows.innerHTML=(data.data||[]).length?(data.data||[]).map(r=>`<tr><td><strong>${esc(r.request_reference)}</strong></td><td>${esc(r.facility_name)}</td><td>${esc(head(r.request_type))}</td><td>${esc(r.needed_blood_type)}</td><td>${esc(r.required_donors)}<br><small class="text-muted">${esc(r.specific_match_required)} exact</small></td><td>${esc(r.notified_count)}</td><td>${esc(r.interested_count)}</td><td><span class="request-badge request-badge--${esc(r.urgency)}">${esc(head(r.urgency))}</span></td><td><span class="request-badge request-badge--${esc(r.status)}">${esc(head(r.status))}</span></td><td>${r.created_at?esc(new Date(r.created_at).toLocaleString()):''}</td><td><a class="btn btn-sm btn-outline-danger" href="${esc(r.show_url)}">View</a></td></tr>`).join(''):'<tr><td colspan="11" class="text-center text-muted py-4">No blood requests found.</td></tr>'}catch(e){rows.innerHTML='<tr><td colspan="11" class="text-center text-danger py-4">Could not load blood requests.</td></tr>'}}
document.getElementById('requestPrev').onclick=()=>{if(page>1){page--;load()}};document.getElementById('requestNext').onclick=()=>{if(page<lastPage){page++;load()}};['requestSearch','requestStatus','requestUrgency','requestBloodType'].forEach(id=>document.getElementById(id).addEventListener(id==='requestSearch'?'input':'change',()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load()},250)}));document.getElementById('requestClear').onclick=()=>{['requestSearch','requestStatus','requestUrgency','requestBloodType'].forEach(id=>document.getElementById(id).value='');page=1;load()};
const modal=new bootstrap.Modal('#requestModal'),form=document.getElementById('requestForm'),errors=document.getElementById('requestErrors');document.getElementById('createRequestButton').onclick=()=>{form.reset();errors.classList.add('d-none');modal.show()};form.onsubmit=async e=>{e.preventDefault();const payload=Object.fromEntries(new FormData(form));payload.allow_other_blood_types=payload.allow_other_blood_types==='1';if(!payload.expires_at)payload.expires_at=null;document.getElementById('requestSave').disabled=true;try{await req(urls.store,{method:'POST',body:JSON.stringify(payload)});modal.hide();load()}catch(err){errors.textContent=err.message||Object.values(err.errors||{}).flat().join(' ');errors.classList.remove('d-none')}finally{document.getElementById('requestSave').disabled=false}};load();
}());
</script>
@endpush
