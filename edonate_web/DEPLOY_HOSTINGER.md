# Hostinger Deployment Notes

## Production Structure

This deployment uses the current Hostinger nested structure:

```text
/home/USER/domains/edonate.online/public_html/
|-- edonate_web/      # Laravel app root
|   `-- public/images/ # canonical branded image assets
|-- public_html/      # served/generated public asset copy
|-- index.php         # live Hostinger front controller
|-- .htaccess
|-- build/
|-- css/
|-- js/
`-- vendor/           # public assets only
```

The outer `public_html` is Hostinger's served document root. The canonical
Laravel source for branded images is now `edonate_web/public/images/`.
`scripts/sync-public-assets.php` prefers assets from `edonate_web/public/` and
falls back to the legacy nested public-asset source for directories that have
not been migrated yet. It copies the resolved public files into the served
outer root, including `images/`, `index.php`, `.htaccess`, `build/`, `css/`,
`js/`, `vendor/leaflet/`, `favicon.ico`, and `robots.txt`.

Keep private Laravel files in `edonate_web`: `.env`, `app/`, `bootstrap/`,
`config/`, `database/`, `resources/`, `routes/`, `storage/`, Composer
`vendor/`, `node_modules/`, SQL dumps, ZIP backups, and keys.

## Entrypoint

The live outer `public_html/index.php` loads Laravel from the nested app folder:

```php
require __DIR__.'/edonate_web/vendor/autoload.php';
$app = require_once __DIR__.'/edonate_web/bootstrap/app.php';
```

`edonate_web/bootstrap/app.php` sets Laravel's public path to
the outer `public_html`, so `public_path()`, Vite, admin cache-busting, and
generated public URLs use the served web root:

```php
$app->usePublicPath(dirname(__DIR__).'/..');
```

## Deploy Commands

Run commands from the private Laravel root:

```bash
cd ~/domains/edonate.online/public_html/edonate_web
php artisan migrate --force
php scripts/sync-public-assets.php --web-root="$HOME/domains/edonate.online/public_html" --skip-cache-clear
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

If the live outer public files disappear after an upload or Git deploy, repair
them with:

```bash
cd ~/domains/edonate.online/public_html/edonate_web
php scripts/sync-public-assets.php --web-root="$HOME/domains/edonate.online/public_html" --skip-cache-clear
```

The script copies `index.php`, `.htaccess`, `build/`, `css/`, `js/`,
`images/`, `vendor/`, `favicon.ico`, and `robots.txt` into the served outer
`public_html`.

Keep the existing production `APP_KEY` unless you intentionally want to
invalidate encrypted cookies and encrypted application data.

If the domain shows `403 Forbidden`, verify the two required files in the
served root before clearing Laravel caches:

```bash
cd ~/domains/edonate.online/public_html
test -f index.php && echo "live index.php: OK" || echo "live index.php: MISSING"
test -f .htaccess && echo "live .htaccess: OK" || echo "live .htaccess: MISSING"
test -f public_html/index.php && echo "source index.php: OK" || echo "source index.php: MISSING"
test -f public_html/.htaccess && echo "source .htaccess: OK" || echo "source .htaccess: MISSING"
```

The Hostinger document root must be `/home/USER/domains/edonate.online/public_html`.
It must not point to the private `edonate_web` application directory or to the
checked-in `public_html` source directory. The deployment workflow runs only
for `main`; pushing an agent branch alone does not deploy it.

## API Rate Limits

The application uses Laravel's named rate limiters and the configured
`CACHE_STORE` (database by default). No Redis service or rate-limit database
tables are required. Optional `EDONATE_RATE_*` overrides are documented in
`.env.example`; after changing them, run:

```bash
php artisan optimize:clear
php artisan config:cache
```

The normal deployment commands above do not need to be changed.

## Vite Assets

Vite is configured to write production assets directly to:

```text
../public_html/build
```

URLs remain domain-root URLs such as:

```text
/build/assets/app-*.css
/build/assets/app-*.js
```

When frontend assets change, run:

```bash
npm ci
npm run build
```

If Node is not available on Hostinger, run the build locally and upload the
updated `public_html/build` directory.

## Verification

These should work:

```text
https://edonate.online/
https://edonate.online/login
https://edonate.online/css/...
https://edonate.online/js/...
https://edonate.online/build/...
https://edonate.online/vendor/leaflet/leaflet.min.css
```

These must return `403` or `404`:

```text
https://edonate.online/.env
https://edonate.online/composer.json
https://edonate.online/config/app.php
https://edonate.online/storage/logs/laravel.log
https://edonate.online/vendor/composer/installed.json
https://edonate.online/edonate_db%20(2).sql
```
