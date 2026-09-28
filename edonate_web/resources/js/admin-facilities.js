const root = document.getElementById('facilityManagement');
if (root) initialiseFacilities(JSON.parse(root.dataset.config));

function initialiseFacilities(config) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const rows = document.getElementById('facilityRows'), links = document.getElementById('facilityPaginationLinks');
    const metaLabel = document.getElementById('facilityMeta');
    const notice = document.getElementById('facilityNotice');
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const headline = v => String(v ?? '').replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
    let page = 1, cache = [], filterTimer, loadRevision = 0;
    function showNotice(message, isError = false) {
        notice.textContent = message;
        notice.classList.toggle('alert-success', !isError);
        notice.classList.toggle('alert-danger', isError);
        notice.classList.remove('d-none');
    }
    async function request(url, options = {}) {
        let response;
        try {
            response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' } });
        } catch (error) {
            if (error.name === 'AbortError') throw error;
            throw { message: 'Unable to connect. Check your connection and try again.' };
        }
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw {
            ...data,
            message: response.status >= 500 ? 'The facility could not be saved. Please contact the system administrator if this continues.'
                : response.status === 419 ? 'Your session expired. Refresh this page and sign in again.'
                    : response.status === 429 ? 'Too many requests. Please wait before trying again.' : data.message,
        };
        return data;
    }
    async function load() {
        const revision = ++loadRevision, query = new URLSearchParams({ page });
        for (const [key, id] of [['search', 'facilitySearch'], ['facility_type', 'facilityTypeFilter'], ['status', 'facilityStatusFilter']]) {
            const value = document.getElementById(id).value.trim(); if (value) query.set(key, value);
        }
        rows.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';
        try {
            const response = await request(`${config.data}?${query}`);
            if (revision !== loadRevision) return;
            page = Number(response.meta?.current_page || page);
            if (page > 1 && !response.data?.length) { page--; return load(); }
            cache = response.data || [];
            for (const [id, key] of [['facilityTotal', 'total'], ['facilityActive', 'active'], ['facilityInactive', 'inactive'], ['facilityMapped', 'mapped']]) document.getElementById(id).textContent = response.summary?.[key] ?? 0;
            window.eDonateAdminPagination?.render(links, response.meta || {}, null, { infoElement: metaLabel });
            rows.innerHTML = cache.length ? cache.map((row, index) => `<tr>
                <td><strong>${esc(row.facility_name)}</strong><br><small class="text-muted">${esc(row.contact_number || 'No contact')}</small></td>
                <td>${esc(headline(row.facility_type))}</td><td>${esc([row.barangay_name, row.city, row.province].filter(Boolean).join(', '))}</td>
                <td>${window.eDonateFacilityStatus(row.status)}</td><td>${esc(row.total_available_units)}</td><td>${esc(row.low_or_out_types)}</td>
                <td>${row.mapped ? 'Mapped' : 'Location not mapped'}</td><td><div class="facility-actions">
                <a class="btn btn-sm btn-outline-danger" href="${esc(row.inventory_url)}">Inventory</a>
                ${config.canManage ? `<button class="btn btn-sm btn-outline-secondary" data-edit="${index}" type="button">Edit</button><button class="btn btn-sm btn-outline-secondary" data-status="${index}" type="button">${row.status === 'active' ? 'Deactivate' : 'Activate'}</button>` : ''}
                </div></td></tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No facilities found.</td></tr>';
        } catch (_) {
            if (revision !== loadRevision) return;
            rows.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Could not load facilities. Please refresh to try again.</td></tr>';
            metaLabel.textContent = 'Showing 0 to 0 of 0 entries'; links.innerHTML = '';
        }
    }
    links.addEventListener('click', event => { const button = event.target.closest('button[data-page]'); if (button) { page = Number(button.dataset.page || 1); load(); } });
    for (const id of ['facilitySearch', 'facilityTypeFilter', 'facilityStatusFilter']) document.getElementById(id).addEventListener(id === 'facilitySearch' ? 'input' : 'change', () => {
        clearTimeout(filterTimer); filterTimer = setTimeout(() => { page = 1; load(); }, 250);
    });
    document.getElementById('facilityClear').onclick = () => { for (const id of ['facilitySearch', 'facilityTypeFilter', 'facilityStatusFilter']) document.getElementById(id).value = ''; page = 1; load(); };
    load(); if (!config.canManage) return;

    const form = document.getElementById('facilityForm'), modalElement = document.getElementById('facilityModal');
    const errorBox = document.getElementById('facilityErrors'), save = document.getElementById('facilitySave');
    const pinMapElement = document.getElementById('facilityPinMap'), pinStatus = document.getElementById('facilityPinStatus');
    const pinNotice = document.getElementById('facilityMapPrivacyNotice');
    const latitude = form.elements.latitude, longitude = form.elements.longitude;
    let editing = null, pickerMap = null, marker = null;
    const modal = () => window.bootstrap.Modal.getOrCreateInstance(modalElement);
    const addressFields = ['address', 'barangay_name', 'city', 'province'];
    const startView = [13.9419, 121.1644];
    const validPoint = (lat, lng) => Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))
        && Number(lat) >= -90 && Number(lat) <= 90 && Number(lng) >= -180 && Number(lng) <= 180
        && !(Number(lat) === 0 && Number(lng) === 0);

    function clearErrors() {
        errorBox.classList.add('d-none'); errorBox.textContent = '';
        form.querySelectorAll('.is-invalid').forEach(field => { field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid'); field.removeAttribute('aria-errormessage'); });
        form.querySelectorAll('.invalid-feedback').forEach(field => field.remove());
    }
    function errors(error) {
        clearErrors(); const entries = Object.entries(error.errors || {});
        errorBox.textContent = entries.length ? 'Please correct the highlighted fields.' : (error.message || 'Unable to save the facility. Please try again.');
        errorBox.classList.remove('d-none');
        for (const [name, messages] of entries) {
            const isPinError = ['location_pin_selected', 'latitude', 'longitude'].includes(name);
            const field = isPinError ? pinMapElement : form.elements[name], anchor = isPinError ? pinStatus : field;
            if (!field) continue;
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block'; feedback.id = `facilities-${isPinError ? 'location_pin' : name}-error`; feedback.textContent = messages.join(' ');
            field.classList.add('is-invalid'); field.setAttribute('aria-invalid', 'true'); field.setAttribute('aria-errormessage', feedback.id); anchor.after(feedback);
        }
        errorBox.focus();
    }
    function formatPinStatus(lat, lng) {
        pinStatus.textContent = `Pin selected at ${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}. Drag it or click elsewhere on the map to adjust.`;
    }
    function savePin(latlng) {
        if (!validPoint(latlng.lat, latlng.lng)) return;
        latitude.value = Number(latlng.lat).toFixed(7); longitude.value = Number(latlng.lng).toFixed(7);
        form.elements.location_pin_selected.value = '1';
        if (!marker) {
            marker = window.L.marker(latlng, { draggable: true, title: 'Facility location pin', alt: 'Draggable facility location pin' }).addTo(pickerMap);
            marker.on('dragend', () => savePin(marker.getLatLng()));
        } else marker.setLatLng(latlng);
        formatPinStatus(latitude.value, longitude.value); clearErrors();
    }
    function clearPin() {
        if (marker && pickerMap) pickerMap.removeLayer(marker);
        marker = null; latitude.value = ''; longitude.value = '';
        form.elements.location_pin_selected.value = '0';
        pinStatus.textContent = 'Choose a pin position on the map.';
    }
    function selectedMapPoint() {
        if (validPoint(latitude.value, longitude.value) && form.elements.location_pin_selected.value === '1') {
            return [Number(latitude.value), Number(longitude.value)];
        }
        return null;
    }
    function ensurePickerMap() {
        const allowed = window.eDonatePrivacy?.allowed('maps') === true;
        pinNotice.hidden = allowed;
        pinMapElement.hidden = !allowed;
        if (!allowed) {
            if (pickerMap) { pickerMap.remove(); pickerMap = null; marker = null; }
            return;
        }
        if (!window.L) { pinStatus.textContent = 'The map is unavailable. Reload the page to try again.'; return; }
        const selected = selectedMapPoint();
        if (!pickerMap) {
            pickerMap = window.L.map(pinMapElement, { zoomControl: true }).setView(selected || startView, selected ? 16 : 12);
            window.L.tileLayer(config.tiles, {
                subdomains: 'abcd', maxZoom: 20,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            }).addTo(pickerMap).on('tileerror', () => {
                pinStatus.textContent = 'Map tiles could not be loaded. Check the map connection or configured tile key.';
            });
            pickerMap.on('click', event => savePin(event.latlng));
        }
        requestAnimationFrame(() => {
            pickerMap?.invalidateSize();
            if (selected) {
                savePin({ lat: selected[0], lng: selected[1] });
                pickerMap.setView(selected, 16);
            } else if (!marker) pickerMap.setView(startView, 12);
        });
    }
    document.addEventListener('edonate:privacy-changed', ensurePickerMap);
    modalElement.addEventListener('shown.bs.modal', ensurePickerMap);

    function open(row) {
        editing = row || null; form.reset(); clearErrors();
        if (row) {
            Object.keys(row).forEach(key => { if (form.elements[key] && row[key] !== null) form.elements[key].value = row[key]; });
            if (validPoint(row.latitude, row.longitude)) {
                latitude.value = String(row.latitude); longitude.value = String(row.longitude);
                form.elements.location_pin_selected.value = '1';
                formatPinStatus(row.latitude, row.longitude);
            } else clearPin();
        } else clearPin();
        document.getElementById('facilityModalTitle').textContent = row ? 'Edit Facility' : 'Create Facility';
        document.getElementById('facilityCurrentStatus').innerHTML = window.eDonateFacilityStatus(form.elements.status.value);
        modal().show();
        if (pickerMap) {
            const selected = selectedMapPoint();
            if (selected) { savePin({ lat: selected[0], lng: selected[1] }); pickerMap.setView(selected, 16); }
            else { clearPin(); pickerMap.setView(startView, 12); }
            requestAnimationFrame(() => pickerMap?.invalidateSize());
        }
    }
    document.getElementById('createFacilityButton').onclick = () => open(null);
    form.elements.status.onchange = () => { document.getElementById('facilityCurrentStatus').innerHTML = window.eDonateFacilityStatus(form.elements.status.value); };
    rows.addEventListener('click', async event => {
        const edit = event.target.closest('[data-edit]'), status = event.target.closest('[data-status]');
        if (edit) open(cache[Number(edit.dataset.edit)]); if (!status) return;
        const row = cache[Number(status.dataset.status)], next = row.status === 'active' ? 'inactive' : 'active';
        if (!confirm(`Set ${row.facility_name} as ${next}?`)) return; status.disabled = true;
        try { await request(`${config.store}/${row.facility_id}/status`, { method: 'PATCH', body: JSON.stringify({ status: next }) }); showNotice(`Facility is now ${next}.`); load(); }
        catch (error) { showNotice(error.message || 'Unable to change facility status.', true); } finally { status.disabled = false; }
    });
    addressFields.forEach(name => form.elements[name].addEventListener('input', () => {
        form.elements.location_confirmed.checked = false;
    }));
    modalElement.addEventListener('hidden.bs.modal', () => { form.elements.location_confirmed.checked = false; });
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (save.disabled) return;
        const payload = Object.fromEntries(new FormData(form)); payload.location_confirmed = form.elements.location_confirmed.checked;
        const invalid = {};
        for (const name of ['facility_name', 'city', 'province']) if (!String(payload[name] || '').trim()) invalid[name] = ['This field is required.'];
        if (payload.location_pin_selected !== '1' || !validPoint(payload.latitude, payload.longitude)) {
            invalid.location_pin_selected = ['Choose the facility position by clicking the map or dragging the pin.'];
        }
        if (!payload.location_confirmed) invalid.location_confirmed = ['Review and confirm the facility address and pin position.'];
        if (Object.keys(invalid).length) return errors({ errors: invalid });
        for (const name of ['address', 'barangay_name', 'contact_number']) if (payload[name] === '') payload[name] = null;
        save.disabled = true;
        try {
            const result = await request(editing ? `${config.store}/${editing.facility_id}` : config.store, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(payload) });
            modal().hide(); showNotice(result.message || 'Facility saved.'); load();
        } catch (error) { errors(error); } finally { save.disabled = false; }
    });
}
