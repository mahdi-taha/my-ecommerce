# Ecommerce Version 1.0 Production Deployment

This guide defines the production deployment procedure for Ecommerce Version
1.0. Adapt operating-system and hosting commands to the target platform without
changing the certified application architecture or business rules.

## 1. Server requirements

- PHP 8.2 or a compatible newer PHP release allowed by `composer.json`.
- Required PHP extensions reported by `composer check-platform-reqs`, including
  PDO MySQL, mbstring, XML, cURL, and ZIP support.
- MySQL 8.4 for the certified production database target.
- Composer 2.
- Node.js 22 and npm for building frontend assets.
- A web server configured with the project `public/` directory as its document
  root. The repository root MUST NOT be publicly accessible.
- A process supervisor for queue workers and cron access for the scheduler.

Production dependencies MUST be installed from the committed lock files.
Packages MUST NOT be updated as part of a deployment.

## 2. Environment variables

Create the production `.env` outside source control. At minimum, verify:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://store.example.com
APP_KEY=base64:existing-generated-key

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce
DB_USERNAME=ecommerce
DB_PASSWORD=strong-secret

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
QUEUE_AFTER_COMMIT=true

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

- Generate `APP_KEY` once with `php artisan key:generate` during the initial
  deployment. Back it up and preserve it across every later deployment.
- Configure real mail credentials before enabling email delivery.
- Secrets MUST NOT be committed, printed in deployment logs, or copied into a
  built frontend asset.
- Confirm the store locale, timezone, currency, taxes, shipping methods,
  payment methods, and notification rules in the administration settings.
- Do not use `MAIL_MAILER=log`, debug logging, or development credentials in
  production.

## 3. HTTPS and reverse proxies

Terminate customer traffic over HTTPS and redirect plain HTTP to HTTPS at the
web server or load balancer. Set `APP_URL` to the canonical HTTPS URL and enable
secure session cookies.

If TLS terminates at a reverse proxy or load balancer, review Laravel's trusted
proxy configuration for the exact infrastructure before deployment. Trust only
known proxy addresses and forwarded headers. Incorrect proxy trust can produce
wrong URL schemes or expose spoofed forwarding information.

## 4. Initial deployment

From the release directory:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
php artisan storage:link
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

Before migrating, configure the production environment and verify database
connectivity. Run only approved configuration seeders deliberately. Do not run
demo seeders against production data.

After deployment, start supervised queue workers and complete the health and
smoke checks below.

## 5. Upgrade deployment

Use a reviewed, versioned release and follow this order:

1. Confirm the release commit and review its migrations and release notes.
2. Back up the database and persistent uploaded files.
3. Enable maintenance mode when the release is not backward-compatible with
   the currently running application.
4. Install locked production dependencies and build assets.
5. Clear stale cached bootstrap artifacts with `php artisan optimize:clear`.
6. Run `php artisan migrate --force` once.
7. Build the production caches.
8. Atomically switch the web root or release symlink where supported.
9. Run `php artisan queue:restart` so supervised workers load the new release.
10. Disable maintenance mode and run the post-deployment checks.

Do not run `migrate:fresh`, truncate tables, or automatically run demo seeders
during an upgrade.

## 6. Maintenance mode

For deployments requiring a maintenance window:

```bash
php artisan down --secret="temporary-bypass-token"
```

Keep the bypass token confidential. Restore normal traffic only after database
migrations, caches, workers, and smoke checks succeed:

```bash
php artisan up
```

The health-check behavior during maintenance MUST be considered in load
balancer and monitoring configuration.

## 7. Database backup strategy

- Take a consistent database backup before every migration-bearing release.
- Back up persistent uploaded files with the database when both are needed to
  reconstruct application state.
- Encrypt backups, restrict access, and define retention appropriate to the
  stored customer and order data.
- Regularly test restoration into an isolated environment. An untested backup
  is not a verified recovery mechanism.
- Record the release commit, migration status, backup timestamp, and backup
  location in the deployment log.

## 8. Forward-only migration policy

Version 1 uses forward migrations. Existing applied migrations MUST NOT be
edited, reordered, or deleted.

Before applying migrations:

```bash
php artisan migrate:status
```

Apply pending migrations with:

```bash
php artisan migrate --force
```

Some migrations intentionally cannot restore discarded or transformed data in
`down()`. Production rollback therefore MUST NOT assume that `migrate:rollback`
is safe. Prefer a reviewed corrective forward migration. If a release must be
fully reverted after an irreversible schema change, restore the matching
pre-deployment database and files backup together with the previous code.

## 9. Queue workers

Production MUST use supervised, long-running workers rather than an interactive
terminal. A typical worker command is:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Configure the supervisor to restart failed workers, run as the deployment user,
and write output to an operationally managed log. Align `--timeout` with the
configured queue `retry_after` so a job is not processed twice prematurely.

