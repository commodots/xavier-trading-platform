# Withdrawal Audit

**Date:** 2026-06-09 
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
| `deductFromWallet()` | Private — deducts from `ngn_cleared` or `usd_cleared` inside a DB transaction |
| `approveWithdrawal()` / `rejectWithdrawal()` | Approval/rejection paths reference `WithdrawalApprovedNotification` / `WithdrawalRejectedNotification` classes, but those notification classes do not exist in `app/Notifications` |

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
**Implemented in `WithdrawalController::store()`**

`WithdrawalController::store()` requires `verification_level >= 3` before creating a withdrawal request. KYC still needs consistent enforcement across all withdrawal entry points.

### OTP Validation
**Partial — separate flow, not enforced here**

`WithdrawalOtpNotification` exists and sends a 6-digit OTP via email. `NewTransactionController` has a `sendOtp()` method. However:
- The `/security/withdrawals` POST route does **not** require OTP verification
- OTP is only wired into the `WalletController` withdrawal flow, not `WithdrawalController`
- Three separate withdrawal flows exist: `/withdraw` (WalletController), `/security/withdrawals` (WithdrawalController), and `NewTransactionController` routes — they are not in sync.

### 2FA Enforcement
**Implemented in `WithdrawalController::store()`**

`WithdrawalController::store()` requires either `google2fa_enabled` or `two_factor_enabled` before allowing a withdrawal. The multiple withdrawal flows are not all aligned to the same 2FA protection.

### Fraud Detection
**Implemented**

`WithdrawalController::store()` calls `checkFraudPatterns()` before initiating, with audit logging via `AuditService::logSecurityEvent()` on fraud flag. `RateLimitService::isWithdrawalLimited()` is also checked for rate limiting.

---

## Bugs Found

`WithdrawalController::store()` | 2FA and KYC level 3 checks are present in this flow, but the protection does not extend uniformly across all withdrawal endpoints. |
`/security/withdrawals` | No OTP verification is required, unlike the `WalletController` and `NewTransactionController` withdrawal flows. |
 Dual withdrawal flows | `/withdraw` (WalletController) uses `PciPsd2Compliance` (SCA/Limits) while `/security/withdrawals` (WithdrawalController) bypasses it. |
`WithdrawalProtectionService::check()` | Does not check daily limits — protection service is incomplete as a standalone gate |
`rejectWithdrawal()` / `failWithdrawal()` | Limit counter refunded using direct subtraction without DB transaction — can go negative |
