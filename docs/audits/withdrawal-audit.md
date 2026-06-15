# Withdrawal Audit

**Date:** 2026-06-11 (Updated)
**Scope:** WithdrawalController (Security), WithdrawalService, WithdrawalProtectionService
**Version:** v2 (Post-Fix Updates)

---

## What Exists

### WithdrawalController (`app/Http/Controllers/Api/Security/WithdrawalController.php`)

| Method | Route | Description |
|--------|-------|-------------|
| `index` | GET `/security/withdrawals` | List user's withdrawals (paginated) |
| `store` | POST `/security/withdrawals` | Initiate a withdrawal |
| `show` | GET `/security/withdrawals/{id}` | Get withdrawal detail |
| `approve` | POST `/security/withdrawals/{id}/approve` | Admin approve |
| `reject` | POST `/security/withdrawals/{id}/reject` | Admin reject |
| `sendWithdrawalOtp` | POST `/security/withdrawals/otp` | Send OTP for withdrawal verification |
| `sendOtp` | POST `/security/withdrawals/otp` | Alias for sendWithdrawalOtp |

### WithdrawalService (`app/Services/WithdrawalService.php`)

| Method | Purpose |
|--------|---------|
| `initiateWithdrawal()` | Creates `WithdrawalRequest`, records daily limit usage, fires `WithdrawalInitiated` notification |
| `approveWithdrawal()` | Marks approved, calls `deductFromWallet()` |
| `rejectWithdrawal()` | Marks rejected, refunds daily limit counter **(now in DB transaction)** |
| `completeWithdrawal()` | Marks completed (approved → completed) |
| `failWithdrawal()` | Marks failed, refunds daily limit counter **(now in DB transaction)** |
| `checkFraudPatterns()` | Checks multiple withdrawals in 1 hour, checks unusually large amount (3× average) |
| `deductFromWallet()` | Private — deducts from `ngn_cleared` or `usd_cleared` inside a DB transaction |
| `sendWithdrawalOtp()` | Generates 6-digit OTP, stores in cache (5 min TTL), sends via `WithdrawalOtpNotification` |
| `verifyWithdrawalOtp()` | Verifies OTP against cached value **(uses hash_equals)** |

### WithdrawalProtectionService (`app/Services/WithdrawalProtectionService.php`)

Single `check()` method. Validates:
- Account not suspended/inactive
- No outstanding `wallet_debt`
- **Daily withdrawal limits** (NEW)
- Sufficient `ngn_cleared` / `usd_cleared` balance

---

## Checks

### Daily Limits
** Implemented**

`WithdrawalLimit` model is created per-user with defaults:
- NGN: 500,000
- USD: 2,500

`WithdrawalService::initiateWithdrawal()` calls `$limit->canWithdraw()` and `$limit->recordWithdrawal()`.

**Fixed:** `WithdrawalProtectionService::check()` now also calls the daily limit check for consistency.

### Settlement Validation (Cleared Balance)
** Implemented**

Both `WithdrawalProtectionService::check()` and `WithdrawalService::initiateWithdrawal()` validate against `ngn_cleared` / `usd_cleared` only. Uncleared (unsettled) funds cannot be withdrawn.

### Debt Validation
** Implemented**

`WithdrawalProtectionService::check()` explicitly blocks if `$user->wallet_debt > 0`.

### KYC Validation
** Implemented in `WithdrawalController::store()`**

`WithdrawalController::store()` requires `verification_level >= 3` before creating a withdrawal request.

### OTP Validation
** Implemented in `WithdrawalController::store()`**

`WithdrawalController::store()` requires `otp` field (6-digit) and validates against cached OTP using **timing-safe `hash_equals()`**.

### 2FA Enforcement
** Implemented in `WithdrawalController::store()`**

`WithdrawalController::store()` requires `google2fa_enabled` before allowing a withdrawal.

### Fraud Detection
** Implemented**

`WithdrawalController::store()` calls `checkFraudPatterns()` before initiating, with audit logging via `AuditService::logSecurityEvent()` on fraud flag. `RateLimitService::isWithdrawalLimited()` is also checked for rate limiting.

---

## Withdrawal Flow (Current)

1. **User requests OTP** → `POST /security/withdrawals/otp` → `sendWithdrawalOtp()` → sends 6-digit OTP via email
2. **User submits withdrawal** → `POST /security/withdrawals` with `otp` field
3. **Validation chain:**
   - 2FA enabled (`google2fa_enabled`)
   - OTP verified (timing-safe `hash_equals()` comparison)
   - KYC Level 3 (`verification_level >= 3`)
   - Rate limit check (`RateLimitService::isWithdrawalLimited()`)
   - Fraud pattern check (`checkFraudPatterns()`)
   - Daily limit check (`WithdrawalLimit::canWithdraw()`)
   - Balance check (`ngn_cleared` / `usd_cleared`)
   - Debt check (`wallet_debt <= 0`)
4. **Admin approval** → `POST /security/withdrawals/{id}/approve` → `deductFromWallet()` → `Ledger::create()`
5. **Notification** → `WithdrawalApprovedNotification` / `WithdrawalRejectedNotification`

---

## Bugs Fixed

| Issue | Severity | Status | Details |
|-------|----------|--------|---------|
| `WithdrawalProtectionService::check()` incomplete | Medium | **FIXED** | Now checks daily limits |
| `rejectWithdrawal()` / `failWithdrawal()` limit refund | Medium | **FIXED** | Now wrapped in DB transaction |
| Dual withdrawal flows | Low | **FIXED** | Deprecated `/withdraw` endpoint removed |
| `WithdrawalService` notification classes | Low | **RESOLVED** | Notification classes exist in `app/Notifications/` |
| OTP comparison | Low | **FIXED** | Uses `hash_equals()` for timing-safe comparison |

---

## Remaining Items

1. ~~**`WithdrawalProtectionService` daily limit check**~~ — **FIXED**
2. ~~**DB transaction on limit refund**~~ — **FIXED**
3. ~~**Timing-safe OTP comparison**~~ — **FIXED**