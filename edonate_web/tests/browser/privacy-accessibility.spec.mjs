import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
const publicRoot = path.resolve('../public_html');
const fixtures = path.resolve('tests/browser/fixtures');
const contentTypes = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml' };
const external = [];
async function mount(page, name) {
    external.length = 0;
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.hostname !== 'audit.test') { external.push(url.origin); return route.abort(); }
        if (url.pathname === '/fixture') return route.fulfill({ contentType: 'text/html', body: await readFile(path.join(fixtures, name + '.html')) });
        if (url.pathname === '/privacy/preferences') {
            const posted = route.request().postDataJSON();
            return route.fulfill({ json: { choices: { ...posted, analytics: false, necessary: true, decided: true, expires_at: Math.floor(Date.now()/1000) + 86400 } } });
        }
        const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(publicRoot + path.sep)) return route.abort();
        try { return await route.fulfill({ contentType: contentTypes[path.extname(file)] || 'application/octet-stream', body: await readFile(file) }); }
        catch (_) { return route.fulfill({ json: { data: [], messages: [], facilities: [], barangays: [], summary: {}, map_points: [], meta: {total: 0}, success: true } }); }
    });
    await page.goto('http://audit.test/fixture');
    await page.waitForLoadState('networkidle');
}
const pages = ['privacy', 'terms', 'cookies', 'signup', 'login', 'admin-login', 'admin-dashboard', 'admin-users', 'admin-facilities', 'admin-map', 'admin-blood-requests', 'donor-screening', 'donor-verification', 'donor-booking'];
for (const name of pages) {
    test(name + ': WCAG A/AA scan and no passive third-party requests', async ({ page }) => {
        await mount(page, name);
        expect(external).toEqual([]);
        const results = await new AxeBuilder({ page }).withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
        expect(results.violations.map(v => ({ id:v.id, impact:v.impact, nodes:v.nodes.map(n => ({target:n.target,summary:n.failureSummary})) }))).toEqual([]);
    });
}
test('choices are unchecked, reject works, and keyboard can reopen/escape', async ({ page }) => {
    await mount(page, 'privacy');
    const panel = page.locator('#ed-privacy-panel');
    await expect(panel).toBeVisible();
    await expect(panel.locator('input:checked')).toHaveCount(0);
    await panel.getByRole('button', {name:'Reject optional services'}).focus();
    await page.keyboard.press('Enter');
    await expect(panel).toBeHidden();
    expect(await page.evaluate(() => eDonatePrivacy.allowed('maps'))).toBe(false);
    const open = page.getByRole('button', {name:'Privacy choices', exact:true});
    await open.focus(); await page.keyboard.press('Enter');
    await expect(page.locator('#ed-privacy-title')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(open).toBeFocused();
    expect(external).toEqual([]);
});
test('map waits for explicit consent; table is still available', async ({ page }) => {
    await mount(page, 'admin-map');
    expect(external).toEqual([]);
    await expect(page.locator('.availability-table')).toBeVisible();
    await page.locator('#ed-privacy-form input[name="maps"]').check();
    await page.getByRole('button', {name:'Save selected choices'}).click();
    await expect.poll(() => external.some(origin => origin.endsWith('.basemaps.cartocdn.com'))).toBe(true);
});
test('chat creates no conversation identifier before consent and starts only after opt-in', async ({ page }) => {
    await mount(page, 'admin-dashboard');
    await page.locator('#ed-privacy-panel [data-privacy-close]').click();
    await page.getByRole('button', {name:'Open eDonate assistant'}).click();
    await expect(page.locator('#ec-input')).toBeDisabled();
    expect(await page.evaluate(() => sessionStorage.getItem('edonate.chat.session'))).toBeNull();
    await page.locator('#edonate-chatbot [data-privacy-open]').click();
    await page.locator('#ed-privacy-form input[name="ai"]').check();
    await page.getByRole('button', {name:'Save selected choices'}).click();
    await expect(page.locator('#ec-input')).toBeEnabled();
    expect(await page.evaluate(() => sessionStorage.getItem('edonate.chat.session'))).toMatch(/^[0-9a-f-]{36}$/);
});
test('facility modal: named fields, keyboard Escape and visible backdrop', async ({ page }) => {
    await mount(page, 'admin-facilities');
    await page.locator('#ed-privacy-panel [data-privacy-close]').click();
    await page.getByRole('button', {name:'Create Facility', exact:true}).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await expect(page.getByLabel(/^Facility name/)).toBeVisible();
    const results = await new AxeBuilder({page}).include('#facilityModal').withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
    expect(results.violations).toEqual([]);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByRole('button', {name:'Create Facility', exact:true})).toBeFocused();
});
test('mobile privacy controls reflow and remain keyboard accessible at 320px', async ({page}) => {
    await page.setViewportSize({width:320, height:740});
    await mount(page, 'privacy');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.locator('[data-privacy-reject]').focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#ed-privacy-panel')).toBeHidden();
    const results = await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
    expect(results.violations).toEqual([]);
});
