# Hostinger Laravel Security Fix

This project is Laravel 12. The live incident happened because the Laravel project
root was used as Hostinger's served `public_html` directory. That makes `.env`,
Composer files, backups, SQL dumps, `vendor/`, `config/`, `storage/`, and other
internal files reachable from the browser unless Apache/LiteSpeed blocks them.

## Safest Production Structure

Use this structure on Hostinger:

```text
/home/u12345678/domains/edonate.online/
├── edonate_app/                 # Laravel project root, not web-accessible
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   ├── artisan
│   └── composer.json
└── public_html/                 # Hostinger web root
    ├── index.php
    ├── .htaccess
    ├── build/
    ├── css/
    ├── js/
    ├── vendor/leaflet/          # only intentional public static assets
    ├── favicon.ico
    └── robots.txt
```

Never put these in `public_html`: `.env`, `app/`, `bootstrap/`, `config/`,
`database/`, `resources/`, `routes/`, `storage/`, Composer `vendor/`,
`node_modules/`, `.git/`, `.github/`, SQL dumps, ZIP backups, or private keys.

## Hostinger hPanel Steps

1. Sign in to Hostinger hPanel.
2. Open `Websites` -> `Dashboard` for `edonate.online`.
3. Open `Files` -> `File Manager`.
4. Go to:

   ```text
   /home/u12345678/domains/edonate.online/
   ```

   Replace `u12345678` with the actual Hostinger username.

5. Create a folder named:

   ```text
   edonate_app
   ```

6. Move the Laravel project files from `public_html` into `edonate_app`, except
   files that belong inside Laravel's `public/` directory.
7. Empty `public_html` of private files. Leave only public browser assets.
8. Copy the contents of `edonate_app/public/` into `public_html/`.
9. Edit `public_html/index.php` so its paths point to `../edonate_app`.
10. Put the hardened `.htaccess` from this repository into `public_html/.htaccess`.
11. Move all `.zip`, `.sql`, `.bak`, `.old`, and temporary files outside
    `public_html`, or delete them after making a private local backup.

Hostinger's current documentation says the root directory is generally
`public_html`, and on Web/WordPress/Cloud plans the home directory cannot usually
be changed in hPanel. The split structure above is the safe workaround.

## public_html/index.php

Use this `index.php` in `public_html` after moving Laravel to `edonate_app`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../edonate_app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../edonate_app/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../edonate_app/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

## public_html/.htaccess

Use the repository's `public/.htaccess` as the production `public_html/.htaccess`.
It blocks dotfiles, `.env`, config files, dumps, backups, archives, private keys,
and Laravel internal directories, while still allowing normal Laravel routing.

## Emergency Patch for Current Unsafe Layout

If the Laravel root must temporarily remain in `public_html`, upload the repository
root `.htaccess` to:

```text
/home/u12345678/domains/edonate.online/public_html/.htaccess
```

This is a stopgap only. The permanent fix is to move Laravel private files outside
`public_html`.

## Commands After Moving Files

Run from the private Laravel root:

```bash
cd /home/u12345678/domains/edonate.online/edonate_app
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Keep the existing production `APP_KEY` unless you intentionally want to invalidate
encrypted cookies and any encrypted application data.

If assets changed and Node is available:

```bash
npm ci
npm run build
```

Then copy the updated contents of `public/` to `../public_html/`.

## Verification Checklist

These URLs must return `403` or `404`:

```text
https://edonate.online/.env
https://edonate.online/.git/config
https://edonate.online/composer.json
https://edonate.online/composer.lock
https://edonate.online/package.json
https://edonate.online/phpunit.xml
https://edonate.online/artisan
https://edonate.online/config/app.php
https://edonate.online/storage/logs/laravel.log
https://edonate.online/vendor/composer/installed.json
https://edonate.online/edonate_db%20(2).sql
https://edonate.online/BACK%20UP%20FILE%20JUST%20AN%20CASE%20SOMETHING%20BAD%20MIGHT%20HAPPEN.zip
```

These should still work:

```text
https://edonate.online/
https://edonate.online/login
https://edonate.online/css/...
https://edonate.online/js/...
https://edonate.online/build/...
https://edonate.online/vendor/leaflet/leaflet.min.css
```

## Incident Response

Treat every value that was in `.env` as compromised.

1. Rotate the Hostinger database password.
2. Update `.env` with the new database password.
3. Rotate mail SMTP passwords and API tokens.
4. Rotate Firebase/service account credentials if present.
5. Rotate payment, SMS, cloud storage, OAuth, and webhook secrets if present.
6. Generate a new Laravel app key only if you are prepared to invalidate encrypted
   cookies and encrypted stored data:

   ```bash
   php artisan key:generate --force
   php artisan optimize:clear
   ```

7. Invalidate sessions if admin/user session hijacking is a concern:

   ```bash
   php artisan session:table
   php artisan migrate --force
   php artisan cache:clear
   ```

   Then truncate the sessions table or clear the session store used by production.

8. Review Hostinger access logs for requests to sensitive paths such as `.env`,
   `.git`, `composer.json`, `vendor/`, `.sql`, `.zip`, and `storage/logs`.
9. Check for unknown admin users or database changes.
10. Remove public backups and SQL dumps from the server.
