@extends('layouts.admin')

@section('title', 'eDonate - Event Management')
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

@section('header_title', 'Event Management')
@section('header_subtitle', 'Manage donation events, capacity, and booked donors')

@section('header_slot')
    <button class="hamburger" id="donationEventsHamburger" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="donationEventsSidebar">
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
        <span class="hamburger__bar"></span>
    </button>
@endsection

@section('header_actions')
    <button class="btn btn-danger fw-semibold" type="button" id="createEventBtn">Create Event</button>
@endsection

@section('admin_page_data')
{!! json_encode([
    'page' => 'donation-events',
    'donationEvents' => $donationEventPayload ?? [
        'api' => [
            'listUrl' => '',
            'storeUrl' => '',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
@endsection

@section('main_content')
<main class="event-main container-fluid px-0">
    <div class="event-shell">
        <section class="event-stats row g-3" aria-label="Donation event summary">
            <div class="col-6 col-xl-3">
                <article class="stat-card h-100">
                    <p class="event-stat__label">Upcoming Events</p>
                    <p class="event-stat__value" id="eventStatUpcoming">0</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card h-100">
                    <p class="event-stat__label">Open Events</p>
                    <p class="event-stat__value" id="eventStatOpen">0</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card h-100">
                    <p class="event-stat__label">Confirmed Bookings</p>
                    <p class="event-stat__value" id="eventStatBookings">0</p>
                </article>
            </div>
            <div class="col-6 col-xl-3">
                <article class="stat-card h-100">
                    <p class="event-stat__label">Full Events</p>
                    <p class="event-stat__value" id="eventStatFull">0</p>
                </article>
            </div>
        </section>

        <form class="event-toolbar row g-3 align-items-center" role="search" aria-label="Filter donation events" onsubmit="return false;">
            <div class="col-12 col-lg">
                <input id="eventSearchInput" type="search" class="form-control" placeholder="Search by event, location, or address" aria-label="Search donation events">
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <input id="eventDateFilter" type="date" class="form-control" aria-label="Filter by date">
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <select id="eventStatusFilter" class="form-select" aria-label="Filter by status">
                    <option value="">All Status</option>
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </form>

        <section class="event-table table-responsive" aria-label="Donation event list">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Location</th>
                        <th>Date &amp; Time</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="eventTableBody">
                    <tr><td colspan="7" class="text-muted">Loading events...</td></tr>
                </tbody>
            </table>
        </section>

        <div class="event-pagination" aria-label="Donation event pagination">
            <span id="eventPaginationInfo">Showing 0 to 0 of 0 events</span>
            <div id="eventPaginationPages"></div>
        </div>
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
                            <label for="eventTitle" class="form-label fw-semibold">Title</label>
                            <input type="text" id="eventTitle" class="form-control" maxlength="150" required>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label for="eventStatus" class="form-label fw-semibold">Status</label>
                            <select id="eventStatus" class="form-select" required>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="eventLocation" class="form-label fw-semibold">Location Name</label>
                            <input type="text" id="eventLocation" class="form-control" maxlength="150" required>
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
                            <input type="time" id="eventStartTime" class="form-control" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label for="eventEndTime" class="form-label fw-semibold">End Time</label>
                            <input type="time" id="eventEndTime" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="eventMaxCapacity" class="form-label fw-semibold">Maximum Capacity</label>
                            <input type="number" id="eventMaxCapacity" class="form-control" min="1" max="100000" required>
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
        var csrfToken = @json(csrf_token());
        var state = { page: 1, perPage: 10, search: '', status: '', date: '' };
        var rowsById = {};
        var searchTimer = null;

        var tableBody = document.getElementById('eventTableBody');
        var paginationInfo = document.getElementById('eventPaginationInfo');
        var paginationPages = document.getElementById('eventPaginationPages');
        var searchInput = document.getElementById('eventSearchInput');
        var statusFilter = document.getElementById('eventStatusFilter');
        var dateFilter = document.getElementById('eventDateFilter');
        var modalElement = document.getElementById('eventModal');
        var modal = modalElement && typeof bootstrap !== 'undefined' ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
        var form = document.getElementById('eventForm');
        var errorBox = document.getElementById('eventFormError');

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function formatNumber(value) {
            return new Intl.NumberFormat('en-US').format(Number(value || 0));
        }

        function formatDate(value) {
            if (!value) return '-';
            var parsed = new Date(value + 'T00:00:00');
            return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function formatTime(value) {
            if (!value) return '-';
            var parsed = new Date('1970-01-01T' + String(value).slice(0, 5) + ':00');
            return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }

        function statusLabel(status) {
            return { open: 'Open', closed: 'Closed', completed: 'Completed', cancelled: 'Cancelled' }[String(status || '').toLowerCase()] || 'Closed';
        }

        function updateStats(stats) {
            document.getElementById('eventStatUpcoming').textContent = formatNumber((stats.open || 0) + (stats.closed || 0));
            document.getElementById('eventStatOpen').textContent = formatNumber(stats.open || 0);
            document.getElementById('eventStatBookings').textContent = formatNumber(stats.total_confirmed_bookings || 0);
            document.getElementById('eventStatFull').textContent = formatNumber(stats.full || 0);
        }

        function renderRows(rows) {
            rowsById = {};
            if (!Array.isArray(rows) || rows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" class="text-muted">No donation events found.</td></tr>';
                return;
            }

            tableBody.innerHTML = rows.map(function (event) {
                rowsById[String(event.event_id)] = event;
                var capacityLabel = formatNumber(event.confirmed_count) + ' / ' + formatNumber(event.max_capacity);
                var remainingLabel = event.availability_status === 'full' ? 'Full' : (formatNumber(event.remaining_slots) + ' remaining');
                var statusClass = event.availability_status === 'full' ? 'full' : event.status;

                return ''
                    + '<tr data-event-id="' + escapeHtml(event.event_id) + '">'
                    + '<td><div class="event-title">' + escapeHtml(event.title) + '</div><div class="event-meta">EV' + String(event.event_id).padStart(3, '0') + '</div></td>'
                    + '<td><div>' + escapeHtml(event.location_name || '-') + '</div><div class="event-meta">' + escapeHtml(event.address || '') + '</div></td>'
                    + '<td><div>' + escapeHtml(formatDate(event.event_date)) + '</div><div class="event-meta">' + escapeHtml(formatTime(event.start_time)) + ' - ' + escapeHtml(formatTime(event.end_time)) + '</div></td>'
                    + '<td><div class="fw-semibold">' + capacityLabel + '</div><div class="event-meta">' + escapeHtml(remainingLabel) + '</div></td>'
                    + '<td><span class="event-badge event-badge--' + escapeHtml(statusClass) + '">' + escapeHtml(statusClass === 'full' ? 'Full' : statusLabel(event.status)) + '</span></td>'
                    + '<td>' + escapeHtml(event.created_by || '-') + '</td>'
                    + '<td><div class="event-actions">'
                    + '<a class="event-action-btn text-decoration-none" href="' + escapeHtml(storeUrl.replace(/\/$/, '') + '/' + event.event_id) + '">Donors</a>'
                    + '<button type="button" class="event-action-btn" data-action="edit">Edit</button>'
                    + '<button type="button" class="event-action-btn" data-action="open">Open</button>'
                    + '<button type="button" class="event-action-btn" data-action="close">Close</button>'
                    + '<button type="button" class="event-action-btn" data-action="complete">Complete</button>'
                    + '<button type="button" class="event-action-btn event-action-btn--danger" data-action="cancel">Cancel</button>'
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
            if (state.date) url.searchParams.set('date', state.date);
            return url.toString();
        }

        function loadEvents() {
            if (!listUrl) return;
            tableBody.innerHTML = '<tr><td colspan="7" class="text-muted">Loading events...</td></tr>';
            fetch(requestUrl(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
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

        function setError(message) {
            errorBox.textContent = message || '';
            errorBox.style.display = message ? 'block' : 'none';
        }

        function openModal(event) {
            setError('');
            document.getElementById('eventId').value = event ? event.event_id : '';
            document.getElementById('eventTitle').value = event ? event.title : '';
            document.getElementById('eventStatus').value = event ? event.status : 'open';
            document.getElementById('eventLocation').value = event ? event.location_name : '';
            document.getElementById('eventAddress').value = event ? event.address : '';
            document.getElementById('eventDate').value = event ? event.event_date : '';
            document.getElementById('eventStartTime').value = event && event.start_time ? String(event.start_time).slice(0, 5) : '';
            document.getElementById('eventEndTime').value = event && event.end_time ? String(event.end_time).slice(0, 5) : '';
            document.getElementById('eventMaxCapacity').value = event ? event.max_capacity : '';
            document.getElementById('eventModalTitle').textContent = event ? 'Edit Donation Event' : 'Create Donation Event';
            document.getElementById('eventSubmitBtn').textContent = event ? 'Update Event' : 'Create Event';
            modal && modal.show();
        }

        function collectPayload() {
            return {
                title: document.getElementById('eventTitle').value.trim(),
                status: document.getElementById('eventStatus').value,
                location_name: document.getElementById('eventLocation').value.trim(),
                address: document.getElementById('eventAddress').value.trim(),
                event_date: document.getElementById('eventDate').value,
                start_time: document.getElementById('eventStartTime').value,
                end_time: document.getElementById('eventEndTime').value,
                max_capacity: document.getElementById('eventMaxCapacity').value
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

        function patchEvent(event, action) {
            var text = action === 'cancel'
                ? 'Future active appointments for this event will be cancelled.'
                : 'This will update the event status.';
            Swal.fire({
                title: action.charAt(0).toUpperCase() + action.slice(1) + ' event?',
                text: text,
                input: action === 'cancel' ? 'textarea' : undefined,
                inputPlaceholder: 'Optional cancellation reason',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'cancel' ? '#b91c1c' : '#991b1b',
                confirmButtonText: 'Confirm'
            }).then(function (result) {
                if (!result.isConfirmed) return;
                fetch(storeUrl.replace(/\/$/, '') + '/' + event.event_id + '/' + action, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ reason: result.value || null })
                })
                    .then(function (response) {
                        return response.json().catch(function () { return {}; }).then(function (body) {
                            if (!response.ok) throw new Error(body.message || 'Unable to update event.');
                            return body;
                        });
                    })
                    .then(function () {
                        loadEvents();
                        Swal.fire({ title: 'Updated', text: 'Donation event status has been updated.', icon: 'success', timer: 1800, showConfirmButton: false });
                    })
                    .catch(function (error) {
                        Swal.fire('Error', error && error.message ? error.message : 'Unable to update event.', 'error');
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

        dateFilter.addEventListener('change', function () {
            state.date = dateFilter.value;
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
            if (button.dataset.action === 'edit') {
                openModal(item);
                return;
            }
            patchEvent(item, button.dataset.action);
        });

        loadEvents();
    })();
</script>
@endpush
