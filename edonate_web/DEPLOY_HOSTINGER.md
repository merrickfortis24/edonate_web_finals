# Hostinger Deployment Notes

## Production Structure

Use Hostinger's official Laravel split:

```text
/home/USER/domains/edonate.online/
|-- edonate_web/      # Laravel app root, not web-accessible
|-- public_html/      # Hostinger document root
```

`public_html` must contain only files that came from Laravel's `public/`
directory, such as `index.php`, `.htaccess`, `build/`, `css/`, `js/`,
`vendor/leaflet/`, `favicon.ico`, and `robots.txt`.

Keep private Laravel files in `edonate_web`: `.env`, `app/`, `bootstrap/`,
`config/`, `database/`, `resources/`, `routes/`, `storage/`, Composer
`vendor/`, `node_modules/`, SQL dumps, ZIP backups, and keys.

## Entrypoint

`public_html/index.php` loads Laravel from the private sibling folder:

```php
require __DIR__.'/../edonate_web/vendor/autoload.php';
$app = require_once __DIR__.'/../edonate_web/bootstrap/app.php';
```

`edonate_web/bootstrap/app.php` sets Laravel's public path to
`../public_html`, so `public_path()`, Vite, `storage:link`, admin cache-busting,
and generated `/storage` URLs use the served web root.

## Deploy Commands

Run commands from the private Laravel root:

```bash
cd ~/domains/edonate.online/edonate_web
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Keep the existing production `APP_KEY` unless you intentionally want to
invalidate encrypted cookies and encrypted application data.

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
