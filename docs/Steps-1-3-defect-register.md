# Steps 1-3 Defect Register

**Project:** Xavier Trading Platform  
**Branch:** stabilization/Steps-1-3  
**Date:** 2026-10-16  
**Status:** Step 1 Complete — Baseline established, defects logged

---

## Step 1 Baseline Summary

| Check | Command | Result |
|-------|---------|--------|
| Framework | `php artisan --version` | Laravel Framework 12.62.0 |
| Environment | `php artisan about` | PHP 8.2.12, Composer 2.8.12, env=local |
| Routes | `php artisan route:list` | 411 routes registered |
| Migrations | `php artisan migrate:status` | All migrations "Ran" |
| Tests | `php artisan test` | 5 failures in `CslDiagnoseCommandTest` |
| Frontend Build | `npm run build` | Vite build succeeds |

**Test Results:** All test suites pass except `Tests\Feature\CSL\CslDiagnoseCommandTest` (5/5 tests fail).

---

## Defect Register Summary

| ID | Defect | Severity | Location | Test Result |
|----|--------|----------|----------|-------------|
| DS1-001 | `csl:diagnose` scheduler checks crash: `Call to a member function isEmpty() on array` | Critical | `app/Console/Commands/CslDiagnose.php:516` | Fail |

---

## Detailed Defect Analysis

### DS1-001: Scheduler `isEmpty()` on array (CRITICAL)

**Root cause:** In Laravel 12, `Illuminate\Console\Scheduling\Schedule::events()` returns a plain PHP array, not a collection. The `CslDiagnose::checkScheduler()` method calls `$events->isEmpty()` on line 523, which throws:

```
Call to a member function isEmpty() on array
```

This exception is caught by the try/catch in `checkScheduler()`, which marks all 4 scheduler checks as `UNAVAILABLE` with `failed: true`. Since any `failed: true` check flips the overall status to NOT READY, the command always exits with code 1.

**Observed output:**
```
Scheduler
---------
  Instrument sync                  UNAVAILABLE  (Call to a member function isEmpty() on array)
  Order reconciliation             UNAVAILABLE  (Call to a member function isEmpty() on array)
  Portfolio sync                   UNAVAILABLE  (Call to a member function isEmpty() on array)
  Account sync                     UNAVAILABLE  (Call to a member function isEmpty() on array)

Overall

### DS1-002: Credentials FAIL in mock mode (MEDIUM)

**Root cause:** `CslDiagnose::checkConfiguration()` unconditionally checks `CSL_CLIENT_ID` and `CSL_CLIENT_SECRET` (lines 182-185), reporting FAIL when they are empty. However, when `CSL_MOCK=true`, the mock transport handles all CSL calls without needing real credentials. `CslHealthService::check()` already handles this correctly by short-circuiting and returning all checks as OK when mock is enabled — but `CslDiagnose::checkConfiguration()` runs independently and does not account for mock mode.

**Impact:** Operators running the diagnostic in mock mode see "CSL credentials configured: FAIL" and may incorrectly conclude the integration is broken.

**Fix:** In `checkConfiguration()`, skip the credentials pass/fail when mock is enabled, or report it as informational only (non-failing) in mock mode.
-------
  CSL integration status           NOT READY
```

**Impact:** The `csl:diagnose` command is the single operational health check for CSL integration. It cannot be used to verify readiness because it always reports NOT READY due to this bug. All 5 tests in `CslDiagnoseCommandTest` fail as a result.

**Fix:** Change line 523 from:

### DS1-003: CSL credentials not configured (HIGH)

**Root cause:** `.env` has empty values for `CSL_CLIENT_ID` and `CSL_CLIENT_SECRET`. Without these, the application cannot authenticate to the real CSL OAuth endpoint.

**Impact:** Live CSL integration cannot be tested. Mock mode works fine for development and UAT, but final acceptance testing against real CSL endpoints requires valid credentials.

**Fix:** Obtain CSL test credentials from the provider and add them to `.env` (never commit to git). `CSL_LIVE_TRADING_ENABLED` must remain `false` per project rules.
```php
if ($events->isEmpty()) {
```
to:

### DS1-004: Inconsistent CSL_MOCK configuration (LOW)

**Root cause:** `.env` sets `CSL_MOCK=true` while `phpunit.xml` sets `CSL_MOCK=false`. This means tests run against real CSL endpoints (via `Http::fake()`) while local development uses the deterministic fixture transport.

**Impact:** Subtle behavioral differences between test and local environments. The `CslDiagnoseCommandTest` sets `config()->set('services.csl.mock', false)` explicitly, which disables the mock transport in tests and exercises a different code path than local development.

**Fix:** Either align both to the same value, or document that tests intentionally run with mock disabled to exercise `Http::fake()` paths.
```php
if (empty($events)) {
```
| DS1-002 | `csl:diagnose` reports "CSL credentials configured: FAIL" even when mock transport is enabled | Medium | `app/Console/Commands/CslDiagnose.php:182-185` | Fail |
| DS1-003 | CSL credentials not configured in `.env` | High | `.env` lines 166-167 | Pending |

---

## Verified Working Features (Regression Baseline)

The following areas were confirmed working during Step 1:

- **Authentication:** Login, registration, password reset, 2FA, email verification — all tests pass
- **Wallet:** Balances, deposits, minimum amount validation — all tests pass
- **CSL Services (excluding diagnose command):** Authentication, order submission, cancellation, reconciliation, partial fill, instrument sync, portfolio sync, market data, idempotency, safety guards — all tests pass
- **Demo mode:** Wallet funding, trading, portfolio, reset — all tests pass
- **Billing:** Fee deduction, debt handling, suspension — all tests pass
- **Admin:** Controls, notifications — all tests pass
- **Security:** Device tracking, 2FA enforcement — all tests pass
- **Frontend build:** Vite production build succeeds — verified

---

## Untested Features (Marked as Unverified)

Per Step 1 rule #9, the following are marked unverified, not completed:

- Live CSL trading (must remain disabled per project rules)
- Real Paystack/Fincra webhook processing (sandbox keys only)
- Production database migrations (only SQLite test DB used)
- Real financial transactions (blocked by project rules)

---

## Next Steps (Step 2)

1. Fix DS1-001 (scheduler `isEmpty()` bug) — critical path unblocker
2. Fix DS1-002 (credentials check in mock mode)
3. Re-run `php artisan test` to confirm all tests pass
4. Configure CSL test credentials in `.env` for live integration validation
5. Proceed to CSL integration validation and financial workflow testing
| DS1-004 | Inconsistent `CSL_MOCK` value between `.env` and `phpunit.xml` | Low | `.env:164` vs `phpunit.xml:25` | Pending |