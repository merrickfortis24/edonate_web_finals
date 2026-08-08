@extends('layouts.admin')

@section('title', 'eDonate - Facility Inventory')
@section('admin_page_class', 'admin-facility-inventory-page')
@section('header_title', $facility->facility_name)
@section('header_subtitle', 'Blood inventory and update history')
@section('header_actions')<a class="btn btn-outline-secondary" href="{{ route('admin.facilities.index') }}">Back to Facilities</a>@endsection

@push('admin_head')
<style>
    .inventory-content{padding:24px 32px 40px}.inventory-panel{background:var(--edonate-card-bg);border:1px solid var(--bs-border-color);border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);color:var(--bs-body-color);margin-bottom:18px;padding:18px}.inventory-grid{display:grid;gap:14px;grid-template-columns:repeat(4,minmax(0,1fr))}.inventory-field label{display:block;font-size:12px;font-weight:600;margin-bottom:5px}.inventory-status{border-radius:999px;display:inline-block;font-size:11px;font-weight:700;padding:4px 8px}.inventory-status--available{background:#dff3e4;color:#17642c}.inventory-status--low{background:#fff0bd;color:#725500}.inventory-status--out_of_stock{background:#f8d8d8;color:#8a1616}.inventory-table-wrap{overflow-x:auto}.inventory-table{min-width:820px}.inventory-table td,.inventory-table th{font-size:13px;vertical-align:middle}@media(max-width:900px){.inventory-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.inventory-content{padding:16px}.inventory-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('main_content')
<main class="inventory-content">
    <section class="inventory-panel"><div id="facilityDetails" class="text-muted">Loading facility details...</div></section>
    <form class="inventory-panel" id="inventoryForm">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><div><h2 class="h5 mb-1">Current Inventory</h2><p class="text-muted small mb-0">Missing blood type rows are treated as zero units.</p></div>@unless($canManage)<span class="badge text-bg-secondary">Read only</span>@endunless</div>
        <div class="inventory-grid" id="inventoryGrid"><div class="text-muted">Loading...</div></div>
        @if($canManage)<div class="mt-3"><label class="form-label" for="inventoryReason">Reason for update</label><textarea class="form-control" id="inventoryReason" rows="2" maxlength="500" required placeholder="Stock count, receipt, release, or correction"></textarea></div><div class="alert alert-danger d-none mt-3 mb-0" id="inventoryError"></div><div class="mt-3 text-end"><button class="btn btn-danger" id="inventorySave" type="submit">Save Inventory</button></div>@endif
    </form>
    <section class="inventory-panel"><h2 class="h5 mb-3">Update History</h2><div class="inventory-table-wrap"><table class="table table-hover inventory-table"><thead><tr><th>Date</th><th>Blood Type</th><th>Previous</th><th>New</th><th>Change</th><th>Reason</th><th>Updated By</th></tr></thead><tbody id="inventoryHistory"><tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr></tbody></table></div></section>
</main>
@endsection

@push('admin_scripts')
<script>
(function(){
    'use strict';const dataUrl=@json(route('admin.facilities.inventory.data',$facility));const updateUrl=@json(route('admin.facilities.inventory.update',$facility));const canManage=@json($canManage);const csrf=document.querySelector('meta[name="csrf-token"]').content;const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));const date=v=>v?new Date(v).toLocaleString():'Never';
    async function request(url,options={}){const response=await fetch(url,{...options,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'Content-Type':'application/json'}});const data=await response.json().catch(()=>({}));if(!response.ok)throw data;return data;}
    function render(data){const f=data.facility||{};document.getElementById('facilityDetails').innerHTML=`<div class="row g-3"><div class="col-md-4"><strong>${esc(f.facility_name)}</strong><br><span>${esc(String(f.facility_type||'').replaceAll('_',' '))}</span></div><div class="col-md-5">${esc([f.address,f.barangay_name,f.city,f.province].filter(Boolean).join(', '))}</div><div class="col-md-3">${esc(f.contact_number||'No contact number')}<br>${f.mapped?'Mapped coordinates':'No map coordinates'}</div></div>`;document.getElementById('inventoryGrid').innerHTML=(data.inventory||[]).map(row=>`<div class="inventory-field" data-id="${row.blood_type_id}"><div class="d-flex justify-content-between align-items-center mb-2"><strong>${esc(row.blood_type)}</strong><span class="inventory-status inventory-status--${esc(row.status)}">${esc(row.status.replaceAll('_',' '))}</span></div><label>Available units</label><input class="form-control units" type="number" min="0" max="1000000" value="${esc(row.available_units)}" ${canManage?'':'disabled'}><label class="mt-2">Low stock threshold</label><input class="form-control threshold" type="number" min="0" max="1000000" value="${esc(row.low_stock_threshold)}" ${canManage?'':'disabled'}><small class="text-muted d-block mt-2">Updated ${esc(date(row.last_updated))}${row.updated_by?' by '+esc(row.updated_by):''}</small></div>`).join('');const history=data.history||[];document.getElementById('inventoryHistory').innerHTML=history.length?history.map(row=>`<tr><td>${esc(date(row.created_at))}</td><td><strong>${esc(row.blood_type)}</strong></td><td>${esc(row.previous_units)}</td><td>${esc(row.new_units)}</td><td>${row.change_amount>0?'+':''}${esc(row.change_amount)}</td><td>${esc(row.reason)}</td><td>${esc(row.updated_by||'Admin')}</td></tr>`).join(''):'<tr><td colspan="7" class="text-center text-muted py-4">No inventory changes recorded.</td></tr>';}
    async function load(){try{render(await request(dataUrl));}catch(e){document.getElementById('inventoryGrid').innerHTML='<div class="text-danger">Could not load inventory.</div>';}}
    if(canManage)document.getElementById('inventoryForm').onsubmit=async event=>{event.preventDefault();const entries=[...document.querySelectorAll('.inventory-field')].map(item=>({blood_type_id:Number(item.dataset.id),available_units:Number(item.querySelector('.units').value),low_stock_threshold:Number(item.querySelector('.threshold').value)}));const error=document.getElementById('inventoryError'),button=document.getElementById('inventorySave');button.disabled=true;error.classList.add('d-none');try{const data=await request(updateUrl,{method:'PUT',body:JSON.stringify({inventory:entries,reason:document.getElementById('inventoryReason').value.trim()})});render(data.data);document.getElementById('inventoryReason').value='';}catch(e){error.textContent=e.message||Object.values(e.errors||{}).flat().join(' ');error.classList.remove('d-none');}finally{button.disabled=false;}};load();
}());
</script>
@endpush
