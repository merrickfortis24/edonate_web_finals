@extends('layouts.admin')

@section('title', 'eDonate - Donation Events')
@section('admin_page_class', 'admin-donation-events-page')
@section('layout_wrapper_class', 'layout')
@section('sidebar_id', 'donationEventsSidebar')
@section('sidebar_aria_label', 'Admin navigation')
@section('sidebar_nav_aria_label', 'Primary navigation')
@section('sidebar_link_mode', 'link')
@section('sidebar_open_class', 'is-open')
@section('overlay_id', 'donationEventsOverlay')
@section('overlay_class', 'appointment-overlay overlay')
@section('overlay_open_class', 'is-visible')
@section('hamburger_id', 'donationEventsHamburger')
@section('render_default_hamburger', 'false')

@section('header_title', 'Donation Events')
@section('header_subtitle', 'Manage donation drives, capacity, and blood type needs')

@section('header_slot')
    <button class="hamburger" id="donationEventsHamburger" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="donationEventsSidebar">
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
    </button>
@endsection

@section('header_actions')
    <button class="btn btn-danger fw-semibold" type="button" id="createEventBtn">New Event</button>
@endsection

@section('admin_page_data')
{!! json_encode([
    'page' => 'donation-events',
    'donationEvents' => $donationEventPayload ?? [
        'api' => [
            'listUrl' => '',
            'storeUrl' => '',
        ],
        'bloodTypes' => [],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
<style>
    .event-main { padding: 18px; }
    .event-toolbar,
    .event-table,
    .event-stats { max-width: 1400px; margin: 0 auto; }
    .event-stats .stat-card { border: 1px solid rgba(0,0,0,.08); border-radius: 8px; padding: 18px; background: #fff; }
    .event-stat__label { margin: 0; color: #6b7280; font-size: 12px; font-weight: 700; text-transform: uppercase; }
    .event-stat__value { margin: 6px 0 0; color: #171717; font-size: 28px; font-weight: 800; }
    .event-toolbar { margin-top: 18px; padding: 14px; border: 1px solid rgba(0,0,0,.08); border-radius: 8px; background: #fff; }
    .event-table { margin-top: 18px; border: 1px solid rgba(0,0,0,.08); border-radius: 8px; overflow: hidden; background: #fff; }
    .event-table table { margin: 0; }
    .event-table th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; }
    .event-title { font-weight: 800; color: #111827; }
    .event-meta { color: #64748b; font-size: 12px; }
    .event-badge { border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 700; }
    .event-badge--upcoming { background: #eff6ff; color: #1d4ed8; }
    .event-badge--ongoing { background: #ecfdf5; color: #047857; }
    .event-badge--completed { background: #f1f5f9; color: #475569; }
    .event-badge--cancelled { background: #fef2f2; color: #b91c1c; }
    .event-actions { display: flex; flex-wrap: wrap; gap: 6px; }
    .event-action-btn { border: 1px solid #d1d5db; border-radius: 6px; background: #fff; padding: 6px 9px; font-size: 12px; font-weight: 700; }
    .event-action-btn--danger { border-color: #fecaca; color: #b91c1c; }
    .event-pagination { max-width: 1400px; margin: 12px auto 0; display: flex; justify-content: space-between; gap: 12px; align-items: center; color: #64748b; font-size: 13px; }
    .event-page-btn { border: 1px solid #d1d5db; border-radius: 6px; background: #fff; padding: 6px 10px; margin-left: 4px; }
    .event-page-btn.is-active { background: #991b1b; border-color: #991b1b; color: #fff; }
    .event-blood-list { display: flex; flex-wrap: wrap; gap: 8px; }
    .event-blood-list label { border: 1px solid #d1d5db; border-radius: 999px; padding: 7px 10px; font-size: 13px; font-weight: 600; }
    .event-error { display: none; border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: 8px; padding: 10px 12px; font-size: 13px; }
</style>

<main class="event-main container-fluid px-0">
    <section class="event-stats row g-3" aria-label="Donation event summary">
        <div class="col-6 col-xl-3">
            <article class="stat-card h-100">
                <p class="event-stat__label">Upcoming</p>
                <p class="event-stat__value" id="eventStatUpcoming">0</p>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="stat-card h-100">
                <p class="event-stat__label">Ongoing</p>
                <p class="event-stat__value" id="eventStatOngoing">0</p>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="stat-card h-100">
                <p class="event-stat__label">Completed</p>
                <p class="event-stat__value" id="eventStatCompleted">0</p>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="stat-card h-100">
                <p class="event-stat__label">Cancelled</p>
                <p class="event-stat__value" id="eventStatCancelled">0</p>
            </article>
        </div>
    </section>

    <form class="event-toolbar row g-3 align-items-center" role="search" aria-label="Filter donation events" onsubmit="return false;">
        <div class="col-12 col-lg">
            <input id="eventSearchInput" type="search" class="form-control" placeholder="Search by event, venue, or address" aria-label="Search donation events">
        </div>
        <div class="col-12 col-md-4 col-xl-3">
            <select id="eventStatusFilter" class="form-select" aria-label="Filter by status">
                <option value="">All Status</option>
                <option value="upcoming">Upcoming</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </form>

    <section class="event-table table-responsive" aria-label="Donation event list">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Venue</th>
                    <th>Date &amp; Time</th>
                    <th>Slots</th>
                    <th>Blood Types</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="eventTableBody">
                <tr>
                    <td colspan="7" class="text-muted">Loading events...</td>
                </tr>
            </tbody>
        </table>
    </section>

    <div class="event-pagination" aria-label="Donation event pagination">
        <span id="eventPaginationInfo">Showing 0 to 0 of 0 events</span>
        <div id="eventPaginationPages"></div>
    </div>
</main>

<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="eventModalTitle">Donation Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="eventForm">
                <div class="modal-body">
                    <div class="event-error mb-3" id="eventFormError"></div>
                    <input type="hidden" id="eventId">

                    <div class="row g-3">
                        <div class="col-12 col-lg-8">
                            <label for="eventTitle" class="form-label fw-semibold">Event Name</label>
                            <input type="text" id="eventTitle" class="form-control" maxlength="150" required>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label for="eventStatus" class="form-label fw-semibold">Status</label>
                            <select id="eventStatus" class="form-select" required>
                                <option value="upcoming">Upcoming</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="eventDescription" class="form-label fw-semibold">Description</label>
                            <textarea id="eventDescription" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="eventVenue" class="form-label fw-semibold">Venue</label>
                            <input type="text" id="eventVenue" class="form-control" maxlength="150" required>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="eventAddress" class="form-label fw-semibold">Address</label>
                            <input type="text" id="eventAddress" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="eventDate" class="form-label fw-semibold">Event Date</label>
                            <input type="date" id="eventDate" class="form-control" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label for="eventStartTime" class="form-label fw-semibold">Start Time</label>
                            <input type="time" id="eventStartTime" class="form-control">
                        </div>
                        <div class="col-6 col-md-4">
                            <label for="eventEndTime" class="form-label fw-semibold">End Time</label>
                            <input type="time" id="eventEndTime" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="eventMaxCapacity" class="form-label fw-semibold">Maximum Slots</label>
                            <input type="number" id="eventMaxCapacity" class="form-control" min="1" max="100000" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="eventLatitude" class="form-label fw-semibold">Latitude</label>
                            <input type="number" step="0.000001" id="eventLatitude" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="eventLongitude" class="form-label fw-semibold">Longitude</label>
                            <input type="number" step="0.000001" id="eventLongitude" class="form-control">
                        </div>
                        <div class="col-12">
                            <p class="form-label fw-semibold mb-2">Blood Types Needed</p>
                            <div class="event-blood-list" id="eventBloodTypes"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-semibold" id="eventSubmitBtn">Save Event</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
<script>
    (function () {
        var payload = (window.AdminPageData && window.AdminPageData.donationEvents) ? window.AdminPageData.donationEvents : {};
        var listUrl = payload.api && payload.api.listUrl ? payload.api.listUrl : '';
        var storeUrl = payload.api && payload.api.storeUrl ? payload.api.storeUrl : '';
        var bloodTypes = Array.isArray(payload.bloodTypes) ? payload.bloodTypes : [];
        var csrfToken = @json(csrf_token());
        var state = { page: 1, perPage: 10, search: '', status: '' };
        var rowsById = {};
        var searchTimer = null;

        var tableBody = document.getElementById('eventTableBody');
        var paginationInfo = document.getElementById('eventPaginationInfo');
        var paginationPages = document.getElementById('eventPaginationPages');
        var searchInput = document.getElementById('eventSearchInput');
        var statusFilter = document.getElementById('eventStatusFilter');
        var modalElement = document.getElementById('eventModal');
        var modal = modalElement && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalElement) : null;
        var form = document.getElementById('eventForm');
        var errorBox = document.getElementById('eventFormError');

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatNumber(value) {
            return new Intl.NumberFormat('en-US').format(Number(value || 0));
        }

        function formatDate(value) {
            if (!value) return '-';
            var parsed = new Date(value + 'T00:00:00');
            if (Number.isNaN(parsed.getTime())) return String(value);
            return parsed.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function formatTime(value) {
            if (!value) return '-';
            var parsed = new Date('1970-01-01T' + String(value).slice(0, 5) + ':00');
            if (Number.isNaN(parsed.getTime())) return String(value);
            return parsed.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }

        function statusLabel(status) {
            return {
                upcoming: 'Upcoming',
                ongoing: 'Ongoing',
                completed: 'Completed',
                cancelled: 'Cancelled'
            }[String(status || '').toLowerCase()] || 'Upcoming';
        }

        function updateStats(stats) {
            document.getElementById('eventStatUpcoming').textContent = formatNumber(stats.upcoming || 0);
            document.getElementById('eventStatOngoing').textContent = formatNumber(stats.ongoing || 0);
            document.getElementById('eventStatCompleted').textContent = formatNumber(stats.completed || 0);
            document.getElementById('eventStatCancelled').textContent = formatNumber(stats.cancelled || 0);
        }

        function renderRows(rows) {
            rowsById = {};

            if (!Array.isArray(rows) || rows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" class="text-muted">No donation events found.</td></tr>';
                return;
            }

            tableBody.innerHTML = rows.map(function (event) {
                rowsById[String(event.event_id)] = event;
                var bloodTypesLabel = Array.isArray(event.blood_types_needed) && event.blood_types_needed.length
                    ? event.blood_types_needed.map(escapeHtml).join(', ')
                    : 'Any';
                var slotLabel = formatNumber(event.booked_slots) + ' / ' + formatNumber(event.maximum_slots);
                var remainingLabel = formatNumber(event.remaining_slots) + ' remaining';

                return ''
                    + '<tr data-event-id="' + escapeHtml(event.event_id) + '">'
                    + '<td><div class="event-title">' + escapeHtml(event.event_name) + '</div><div class="event-meta">EV' + String(event.event_id).padStart(3, '0') + '</div></td>'
                    + '<td><div>' + escapeHtml(event.venue || '-') + '</div><div class="event-meta">' + escapeHtml(event.address || '') + '</div></td>'
                    + '<td><div>' + escapeHtml(formatDate(event.event_date)) + '</div><div class="event-meta">' + escapeHtml(formatTime(event.start_time)) + ' - ' + escapeHtml(formatTime(event.end_time)) + '</div></td>'
                    + '<td><div class="fw-semibold">' + slotLabel + '</div><div class="event-meta">' + remainingLabel + '</div></td>'
                    + '<td>' + bloodTypesLabel + '</td>'
                    + '<td><span class="event-badge event-badge--' + escapeHtml(event.status) + '">' + escapeHtml(statusLabel(event.status)) + '</span></td>'
                    + '<td><div class="event-actions">'
                    + '<button type="button" class="event-action-btn" data-action="details">Details</button>'
                    + '<button type="button" class="event-action-btn" data-action="edit">Edit</button>'
                    + '<button type="button" class="event-action-btn event-action-btn--danger" data-action="delete">Delete</button>'
                    + '</div></td>'
                    + '</tr>';
            }).join('');
        }

        function renderPagination(meta) {
            var total = Number(meta.total || 0);
            var from = Number(meta.from || 0);
            var to = Number(meta.to || 0);
            var currentPage = Number(meta.current_page || 1);
            var lastPage = Number(meta.last_page || 1);

            paginationInfo.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total + ' events';
            paginationPages.innerHTML = '';

            for (var page = 1; page <= lastPage; page += 1) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'event-page-btn' + (page === currentPage ? ' is-active' : '');
                button.textContent = String(page);
                button.dataset.page = String(page);
                paginationPages.appendChild(button);
            }
        }

        function requestUrl() {
            var url = new URL(listUrl, window.location.origin);
            url.searchParams.set('page', String(state.page));
            url.searchParams.set('per_page', String(state.perPage));
            if (state.search) url.searchParams.set('search', state.search);
            if (state.status) url.searchParams.set('status', state.status);
            return url.toString();
        }

        function loadEvents() {
            if (!listUrl) return;
            tableBody.innerHTML = '<tr><td colspan="7" class="text-muted">Loading events...</td></tr>';

            fetch(requestUrl(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Unable to load events.');
                    return response.json();
                })
                .then(function (responsePayload) {
                    updateStats(responsePayload.stats || {});
                    renderRows(responsePayload.data || []);
                    renderPagination(responsePayload.meta || {});
                })
                .catch(function () {
                    tableBody.innerHTML = '<tr><td colspan="7" class="text-danger">Unable to load donation events.</td></tr>';
                });
        }

        function renderBloodTypeInputs(selected) {
            var container = document.getElementById('eventBloodTypes');
            selected = Array.isArray(selected) ? selected : [];
            container.innerHTML = bloodTypes.map(function (type) {
                var checked = selected.indexOf(type) !== -1 ? ' checked' : '';
                return '<label><input type="checkbox" value="' + escapeHtml(type) + '"' + checked + '> ' + escapeHtml(type) + '</label>';
            }).join('');
        }

        function setError(message) {
            errorBox.textContent = message || '';
            errorBox.style.display = message ? 'block' : 'none';
        }

        function openModal(event) {
            setError('');
            renderBloodTypeInputs(event ? event.blood_types_needed : []);

            document.getElementById('eventId').value = event ? event.event_id : '';
            document.getElementById('eventTitle').value = event ? event.event_name : '';
            document.getElementById('eventStatus').value = event ? event.status : 'upcoming';
            document.getElementById('eventDescription').value = event ? event.description : '';
            document.getElementById('eventVenue').value = event ? event.venue : '';
            document.getElementById('eventAddress').value = event ? event.address : '';
            document.getElementById('eventDate').value = event ? event.event_date : '';
            document.getElementById('eventStartTime').value = event && event.start_time ? String(event.start_time).slice(0, 5) : '';
            document.getElementById('eventEndTime').value = event && event.end_time ? String(event.end_time).slice(0, 5) : '';
            document.getElementById('eventMaxCapacity').value = event ? event.maximum_slots : '';
            document.getElementById('eventLatitude').value = event && event.latitude !== null ? event.latitude : '';
            document.getElementById('eventLongitude').value = event && event.longitude !== null ? event.longitude : '';
            document.getElementById('eventModalTitle').textContent = event ? 'Edit Donation Event' : 'New Donation Event';
            document.getElementById('eventSubmitBtn').textContent = event ? 'Update Event' : 'Create Event';

            modal && modal.show();
        }

        function collectPayload() {
            var checkedBloodTypes = Array.from(document.querySelectorAll('#eventBloodTypes input:checked')).map(function (input) {
                return input.value;
            });

            return {
                title: document.getElementById('eventTitle').value.trim(),
                status: document.getElementById('eventStatus').value,
                description: document.getElementById('eventDescription').value.trim(),
                location_name: document.getElementById('eventVenue').value.trim(),
                address: document.getElementById('eventAddress').value.trim(),
                event_date: document.getElementById('eventDate').value,
                start_time: document.getElementById('eventStartTime').value,
                end_time: document.getElementById('eventEndTime').value,
                max_capacity: document.getElementById('eventMaxCapacity').value,
                latitude: document.getElementById('eventLatitude').value || null,
                longitude: document.getElementById('eventLongitude').value || null,
                blood_types_needed: checkedBloodTypes
            };
        }

        function saveEvent(event) {
            event.preventDefault();
            setError('');

            var eventId = document.getElementById('eventId').value;
            var url = eventId ? storeUrl.replace(/\/$/, '') + '/' + eventId : storeUrl;
            var method = eventId ? 'PUT' : 'POST';

            fetch(url, {
                method: method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(collectPayload())
            })
                .then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (body) {
                        if (!response.ok) {
                            var message = body.message || 'Unable to save donation event.';
                            if (body.errors) {
                                var firstKey = Object.keys(body.errors)[0];
                                message = firstKey ? body.errors[firstKey][0] : message;
                            }
                            throw new Error(message);
                        }
                        return body;
                    });
                })
                .then(function () {
                    modal && modal.hide();
                    loadEvents();
                    Swal.fire({ title: 'Saved', text: 'Donation event has been saved.', icon: 'success', timer: 1800, showConfirmButton: false });
                })
                .catch(function (error) {
                    setError(error && error.message ? error.message : 'Unable to save donation event.');
                });
        }

        function showDetails(event) {
            Swal.fire({
                title: escapeHtml(event.event_name),
                html: ''
                    + '<p class="mb-1"><strong>Venue:</strong> ' + escapeHtml(event.venue || '-') + '</p>'
                    + '<p class="mb-1"><strong>Date:</strong> ' + escapeHtml(formatDate(event.event_date)) + '</p>'
                    + '<p class="mb-1"><strong>Slots:</strong> ' + escapeHtml(event.booked_slots + ' booked, ' + event.remaining_slots + ' remaining') + '</p>'
                    + '<p class="mb-0"><strong>Blood Types:</strong> ' + escapeHtml((event.blood_types_needed || []).join(', ') || 'Any') + '</p>',
                icon: 'info'
            });
        }

        function deleteEvent(event) {
            Swal.fire({
                title: 'Delete Event?',
                text: 'Appointments linked to this event will keep their appointment records.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#b91c1c',
                confirmButtonText: 'Delete'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                fetch(storeUrl.replace(/\/$/, '') + '/' + event.event_id, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    }
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Unable to delete donation event.');
                        return response.json();
                    })
                    .then(function () {
                        loadEvents();
                        Swal.fire({ title: 'Deleted', text: 'Donation event has been deleted.', icon: 'success', timer: 1800, showConfirmButton: false });
                    })
                    .catch(function (error) {
                        Swal.fire('Error', error && error.message ? error.message : 'Unable to delete donation event.', 'error');
                    });
            });
        }

        document.getElementById('createEventBtn').addEventListener('click', function () { openModal(null); });
        form.addEventListener('submit', saveEvent);

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                state.search = searchInput.value.trim();
                state.page = 1;
                loadEvents();
            }, 250);
        });

        statusFilter.addEventListener('change', function () {
            state.status = statusFilter.value;
            state.page = 1;
            loadEvents();
        });

        paginationPages.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-page]');
            if (!button) return;
            state.page = Number(button.dataset.page || '1');
            loadEvents();
        });

        tableBody.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-action]');
            if (!button) return;
            var row = button.closest('tr[data-event-id]');
            var item = row ? rowsById[String(row.dataset.eventId)] : null;
            if (!item) return;

            if (button.dataset.action === 'details') showDetails(item);
            if (button.dataset.action === 'edit') openModal(item);
            if (button.dataset.action === 'delete') deleteEvent(item);
        });

        renderBloodTypeInputs([]);
        loadEvents();
    })();
</script>
@endpush
