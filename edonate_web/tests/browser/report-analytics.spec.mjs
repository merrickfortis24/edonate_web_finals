import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

const publicRoot = path.resolve('../public_html');
const contentTypes = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png' };

function payloadFor(params) {
    const range = params.get('range') || 'year';
    const startDate = params.get('start_date') || '2026-01-01';
    const endDate = params.get('end_date') || '2026-12-31';
    const facilityId = params.get('facility_id');
    const bloodTypeId = params.get('blood_type_id');
    const empty = startDate.startsWith('2019');
    const filteredType = bloodTypeId === '2';
    const filteredFacility = Boolean(facilityId);
    const metrics = [
        'total_donations', 'verified_donors', 'completed_donations', 'deferred_donations', 'failed_donations', 'success_rate',
        'donors_in_period', 'eligible_donors', 'upcoming_appointments', 'appointments_in_period', 'no_shows', 'deferred_on_site',
        'open_requests', 'emergency_requests', 'fulfilled_requests', 'events_in_period', 'low_stock_blood_types',
        'out_of_stock_blood_types', 'total_inventory_units', 'pending_verification', 'verified_donor_accounts',
    ];
    const summary = Object.fromEntries(metrics.map(key => [key, 0]));
    if (!empty) {
        summary.total_donations = filteredFacility ? 0 : (filteredType ? 3 : 2);
        summary.completed_donations = filteredFacility ? 0 : (filteredType ? 2 : 1);
        summary.donors_in_period = filteredFacility ? 0 : 1;
        summary.total_inventory_units = filteredType ? (filteredFacility ? 5 : 10) : 20;
    }
    const availability = Object.fromEntries(metrics.map(key => [key, !filteredFacility]));
    availability.total_inventory_units = true;
    availability.low_stock_blood_types = true;
    availability.out_of_stock_blood_types = true;
    const bloodType = filteredType ? { id: 2, label: 'A-' } : { id: 1, label: 'A+' };
    const inventory = filteredType
        ? [{ blood_type_id: bloodType.id, blood_type: bloodType.label, available_units: summary.total_inventory_units, reserved_units: 0, open_request_demand: 1, status: 'available', status_label: 'Available' }]
        : [
            { blood_type_id: 1, blood_type: 'A+', available_units: 10, reserved_units: 1, open_request_demand: 2, status: 'available', status_label: 'Available' },
            { blood_type_id: 2, blood_type: 'A-', available_units: 10, reserved_units: 0, open_request_demand: 1, status: 'available', status_label: 'Available' },
        ];
    const distributionItems = empty ? [] : [{ label: bloodType.label, count: filteredFacility ? 0 : 1, self_reported_count: 0 }];

    return {
        period: { range, start: startDate, end: endDate, label: range === 'custom' ? `${startDate} - ${endDate}` : 'This year' },
        summary,
        availability: { summary: availability },
        trend: {
            labels: ['Sep 2026'], donors: filteredFacility ? [] : [summary.donors_in_period], donations: [summary.completed_donations],
            donors_available: !filteredFacility,
            donors_message: filteredFacility ? 'Donor profiles are not associated with facilities, so their trend cannot be filtered by facility.' : null,
            granularity: 'month',
        },
        distribution: {
            available: !filteredFacility,
            message: filteredFacility ? 'Donor profiles are not associated with facilities, so distribution cannot be filtered by facility.' : null,
            basis: 'verified', verified_total: empty || filteredFacility ? (filteredFacility ? null : 0) : 1,
            self_reported_total: empty || filteredFacility ? (filteredFacility ? null : 0) : 0,
            unknown_total: 0, items: filteredFacility ? [] : distributionItems,
        },
        inventory,
        inventory_snapshot_at: '2026-09-28T00:00:00Z',
        filters: {
            range, start_date: startDate, end_date: endDate,
            facility_id: facilityId ? Number(facilityId) : null,
            blood_type_id: bloodTypeId ? Number(bloodTypeId) : null,
            facilities: [{ value: 1, label: 'Test Facility' }],
            blood_types: [{ value: 1, label: 'A+' }, { value: 2, label: 'A-' }],
        },
    };
}

