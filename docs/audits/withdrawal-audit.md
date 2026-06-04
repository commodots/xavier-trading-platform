# Withdrawal Audit

**Date:** 2026-06-03 
**Scope:** WithdrawalController (Security), WithdrawalService, WithdrawalProtectionService

---

## What Exists

### WithdrawalController (`app/Http/Controllers/Api/Security/WithdrawalController.php`)

        | Method | Route | Description |
| `index` | GET `/security/withdrawals` | List user's withdrawals (paginated) |
| `store` | POST `/security/withdrawals` | Initiate a withdrawal |
| `show` | GET `/security/withdrawals/{id}` | Get withdrawal detail |
| `approve` | POST `/security/withdrawals/{id}/approve` | Admin approve |
| `reject` | POST `/security/withdrawals/{id}/reject` | Admin reject |

### WithdrawalService (`app/Services/WithdrawalService.php`)

                | Method | Purpose |

| `initiateWithdrawal()` | Creates `WithdrawalRequest`, records daily limit usage, fires `WithdrawalInitiated` notification |
| `approveWithdrawal()` | Marks approved, calls `deductFromWallet()` |
| `rejectWithdrawal()` | Marks rejected, refunds daily limit counter |
| `completeWithdrawal()` | Marks completed (approved → completed) |
| `failWithdrawal()` | Marks failed, refunds daily limit counter |
| `checkFraudPatterns()` | Checks multiple withdrawals in 1 hour, checks unusually large amount (3× average) |
| `deductFromWallet()` | Private — deducts from `ngn_cleared` or `usd_cleared` |

### WithdrawalProtectionService (`app/Services/WithdrawalProtectionService.php`)

Single `check()` method. Validates:
- Account not suspended/inactive
- No outstanding `wallet_debt`
- Sufficient `ngn_cleared` / `usd_cleared` balance

---

## Checks

### Daily Limits
**Implemented**

`WithdrawalLimit` model is created per-user with defaults:
- NGN: 500,000
- USD: 2,500

`WithdrawalService::initiateWithdrawal()` calls `$limit->canWithdraw()` and `$limit->recordWithdrawal()`.

**Gap:** `WithdrawalProtectionService::check()` does **not** call the daily limit check — it only checks balance and debt. The limit check only happens inside `WithdrawalService::initiateWithdrawal()`. 

### Settlement Validation (Cleared Balance)
**Implemented**

Both `WithdrawalProtectionService::check()` and `WithdrawalService::initiateWithdrawal()` validate against `ngn_cleared` / `usd_cleared` only. Uncleared (unsettled) funds cannot be withdrawn.

### Debt Validation
**Implemented**

`WithdrawalProtectionService::check()` explicitly blocks if `$user->wallet_debt > 0`.

### KYC Validation
**Missing**

Neither `WithdrawalController`, `WithdrawalService`, nor `WithdrawalProtectionService` checks KYC status before allowing a withdrawal. There is no `KycLevelMiddleware` on withdrawal routes. A user with no KYC can currently withdraw.

### OTP Validation
**Partial — separate flow, not enforced here**

`WithdrawalOtpNotification` exists and sends a 6-digit OTP via email. `NewTransactionController` has a `sendOtp()` method. However:
- The `/security/withdrawals` POST route does **not** require OTP verification
- OTP is only wired into the `NewTransactionController` withdrawal flow, not `WithdrawalController`
- Two separate withdrawal flows exist: `/withdraw` (NewTransactionController) and `/security/withdrawals` (WithdrawalController) — they are not in sync

### 2FA Enforcement
**Missing**

`WithdrawalController::store()` does not check `$user->google2fa_enabled` or `$user->two_factor_enabled` before proceeding. The plan to enforce 2FA before withdrawals is not yet implemented.

### Fraud Detection
**Implemented**

`WithdrawalController::store()` calls `checkFraudPatterns()` before initiating, with audit logging via `AuditService::logSecurityEvent()` on fraud flag. `RateLimitService::isWithdrawalLimited()` is also checked for rate limiting.

---

## Bugs Found

`WithdrawalController::store()` | No KYC level check — any authenticated user can initiate a withdrawal |
 `WithdrawalController::store()` | No 2FA enforcement — `two_factor_enabled` is not checked |
`WithdrawalService::deductFromWallet()` | No DB transaction wrapping — balance deduction and status update can desync on failure |
 Dual withdrawal flows | `/withdraw` (OTP-gated) and `/security/withdrawals` (no OTP) both exist — inconsistent protection |
`WithdrawalProtectionService::check()` | Does not check daily limits — protection service is incomplete as a standalone gate |
`rejectWithdrawal()` / `failWithdrawal()` | Limit counter refunded using direct subtraction without DB transaction — can go negative |
