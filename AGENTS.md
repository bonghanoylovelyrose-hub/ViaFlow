# AGENTS.md

ViaFlow System: Laravel 12 server-rendered app for Via Vida water billing, payments, meter readings, loans, incidents, and reconnection.

## Commands

- Dev: start XAMPP MySQL, then run `serve.bat` (hardcodes `C:\xampp\php\php.exe`, port 8000). `C:\xampp\php\php.exe artisan serve` is equivalent.
- Tests: `C:\xampp\php\php.exe artisan test`; focused: `... artisan test --filter=PaymentHistoryTest` or `--filter=test_name`. The suite uses in-memory SQLite from `phpunit.xml`, so it needs no MySQL.
- Format check: `vendor\bin\pint.bat --test path\to\file.php`. Pint is installed but not wired into a script/CI and the tree is not fully Pint-clean; do not mass-format unrelated files.
- Frontend: use `npm.cmd run build` (PowerShell blocks `npm.ps1`). Blade does not use `@vite`; layouts load Bootstrap 5.3/icons from CDN and keep CSS inline in `resources/views/layouts/app.blade.php`. Changing `resources/css/app.css` alone will not change pages. There is no JS test suite or typecheck step.
- Seed: `C:\xampp\php\php.exe artisan migrate --seed`. `DatabaseSeeder` creates demo accounts `admin@example.com`, `meter.reader@example.com`, `john.ver.navarra@example.com` (password `password`) plus sample bills.

## Environment / repo gotchas

- `.git/` exists but is empty: `git status` fails with "not a git repository". Do not rely on git diff/checkout/restore.
- `.env` points at MySQL database `water_billing_systems`; `.env.example` and PHPUnit default to SQLite. Start MySQL for normal app/migrate commands.
- Uploads use the private `local` disk (`storage/app/private`) and are served by `route('uploads.serve')`. `public/storage` is absent and not used; do not add `storage:link` or `Storage::url` links.
- Meter and receipt OCR shell out to Windows PowerShell (`scripts/ocr-meter.ps1`, `scripts/ocr-receipt.ps1`). Windows-only; PDF receipts also need Chrome (`CHROME_PATH` is optional). Unit tests cover text parsing, not OCR execution.
- Reports PDF/XLSX exports use `barryvdh/laravel-dompdf` and `phpoffice/phpspreadsheet`; PHP `zip` and `gd` must be enabled. The XAMPP `php.ini` used for local development has both enabled.
- Ignore scaffold/backup artifacts: `laravel-skeleton/`, `node_modules_old/`, `*.zip`, `prompt*.md`, `.composer*`, `tmp/`. `AGENTS.md.md` is an older generic prompt, not auto-loaded.

## Architecture and invariants

- `routes/web.php` contains all web routes. Roles are `admin`, `consumer`, and `staff`/`meter_reader`; `EnsureUserHasRole` normalizes `meter_reader` to `staff`, and `DashboardController` branches by role.
- Controllers choose role-specific views with `roleViewPath()` (`admin.*`, `consumer.*`, `meter-reading.*`). Shared flows have separate Blade files per role; update every affected variant, not just admin.
- Put domain logic in `app/Services/`; controllers and views should call those services. Billing/payment math has extensive Feature-test coverage.
- Billing: defaults in `BillingComputationService` are base `150`, member `18`/m³, non-member `20`/m³ (the generate form can override them). New bills carry no penalty/minimum; historical values are preserved. Unpaid balances carry forward, each peso must live on exactly one bill (`carried_forward_to_bill_id`), and payments allocate oldest-first.
- Disconnection: use `ConsumerBalanceService::DISCONNECTION_NOTICE_AFTER_MONTHS` (currently 3) and `PAYMENT_GRACE_DAYS` (3). Three consecutive overdue months raise a notice in `disconnection_notices` while the account stays Active; the consumer has three days to settle in full. Full payment before confirmation settles the notice (history is retained), and only an admin-confirmed physical disconnection moves the account to `Disconnected`/Inactive Accounts. Once a notice exists, payment must be in full.
- Reconnection: requires verified full payment and a pending `ServiceRequest`. Admin approval/completion moves the account only to `For Reconnection`, never back to `Active`. Service-request review is admin-only.
- Loan eligibility is centralized in `DssEligibilityService`; terms (3/6/12 months) and material costs are in `LoanMaterialRateService`. Do not add parallel eligibility rules.
- Registration address/purok is fixed by `config/service_area.php` (Via Vida, Panabo City); arbitrary locations are rejected.
- Some GET pages reconcile and write status changes (for example `DashboardController` and `BillingController@index`). Do not assume reads are side-effect free.
- Scheduled `billing:monitor-overdue` runs daily at 06:00 and sends overdue/disconnection email. SMTP failures are logged and must not abort the business transaction.
- Business intent in `SKILLS.md`, `DSS.md`, and `obj.md` is useful but partly stale; code and tests win.
