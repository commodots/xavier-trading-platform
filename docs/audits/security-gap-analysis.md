# Security & Compliance Gap Analysis

**Date:** 2026-06-15 (Updated)
**Status:** AUDIT COMPLETE
**Version:** v4 (Post-Fix Updates)

---

## 1. SETTLEMENT AUDIT

### Implemented
- **`SettlementService`** — Full T+2 logic with DB transactions and pessimistic locking
- **`SettleUnsettledTrades` Job** — Currency-aware column mapping (usd_uncleared vs ngn_uncleared)
- **Scheduler:** `settlements:process` → 08:00 daily, `settlement:process` → hourly
- **`settlement_date`**, **`is_settled`**, **`settlement_status`** columns in migrations and model
- **`SettlementCompletedNotification`** — Dispatched after trade settlement
- **`refreshBalance()`** — Now called after settlement to prevent balance drift

### Gaps Remaining
- Partial settlement scenarios not handled (e.g., partial asset liquidation)
- Settlement audit trail metadata limited (no admin context)

**Full audit:** `docs/audits/settlement-audit.md`

---

## 2. WITHDRAWAL AUDIT

### Implemented
- **`WithdrawalController`** — 2FA enforcement, KYC Level 3, OTP verification, fraud detection, rate limiting
- **`WithdrawalService`** — Daily limit checks, OTP methods, wallet deduction in transaction, ledger entries
- **`WithdrawalProtectionService`** — Account status, debt, balance checks, **daily limit checks**
- **Routes:** Centralized at `/security/withdrawals` with `['kyc:2', '2fa']` middleware
- **Notifications:** `WithdrawalInitiated`, `WithdrawalApprovedNotification`, `WithdrawalRejectedNotification`
- **Timing-safe OTP** — `hash_equals()` used for OTP comparison
- **DB transactions** — `rejectWithdrawal()` and `failWithdrawal()` now wrap limit refunds in transactions


---

## 3. KYC AUDIT

### Implemented
- **`KycProfile`** — States: pending, approved, rejected, verified; Tier levels 0–3
- **`KycController`** — Document submission, auto-dispatch ProcessKycVerification job
- **`DojahKycController`** — BVN, NIN, selfie, liveness verification (**PRIMARY PROVIDER**)
- **`KycLevelMiddleware`** — Parameterized level checks applied to routes
- **Route enforcement:**
  - `kyc:0` — Market reads
  - `kyc:1` — Deposits, trading
  - `kyc:2` — Withdrawals, crypto

### Decisions
- **Primary KYC Provider:** Dojah (via `DojahService` and `DojahKycController`)
- **QoreID:** Deprecated — files retained for backward compatibility but not actively used
- **KYC Status:** Both `approved` and `verified` are valid (via `KycService::VERIFIED_STATUSES`)

### Gaps Remaining
- Status inconsistency: `approved` vs `verified` — **KEEP BOTH** 
- No Dojah webhook — manual polling only
- Duplicate KYC endpoints (`KycController::submit()` and `ProfileController::submitKyc()`)


**Full audit:** `docs/audits/kyc-audit.md`

---

## 4. SESSION & DEVICE AUDIT

### Implemented
- **Sanctum token-based auth** — 30-day token expiration
- **`AuthController::login()`** — Activity logging, device tracking, 2FA challenge, new device notification
- **`TwoFactorController::verifyLogin()`** — Post-login 2FA with device tracking
- **`UserDevice`** — Tracks device name, IP, last_active_at
- **`UserDeviceController`** — Register, trust, revoke device endpoints
- **`NewDeviceLoginNotification`** — Dispatched on new device login
- **Activity logging** — Login, logout, failed login with error logging in catch blocks

### Gaps Remaining
- No automatic session cleanup (expired Sanctum tokens)
- Device trust not enforced in middleware
- No geo-blocking or IP-based anomaly detection

**Full audit:** `docs/audits/session-audit.md`

---

## 5. TWO-FACTOR AUTHENTICATION AUDIT

### Implemented (Unified to Google2FA)
- **`TwoFactorController`** — setup, verify, disable, status, verifyLogin
- **`TwoFactorService`** — Recovery code generation, QR generation, enable/disable logic
- **`google2fa_enabled`** and **`google2fa_secret`** fields on users table
- **Login flow** — `AuthController::login()` checks `google2fa_enabled`, returns `requires_2fa: true`
- **Withdrawal enforcement** — `WithdrawalController::store()` requires `google2fa_enabled`
- **Rate limiting** — 5 attempts, 5-minute lockout on failed 2FA

### Decisions
- **2FA Library:** Keep Google2FA (already unified, no migration needed)

### Gaps Remaining
- Recovery code UI not fully implemented
- WebAuthn/FIDO2 not supported
- Backup token regeneration not documented

**Full audit:** `docs/audits/session-audit.md` (2FA section)

---

## 6. COMPLIANCE AUDIT

### Implemented
- **`PciPsd2Compliance`** — Email verification, high-value 2FA, daily limits, suspicious activity
- **`WalletPolicy`** — Withdrawal requires google2fa_enabled, owns-only deposit check

### Gaps Remaining
- PCI-DSS logging not comprehensive
- SCA (Strong Customer Auth) for EU not compliant (WebAuthn needed)
- Data retention policy not defined in code

---

## 7. NOTIFICATION AUDIT

