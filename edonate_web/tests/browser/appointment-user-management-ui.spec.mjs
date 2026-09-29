import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../');
const publicRoot = path.resolve(appRoot, '../public_html');
const manifest = JSON.parse(await readFile(path.join(publicRoot, 'build/manifest.json'), 'utf8'));
const adminCss = await readFile(path.join(publicRoot, 'build', manifest['resources/css/adminlte.css'].file), 'utf8');
const viewToggleScript = path.join(appRoot, 'resources/js/admin/appointment-view-toggle.js');

const userManagementFixture = `
<div class="edonate-admin-page admin-users-page">
  <main class="main container-fluid p-3">
    <div class="filter-bar row g-3 align-items-center mb-3">
      <div class="filter-bar__search col"><input class="form-control" aria-label="Search donors"></div>
      <div class="filter-bar__dropdown col"><select class="form-select" aria-label="Blood type"><option>All Blood Types</option></select></div>
      <div class="filter-bar__dropdown col"><select class="form-select" aria-label="Donor status"><option>All Status</option></select></div>
    </div>
    <section class="table-wrap">
      <div class="donor-table-wrapper table-responsive" tabindex="0" aria-label="Scrollable donor records table">
        <div class="table-inner">
          <div class="table-grid table-thead">
            <div class="table-th">Donor ID</div><div class="table-th">Name</div><div class="table-th">Blood Type</div><div class="table-th">Contact</div><div class="table-th">Last Donation</div><div class="table-th">Status</div><div class="table-th">Donations</div><div class="table-th">Actions</div>
          </div>
          <div class="table-body">
            <div class="table-grid table-row">
              <div class="table-td">D001</div><div class="table-td"><div class="donor-name__primary">Sample Donor</div><div class="donor-name__email">sample@example.test</div></div><div class="table-td">O+</div><div class="table-td">09123456789</div><div class="table-td">—</div><div class="table-td">Eligible</div><div class="table-td">2</div>
              <div class="table-td actions"><button class="btn-action" aria-label="View donor">V</button><button class="btn-action" aria-label="Edit donor">E</button><button class="btn-action" aria-label="Deactivate donor">D</button></div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>`;

const appointmentFixture = `
<div class="edonate-admin-page admin-appointments-page">
  <div id="appointmentListView" class="card appointment-table-card">
    <div class="table-responsive appointment-table-wrapper">
      <table class="admin-standard-table admin-standard-table--appointments table">
        <tbody><tr class="appointment-data-row"><td>AP001</td><td>Sample Donor</td><td>Sep 30, 2026</td><td>Center</td><td>Confirmed</td><td class="appointment-actions"><div class="appointment-action-group">
          <button class="appointment-btn appointment-btn--reschedule"><svg viewBox="0 0 24 24"><path d="M3 5h18"></path></svg>Reschedule</button>
          <button class="appointment-btn appointment-btn--approve"><svg viewBox="0 0 24 24"><path d="M4 12l5 5L20 6"></path></svg>Check In</button>
          <button class="appointment-btn appointment-btn--no-show"><svg viewBox="0 0 24 24"><path d="M5 5l14 14"></path></svg>No Show</button>
          <button class="appointment-btn appointment-btn--cancel"><svg viewBox="0 0 24 24"><path d="M5 5l14 14"></path></svg>Cancel</button>
        </div></td></tr></tbody>
      </table>
    </div>
  </div>
  <button id="appointmentViewToggle" type="button" data-current-view="list" aria-pressed="false">Calendar View</button>
  <section id="appointmentCalendarPanel" aria-hidden="true" hidden><p>Appointment calendar preview</p><div id="appointmentCalendar"></div></section>
  <input id="appointmentSearchInput" value="Sample Donor">
  <select id="appointmentStatusFilter"><option value="confirmed" selected>Confirmed</option></select>
</div>`;

test('User Management table columns and action controls stay aligned without clipping on desktop and mobile', async ({ page }) => {
    await page.setContent(userManagementFixture);
    await page.addStyleTag({ content: adminCss });

    const columnTracks = await page.locator('.table-grid').evaluateAll((grids) => grids.map((grid) => getComputedStyle(grid).gridTemplateColumns));
    const headerTrackWidths = columnTracks[0].split(' ').map(parseFloat);
    const rowTrackWidths = columnTracks[1].split(' ').map(parseFloat);
    expect(headerTrackWidths).toHaveLength(8);
    expect(rowTrackWidths).toHaveLength(8);
    expect(Math.max(...headerTrackWidths.map((width, index) => Math.abs(width - rowTrackWidths[index])))).toBeLessThan(1);
    const actionAlignment = await page.locator('.table-td.actions').evaluate((cell) => ({
        justifyContent: getComputedStyle(cell).justifyContent,
        gap: getComputedStyle(cell).gap,
        buttons: Array.from(cell.querySelectorAll('button')).map((button) => button.getBoundingClientRect().height),
    }));
    expect(actionAlignment.justifyContent).toBe('flex-start');
    expect(actionAlignment.buttons).toEqual([36, 36, 36]);

    await page.setViewportSize({ width: 390, height: 844 });
    const scroller = page.locator('.donor-table-wrapper');
    const scrollMetrics = await scroller.evaluate((element) => ({ clientWidth: element.clientWidth, scrollWidth: element.scrollWidth }));
    expect(scrollMetrics.scrollWidth).toBeGreaterThan(scrollMetrics.clientWidth);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
});

test('appointment actions have consistent sizes and the view toggle preserves active filters', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.setContent(appointmentFixture);
    await page.addStyleTag({ content: adminCss });
    await page.addScriptTag({ path: viewToggleScript });

    const buttons = page.locator('.appointment-action-group .appointment-btn');
    await expect(buttons).toHaveCount(4);
    const heights = await buttons.evaluateAll((items) => items.map((button) => button.getBoundingClientRect().height));
    expect(new Set(heights).size).toBe(1);
    const icon = await buttons.first().locator('svg').evaluate((node) => ({ width: node.getBoundingClientRect().width, height: node.getBoundingClientRect().height }));
    expect(icon).toEqual({ width: 16, height: 16 });
    await page.locator('.appointment-action-group').evaluate((group) => {
        group.style.maxWidth = '250px';
    });
    const wrappedRows = await buttons.evaluateAll((items) => new Set(items.map((button) => Math.round(button.getBoundingClientRect().top))).size);
    expect(wrappedRows).toBeGreaterThan(1);

    const toggle = page.locator('#appointmentViewToggle');
    const list = page.locator('#appointmentListView');
    const calendar = page.locator('#appointmentCalendarPanel');
    await expect(toggle).toHaveText('Calendar View');
    await expect(list).toBeVisible();
    await expect(calendar).toBeHidden();

    await toggle.click();
    await expect(toggle).toHaveText('List View');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await expect(calendar).toBeVisible();
    await expect(list).toBeHidden();
    await expect(page.locator('#appointmentSearchInput')).toHaveValue('Sample Donor');
    await expect(page.locator('#appointmentStatusFilter')).toHaveValue('confirmed');

    await toggle.click();
    await expect(toggle).toHaveText('Calendar View');
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await expect(list).toBeVisible();

    await page.setViewportSize({ width: 390, height: 844 });
    await toggle.click();
    await expect(calendar).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
});