async function mount(page) {
    const requests = [];
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.hostname !== 'audit.test') return route.abort();
        if (url.pathname === '/fixture') {
            return route.fulfill({ contentType: 'text/html', body: await readFile('tests/browser/report-analytics.html', 'utf8') });
        }
        if (url.pathname === '/privacy/preferences') {
            return route.fulfill({ json: { choices: { ...route.request().postDataJSON(), necessary: true, analytics: false, decided: true, expires_at: Math.floor(Date.now() / 1000) + 86400 } } });
        }
        if (url.pathname === '/admin/report-analytics/data') {
            const params = new URL(url).searchParams;
            requests.push(Object.fromEntries(params.entries()));
            return route.fulfill({ json: payloadFor(params) });
        }
        const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(publicRoot + path.sep)) return route.abort();
        try { return await route.fulfill({ contentType: contentTypes[path.extname(file)] || 'application/octet-stream', body: await readFile(file) }); }
        catch (_) { return route.fulfill({ json: { success: true } }); }
    });
    await page.goto('http://audit.test/fixture');
    await page.locator('#ed-privacy-form input[name="maps"]').check();
    await page.getByRole('button', { name: 'Save selected choices' }).click();
    await expect(page.locator('#reportInventoryBody')).toContainText('A+');
    return requests;
}

test('desktop report filters and summary layout remain usable', async ({ page }) => {
    await mount(page);
    await page.setViewportSize({ width: 1280, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.locator('#reportRangeFilter').selectOption('month');
    await page.locator('#reportBloodTypeFilter').selectOption('1');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.locator('[data-report-metric="total_donations"]')).toHaveText('2');
    await expect(page.locator('#reportInventoryBody')).toContainText('A+');
    await expect(page.locator('#reportHeaderExport')).toHaveAttribute('href', /range=month.*blood_type_id=1/);
});

test('report filters apply, combine, persist in URL, restore on Back, reset, and show empty results', async ({ page }) => {
    const requests = await mount(page);
    await page.setViewportSize({ width: 375, height: 820 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);

    await page.locator('#reportBloodTypeFilter').selectOption('2');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.locator('[data-report-metric="total_donations"]')).toHaveText('3');
    await expect(page.locator('#reportInventoryBody')).toContainText('A-');
    await expect.poll(() => new URL(page.url()).searchParams.get('blood_type_id')).toBe('2');
    expect(requests.at(-1).blood_type_id).toBe('2');

    await page.locator('#reportFacilityFilter').selectOption('1');
    await page.locator('#reportBloodTypeFilter').selectOption('');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.locator('#reportDistributionCaption')).toContainText('not associated with facilities');
    await expect(page.locator('#reportTrendCaption')).toContainText('cannot be filtered by facility');
    await expect(page.locator('#reportDonorTrendLegend')).toBeHidden();
    await expect(page.locator('[data-report-metric="total_donations"]')).toHaveText('N/A');
    await expect.poll(() => new URL(page.url()).searchParams.get('facility_id')).toBe('1');
    await expect(page.locator('#reportInventoryBody tr')).toHaveCount(2);

    await page.locator('#reportBloodTypeFilter').selectOption('2');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.locator('#reportHeaderExport')).toHaveAttribute('href', /facility_id=1.*blood_type_id=2/);

    await page.goBack();
    await expect(page.locator('#reportFacilityFilter')).toHaveValue('1');
    await expect(page.locator('#reportBloodTypeFilter')).toHaveValue('');
    await page.goBack();
    await expect(page.locator('#reportFacilityFilter')).toHaveValue('');
    await expect(page.locator('#reportBloodTypeFilter')).toHaveValue('2');
    await expect(page.locator('[data-report-metric="total_donations"]')).toHaveText('3');

    await page.locator('#reportRangeFilter').selectOption('custom');
    await page.locator('#reportStartDate').fill('2019-01-01');
    await page.locator('#reportEndDate').fill('2019-01-31');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.locator('#reportEmptyState')).toBeVisible();
    await expect(page.locator('[data-report-metric="total_donations"]')).toHaveText('0');
    await expect(page.locator('#reportInventoryBody')).toContainText('A-');

    const requestCount = requests.length;
    await page.locator('#reportEndDate').fill('');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.locator('#reportFilterError')).toContainText('Choose an end date');
    await expect(page.locator('#reportHeaderExport')).toHaveAttribute('aria-disabled', 'true');
    expect(requests).toHaveLength(requestCount);

    await page.getByRole('button', { name: 'Reset report filters' }).click();
    await expect(page.locator('#reportRangeFilter')).toHaveValue('year');
    await expect(page.locator('#reportFacilityFilter')).toHaveValue('');
    await expect(page.locator('#reportBloodTypeFilter')).toHaveValue('');
    await expect(page.locator('#reportEmptyState')).toBeHidden();
    await expect.poll(() => new URL(page.url()).searchParams.get('range')).toBe('year');
    expect(await page.locator('#reportSearchFilter').count()).toBe(0);
    expect(await page.locator('#reportStatusFilter').count()).toBe(0);
    expect(await page.locator('#reportPagination').count()).toBe(0);
});