### Implemented (16 notification classes)
| Notification | Trigger | Channel |
|-------------|---------|---------|
| `TradeExecutedNotification` | Trade opened/closed | database + mail |
| `SettlementCompletedNotification` | Settlement completes | database |
| `WithdrawalInitiated` | Withdrawal created | database + mail |
| `WithdrawalApprovedNotification` | Withdrawal approved | database + mail |
| `WithdrawalRejectedNotification` | Withdrawal rejected | database + mail |
| `WithdrawalOtpNotification` | OTP sent | mail |
| `NewDeviceLoginNotification` | New device login | database + mail |
| `BillingAlertNotification` | Billing events | database |
| `FeeChargedNotification` | Fee charged | database |
| `TrialEndingNotification` | Trial ending | database |
| `KycStatusNotification` | KYC status change | database + mail |
| `AccountSuspendedNotification` | Account suspended | database + mail |
| `InactivityWarningNotification` | Inactivity warning | database |
| `NewAdvisoryNotification` | New advisory | database |
| `AdminBroadcastNotification` | Admin broadcast | database |
| `VerifyEmailNotification` | Email verification | mail |

### Decisions
- **Notification Channels:** Mail channel added for critical notifications (withdrawals, KYC, new device login, account suspension, trade execution)

### Gaps Remaining
- SMS channel not configured (requires Twilio/Africa's Talking integration)
- No notification delivery tracking

**Full audit:** `docs/audits/notification-audit.md`

---

## 8. CODE REVIEW FIXES APPLIED (2026-06-15)

### Critical Fixes
| Fix | File | Impact |
|-----|------|--------|
| Wallet `refreshBalance()` now includes `locked` | `app/Models/Wallet.php` | Balance display was missing locked funds |
| Wallet `debit()`/`credit()` no longer double-count | `app/Models/Wallet.php` | Balance was being corrupted |
| Paystack webhook wrapped in DB transaction | `app/Http/Controllers/Api/PaystackController.php` | Prevented orphaned wallet credits |
| Hardcoded crypto prices removed | `app/Services/MarketService.php` | Prevented trading at stale prices |
| Profile GET no longer writes to DB | `app/Http/Controllers/Api/ProfileController.php` | Removed side effects from read endpoint |

### High-Severity Fixes
| Fix | File | Impact |
|-----|------|--------|
| Sell-side position verification | `app/Http/Controllers/Api/TradeController.php` | Prevented unauthorized short-selling |
| FX conversion credits to cleared | `app/Http/Controllers/Api/WalletController.php` | Funds no longer stuck in uncleared |
| Silent exception logging | `app/Http/Controllers/Api/AuthController.php` | Errors now logged instead of swallowed |
| Webhook rate limiting | `routes/api.php` | Prevented abuse of public webhook endpoints |
| Admin auth on getKycFull | `app/Http/Controllers/Api/ProfileController.php` | Prevented non-admin access to unmasked KYC |

### Medium-Severity Fixes
| Fix | File | Impact |
|-----|------|--------|
| Portfolio change calculation | `app/Http/Controllers/Api/PortfolioController.php` | Was hardcoded to 1.25% |
| SubscriptionService return type | `app/Services/SubscriptionService.php` | Fixed void/mixed type mismatch |
| searchSymbols fallback narrowed | `app/Http/Controllers/Api/TradeController.php` | Prevented loading entire symbols table |
| Duplicate message key removed | `app/Http/Controllers/Api/PaystackController.php` | User now sees correct demo message |

### New Fixes (2026-06-15 v4)
| Fix | File | Impact |
|-----|------|--------|
| Timing-safe OTP comparison | `app/Http/Controllers/Api/Security/WithdrawalController.php` | Prevented timing attacks on OTP verification |
| Daily limit check in WithdrawalProtectionService | `app/Services/WithdrawalProtectionService.php` | Protection service now checks daily limits |
| DB transaction on limit refund | `app/Services/WithdrawalService.php` | Prevented limit counter going negative |
| Deprecated withdrawal endpoint removed | `app/Http/Controllers/Api/WalletController.php` | Eliminated dual withdrawal flows |
| Settlement refreshBalance after settlement | `app/Services/SettlementService.php` | Prevented balance drift after settlement |
| Mail channel for critical notifications | Multiple notification files | Users now receive email alerts for withdrawals, KYC, new device login |
| Dojah as primary KYC provider | `app/Http/Controllers/Api/DojahKycController.php` | QoreID deprecated, Dojah is primary |

---



---

## PRIORITY ACTION ITEMS

1. ~~**Unify 2FA fields**~~ — **RESOLVED** — Google2FA is already unified
2. ~~**Resolve KYC status**~~ — **RESOLVED** — Both `approved` and `verified` are valid
3. ~~**Email/SMS channels**~~ — **PARTIALLY RESOLVED** — Mail channel added for critical notifications
4. ~~**WithdrawalProtectionService**~~ — **RESOLVED** — Daily limit checks integrated
5. ~~**Timing-safe OTP**~~ — **RESOLVED** — `hash_equals()` used for OTP comparison
6. **Session cleanup** — Add job to purge expired Sanctum tokens
7. **Dojah webhook** — Implement real-time KYC status updates
8. ~~**Remove legacy withdrawal flows**~~ — **RESOLVED** — Deprecated endpoint removed
9. **Remove duplicate KYC endpoints** — Consolidate `KycController::submit()` and `ProfileController::submitKyc()`
10. **Remove QoreID code** — Deprecate QoreID service files (Dojah is now primary)