# Steps 1-3 Defect Register

**Project:** Xavier Trading Platform  
**Branch:** stabilization/Steps-1-3  
**Date:** 2026-10-19
**Status:** Steps 1–3 automated stabilization checks pass; authenticated browser workflows and real CSL OAuth remain unverified/blocked.

---

## Step 1 Baseline Summary

| Check | Command | Result |
|-------|---------|--------|
| Framework | `php artisan --version` | Laravel Framework 12.62.0 |
| Environment | `php artisan about` | PHP 8.2.12, Composer 2.8.12, env=local |
| Routes | `php artisan route:list` | 411 routes registered |
| Migrations | `php artisan migrate:status` | All migrations "Ran" |
| Tests (baseline) | `php artisan test` | 5 failures in `CslDiagnoseCommandTest` |
| Tests (after fixes) | `php vendor/bin/phpunit` | **OK — 304 tests, 1124 assertions** (6 PHPUnit deprecations, non-failing) |
| Final Step 3 suite | `php artisan test` | **OK — 311 tests, 1,142 assertions, 0 failures** (6 non-failing PHPUnit metadata deprecations) |
| Frontend Build | `npm run build` | Vite build succeeds |

---

## Defect Register Summary

| ID | Defect | Severity | Location | Reproduction | Test Result |
|----|--------|----------|----------|--------------|-------------|
| DS1-001 | `csl:diagnose` scheduler checks crash: `Call to a member function isEmpty() on array` | Critical | `app/Console/Commands/CslDiagnose.php:523,537` | Run `php artisan csl:diagnose` with Laravel 12's array result from `Schedule::events()` | Pass (fixed) |
| DS1-002 | `csl:diagnose` reports "CSL credentials configured: FAIL" even when mock transport is enabled | Medium | `app/Console/Commands/CslDiagnose.php:182-186` | Enable `CSL_MOCK` and leave CSL credentials empty, then run `php artisan csl:diagnose` | Pass (fixed) |
| DS1-003 | CSL test credentials not configured in `.env` | High | `.env` (`CSL_CLIENT_ID`, `CSL_CLIENT_SECRET` empty) | Attempt real CSL authentication with mock mode disabled; provider request cannot authenticate | Blocked — awaiting credentials |
| DS1-004 | `CslDiagnoseCommandTest` output expectations overlap, so Mockery's first-match-wins output matching masks command output and produces false failures | Medium | `tests/Feature/CSL/CslDiagnoseCommandTest.php` | Run `php artisan test --filter=CslDiagnoseCommandTest` against the overlapping output expectations | Pass (fixed) |
| DS1-005 | Inconsistent `CSL_MOCK` value between `.env` (true) and `phpunit.xml` (false) | Low | `.env` vs `phpunit.xml` | Compare the effective local and PHPUnit values of `CSL_MOCK` | Documented intentional difference; real CSL remains blocked |
| DS2-001 | Re-running `UserSeeder` appended 20 new random users instead of converging on the same fixture set | Medium | `database/seeders/UserSeeder.php` | Run `RolePermissionSeeder`, then run `UserSeeder` twice and compare user counts (21 → 41 before fix) | Pass (fixed; focused regression passes) |
| DS2-002 | Full demo seed chain appended records and changed fixture data on repeat runs | Medium | `database/seeders/` reference, transaction, wallet, portfolio, KYC, compliance, and expense seeders | Run the full `DatabaseSeeder` twice on a migrated disposable SQLite DB and compare every table plus wallet balances | Pass (fixed; full repeatability regression passes) |
| DS2-003 | `NewTransactionSeeder` generated completed deposits and fee earnings for every user on each run | Medium | `database/seeders/NewTransactionSeeder.php` | Run `NewTransactionSeeder` twice and compare seeded transaction and earning counts; before fix 21 → 70 | Pass (fixed; focused regression passes) |
| DS2-004 | Transaction detail requests returned HTTP 500 because the selected model class string was queried as an object | Medium | `app/Http/Controllers/Api/NewTransactionController.php:147` | Request `/api/transactions/{id}` as the owner and as a different authenticated user; both returned 500 before fix | Pass (fixed; owner and cross-user tests pass) |
| DS2-005 | `DatabaseSeeder` could seed demo users/data and the shared `password` credential in production | High | `database/seeders/DatabaseSeeder.php` | Invoke `DatabaseSeeder::run()` with the app environment set to production | Pass (fixed; production guard test passes) |
| DS2-006 | Demo database seeding is slow on synchronized storage | Low | `database/seeders/TransactionSeeder.php`, `OrderSeeder.php`, `UserSettingsSeeder.php`, `ComplianceSeeder.php` | Time the full seed on OneDrive vs a system-temp SQLite database | Open — repeatability passes; performance remains slow and environment-dependent |

---

## Detailed Defect Analysis

### DS1-001: Scheduler `isEmpty()` on array (CRITICAL) — FIXED

