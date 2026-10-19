# Step 1 — Environment Report & Baseline Results

**Project:** Xavier Trading Platform  
**Branch:** stabilization/Steps-1-3 (clean working tree)  
**Date:** 2026-10-16  
**Platform:** Windows (win32), VS Code, PowerShell

---

## 1. Environment Report

### Toolchain

| Tool | Version | Notes |
|------|---------|-------|
| PHP | 8.2.12 (ZTS, VS2019 x64) | Matches `composer.json` requirement `^8.2` |
| Laravel | 12.62.0 | |
| Composer | 2.8.12 | `vendor/` installed; lock file respected (no upgrades performed) |
| Node.js | v23.3.0 | |
| npm | 11.19.1 | |
| PHPUnit | 11.5.55 | |

### Dependency installation

- **Composer:** `vendor/` present and working (all artisan commands succeed).
- **npm:** initially broken — a prior interrupted install left `node_modules` corrupt
  (`ENOTEMPTY` on `core-js`, missing `@vue/compiler-dom` which broke `vite build`).
  Repaired with `npm install` (no lock-file deletion, no version upgrades).
  `npm run build` now succeeds.

### Runtime configuration (local `.env`)

| Area | Value | Notes |
|------|-------|-------|
| Environment | `local`, `APP_DEBUG=true` | |
| Database | MySQL `127.0.0.1:3306` / `laravel` | `.env.example` defaults to sqlite; local uses MySQL. All migrations **Ran** |
| Cache / Session / Queue | Redis (predis) | Redis must be running for the app to serve requests |
| Mail | `log` driver | SMTP host present in `.env` (local only) |
| Broadcast | `log` (`.env`) | Pusher creds present for frontend |
| Tests | SQLite `:memory:` | per `phpunit.xml`; array cache, sync queue |
| Telescope / Pulse | Enabled (local) | Disabled during tests via `phpunit.xml` |
| CSL | `CSL_MOCK=true`, `CSL_MODE=test`, **`CSL_LIVE_TRADING_ENABLED=false`** | `CSL_CLIENT_ID`/`CSL_CLIENT_SECRET` empty → defect DS1-003 |
| Alpaca | `ALPACA_MOCK=true`, live trading disabled | |
| KYC | `KYC_PROVIDER=dojah` (QoreID deprecated) | |
| Payments | Paystack + Fincra **sandbox/test** keys | |

### Security notes

- `.env` is **git-ignored and untracked** — no secrets committed (verified with `git check-ignore .env`).
- `.env` contains sandbox/test credentials for several providers; treat as sensitive, never copy into shared docs.
- CSL live trading remains disabled everywhere (`.env`, `phpunit.xml`, config defaults) per project rule #5.

### Project structure inventory

| Area | Count |
|------|-------|
| Controllers (`app/Http/Controllers`) | 81 |
| Models (`app/Models`) | 82 |
| Service classes (`app/Services`) | 141 |
| Migrations (`database/migrations`) | 190 (all applied) |
| Route files (`routes/`) | 4 (`web.php`, `api.php`, `console.php`, + bootstrap) |
| Frontend files (`resources/js`, `.vue`/`.js`) | 227 |
| Test files (`tests/`) | 71 |


---

## 2. Baseline Command Results

Each required baseline command was run individually:

| # | Command | Outcome |
|---|---------|---------|
| 1 | `php artisan --version` | **Laravel Framework 12.62.0** |
| 2 | `php artisan about` | **OK** — env=local, PHP 8.2.12, debug ENABLED; drivers: DB mysql, cache/session/queue redis, mail log; Config/Events/Routes not cached; Views cached; storage linked; Pulse v1.7.4 enabled; Spatie Permissions 6.25.0 |
| 3 | `php artisan route:list` | **OK — 411 routes**; full output saved to `docs/Steps-1-route-inventory.txt` |
| 4 | `php artisan migrate:status` | **OK — every migration "Ran"** (batches 1–47; latest `2026_09_30_084922_change_kyc_identity_fields_to_text_on_kyc_profiles_table`) |
| 5 | `php artisan test` | Baseline: **303/304 pass, 5 failures** — all in `Tests\Feature\CSL\CslDiagnoseCommandTest` (defects DS1-001/DS1-002/DS1-004). After fixes: **OK — 304 tests, 1124 assertions**, 6 non-failing PHPUnit deprecations |
| 6 | `npm run build` | Baseline: **FAIL** — `Cannot find module '@vue/compiler-dom'` (corrupt `node_modules`). After `npm install` repair: **Vite 6.4.3 build succeeds** |

No command failures remain outstanding except those recorded in the defect register.

---

## 3. Route Inventory (summary)

Full machine-generated list: **`docs/Steps-1-route-inventory.txt`** (411 routes).

