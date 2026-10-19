# Steps 1-3 Defect Register

**Project:** Xavier Trading Platform  
**Branch:** stabilization/Steps-1-3  
**Date:** 2026-10-16  
**Status:** Step 1 Complete — Baseline established, defects logged and prioritized

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
| Frontend Build | `npm run build` | Vite build succeeds |

---

## Defect Register Summary

| ID | Defect | Severity | Location | Reproduction | Test Result |
|----|--------|----------|----------|--------------|-------------|
| DS1-001 | `csl:diagnose` scheduler checks crash: `Call to a member function isEmpty() on array` | Critical | `app/Console/Commands/CslDiagnose.php:523,537` | Run `php artisan csl:diagnose` with Laravel 12's array result from `Schedule::events()` | Pass (fixed) |
| DS1-002 | `csl:diagnose` reports "CSL credentials configured: FAIL" even when mock transport is enabled | Medium | `app/Console/Commands/CslDiagnose.php:182-186` | Enable `CSL_MOCK` and leave CSL credentials empty, then run `php artisan csl:diagnose` | Pass (fixed) |
| DS1-003 | CSL test credentials not configured in `.env` | High | `.env` (`CSL_CLIENT_ID`, `CSL_CLIENT_SECRET` empty) | Attempt real CSL authentication with mock mode disabled; provider request cannot authenticate | Blocked — awaiting credentials |
| DS1-004 | `CslDiagnoseCommandTest` output expectations overlap, so Mockery's first-match-wins output matching masks command output and produces false failures | Medium | `tests/Feature/CSL/CslDiagnoseCommandTest.php` | Run `php artisan test --filter=CslDiagnoseCommandTest` against the overlapping output expectations | Pass (fixed) |
| DS1-005 | Inconsistent `CSL_MOCK` value between `.env` (true) and `phpunit.xml` (false) | Low | `.env` vs `phpunit.xml` | Compare the effective local and PHPUnit values of `CSL_MOCK` | Pending — documentation decision |
| DS2-001 | Re-running `UserSeeder` appended 20 new random users instead of converging on the same fixture set | Medium | `database/seeders/UserSeeder.php` | Run `RolePermissionSeeder`, then run `UserSeeder` twice and compare user counts (21 → 41 before fix) | Pass (fixed; focused regression passes) |
| DS2-002 | Several demo seeders append new transactions, orders, linked accounts and billing records on each run | Medium | `database/seeders/TransactionSeeder.php`, `OrderSeeder.php`, `UserSettingsSeeder.php`, `ComplianceSeeder.php` | Run `php artisan db:seed` twice on a fresh disposable database and compare record counts and references | Open — first full seed passes in temp; repeat full seed not run |
| DS2-003 | `NewTransactionSeeder` generated completed deposits and fee earnings for every user on each run | Medium | `database/seeders/NewTransactionSeeder.php` | Run `NewTransactionSeeder` twice and compare seeded transaction and earning counts; before fix 21 → 70 | Pass (fixed; focused regression passes) |
| DS2-004 | Transaction detail requests returned HTTP 500 because the selected model class string was queried as an object | Medium | `app/Http/Controllers/Api/NewTransactionController.php:147` | Request `/api/transactions/{id}` as the owner and as a different authenticated user; both returned 500 before fix | Pass (fixed; owner and cross-user tests pass) |

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

### DS2-002: Full demo seeding is slow and append-only in several seeders (MEDIUM) — OPEN

**Evidence:** A fresh migration and complete `DatabaseSeeder` run succeeded against SQLite under the system temp directory. Under the OneDrive workspace, the initial attempt exceeded ten minutes before being stopped. On the synced workspace, earlier seeders reported approximately 59 seconds for `NewTransactionSeeder`, 195 seconds for `OrderSeeder`, 75 seconds for `PortfolioSeeder`, and 99 seconds for `UserSettingsSeeder`; the temp-directory run completed substantially faster. `TransactionSeeder` creates rows with random ULID references; `OrderSeeder`, `UserSettingsSeeder` and `ComplianceSeeder` also use append-only `create()` calls for sample records.

**Impact:** A fresh development install can exceed a five-minute seed timeout, and rerunning the full seeder can add additional demo activity and reference records.

**Required fix:** Repeat the full migration and seed checks using a disposable SQLite database outside the synchronized workspace, then make the append-only demo fixtures idempotent or clearly isolate them as an explicitly opt-in demo seeder. Optimize only after measuring outside OneDrive to distinguish database write cost from application work.

**Test result:** Partial — fresh migration and first full seed pass in temp; second full seed and count/reference comparison remain unverified. Workspace-synced seed performance is poor but is not a migration or seeder failure.

### DS2-003: New transaction seeder duplicates completed deposits (MEDIUM) — FIXED

**Root cause:** `NewTransactionSeeder` created a completed deposit for every user without a stable seed key. It then called `TransactionService::applyFees()`, which created another platform-earning row. Repeating the seeder on the temp database increased `new_transactions_table` from 21 to 70 and platform earnings from 21 to 70.

**Fix applied:** Seed deposits only for the stable demo-user identities introduced by `UserSeeder`, attach a deterministic `seed_key` in transaction metadata, and skip an existing seeded deposit before applying fees.

**Verification:** `tests/Feature/NewTransactionSeederTest.php` passes. It runs the seeder twice, checks transaction and earning counts are unchanged, asserts only the 20 demo users are targeted, and verifies an ordinary user receives no seeded deposit.

### DS2-004: Transaction detail endpoint throws on class-string query (MEDIUM) — FIXED

**Root cause:** `resolveModels()` returns the transaction model class name as a string. `show()` attempted to call `where()` directly on that string, causing a 500 for both valid and unauthorized transaction IDs.

**Fix applied:** Query through the selected model's static `query()` builder while retaining the `user_id` ownership filter.

**Verification:** `tests/Feature/TransactionOwnershipTest.php` passes for both owner access (200) and cross-user access (404).

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

## Next Steps (Step 2)

1. Obtain CSL test credentials and resolve DS1-003.
2. Run `csl:diagnose` against real CSL test endpoints with mock disabled.
3. Execute financial workflow testing (deposit → trade → settlement → withdrawal) on a safe test database.
4. Decide DS1-005 (documentation vs alignment).
5. Migrate `@test` doc-comments to `#[Test]` attributes (low priority).