**Root cause:** In Laravel 12, `Illuminate\Console\Scheduling\Schedule::events()` returns a plain PHP array, not a collection. `CslDiagnose::checkScheduler()` called `$events->isEmpty()` (and later `$events->map()`), which threw `Call to a member function isEmpty() on array`. The try/catch marked all 4 scheduler checks as `UNAVAILABLE` with `failed: true`, forcing the overall status to NOT READY with exit code 1 regardless of the rest of the integration state.

**Fix applied:** `empty($events)` instead of `$events->isEmpty()`; `collect($events)->map(...)` instead of `$events->map(...)`. `php artisan csl:diagnose` now reports `READY` with exit code 0 in the local mock environment.

**Verification:** `CslDiagnoseCommandTest` — 5/5 tests pass (36 assertions).

### DS1-002: Credentials FAIL in mock mode (MEDIUM) — FIXED

**Root cause:** `CslDiagnose::checkConfiguration()` unconditionally checked `CSL_CLIENT_ID`/`CSL_CLIENT_SECRET`, reporting FAIL when empty. When `CSL_MOCK=true` the deterministic mock transport serves every CSL call, so credentials are not required — `CslHealthService::check()` already short-circuits in mock mode, but the configuration section did not.

**Fix applied:** Credentials are reported `OK` (non-failing) when the mock transport is enabled, with the note "not required (mock transport)". A missing-credential FAIL is still raised when mock is disabled, preserving the DS1-003 guarantee.

### DS1-003: CSL test credentials not configured (HIGH) — OPEN (BLOCKED)

**Root cause:** `.env` has empty values for `CSL_CLIENT_ID` and `CSL_CLIENT_SECRET`. The application cannot authenticate to the real CSL OAuth endpoint without them.

**Impact:** Live CSL integration validation cannot proceed. Mock mode works for development and UAT, but acceptance testing against real CSL endpoints requires valid test credentials.

**Required fix:** Obtain CSL test credentials from the provider and add them to `.env` (never commit). `CSL_LIVE_TRADING_ENABLED` must remain `false` per project rules.

**Test result:** Blocked — no code change possible until credentials are supplied.

### DS1-004: Overlapping output expectations in CslDiagnoseCommandTest (MEDIUM) — FIXED

**Root cause:** Laravel's `PendingCommand::mockConsoleOutput()` registers one Mockery expectation per `expectsOutputToContain()` substring, and Mockery serves each `doWrite()` call from the **first** matching expectation (earlier-registered expectations are never exhausted). Substring overlaps therefore masked later expectations:

- `"Market"` matched the `"Market account mapped"` row first → its own expectation never fired.
- `"CSL integration status"` matched the `NOT READY` / `READY` summary line first → `"NOT READY"` / `"READY"` never fired.

The command itself rendered every line correctly (confirmed via temporary per-row debug logging), so these were false negatives.

**Fix applied:** Expectations were made non-overlapping (specific substrings registered before — and replacing — their overlapping supstrings; e.g. the summary line is asserted via `READY`/`NOT READY` instead of `CSL integration status`). Temporary debug logging was removed afterwards.


### DS1-005: Inconsistent CSL_MOCK configuration (LOW) — OPEN

**Root cause:** `.env` sets `CSL_MOCK=true` (local development uses the deterministic fixture transport) while `phpunit.xml` sets `CSL_MOCK=false` (tests use `Http::fake()`). This is intentional — tests deliberately exercise the non-mock HTTP code path — but it is undocumented.

**Required fix:** Document the intentional difference (e.g. a comment in `phpunit.xml`) or align the values. No behavioural defect observed; `CslDiagnoseCommandTest::setUp()` also sets the config explicitly.

**Test result:** Pending — documentation decision.

### DS2-001: User seeder appends duplicate demo users (MEDIUM) — FIXED

**Root cause:** `UserSeeder` used `User::factory()->count(20)->create()`, which generates new random email addresses each run. The super-admin record was already idempotent, but the normal demo users were not.

**Reproduction:** On a fresh test database, run `RolePermissionSeeder`, then run `UserSeeder` twice. Before the fix, the user count increased from 21 to 41.

**Fix applied:** Seed 20 normal demo users using stable email identities and `updateOrCreate`.

**Verification:** `tests/Feature/UserSeederTest.php` passes, asserting that the second run does not increase the user count.

### DS2-002: Full demo seed chain was not repeatable (MEDIUM) — FIXED

**Root cause:** Multiple fixture seeders used random values and unconditional `create()` calls; compliance users were created after seeders that enumerate all users. Repeated runs appended transactions, orders, linked accounts, KYC records, notifications, activity/billing/device records, FX singleton settings, advisories, and expenses, and the wallet seeder could overwrite financial fixture values.

**Fix applied:** Use stable fixture keys and `updateOrCreate`/`firstOrCreate`, deterministic fixture values and user selections, seed compliance users before user-wide data, and guard the demo `DatabaseSeeder` from production.

