# eDonate Phase 12 Operations

## Backups and recovery

Keep database, private uploads, public uploads, and deployment configuration in separate protected backup locations. Do not commit `.env`, Firebase service-account JSON, passwords, OTPs, FCM tokens, 2FA secrets, or generated recovery codes.

Example MySQL backup (replace placeholders in the operator's secure shell only):

```bash
mysqldump --single-transaction --routines --triggers -u DB_USER -p edonate_db > edonate_db_YYYYMMDD.sql
```

Back up `storage/app/private` and `storage/app/public` with the host's encrypted backup tool. Restore the database first, restore storage, set the correct environment configuration, run `php artisan migrate --force`, then run the application smoke checks. Never use `migrate:fresh`, `db:wipe`, or a whole-database truncate during recovery.

## Retention

Preview temporary-data cleanup before running it:

```bash
php artisan edonate:cleanup --dry-run
```

Execute only after reviewing the preview:

```bash
php artisan edonate:cleanup --force
```

The command is limited to expired OTP/reset tokens and old read donor notifications. Rejected verification files are disabled by default and can be enabled through environment configuration after the retention policy has been approved. `sessions`, `audit_logs`, `admin_notifications`, completed donation history, inventory logs, and fulfilled requests are intentionally retained.

## Next-eligible reminders

Run manually with:

```bash
php artisan edonate:eligibility-reminders --dry-run
php artisan edonate:eligibility-reminders
```

The command is idempotent for a donor/date/day and does not use FCM or an external push provider. To opt into Laravel scheduling, set `EDONATE_SCHEDULE_ELIGIBILITY_REMINDERS=true`, then run the normal scheduler. Linux hosts commonly use:

```cron
* * * * * cd /absolute/path/to/edonate_web && php artisan schedule:run >> /dev/null 2>&1
```

On Windows, use Task Scheduler to run `php artisan schedule:run` every minute from the Laravel project directory.

## Privacy and security checklist

- Reports and CSV exports contain aggregate values only; they do not include donor contact details, emails, exact addresses, documents, screening answers, passwords, OTPs, FCM tokens, session payloads, or 2FA secrets.
- Donor notification actions scope every read/update through the authenticated donor session.
- Verification documents remain on the private disk and are served only through an authenticated admin route with the existing upload type/size validation.
- Audit logs are append-only in the application: the UI exposes filtering, viewing, and export, but no edit/delete action.
- The deployment webhook requires `EDONATE_DEPLOY_WEBHOOK_SECRET` and a valid GitHub `X-Hub-Signature-256`; the secret is never stored in source control and command output is not returned to callers.
- Staff access continues to be enforced server-side by `admin.auth` and `admin.role`; hiding a menu item is not treated as authorization.
