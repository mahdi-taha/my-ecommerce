# Ecommerce Version 1.0

Custom ecommerce platform built with Laravel 12 and PHP 8.2.

The frozen Ecommerce Architecture Modules, Architecture Constitution,
Implementation Constitution, and Implementation Roadmap are the source of
truth. The project source code implements those documents and must not redefine
their architecture or business rules.

## Technology

- PHP 8.2
- Laravel 12
- MySQL for production and compatibility verification
- SQLite for fast local tests
- Bootstrap 5
- jQuery and AJAX
- DataTables with Yajra DataTables
- SweetAlert2
- Select2
- Vite

## Local setup

1. Install the locked PHP and JavaScript dependencies:

   ```bash
   composer install
   npm install
   ```

2. Create the local environment and application key:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. Configure the database in `.env`, then run migrations and approved seeders:

   ```bash
   php artisan migrate
   php artisan db:seed
   ```

4. Create the public storage link:

   ```bash
   php artisan storage:link
   ```

5. Start the application and Vite development server:

   ```bash
   php artisan serve
   npm run dev
   ```

On Windows PowerShell systems that block `npm.ps1`, use `npm.cmd` in place of
`npm`.

## Environment conventions

- Persistent application timestamps use UTC.
- The configured store timezone controls customer-facing business time.
- SQLite is supported for local development and fast tests.
- MySQL compatibility is verified separately in continuous integration.
- Database-backed queues default to dispatching jobs after database commit.
- The public filesystem disk is used explicitly for approved public Catalog
  images.
- Secrets and production credentials belong only in environment configuration
  and must never be committed.

## Verification

Run PHP formatting and tests:

```bash
vendor/bin/pint --test
php artisan test
```

Run the production frontend build:

```bash
npm run build
```

Useful Laravel inspections:

```bash
php artisan about
php artisan route:list
php artisan migrate:status
```

The CI workflow runs the test suite with SQLite and separately against MySQL.
It also verifies Pint formatting and the Vite production build.

## Correlation IDs

Every HTTP response includes an `X-Correlation-ID` header. A safe incoming
correlation ID is preserved; otherwise, the application generates one. The same
identifier is attached to the request logging context for operational tracing.

Correlation IDs must not contain personal data, credentials, payment details,
or other secrets.

## Implementation discipline

- Business logic belongs in Services.
- Controllers remain thin.
- Models represent persistence and relationships.
- Form Requests validate and normalize untrusted input.
- Authorization is enforced server-side.
- Critical state changes use the certified transaction and locking boundaries.
- Tests must cover successful behavior, rejected behavior, and rollback safety.
- Unrelated refactoring and unapproved package installation are prohibited.
