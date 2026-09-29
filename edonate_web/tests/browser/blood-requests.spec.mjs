import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

const publicRoot = path.resolve('../public_html');
const fixturePath = path.resolve('tests/browser/fixtures/admin-blood-requests.html');
const contentTypes = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml' };

async function mount(page, options = {}) {
    const state = { saves: 0, payload: null, created: false };
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.hostname !== 'audit.test') return route.abort();
        if (url.pathname === '/fixture') {
            let html = await readFile(fixturePath, 'utf8');
            const manifest = JSON.parse(await readFile(path.resolve('../public_html/build/manifest.json'), 'utf8'));
            html = html
                .replace(/build\/assets\/adminlte-[^"']+\.css/g, `build/${manifest['resources/css/adminlte.css'].file}`)
                .replace(/build\/assets\/adminlte-[^"']+\.js/g, `build/${manifest['resources/js/adminlte.js'].file}`);
            return route.fulfill({ contentType: 'text/html', body: html });
        }
        if (url.pathname === '/privacy/preferences') {
            return route.fulfill({ json: { choices: { necessary: true, analytics: false, maps: false, decided: true, expires_at: Math.floor(Date.now() / 1000) + 86400 } } });
        }
        if (url.pathname === '/admin/blood-requests/data') {
            const row = {
                request_reference: 'BR-2026-000001', facility_name: 'Test Facility', request_type: 'blood_request',
                needed_blood_type: 'O+', required_donors: 1, specific_match_required: 1, notified_count: 0,
                interested_count: 0, urgency: 'urgent', status: 'open', created_at: new Date().toISOString(), show_url: '/admin/blood-requests/1',
            };
            const rows = state.created ? [row] : [];
            return route.fulfill({ json: { data: rows, summary: { open: rows.length, emergency: 0, fulfilled: 0, cancelled: 0 }, meta: { current_page: 1, last_page: 1, per_page: 10, total: rows.length, from: rows.length ? 1 : null, to: rows.length || null } } });
        }
        if (url.pathname === '/admin/blood-requests' && route.request().method() === 'POST') {
            state.saves++;
            state.payload = route.request().postDataJSON();
            if (options.validationFailure) {
                return route.fulfill({ status: 422, json: { message: 'The given data was invalid.', errors: {
                    required_donors: ['The required donors field must be at least 1.'],
                    expires_at: ['The expires at field must be a date after now.'],
                } } });
            }
            await new Promise(resolve => setTimeout(resolve, 250));
            state.created = true;
            return route.fulfill({ status: 201, json: { message: 'Blood request created.', request: { request_id: 1 } } });
        }
        const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(publicRoot + path.sep)) return route.abort();
        try { return await route.fulfill({ contentType: contentTypes[path.extname(file)] || 'application/octet-stream', body: await readFile(file) }); }
        catch (_) { return route.fulfill({ json: { success: true, data: [], summary: {}, meta: { total: 0 } } }); }
    });

    await page.goto('http://audit.test/fixture');
    await page.waitForLoadState('networkidle');
    const privacyPanel = page.locator('#ed-privacy-panel');
    if (await privacyPanel.isVisible().catch(() => false)) {
        await privacyPanel.getByRole('button', { name: 'Reject optional services' }).click();
    }
    await page.evaluate(() => {
        document.querySelector('[name="facility_id"]').add(new Option('Test Facility', '1'));
        document.querySelector('[name="needed_blood_type_id"]').add(new Option('O+', '4'));
    });
    await expect(page.locator('#requestOpen')).toHaveText('0');
    return state;
}

test('blood request validation is field-specific and preserves form values', async ({ page }) => {
    await mount(page, { validationFailure: true });
    await page.getByRole('button', { name: 'Create Request' }).click();
    await page.locator('[name="required_donors"]').fill('17');
    await page.locator('[name="expires_at"]').fill('2026-10-01T12:00');
    await page.getByRole('button', { name: 'Save Request' }).click();

    await expect(page.locator('[data-validation-for="required_donors"]')).toHaveText('The required donors field must be at least 1.');
    await expect(page.locator('[data-validation-for="expires_at"]')).toContainText('must be a date after now');
    await expect(page.locator('[name="required_donors"]')).toHaveValue('17');
    await expect(page.locator('[name="expires_at"]')).toHaveValue('2026-10-01T12:00');
    await expect(page.locator('#requestModal')).toBeVisible();
});

test('rapid repeated Save Request submissions create one request and refresh list counts', async ({ page }) => {
    const state = await mount(page);
    await page.getByRole('button', { name: 'Create Request' }).click();
    await page.locator('[name="expires_at"]').fill('2026-10-01T12:00');

    await Promise.all([
        page.waitForResponse(response => response.url().includes('/admin/blood-requests') && response.request().method() === 'POST'),
        page.evaluate(() => {
            const form = document.getElementById('requestForm');
            form.requestSubmit();
            form.requestSubmit();
        }),
    ]);
    await expect(page.locator('#requestModal')).toBeHidden();
    await expect(page.locator('#requestOpen')).toHaveText('1');
    await expect(page.locator('#requestRows')).toContainText('BR-2026-000001');
    expect(state.saves).toBe(1);
    expect(state.payload.submission_key).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
    expect(state.payload.expires_at).toMatch(/Z$/);
});
