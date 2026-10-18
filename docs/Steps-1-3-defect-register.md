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

| ID | Defect | Severity | Location | Test Result |
|----|--------|----------|----------|-------------|
| DS1-001 | `csl:diagnose` scheduler checks crash: `Call to a member function isEmpty() on array` | Critical | `app/Console/Commands/CslDiagnose.php:523,537` | Pass (fixed) |
| DS1-002 | `csl:diagnose` reports "CSL credentials configured: FAIL" even when mock transport is enabled | Medium | `app/Console/Commands/CslDiagnose.php:182-186` | Pass (fixed) |
| DS1-003 | CSL test credentials not configured in `.env` | High | `.env` (`CSL_CLIENT_ID`, `CSL_CLIENT_SECRET` empty) | Blocked — awaiting credentials |
| DS1-004 | `CslDiagnoseCommandTest` output expectations overlap, so Mockery's first-match-wins output matching masks command output and produces false failures | Medium | `tests/Feature/CSL/CslDiagnoseCommandTest.php` | Pass (fixed) |
| DS1-005 | Inconsistent `CSL_MOCK` value between `.env` (true) and `phpunit.xml` (false) | Low | `.env` vs `phpunit.xml` | Pending — documentation decision |

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

