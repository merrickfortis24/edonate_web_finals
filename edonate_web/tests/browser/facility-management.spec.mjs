import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

const publicRoot = path.resolve('../public_html');
const types = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png' };
const baseFacility = {
    facility_id: 1, facility_name: 'Test Hospital', facility_type: 'hospital', address: 'Original address',
    barangay_name: 'Barangay 1', city: 'Lipa City', province: 'Batangas', contact_number: '09170000000',
    latitude: 13.94, longitude: 121.16, status: 'active', mapped: true, total_available_units: 5, low_or_out_types: 2,
    inventory_url: '/admin/facilities/1/inventory',
};

async function mount(page, options = {}) {
    const state = {
        facilities: [{ ...baseFacility }, { ...baseFacility, facility_id: 2, facility_name: 'Inactive Clinic', status: 'inactive' }],
        saved: [], tileRequests: 0,
    };
    await page.route('**/*', async route => {
        const url = new URL(route.request().url()), pathname = url.pathname;
        if (url.hostname !== 'audit.test') {
            if (url.hostname.endsWith('.basemaps.cartocdn.com')) {
                state.tileRequests++;
                if (options.tileFailure) return route.abort();
                return route.fulfill({ status: 200, contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/f24AAAAASUVORK5CYII=', 'base64') });
            }
            return route.abort();
        }
        if (pathname === '/fixture') {
            let html = await readFile(`tests/browser/fixtures/${options.mapFixture ? 'admin-map' : 'admin-facilities'}.html`, 'utf8');
            html = html.replace(/([?&]key=)[^&\s"'<>]+/g, '$1browser-test-key');
            expect(html).not.toContain('maps.googleapis.com');
            return route.fulfill({ contentType: 'text/html', body: html });
        }
        if (pathname === '/privacy/preferences') return route.fulfill({ json: { choices: { ...route.request().postDataJSON(), necessary: true, analytics: false, decided: true, expires_at: Math.floor(Date.now() / 1000) + 86400 } } });
        if (pathname === '/admin/facilities/data') {
            const status = url.searchParams.get('status');
            const rows = state.facilities.filter(row => !status || row.status === status);
            return route.fulfill({ json: { data: rows, summary: {
                total: state.facilities.length,
                active: state.facilities.filter(row => row.status === 'active').length,
                inactive: state.facilities.filter(row => row.status === 'inactive').length,
                mapped: state.facilities.filter(row => row.status === 'active' && row.latitude !== null).length,
            }, meta: { current_page: 1, last_page: 1, total: rows.length, per_page: 10, from: 1, to: rows.length } } });
        }
        if (pathname === '/admin/blood-availability/facilities') {
            const facilities = state.facilities.filter(row => row.status === 'active').map(row => ({
                ...row, blood_types: { 'A+': { units: 5, status: 'available' } }, last_updated: null,
            }));
            const mapPoints = facilities.filter(row => Number.isFinite(Number(row.latitude)) && Number.isFinite(Number(row.longitude)));
            return route.fulfill({ json: { facilities, map_points: mapPoints, summary: {
                facilities: facilities.length, mapped_facilities: mapPoints.length, total_units: facilities.length * 5,
                facilities_with_low_stock: 0, facilities_with_out_of_stock: 0,
            } } });
        }
        if (pathname === '/admin/map/map-data') return route.fulfill({ json: { barangays: [], map_points: [], summary: {} } });
        if (pathname.endsWith('/status')) {
            const id = Number(pathname.split('/').at(-2));
            state.facilities.find(row => row.facility_id === id).status = route.request().postDataJSON().status;
            return route.fulfill({ json: { message: 'Facility status updated.' } });
        }
        if (pathname.match(/^\/admin\/facilities(?:\/\d+)?$/) && route.request().method() !== 'GET') {
            const payload = route.request().postDataJSON();
            state.saved.push(payload);
            if (options.validationFailure) return route.fulfill({ status: 422, json: { message: 'Validation failed.', errors: { contact_number: ['The contact number must not exceed 30 characters.'] } } });
            if (options.saveFailure) return route.fulfill({ status: 500, json: { message: 'PRIVATE SQL INTERNAL ERROR' } });
            const id = Number(pathname.match(/\d+$/)?.[0] || 0);
            if (id) Object.assign(state.facilities.find(row => row.facility_id === id), payload, { latitude: Number(payload.latitude), longitude: Number(payload.longitude) });
            else state.facilities.push({ ...baseFacility, ...payload, facility_id: 3, latitude: Number(payload.latitude), longitude: Number(payload.longitude) });
            return route.fulfill({ status: id ? 200 : 201, json: { message: id ? 'Facility updated.' : 'Facility created.' } });
        }
        const file = path.resolve(publicRoot, '.' + decodeURIComponent(pathname));
        if (!file.startsWith(publicRoot + path.sep)) return route.abort();
        try { return await route.fulfill({ contentType: types[path.extname(file)] || 'application/octet-stream', body: await readFile(file) }); }
        catch (_) { return route.fulfill({ json: { data: [], unread_count: 0, meta: { total: 0 }, success: true } }); }
    });
    await page.goto('http://audit.test/fixture');
    await page.locator('#ed-privacy-form input[name="maps"]').check();
    await page.getByRole('button', { name: 'Save selected choices' }).click();
    if (options.mapFixture) await expect(page.locator('.availability-table')).toHaveCount(1);
    else await expect(page.locator('#facilityRows')).toContainText('Test Hospital');
    return state;
}

async function fillManualAddress(page, name = 'New Clinic') {
    await page.getByRole('button', { name: 'Create Facility', exact: true }).click();
    await expect(page.locator('#facilityModal')).toBeVisible();
    await page.getByLabel(/^Facility name/).fill(name);
    await page.getByLabel('Address', { exact: true }).fill('45 P. Torres Street');
    await page.getByLabel('Barangay', { exact: true }).fill('Poblacion');
    await page.getByLabel('City / municipality').fill('Lipa City');
    await page.getByLabel('Province').fill('Batangas');
    await expect(page.locator('#facilityPinMap')).toBeVisible();
}

async function placePin(page, position = { x: 80, y: 90 }) {
    await page.locator('#facilityPinMap').click({ position });
    await expect(page.locator('#facilityPinStatus')).toContainText('Pin selected at');
    const lat = Number(await page.locator('#facilityForm [name="latitude"]').inputValue());
    const lng = Number(await page.locator('#facilityForm [name="longitude"]').inputValue());
    expect(lat).toBeGreaterThanOrEqual(-90); expect(lat).toBeLessThanOrEqual(90);
    expect(lng).toBeGreaterThanOrEqual(-180); expect(lng).toBeLessThanOrEqual(180);
    expect(lat === 0 && lng === 0).toBe(false);
    await expect(page.locator('#facilityForm [name="location_pin_selected"]')).toHaveValue('1');
    return { lat, lng };
}

for (const width of [1280, 375, 320]) {
    test(`manual address and map pin save at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const state = await mount(page);
        await fillManualAddress(page);
        const position = await placePin(page);
        await expect(page.locator('#facilityForm input[name="latitude"][type="hidden"]')).toHaveCount(1);
        await expect(page.locator('#facilityForm input[name="longitude"][type="hidden"]')).toHaveCount(1);
        await expect(page.locator('#facilityForm input[name="latitude"]:visible, #facilityForm input[name="longitude"]:visible')).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        const a11y = await new AxeBuilder({ page }).include('#facilityModal').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        expect(a11y.violations).toEqual([]);
        await page.getByLabel('I have reviewed and confirmed the facility address and pin position.').check();
        await page.getByRole('button', { name: 'Save Facility' }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
        expect(state.saved).toHaveLength(1);
        expect(state.saved[0].address).toBe('45 P. Torres Street');
        expect(state.saved[0].barangay_name).toBe('Poblacion');
        expect(Number(state.saved[0].latitude)).toBe(position.lat);
        expect(Number(state.saved[0].longitude)).toBe(position.lng);
        await expect(page.locator('#facilityNotice')).toHaveText('Facility created.');
    });
}

test('pin is required with a clear error and can then be selected to save', async ({ page }) => {
    const state = await mount(page);
    await fillManualAddress(page);
    await page.getByLabel('I have reviewed and confirmed the facility address and pin position.').check();
    await page.getByRole('button', { name: 'Save Facility' }).click();
    await expect(page.locator('#facilities-location_pin-error')).toContainText('Choose the facility position');
    expect(state.saved).toHaveLength(0);
    await placePin(page);
    await page.getByLabel('I have reviewed and confirmed the facility address and pin position.').check();
    await page.getByRole('button', { name: 'Save Facility' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    expect(state.saved).toHaveLength(1);
});

test('editing shows the saved position and moving the pin saves the updated position', async ({ page }) => {
    const state = await mount(page);
    await page.locator('#facilityRows tr').first().getByRole('button', { name: 'Edit', exact: true }).click();
    await expect.poll(async () => Number(await page.locator('#facilityForm [name="latitude"]').inputValue())).toBeCloseTo(13.94, 6);
    await expect(page.locator('#facilityPinStatus')).toContainText('13.940000');
    const newPosition = await placePin(page, { x: 200, y: 180 });
    await page.getByLabel('I have reviewed and confirmed the facility address and pin position.').check();
    await page.getByRole('button', { name: 'Save Facility' }).click();
    expect(state.saved).toHaveLength(1);
    expect(Number(state.saved[0].latitude)).toBe(newPosition.lat);
    expect(Number(state.saved[0].longitude)).toBe(newPosition.lng);
    expect(state.facilities).toHaveLength(2);
});

test('server validation and database errors are field-specific and do not reveal private details', async ({ page }) => {
    const state = await mount(page, { validationFailure: true });
    await fillManualAddress(page);
    await placePin(page);
    await page.getByLabel('I have reviewed and confirmed the facility address and pin position.').check();
    await page.getByRole('button', { name: 'Save Facility' }).click();
    await expect(page.locator('#facilities-contact_number-error')).toContainText('contact number');
    await expect(page.getByRole('dialog')).toBeVisible();
    expect(state.saved).toHaveLength(1);

    // A separate page keeps this test's synthetic server-failure response independent.
    const failurePage = await page.context().newPage();
    await mount(failurePage, { saveFailure: true });
    await fillManualAddress(failurePage);
    await placePin(failurePage);
    await failurePage.getByLabel('I have reviewed and confirmed the facility address and pin position.').check();
    await failurePage.getByRole('button', { name: 'Save Facility' }).click();
    await expect(failurePage.locator('#facilityErrors')).toContainText('could not be saved');
    await expect(failurePage.locator('#facilityErrors')).not.toContainText('PRIVATE SQL');
    await expect(failurePage.getByRole('dialog')).toBeVisible();
});

test('Active and Inactive icons stay consistent when facility status changes', async ({ page }) => {
    await mount(page);
    await expect(page.locator('#facilityRows tr').first().locator('.bi-check-circle-fill')).toBeVisible();
    await expect(page.locator('#facilityRows tr').nth(1).locator('.bi-slash-circle')).toBeVisible();
    page.on('dialog', dialog => dialog.accept());
    await page.locator('#facilityRows tr').first().getByRole('button', { name: 'Deactivate', exact: true }).click();
    await expect(page.locator('#facilityInactive')).toHaveText('2');
    await expect(page.locator('#facilityRows tr').first().locator('.bi-slash-circle')).toBeVisible();
    await page.locator('#facilityRows tr').first().getByRole('button', { name: 'Activate', exact: true }).click();
    await expect(page.locator('#facilityActive')).toHaveText('1');
    await expect(page.locator('#facilityRows tr').first().locator('.bi-check-circle-fill')).toBeVisible();
});

test('updated facility coordinates are displayed on the existing Leaflet facility map', async ({ page }) => {
    const state = await mount(page, { mapFixture: true });
    await page.getByRole('button', { name: 'Facility Inventory', exact: true }).click();
    await expect(page.locator('#availabilityTableBody')).toContainText('Test Hospital');
    await expect(page.locator('#availabilityMap')).toBeVisible();
    await expect.poll(() => page.locator('#availabilityMap .leaflet-marker-icon').count()).toBe(1);
    expect(state.tileRequests).toBeGreaterThan(0);
});

test('CARTO tile failure leaves the facility inventory table available', async ({ page }) => {
    await mount(page, { mapFixture: true, tileFailure: true });
    await page.getByRole('button', { name: 'Facility Inventory', exact: true }).click();
    await expect(page.locator('#mapStatus')).toContainText('Map tiles could not be loaded');
    await expect(page.locator('#availabilityTableBody')).toContainText('Test Hospital');
    await expect(page.locator('.availability-table')).toBeVisible();
});
