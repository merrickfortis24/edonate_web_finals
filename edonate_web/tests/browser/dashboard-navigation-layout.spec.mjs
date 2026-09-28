import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const workspaceRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../');
const publicRoot = path.join(workspaceRoot, 'public_html');
const manifest = JSON.parse(await readFile(path.join(publicRoot, 'build/manifest.json'), 'utf8'));
const adminCss = await readFile(path.join(publicRoot, 'build', manifest['resources/css/adminlte.css'].file), 'utf8');

const dashboardFixture = `
<div class="edonate-admin-page admin-dashboard-page">
  <main class="container-fluid p-3">
    <section class="row row-cols-1 row-cols-md-2 g-3" aria-label="Appointment restriction reviews">
      <div class="col"><a class="dashboard-restriction-card h-100 text-decoration-none" href="/admin/appointment-restrictions">
        <span class="dashboard-restriction-card__label">Restricted Donors</span>
        <strong class="dashboard-restriction-card__value">3</strong>
        <span class="badge text-bg-danger">Review</span>
      </a></div>
      <div class="col"><a class="dashboard-restriction-card h-100 text-decoration-none" href="/admin/appointment-restrictions?appeal_status=pending">
        <span class="dashboard-restriction-card__label">Pending Restriction Appeals</span>
        <strong class="dashboard-restriction-card__value">2</strong>
        <span class="badge text-bg-warning">Review</span>
      </a></div>
    </section>
    <ul class="approval-list mt-3"><li class="approval-item"><a class="approval-item__link" href="/admin/appointments?focus=27">
      <span class="approval-item__info"><span class="approval-item__name">Donor Name</span><span class="approval-item__type">Pending Appointment</span></span>
      <span class="btn-review btn">Review</span>
    </a></li></ul>
  </main>
</div>`;

async function mountDashboardFixture(page, width) {
    await page.setViewportSize({ width, height: 900 });
    await page.setContent(dashboardFixture);
    await page.addStyleTag({ content: adminCss });
}

test('dashboard review cards align left and remain keyboard accessible on desktop', async ({ page }) => {
    await mountDashboardFixture(page, 1280);

    const cards = page.locator('.dashboard-restriction-card');
    await expect(cards).toHaveCount(2);
    const positions = await cards.first().evaluate((card) => {
        const rect = (selector) => card.querySelector(selector).getBoundingClientRect();
        const cardRect = card.getBoundingClientRect();
        return {
            cardLeft: cardRect.left,
            labelLeft: rect('.dashboard-restriction-card__label').left,
            valueLeft: rect('.dashboard-restriction-card__value').left,
            reviewLeft: rect('.badge').left,
        };
    });
    expect(positions.labelLeft).toBeGreaterThan(positions.cardLeft);
    expect(Math.abs(positions.labelLeft - positions.valueLeft)).toBeLessThanOrEqual(1);
    expect(Math.abs(positions.labelLeft - positions.reviewLeft)).toBeLessThanOrEqual(1);

    await page.keyboard.press('Tab');
    await expect(cards.first()).toBeFocused();
    expect(await cards.first().evaluate((node) => getComputedStyle(node).outlineStyle)).toBe('solid');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
});

test('dashboard restriction cards and approval actions stack cleanly on mobile', async ({ page }) => {
    await mountDashboardFixture(page, 390);

    const cards = page.locator('.dashboard-restriction-card');
    const first = await cards.nth(0).boundingBox();
    const second = await cards.nth(1).boundingBox();
    expect(second.y).toBeGreaterThanOrEqual(first.y + first.height);

    const approval = page.locator('.approval-item__link');
    const approvalLayout = await approval.evaluate((link) => {
        const info = link.querySelector('.approval-item__info').getBoundingClientRect();
        const review = link.querySelector('.btn-review').getBoundingClientRect();
        return { infoBottom: info.bottom, reviewTop: review.top };
    });
    expect(approvalLayout.reviewTop).toBeGreaterThanOrEqual(approvalLayout.infoBottom);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
});
