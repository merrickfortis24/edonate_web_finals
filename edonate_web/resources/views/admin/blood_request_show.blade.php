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
    <section class="br-card mt-3" aria-labelledby="interestedDonorsHeading">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
            <h2 class="mb-0" id="interestedDonorsHeading">Interested / Willing Donors</h2>
            <span class="badge bg-danger"><span id="interestedDonorCount">{{ $details['summary']['interested'] }}</span> Interested</span>
        </div>
        <p class="text-muted small">Responses to this blood request, including donors who accepted it in the mobile app.</p>
        <div class="table-responsive">
            <table class="table table-hover br-table">
                <thead><tr><th>Donor</th><th>Blood Type</th><th>Contact</th><th>Barangay / City</th><th>Response</th><th>Responded At</th><th>Action</th></tr></thead>
                <tbody id="interestedDonorRows"><tr><td colspan="7" class="text-center text-muted py-4">Loading interested donors...</td></tr></tbody>
            </table>
        </div>
    </section>
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
<div class="modal fade" id="approveRequestModal" tabindex="-1" aria-labelledby="approveRequestTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="approveRequestForm">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="approveRequestTitle">Approve Blood Request?</h2>
                    <p class="text-muted small mb-0 mt-1">Review the request before opening it for fulfillment.</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="border rounded p-3 mb-3">
                    <div class="fw-semibold">{{ $details['request']['request_reference'] }}</div>
                    <div>{{ $details['request']['facility_name'] }}</div>
                    <div class="small text-muted">{{ $details['request']['needed_blood_type'] }} · {{ Str::headline($details['request']['urgency']) }} urgency</div>
                </div>
                <p class="mb-0">Approving changes the status to <strong>Open</strong>, allowing eligible donors to respond. This will not create a donation record or deduct inventory.</p>
                <div class="alert alert-danger d-none mt-3 mb-0" id="approveRequestError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" type="submit" id="confirmApproveRequest">Approve &amp; Open Request</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="rejectRequestModal" tabindex="-1" aria-labelledby="rejectRequestTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="rejectRequestForm">
        <div class="modal-header"><h2 class="modal-title fs-5" id="rejectRequestTitle">Reject Blood Request</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><label class="form-label" for="rejectRequestReason">Reason for rejection</label><textarea class="form-control" id="rejectRequestReason" name="reason" rows="4" minlength="3" maxlength="1000" required></textarea><div class="invalid-feedback" id="rejectRequestReasonError"></div><div class="alert alert-danger d-none mt-3 mb-0" id="rejectRequestError" role="alert"></div></div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit" id="confirmRejectRequest">Reject Request</button></div>
    </form></div>
</div>
@endif

@if($details['request']['status'] !== 'pending_review')
<div class="modal fade" id="bloodRequestActionModal" tabindex="-1" aria-labelledby="bloodRequestActionTitle" aria-describedby="bloodRequestActionMessage" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="bloodRequestActionForm" novalidate>
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="bloodRequestActionTitle">Confirm action</h2>
                    <p class="text-muted small mb-0 mt-1">Blood request {{ $details['request']['request_reference'] }}</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="bloodRequestActionMessage" class="mb-0"></p>
                <div class="mt-3 d-none" id="bloodRequestActionFieldWrap">
                    <label class="form-label" id="bloodRequestActionFieldLabel" for="bloodRequestActionField"></label>
                    <textarea class="form-control" id="bloodRequestActionField" rows="4" maxlength="500" aria-describedby="bloodRequestActionFieldError"></textarea>
                    <div class="invalid-feedback" id="bloodRequestActionFieldError"></div>
                </div>
                <div class="alert alert-danger d-none mt-3 mb-0" id="bloodRequestActionError" role="alert" aria-live="polite"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" id="bloodRequestActionDismiss" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit" id="bloodRequestActionSubmit">Continue</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('admin_scripts')
