# Hostinger Deployment Notes

## Current Live Structure

For the current eDonate Hostinger setup:

- Laravel project root: `public_html`
- Laravel source public directory: `public_html/public`
- Actual served web root: `public_html`

Because Laravel `asset('js/admin/example.js')` generates a domain-root URL like:

```text
https://edonate.online/js/admin/example.js
```

the browser serves the physical file from:

```text
public_html/js/admin/example.js
```

not from:

```text
public_html/public/js/admin/example.js
```

## Deploy Command

After every code change on Hostinger, run this from the Laravel project root:

```bash
cd ~/domains/edonate.online/public_html
composer deploy:hostinger
```

If your Laravel project root is not exactly `public_html`, pass the served web root explicitly:

```bash
php scripts/sync-public-assets.php --web-root=/home/USER/domains/edonate.online/public_html
```

You can also set it once for the shell session:

```bash
export EDONATE_WEB_ROOT=/home/USER/domains/edonate.online/public_html
composer deploy:hostinger
```

## What The Deploy Sync Does

The command copies static assets from Laravel `public/` into the actual served web root:

- `public/js` -> `public_html/js`
- `public/css` -> `public_html/css`
- `public/images` -> `public_html/images` if the directory exists
- `public/build` -> `public_html/build`
- `public/vendor` -> `public_html/vendor`

It is copy-only:

- It does not delete files from the served web root.
- It does not copy or overwrite `.env`.
- It does not remove uploaded user files.
- It only overwrites files with the same path when the source content changed.

The command also runs:

```bash
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

## Vite Build

This project uses Vite through `@vite(...)`, so when CSS or JS under `resources/` changes, build first:

```bash
npm run build
composer deploy:hostinger
```

If `npm` is not available on Hostinger, run `npm run build` locally, upload the updated `public/build` folder, then run:

```bash
composer deploy:hostinger
```

## Admin JS Cache Busting

Admin JavaScript files are loaded with a `filemtime` query string. After assets are synced and views are cleared, browsers request the new URL version, for example:

```text
/js/admin/eligibility-review.js?v=...
```

This avoids the old-browser-cache problem after deployment.