**Verification:** `tests/Feature/DatabaseSeederTest.php` runs the full seed chain twice, compares every database table count, compares wallet balances/cleared balances/account references, and confirms required roles and permissions exist. It passes.

**Test result:** Pass — fresh migration plus two complete seed runs succeeded on disposable SQLite. Repeat seeding on OneDrive remains substantially slower than in a local temp directory; see DS2-006.

### DS2-003: New transaction seeder duplicates completed deposits (MEDIUM) — FIXED

**Root cause:** `NewTransactionSeeder` created a completed deposit for every user without a stable seed key. It then called `TransactionService::applyFees()`, which created another platform-earning row. Repeating the seeder on the temp database increased `new_transactions_table` from 21 to 70 and platform earnings from 21 to 70.

**Fix applied:** Seed deposits only for the stable demo-user identities introduced by `UserSeeder`, attach a deterministic `seed_key` in transaction metadata, and skip an existing seeded deposit before applying fees.

**Verification:** `tests/Feature/NewTransactionSeederTest.php` passes. It runs the seeder twice, checks transaction and earning counts are unchanged, asserts only the 20 demo users are targeted, and verifies an ordinary user receives no seeded deposit.

### DS2-004: Transaction detail endpoint throws on class-string query (MEDIUM) — FIXED

**Root cause:** `resolveModels()` returns the transaction model class name as a string. `show()` attempted to call `where()` directly on that string, causing a 500 for both valid and unauthorized transaction IDs.

**Fix applied:** Query through the selected model's static `query()` builder while retaining the `user_id` ownership filter.

**Verification:** `tests/Feature/TransactionOwnershipTest.php` passes for both owner access (200) and cross-user access (404).

### DS2-005: Demo database seeder lacked a production guard (HIGH) — FIXED

**Root cause:** `DatabaseSeeder` includes a known demo admin account using the shared password `password` and several financial demo fixtures, but did not prevent execution in production.

**Fix applied:** `DatabaseSeeder::run()` throws a `RuntimeException` when the application environment is production.

**Verification:** `DatabaseSeederTest::test_full_database_seeder_refuses_to_run_in_production` passes.

### DS2-006: Full seed performance on synchronized storage (LOW) — OPEN

**Evidence:** Fresh migrations and full seed passes complete in a system-temp SQLite database. The same writes are substantially slower under the OneDrive workspace, where a first attempt exceeded ten minutes before it was stopped.

**Impact:** Local developer setup can take several minutes when the project is inside a synchronized folder; this is not a migration failure or repeatability failure.

**Required action:** Prefer a local non-synchronized database path for fresh-install verification. Further batch-write optimization is optional and should be measured outside OneDrive first.

**Test result:** Open performance follow-up; functional repeatability passes.

---

## Notes

- 6 PHPUnit deprecations remain (`@test` doc-comment metadata in `LoginTest` and `WalletTest`). Non-failing; should be migrated to `#[Test]` attributes before a PHPUnit 12 upgrade. Recorded as a low-priority follow-up.
- `.env` is correctly git-ignored; no secrets are committed.

---

## Verified Working Features (Regression Baseline)

Confirmed by the passing full suite (304 tests, 1124 assertions):

- **Authentication:** Login, registration, password reset, 2FA, email verification
- **Wallet:** Balances, deposits, minimum-amount validation
- **CSL services:** OAuth/authentication, order submission, cancellation, reconciliation, partial fills, instrument sync, portfolio sync, account sync, market data, idempotency, safety guards, **and the `csl:diagnose` health check**
- **Demo mode:** Wallet funding, trading, portfolio, reset
- **Billing:** Fee deduction, debt handling, suspension
- **Admin/Security:** Controls, notifications, device tracking, 2FA enforcement
- **Frontend build:** `npm run build` (Vite) succeeds

---

## Untested / Unverified (Step 1 rule #9)

- Live CSL trading — intentionally disabled (`CSL_LIVE_TRADING_ENABLED=false`); **unverified**
- Real CSL OAuth connectivity — blocked on DS1-003; **unverified**
- Real Paystack/Fincra webhook processing (sandbox keys only) — **unverified**
- Production database migrations (tests run on SQLite `:memory:`) — **unverified**
- Real financial transactions — blocked by project rules; **unverified**

---

## Remaining Work (Steps 4–6)

1. Obtain CSL test credentials securely and run real OAuth/read-only sandbox validation; keep live trading disabled.
2. Complete financial, fixed-income, settlement, and CSL acceptance work in Steps 4–6 using disposable test data and mock/sandbox providers only.
3. Run authenticated browser workflows with dedicated admin and end-user test accounts.
4. Prefer non-synchronized storage for full seed/migration performance checks; DS2-006 remains low severity.
5. Migrate the six deprecated PHPUnit doc-comment annotations before PHPUnit 12.