| Group | Mount | Description |
|-------|-------|-------------|
| Web | `/`, `/dashboard`, `/wallet`, `/profile`, `/{any}` | SPA shell (Vue); catch-all for Vue Router |
| Web (guest) | `login`, `register`, `reset-password` | Session auth (Fortify-backed controllers) |
| Web (auth) | `confirm-password`, `logout`, `password`, `profile` | Profile & password management |
| Web (health) | `/health` | DB/Redis/queue health probe |
| Admin (web) | `admin/dashboard`, `admin/notifications`, `admin/settings`, `admin/departments`, `admin/audit`, `admin/security/*` | Admin panel pages (`auth` + `admin` middleware) |
| API — auth | `api/login`, `api/register`, `api/logout`, `api/user`, `api/verify-email`, `api/forgot-password`, `api/reset-password/{token}` | Sanctum token auth |
| API — core finance | `api/wallet/*`, `api/deposit`, `api/withdraw`, `api/transactions`, `api/fx/*`, `api/paystack/*`, `api/fincra/*` | Wallet, ledger, FX, payment webhooks |
| API — trading | `api/orders`, `api/trade/*`, `api/market/*`, `api/market-data/*`, `api/portfolio/*`, `api/watchlist/*`, `api/symbols/*` | OMS/Trade/Market controllers (NGX, global, crypto, fixed income) |
| API — CSL/provider | `api/oms/*`, `api/provider/*`, `api/csl/*` | Order placement, cancellation, reconciliation |
| API — KYC/security | `api/kyc/*`, `api/profile/kyc`, `api/security/2fa/*`, `api/security/withdrawals/*`, `api/security/devices/*`, `api/security/audit-logs/*` | Dojah KYC, 2FA, withdrawal protection |
| API — admin | `api/admin/*` | Users, compliance, billing, reports, advisory, model portfolios, subscriptions, fixed-income admin |
| API — fixed income | `api/fixed-income/*`, `admin/fixed-income/*` | Products, investments, reports |
| API — webhooks | `api/webhooks/{paystack,fincra,dojah,qoreid,alpaca,crypto}` | Provider callbacks (rate-limited) |
| Framework | Fortify (`user/*`, `two-factor-*`), Telescope (`telescope/*`), Pulse (`pulse`), Boost | Tooling & session auth |

---

## 4. Test Results

- **Full suite:** `php vendor/bin/phpunit` → **OK — 304 tests, 1124 assertions**, exit code 0.
- **Deprecations:** 6 PHPUnit warnings (`@test` doc-comment metadata in `LoginTest`, `WalletTest`). Non-failing; migrate to `#[Test]` attributes before PHPUnit 12.
- **Isolated re-run after fixes:** `CslDiagnoseCommandTest` → OK (5 tests, 36 assertions).
- Tests run exclusively against SQLite `:memory:`; `CSL_LIVE_TRADING_ENABLED=false` and `ALPACA_LIVE_TRADING_ENABLED=false` enforced in `phpunit.xml` — no real financial transactions possible.

---

## 5. Application Audit Summary

- **Backend:** 81 controllers, 82 models, 141 services reviewed at route/contract level; routes, middleware (`auth`, `admin`, `kyc:N`, `throttle`) and Spatie RBAC verified present; scheduled commands inventoried in `routes/console.php` (settlement, billing, CSL sync/reconcile, Alpaca reconcile).
- **Frontend:** 227 Vue/JS files; Vue Router (auth guards, `requiresAuth`/`adminOnly`), axios Bearer-token API client, sidebar/layout navigation consistent with route inventory.
- **Database:** 190 migrations, all applied; core schemas (users, wallets dual-currency, orders+trades with provider columns, portfolios, ledgers, transactions, KYC profiles, provider accounts/sync logs, fixed income) cross-checked against models.
- **Integrations:** CSL (mock transport + OAuth client + reconciliation + diagnose), Alpaca (paper/mock), Paystack & Fincra (sandbox), Dojah KYC (primary), wallet/ledger/settlement/withdrawal services, fixed-income services — config present in `config/services.php` and `.env`.
- **Prior audit docs:** `docs/audits/*.md` (7 documents) reviewed; their open items are tracked there and are out of Step 1 scope.

---

## 6. Deliverables Checklist

| Deliverable | Location | Status |
|---|---|---|
| Environment report | `docs/Steps-1-environment-report.md` (this file) | ✅ |
| Baseline command results | Section 2 above | ✅ |
| Route inventory | `docs/Steps-1-route-inventory.txt` + Section 3 | ✅ |
| Test results | Section 4 above | ✅ |
| Prioritized defect register | `docs/Steps-1-3-defect-register.md` | ✅ |

## 7. Revalidation — 2026-10-19

The following checks were repeated on `stabilization/Steps-1-3`:

| Check | Current result |
|-------|----------------|
| `php artisan --version` | Laravel Framework 12.62.0 |
| `php artisan about --only=environment` | OK — PHP 8.2.12, local environment, debug enabled |
| `php artisan route:list --json` | 411 routes; committed inventory contains 411 route entries plus its header |
| `php artisan migrate:status` | Latest migration reports `Ran` |
| Full test suite | 301 passed, 0 failed after seeder and transaction-ownership regressions were added |
| `npm run build` | Succeeded; Vite warns that at least one generated chunk exceeds 500 kB |
| Fresh SQLite migration and seed | All migrations and `DatabaseSeeder` completed on a new disposable SQLite database under the system temp directory |

The first fresh seed attempt under the OneDrive workspace was stopped after more than ten minutes while `ComplianceSeeder` was running. Repeating the fresh migration and complete seed under the system temp directory succeeded; writes were substantially faster, confirming workspace synchronization as the main source of the earlier delay. A pre-fix full-suite run returned 297 passing tests; after the seeder and transaction-ownership fixes, the full suite returned 301 tests with zero failures. The current worktree also contains untracked local `.env.test` and SQLite artifacts; these were preserved and not inspected for secrets. The original 2026-10-16 test result remains historical; its 304-test total should not be presented as the current total.