After every deployment, restart workers gracefully:

```bash
php artisan queue:restart
```

Monitor failed jobs and queue latency. Database-backed jobs are configured to
dispatch after the owning database transaction commits.

## 10. Scheduler

The application scheduler includes Cart expiration cleanup. Run the Laravel
scheduler every minute from one designated scheduler process:

```cron
* * * * * cd /var/www/ecommerce/current && php artisan schedule:run >> /dev/null 2>&1
```

When deploying to multiple application servers, avoid running duplicate
schedulers unless the platform's scheduling and locking strategy has been
explicitly reviewed.

## 11. Storage and permissions

The deployment user and PHP/web-server process require write access to:

- `storage/`
- `bootstrap/cache/`

Create the public storage link once per deployed filesystem:

```bash
php artisan storage:link
```

Persist `storage/app/public` across release switches. Multi-server deployments
require shared or externally managed uploaded-file storage. Do not grant broad
write permissions to the application source tree or expose private storage
through the web server.

## 12. Frontend build

Build assets from the committed npm lock file:

```bash
npm ci
npm run build
```

Verify that `public/build/manifest.json` and its referenced assets exist in the
deployed release. Do not run the Vite development server in production.

## 13. Laravel cache compilation

After the final production environment is present, compile caches:

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

When changing environment configuration or deploying a new release, clear old
artifacts first:

```bash
php artisan optimize:clear
```

Environment values MUST be accessed through configuration after config caching;
application runtime code must not depend on direct `.env` reads.

## 14. Logging

Use `daily`, `stderr`, syslog, or an approved centralized production channel.
Set an appropriate production log level and retention period. Ensure the log
destination is writable and monitored for errors, queue failures, and disk
growth.

Preserve correlation IDs when collecting request logs. Logs MUST NOT contain
passwords, raw guest tokens, session identifiers, secrets, payment credentials,
or unnecessary personal information.

## 15. Health endpoint

Laravel exposes:

```text
GET /up
```

Configure uptime monitoring or the load balancer to request the canonical HTTPS
endpoint. A successful response verifies that the application can bootstrap; it
does not replace database, queue, storage, and business-flow smoke checks.

## 16. Smoke tests

After each deployment, verify:

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
```

Also verify:

- `/up` returns a successful response over HTTPS.
- The storefront homepage and a localized Product page load.
- Administrator and customer authentication boundaries work.
- Public images and Vite assets load without mixed-content errors.
- A queue worker is running and failed-job monitoring is operational.
- The scheduler is invoking due tasks.
- No unexpected errors appear in production logs.

Do not place a real customer Order as a smoke test unless a documented test
account and cleanup procedure have been approved.

## 17. Rollback policy

Application rollback is safe only when the previous release remains compatible
with the migrated database and current assets.

If it is compatible:

1. Re-enable maintenance mode if required.
2. Switch back to the previous reviewed release.
3. Rebuild or restore that release's caches and assets.
4. Restart queue workers.
5. Disable maintenance mode and repeat smoke checks.

If migrations were irreversible or made the previous code incompatible, do not
guess or mutate production data manually. Restore the pre-deployment database,
persistent files, and previous code as one recovery point, or deploy an approved
forward corrective migration.

## 18. Troubleshooting

- **Application key or decryption errors:** restore the original production
  `APP_KEY`; do not generate a replacement for an existing installation.
- **Stale configuration:** confirm `.env`, run `php artisan optimize:clear`, then
  rebuild all production caches.
- **Routes or views fail to cache:** stop the deployment, capture the command
  output, and correct the release before switching traffic.
- **Assets missing:** rerun `npm ci` and `npm run build`, then verify the Vite
  manifest and web-server access to `public/build`.
- **Uploads unavailable:** verify the storage link, persistent storage mount,
  permissions, and canonical `APP_URL`.
- **Jobs are not processed:** verify the queue connection, worker supervisor,
  failed jobs, database connectivity, and that workers were restarted.
- **Scheduled cleanup does not run:** verify the cron entry, deployment path,
  PHP executable, scheduler logs, and server timezone.
- **Migration failure:** keep maintenance mode enabled, preserve the error and
  database state, and follow the forward-migration or backup-restoration policy.

## Post-deployment checklist

- [ ] Release commit and dependency lock files verified.
- [ ] Production environment and preserved `APP_KEY` verified.
- [ ] Database and persistent files backed up and restoration location recorded.
- [ ] Locked PHP and JavaScript dependencies installed.
- [ ] Vite production assets built and manifest present.
- [ ] Migrations completed and status reviewed.
- [ ] Storage link and writable directories verified.
- [ ] Laravel production caches compiled.
- [ ] Queue workers restarted and healthy.
- [ ] Scheduler invocation verified.
- [ ] `/up` and storefront/admin smoke checks passed.
- [ ] Logs reviewed for new errors.
- [ ] Maintenance mode disabled.