<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const base = @json(url('/admin/blood-requests/'.$bloodRequest->request_id));
    const rows = document.getElementById('candidateRows');
    const interestedRows = document.getElementById('interestedDonorRows');
    const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[character]));
    const head = value => String(value ?? '').replaceAll('_', ' ').replace(/\b\w/g, character => character.toUpperCase());

    async function req(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw data;
        return data;
    }

    function rowActions(row) {
        if (['interested', 'responded', 'accepted'].includes(row.status)) {
            return `<button class="btn btn-sm btn-outline-success" data-status="confirmed" data-donor="${esc(row.donor_id)}" type="button">Confirm</button>
                <button class="btn btn-sm btn-outline-secondary" data-status="declined" data-donor="${esc(row.donor_id)}" type="button">Decline</button>`;
        }
        if (row.status === 'confirmed' || row.status === 'scheduled') {
            return `<button class="btn btn-sm btn-outline-danger" data-status="completed" data-donor="${esc(row.donor_id)}" type="button">Complete</button>`;
        }
        return '';
    }

    async function load() {
        if (!rows) return;
        rows.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';
        try {
            const filter = document.getElementById('candidateFilter').value;
            const data = await req(`${base}/candidates?filter=${encodeURIComponent(filter)}`);
            const candidates = data.data || [];
            rows.innerHTML = candidates.length ? candidates.map(row => `<tr>
                <td>${row.status === 'candidate' ? `<input type="checkbox" value="${esc(row.donor_id)}" aria-label="Select ${esc(row.donor_name)}">` : ''}</td>
                <td>${esc(row.donor_name)}</td>
                <td>${esc(row.blood_type)}<br><small class="text-muted">${esc(head(row.blood_type_status))}</small></td>
                <td>${esc([row.barangay, row.city].filter(Boolean).join(', '))}</td>
                <td>${esc(head(row.eligibility_status))}</td>
                <td>${esc(head(row.match_type))}</td>
                <td>${esc(head(row.status))}</td>
                <td><div class="btn-group btn-group-sm">${rowActions(row)}</div></td>
            </tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No candidates found.</td></tr>';
        } catch (error) {
            rows.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Could not load candidates.</td></tr>';
        }
    }

    async function loadInterestedDonors() {
        if (!interestedRows) return;
        interestedRows.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading interested donors...</td></tr>';
        try {
            const data = await req(`${base}/candidates?filter=interested`);
            const donors = data.data || [];
            document.getElementById('interestedDonorCount').textContent = donors.length;
            document.getElementById('sumInterested').textContent = donors.length;
            interestedRows.innerHTML = donors.length ? donors.map(row => `<tr>
                <td>${esc(row.donor_name || `Donor #${row.donor_id}`)}<br><small class="text-muted">D${esc(row.donor_id)}</small></td>
                <td>${esc(row.blood_type)}</td>
                <td>${esc(row.contact || '—')}</td>
                <td>${esc([row.barangay, row.city].filter(Boolean).join(', ') || 'Not recorded')}</td>
                <td>${esc(row.status === 'accepted' ? 'Accepted / Willing' : head(row.status))}</td>
                <td>${row.responded_at ? esc(new Date(row.responded_at).toLocaleString()) : 'Not recorded'}</td>
                <td><div class="btn-group btn-group-sm">${rowActions(row)}</div></td>
            </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No donors have expressed interest in this blood request yet.</td></tr>';
        } catch (error) {
            interestedRows.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Could not load interested donors. Please reload the page.</td></tr>';
        }
    }

    const actionModalElement = document.getElementById('bloodRequestActionModal');
    const actionModal = actionModalElement && window.bootstrap?.Modal.getOrCreateInstance(actionModalElement, { backdrop: 'static', keyboard: false });
    const actionForm = document.getElementById('bloodRequestActionForm');
    const actionTitle = document.getElementById('bloodRequestActionTitle');
    const actionMessage = document.getElementById('bloodRequestActionMessage');
    const actionFieldWrap = document.getElementById('bloodRequestActionFieldWrap');
    const actionFieldLabel = document.getElementById('bloodRequestActionFieldLabel');
    const actionField = document.getElementById('bloodRequestActionField');
    const actionFieldError = document.getElementById('bloodRequestActionFieldError');
    const actionError = document.getElementById('bloodRequestActionError');
    const actionDismiss = document.getElementById('bloodRequestActionDismiss');
    const actionClose = actionModalElement?.querySelector('.btn-close');
    const actionSubmit = document.getElementById('bloodRequestActionSubmit');
    let activeAction = null;

    function clearActionErrors() {
        actionError.textContent = '';
        actionError.classList.add('d-none');
        actionError.classList.remove('alert-success');
        actionError.classList.add('alert-danger');
        actionError.setAttribute('role', 'alert');
        actionField.classList.remove('is-invalid');
        actionFieldError.textContent = '';
    }

    function openAction(options) {
        if (!actionModal) return;
        activeAction = options;
        clearActionErrors();
        actionTitle.textContent = options.title;
        actionMessage.textContent = options.message;
        actionFieldWrap.classList.toggle('d-none', !options.fieldLabel);
        actionFieldLabel.textContent = options.fieldLabel || '';
        actionField.value = '';
        actionField.placeholder = options.placeholder || '';
        actionField.maxLength = options.maxLength || 500;
        actionSubmit.className = `btn ${options.buttonClass || 'btn-primary'}`;
        actionSubmit.textContent = options.submitLabel || 'Continue';
        actionSubmit.hidden = Boolean(options.infoOnly);
        actionSubmit.disabled = false;
        actionDismiss.textContent = options.infoOnly ? 'Got it' : 'Cancel';
        actionDismiss.classList.toggle('btn-primary', Boolean(options.infoOnly));
        actionDismiss.classList.toggle('btn-outline-secondary', !options.infoOnly);
        actionModal.show();
    }

    function showActionResult(message) {
        activeAction = null;
        actionFieldWrap.classList.add('d-none');
        actionSubmit.hidden = true;
        actionSubmit.disabled = false;
        actionDismiss.disabled = false;
        actionDismiss.textContent = 'Close';
        actionDismiss.classList.add('btn-primary');
        actionDismiss.classList.remove('btn-outline-secondary');
        actionClose.disabled = false;
        actionError.textContent = message;
        actionError.classList.remove('d-none', 'alert-danger');
        actionError.classList.add('alert-success');
        actionError.setAttribute('role', 'status');
    }

    actionModalElement?.addEventListener('shown.bs.modal', () => {
        if (!actionFieldWrap.classList.contains('d-none')) actionField.focus();
    });
    actionModalElement?.addEventListener('hidden.bs.modal', () => {
        activeAction = null;
    });

    actionForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const action = activeAction;
        if (!action) return;

        const value = actionField.value.trim();
        if (action.fieldLabel && value.length < (action.minLength || 3)) {
            actionField.classList.add('is-invalid');
            actionFieldError.textContent = `Please enter at least ${action.minLength || 3} characters.`;
            actionField.focus();
            return;
        }

        actionSubmit.disabled = true;
        actionSubmit.textContent = action.loadingLabel || 'Processing...';
        actionDismiss.disabled = true;
        actionClose.disabled = true;
        clearActionErrors();
        try {
            const result = await action.run(value);
            if (action.onSuccess) action.onSuccess(result);
            else showActionResult(result.message || 'Action completed successfully.');
        } catch (error) {
            const validationMessage = action.fieldName && error.errors?.[action.fieldName]?.[0];
            if (validationMessage) {
                actionField.classList.add('is-invalid');
                actionFieldError.textContent = validationMessage;
                actionField.focus();
            } else {
                actionError.textContent = error.message || 'The action could not be completed. Please try again.';
                actionError.classList.remove('d-none');
            }
        } finally {
            if (activeAction === action) {
                actionSubmit.disabled = false;
                actionSubmit.textContent = action.submitLabel || 'Continue';
                actionDismiss.disabled = false;
                actionClose.disabled = false;
            }
        }
    });

    document.getElementById('candidateFilter')?.addEventListener('change', load);
    document.getElementById('checkAll')?.addEventListener('change', event => {
        rows?.querySelectorAll('input[type="checkbox"]').forEach(checkbox => { checkbox.checked = event.target.checked; });
    });
    function handleDonorAction(event) {
        const button = event.target.closest('[data-status][data-donor]');
        if (!button) return;
        const status = button.dataset.status;
        openAction({
            title: 'Update donor response?',
            message: `Change this donor's response to ${head(status)}?`,
            submitLabel: `Set ${head(status)}`,
            buttonClass: status === 'declined' ? 'btn-outline-secondary' : 'btn-success',
            loadingLabel: 'Updating...',
            run: () => req(`${base}/donors/${encodeURIComponent(button.dataset.donor)}/status`, {
                method: 'PATCH', body: JSON.stringify({ status }),
            }),
            onSuccess: result => {
                showActionResult(result.message || 'Donor response updated.');
                load();
                loadInterestedDonors();
            },
        });
    }
    rows?.addEventListener('click', handleDonorAction);
    interestedRows?.addEventListener('click', handleDonorAction);
    document.getElementById('notifySelected')?.addEventListener('click', () => {
        const ids = [...(rows?.querySelectorAll('input[type="checkbox"]:checked') || [])]
            .map(checkbox => Number(checkbox.value)).filter(Number.isInteger);
        if (!ids.length) {
            openAction({
                title: 'Select candidates first',
                message: 'Choose at least one eligible candidate from the list before sending notifications.',
                infoOnly: true,
            });
            return;
        }
        openAction({
            title: 'Notify selected donors?',
            message: `Send an in-app notification to ${ids.length} selected candidate${ids.length === 1 ? '' : 's'} about this blood request?`,
            submitLabel: 'Send Notifications',
            loadingLabel: 'Sending...',
            buttonClass: 'btn-danger',
            run: () => req(`${base}/notify`, { method: 'POST', body: JSON.stringify({ donor_ids: ids }) }),
            onSuccess: result => {
                showActionResult(result.message || 'Selected donors were notified.');
                load();
            },
        });
    });
    document.getElementById('cancelRequest')?.addEventListener('click', () => openAction({
        title: 'Cancel this blood request?',
        message: 'The request will be marked Cancelled, and invited donors will be notified. Add a reason for the review history.',
        fieldLabel: 'Cancellation reason',
        placeholder: 'Explain why this request is being cancelled',
        fieldName: 'reason',
        submitLabel: 'Cancel Request',
        loadingLabel: 'Cancelling...',
        buttonClass: 'btn-danger',
        run: reason => req(`${base}/cancel`, { method: 'PATCH', body: JSON.stringify({ reason }) }),
        onSuccess: () => location.reload(),
    }));
    document.getElementById('fulfillRequest')?.addEventListener('click', () => openAction({
        title: 'Mark this request fulfilled?',
        message: 'This records fulfillment and its note in the audit history. It does not create a donation record or deduct physical inventory.',
        fieldLabel: 'Fulfillment note',
        placeholder: 'Add a short note about how this request was fulfilled',
        fieldName: 'note',
        submitLabel: 'Mark Fulfilled',
        loadingLabel: 'Saving...',
        buttonClass: 'btn-success',
        run: note => req(`${base}/fulfill`, { method: 'PATCH', body: JSON.stringify({ note }) }),
        onSuccess: () => location.reload(),
    }));

    const approveModal = document.getElementById('approveRequestModal');
    const approveForm = document.getElementById('approveRequestForm');
    document.getElementById('approveRequest')?.addEventListener('click', () => {
        const error = document.getElementById('approveRequestError');
        error.textContent = '';
        error.classList.add('d-none');
        window.bootstrap?.Modal.getOrCreateInstance(approveModal).show();
    });
    approveForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('confirmApproveRequest');
        const error = document.getElementById('approveRequestError');
        button.disabled = true;
        button.textContent = 'Approving...';
        error.textContent = '';
        error.classList.add('d-none');
        try {
            await req(`${base}/approve`, { method: 'PATCH', body: JSON.stringify({}) });
            location.reload();
        } catch (exception) {
            error.textContent = exception.message || 'The request could not be approved. Please try again.';
            error.classList.remove('d-none');
        } finally {
            button.disabled = false;
            button.textContent = 'Approve & Open Request';
        }
    });

    const rejectModal = document.getElementById('rejectRequestModal');
    const rejectForm = document.getElementById('rejectRequestForm');
    document.getElementById('openRejectRequest')?.addEventListener('click', () => window.bootstrap?.Modal.getOrCreateInstance(rejectModal).show());
    rejectForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const reason = document.getElementById('rejectRequestReason');
        const fieldError = document.getElementById('rejectRequestReasonError');
        const formError = document.getElementById('rejectRequestError');
        const button = document.getElementById('confirmRejectRequest');
        reason.classList.remove('is-invalid');
        fieldError.textContent = '';
        formError.textContent = '';
        formError.classList.add('d-none');
        button.disabled = true;
        try {
            await req(`${base}/reject`, { method: 'PATCH', body: JSON.stringify({ reason: reason.value }) });
            location.reload();
        } catch (error) {
            if (error.errors?.reason) {
                reason.classList.add('is-invalid');
                fieldError.textContent = error.errors.reason[0];
            } else {
                formError.textContent = error.message || 'The request could not be rejected.';
                formError.classList.remove('d-none');
            }
        } finally {
            button.disabled = false;
        }
    });

    if (rows) load();
    loadInterestedDonors();
}());
</script>
@endpush
