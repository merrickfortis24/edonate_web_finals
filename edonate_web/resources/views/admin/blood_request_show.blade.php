@extends('layouts.admin')

@section('title', 'eDonate - Blood Request Details')
@section('admin_page_class', 'admin-blood-request-detail-page')
@section('header_title', $details['request']['request_reference'])
@section('header_subtitle', $details['request']['facility_name'])

@section('header_actions')
    <a class="btn btn-outline-secondary" href="{{ route('admin.blood-requests.index') }}" aria-label="Back to Blood Requests">
        <span aria-hidden="true">&larr;</span> Back to Blood Requests
    </a>
@endsection

@push('admin_head')
<style>
    .br-detail{padding:24px 32px 40px}.br-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.br-card{background:var(--edonate-card-bg);border:1px solid var(--bs-border-color);border-radius:8px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.06);color:var(--bs-body-color)}.br-card h2{font-size:18px;margin-bottom:14px}.br-meta{display:grid;grid-template-columns:180px 1fr;gap:8px;font-size:14px}.br-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.br-stat{border:1px solid var(--bs-border-color);border-radius:8px;padding:12px}.br-stat span{color:var(--bs-secondary-color);font-size:12px;font-weight:600}.br-stat strong{display:block;color:#9f1010;font-size:22px}.br-table td,.br-table th{font-size:13px;vertical-align:middle}.br-actions{display:flex;gap:8px;flex-wrap:wrap}@media(max-width:900px){.br-grid,.br-summary{grid-template-columns:1fr}.br-detail{padding:16px}.br-meta{grid-template-columns:1fr}}
</style>
@endpush

@section('main_content')
<main class="br-detail">
    <div class="br-grid">
        <section class="br-card">
            <h2>Request Details</h2>
            <div class="br-meta">
                <strong>Facility</strong><span>{{ $details['request']['facility_name'] }}</span>
                <strong>Requester</strong><span>{{ $details['request']['requester_name'] }}@if($details['request']['requester_email'])<br><small>{{ $details['request']['requester_email'] }}</small>@endif @if($details['request']['requester_contact'])<br><small>{{ $details['request']['requester_contact'] }}</small>@endif</span>
                <strong>Source</strong><span>{{ Str::headline($details['request']['request_source']) }}</span>
                <strong>Submitted</strong><span>{{ $details['request']['created_at'] ? \Illuminate\Support\Carbon::parse($details['request']['created_at'])->format('M j, Y g:i A') : 'Not available' }}</span>
                <strong>Type</strong><span>{{ Str::headline($details['request']['request_type']) }}</span>
                <strong>Blood Type Needed</strong><span>{{ $details['request']['needed_blood_type'] }}</span>
                <strong>Required Donors</strong><span>{{ $details['request']['required_donors'] }}</span>
                <strong>Specific Matches</strong><span>{{ $details['request']['specific_match_required'] }}</span>
                <strong>Other Donors Allowed</strong><span>{{ $details['request']['allow_other_blood_types'] ? 'Yes' : 'No' }}</span>
                <strong>Urgency</strong><span>{{ Str::headline($details['request']['urgency']) }}</span>
                <strong>Status</strong><span>{{ Str::headline($details['request']['status']) }}</span>
                @if($details['request']['reviewed_at'])
                    <strong>Reviewed</strong><span>{{ \Illuminate\Support\Carbon::parse($details['request']['reviewed_at'])->format('M j, Y g:i A') }}</span>
                @endif
                @if($details['request']['review_reason'])
                    <strong>Review reason</strong><span>{{ $details['request']['review_reason'] }}</span>
                @endif
                <strong>Inventory</strong><span>{{ $details['inventory']['available_units'] }} recorded unit(s)</span>
            </div>
            <p class="small text-muted mt-3 mb-0">Candidate fulfillment tracks donor responses only. It does not allocate or deduct physical inventory; recorded units must be reconciled through facility inventory management.</p>
        </section>
        <section class="br-card">
            @if($details['request']['status'] === 'pending_review')
                <h2>Review donor-submitted request</h2>
                <p class="mb-3">Verify the request details before opening it for fulfillment. Approval changes its status to Open; rejection requires a reason and is retained in the review history.</p>
                <div class="br-actions">
                    <button class="btn btn-success" id="approveRequest" type="button">Approve Request</button>
                    <button class="btn btn-outline-danger" id="openRejectRequest" type="button">Reject Request</button>
                </div>
                <div class="alert alert-danger d-none mt-3 mb-0" id="reviewError" role="alert"></div>
            @else
            <h2>Candidate Summary</h2>
            <div class="br-summary">
                <div class="br-stat"><span>Exact Found</span><strong id="sumExact">{{ $details['summary']['exact_matches_found'] }}</strong></div>
                <div class="br-stat"><span>Other Eligible</span><strong id="sumOther">{{ $details['summary']['other_eligible_candidates'] }}</strong></div>
                <div class="br-stat"><span>Notified</span><strong id="sumNotified">{{ $details['summary']['notified'] }}</strong></div>
                <div class="br-stat"><span>Interested</span><strong id="sumInterested">{{ $details['summary']['interested'] }}</strong></div>
            </div>
            <div class="br-actions mt-3">
                @if(in_array($details['request']['status'], ['open','in_progress'], true))
                    <button class="btn btn-danger btn-sm" id="notifySelected" type="button">Notify Selected</button>
                    <button class="btn btn-outline-success btn-sm" id="fulfillRequest" type="button">Mark Fulfilled</button>
                    <button class="btn btn-outline-danger btn-sm" id="cancelRequest" type="button">Cancel Request</button>
                @endif
            </div>
            @endif
        </section>
    </div>
    @if($details['request']['status'] !== 'pending_review')
    <section class="br-card mt-3">
        <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-3">
            <h2 class="mb-0">Candidates</h2>
            <select class="form-select form-select-sm" id="candidateFilter" style="max-width:220px"><option value="recommended">Recommended</option><option value="exact">Exact Matches</option><option value="other">Other Eligible</option><option value="notified">Notified</option><option value="interested">Interested</option><option value="declined">Declined</option><option value="confirmed">Confirmed</option><option value="completed">Completed</option></select>
        </div>
        <div class="table-responsive">
            <table class="table table-hover br-table"><thead><tr><th><input type="checkbox" id="checkAll"></th><th>Donor</th><th>Blood Type</th><th>Barangay</th><th>Eligibility</th><th>Match</th><th>Response</th><th>Actions</th></tr></thead><tbody id="candidateRows"><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody></table>
        </div>
    </section>
    @endif
</main>

@if($details['request']['status'] === 'pending_review')
<div class="modal fade" id="rejectRequestModal" tabindex="-1" aria-labelledby="rejectRequestTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="rejectRequestForm">
        <div class="modal-header"><h2 class="modal-title fs-5" id="rejectRequestTitle">Reject Blood Request</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><label class="form-label" for="rejectRequestReason">Reason for rejection</label><textarea class="form-control" id="rejectRequestReason" name="reason" rows="4" minlength="3" maxlength="1000" required></textarea><div class="invalid-feedback" id="rejectRequestReasonError"></div><div class="alert alert-danger d-none mt-3 mb-0" id="rejectRequestError" role="alert"></div></div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit" id="confirmRejectRequest">Reject Request</button></div>
    </form></div>
</div>
@endif
@endsection

@push('admin_scripts')
<script>
(function(){'use strict';const csrf=document.querySelector('meta[name="csrf-token"]').content,base=@json(url('/admin/blood-requests/'.$bloodRequest->request_id)),rows=document.getElementById('candidateRows');let cache=[];const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),head=v=>String(v??'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());async function req(url,opt={}){const res=await fetch(url,{...opt,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'Content-Type':'application/json',...(opt.headers||{})}}),data=await res.json().catch(()=>({}));if(!res.ok)throw data;return data}function rowActions(r){if(r.status==='interested'||r.status==='responded')return `<button class="btn btn-sm btn-outline-success" data-status="confirmed" data-donor="${esc(r.donor_id)}" type="button">Confirm</button><button class="btn btn-sm btn-outline-secondary" data-status="declined" data-donor="${esc(r.donor_id)}" type="button">Decline</button>`;if(r.status==='confirmed'||r.status==='scheduled')return `<button class="btn btn-sm btn-outline-danger" data-status="completed" data-donor="${esc(r.donor_id)}" type="button">Complete</button>`;return ''}async function load(){if(!rows)return;rows.innerHTML='<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';try{const f=document.getElementById('candidateFilter').value,data=await req(`${base}/candidates?filter=${encodeURIComponent(f)}`);cache=data.data||[];rows.innerHTML=cache.length?cache.map(r=>`<tr><td>${r.status==='candidate'?`<input type="checkbox" value="${esc(r.donor_id)}">`:''}</td><td>${esc(r.donor_name)}</td><td>${esc(r.blood_type)}<br><small class="text-muted">${esc(head(r.blood_type_status))}</small></td><td>${esc([r.barangay,r.city].filter(Boolean).join(', '))}</td><td>${esc(head(r.eligibility_status))}</td><td>${esc(head(r.match_type))}</td><td>${esc(head(r.status))}</td><td><div class="btn-group btn-group-sm">${rowActions(r)}</div></td></tr>`).join(''):'<tr><td colspan="8" class="text-center text-muted py-4">No candidates found.</td></tr>'}catch(e){rows.innerHTML='<tr><td colspan="8" class="text-center text-danger py-4">Could not load candidates.</td></tr>'}}document.getElementById('candidateFilter')?.addEventListener('change',load);document.getElementById('checkAll')?.addEventListener('change',e=>rows.querySelectorAll('input[type=checkbox]').forEach(cb=>cb.checked=e.target.checked));rows?.addEventListener('click',async e=>{const btn=e.target.closest('[data-status][data-donor]');if(!btn)return;if(!confirm(`Set donor status to ${btn.dataset.status}?`))return;await req(`${base}/donors/${btn.dataset.donor}/status`,{method:'PATCH',body:JSON.stringify({status:btn.dataset.status})});load()});document.getElementById('notifySelected')?.addEventListener('click',async()=>{const ids=[...rows.querySelectorAll('input[type=checkbox]:checked')].map(cb=>Number(cb.value));if(!ids.length){alert('Select at least one candidate.');return}if(!confirm(`Notify ${ids.length} selected candidate(s)?`))return;const data=await req(`${base}/notify`,{method:'POST',body:JSON.stringify({donor_ids:ids})});alert(data.message||'Candidates notified.');load()});document.getElementById('cancelRequest')?.addEventListener('click',async()=>{const reason=prompt('Cancellation reason');if(!reason)return;await req(`${base}/cancel`,{method:'PATCH',body:JSON.stringify({reason})});location.reload()});document.getElementById('fulfillRequest')?.addEventListener('click',async()=>{const note=prompt('Fulfillment note');if(!note)return;await req(`${base}/fulfill`,{method:'PATCH',body:JSON.stringify({note})});location.reload()});
const showReviewError=message=>{const alert=document.getElementById('reviewError');if(alert){alert.textContent=message;alert.classList.remove('d-none')}};document.getElementById('approveRequest')?.addEventListener('click',async()=>{if(!confirm('Approve this request and open it for fulfillment?'))return;try{await req(`${base}/approve`,{method:'PATCH',body:JSON.stringify({})});location.reload()}catch(error){showReviewError(error.message||'The request could not be approved.')}});const rejectModal=document.getElementById('rejectRequestModal'),rejectForm=document.getElementById('rejectRequestForm');document.getElementById('openRejectRequest')?.addEventListener('click',()=>window.bootstrap?.Modal.getOrCreateInstance(rejectModal).show());rejectForm?.addEventListener('submit',async event=>{event.preventDefault();const reason=document.getElementById('rejectRequestReason'),fieldError=document.getElementById('rejectRequestReasonError'),formError=document.getElementById('rejectRequestError'),button=document.getElementById('confirmRejectRequest');reason.classList.remove('is-invalid');fieldError.textContent='';formError.textContent='';formError.classList.add('d-none');button.disabled=true;try{await req(`${base}/reject`,{method:'PATCH',body:JSON.stringify({reason:reason.value})});location.reload()}catch(error){if(error.errors?.reason){reason.classList.add('is-invalid');fieldError.textContent=error.errors.reason[0]}else{formError.textContent=error.message||'The request could not be rejected.';formError.classList.remove('d-none')}}finally{button.disabled=false}});if(rows)load();}());
</script>
@endpush
