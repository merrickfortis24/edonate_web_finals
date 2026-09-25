import { defineConfig } from '@playwright/test';
import { existsSync } from 'node:fs';
const chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
export default defineConfig({
    testDir: './tests/browser',
    timeout: 60000,
    workers: 1,
    reporter: [['list'], ['json', { outputFile: 'test-results/accessibility-results.json' }]],
    use: { headless: true, viewport: { width: 1280, height: 900 },
        launchOptions: existsSync(chrome) ? { executablePath: chrome } : {},
        screenshot: 'only-on-failure' },
});
