# Security & Compliance Gap Analysis

**Date:** 2026-06-10  
**Status:** IMPLEMENTATION COMPLETE  
**Version:** v2 (Post-Security-Fixes)

---

## 1. SETTLEMENT AUDIT

### Implemented
- **File:** `app/Services/SettlementService.php`
  - DB::transaction() wrapping
  - Pessimistic locking (lockForUpdate)
  - Trade marked: `is_settled=true`, `settlement_date=now()`, `settlement_status='settled'`
  - Portfolio quantities updated (cleared/uncleared)
  - SettlementCompletedNotification dispatch

- **File:** `app/Jobs/SettleUnsettledTrades.php`
  - Currency-aware column mapping (usd_uncleared vs ngn_uncleared)
  - Wallet atomic update

- **Scheduler:** `routes/console.php`
  - `settlements:process` → 08:00 daily
  - `settlement:process` → hourly

### Gaps Remaining
- Partial settlement scenarios not handled (e.g., partial asset liquidation)
- Settlement audit trail metadata limited (no admin context)
- Settlement notifications untested at scale

---

## 2. WITHDRAWAL AUDIT

### Implemented
- **File:** `app/Http/Controllers/Api/Security/WithdrawalController.php`
  - 2FA enforcement (google2fa_enabled check)
  - KYC level 2+ requirement
  - Fraud pattern detection
  - Rate limiting

- **File:** `app/Services/WithdrawalService.php`
  - Daily limit checks
  - OTP methods: sendWithdrawalOtp(), verifyWithdrawalOtp()
  - Wallet deduction in transaction
  - Ledger entries
  - Notifications: WithdrawalApprovedNotification, WithdrawalRejectedNotification

- **Routes:** `routes/api.php`
  - Centralized at `/security/withdrawals`
  - Requires `['kyc:2', '2fa']` middleware
  - Legacy `/withdraw` endpoints deprecated with 301 redirect

### Gaps Remaining
- OTP route (`POST /security/withdrawals/otp`) not explicitly in routes/api.php
- Frontend still references `/otp/send-withdrawal` (update pending)
- WalletController/NewTransactionController methods need full removal

---

## 3. KYC AUDIT

### Implemented
- **Model:** `app/Models/KycProfile.php`
  - States: pending, approved, rejected, verified
  - Tier levels (0–3)

- **File:** `app/Http/Controllers/Api/KycController.php`
  - Document submission
  - Auto-dispatch ProcessKycVerification job
  - File storage (biometrics, docs, address)

- **Dojah Integration:** `app/Services/DojahKycService.php`, `app/Http/Controllers/Api/DojahKycController.php`
  - BVN verification
  - NIN verification
  - Selfie verification

- **Middleware:** `app/Http/Middleware/KycLevelMiddleware.php`
  - Parameterized level checks
  - 403 response if insufficient tier

### Gaps Remaining
- No real-time webhook from Dojah (manual polling only)
- Recovery from rejected KYC not clearly documented
- KYC resubmission after rejection not validated

---

## 4. SESSION & DEVICE AUDIT

### Implemented
- **File:** `app/Http/Controllers/Api/AuthController.php`
  - Device tracking via UserDevice::firstOrCreate()
  - New device detection → NewDeviceLoginNotification
  - Device detection sequenced AFTER 2FA verification

- **File:** `app/Http/Controllers/Api/User/SecurityController.php`
  - getActiveSessions() lists Sanctum tokens
  - logoutOtherDevices() atomically deletes other tokens

- **Sanctum Integration:**
  - Token expiration per config
  - Bearer validation

### Gaps Remaining
- Device trust mechanism (is_trusted flag) not enforced
- Geo-blocking not implemented
- IP-based anomaly detection not implemented

---

## 5. TWO-FACTOR AUTHENTICATION AUDIT

### Unified to Google2FA Only
- **Removed:** all `two_factor_*` field references except recovery codes
- **Using:** `google2fa_enabled`, `google2fa_secret`, `two_factor_recovery_codes`

- **File:** `app/Http/Controllers/Api/TwoFactorController.php`
  - enable2FA() — QR setup
  - confirm2FA() — verify & enable
  - verify2FA() — post-login challenge with rate limiting
  - disable2FA() — requires user context
  - status() — returns current state

- **File:** `app/Services/TwoFactorService.php`
  - Recovery code generation (8 codes)
  - Recovery code consumption
  - QR generation
  - Enable/disable logic

- **Routes:** `routes/api.php`
  - Centralized at `/security/2fa/*`
  - All endpoints use TwoFactorController

- **Middleware:** `app/Http/Middleware/EnsureTwoFactorEnabled.php`
  - Checks `google2fa_enabled`

### Gaps Remaining
- Recovery code UI not fully implemented
- WebAuthn/FIDO2 not supported
- Backup token regeneration not documented

---

## 6. COMPLIANCE AUDIT

### Implemented
- **File:** `app/Services/Compliance/PciPsd2Compliance.php`
  - Email verification check
  - High-value transaction 2FA (>NGN 1M)
  - Daily limits
  - Suspicious activity detection

- **File:** `app/Policies/WalletPolicy.php`
  - Withdrawal requires google2fa_enabled
  - Owns-only deposit check

### Gaps Remaining
- PCI-DSS logging not comprehensive
- SCA (Strong Customer Auth) for EU not compliant (WebAuthn needed)
- Data retention policy not defined in code

---

## 7. NOTIFICATION AUDIT

### Implemented Notifications
- TradeExecutedNotification
- SettlementCompletedNotification
- WithdrawalApprovedNotification
- WithdrawalRejectedNotification
- WithdrawalInitiated
- NewDeviceLoginNotification

### Gaps Remaining
- Email/SMS channels not configured (database only)
- Billing event notifications missing
- Notification preferences not fully enforced

---

## 8. FRONTEND UPDATES

### Files Updated
- `resources/js/Pages/Wallet.vue` — withdraw endpoints
- `resources/js/Pages/Profile/Partials/TwoFactorSetup.vue` — 2FA endpoints
- `resources/js/Pages/Login.vue` — 2FA verify endpoint

### Files Requiring Review
- `resources/js/Pages/Kyc/VerifyIdentity.vue` — Dojah integration
- `resources/js/router/index.js` — route metadata

---

## SUMMARY

| Component | Status | Risk | Action |
|-----------|--------|------|--------|
| Settlement | 90% | Low | Monitor notifications at scale |
| Withdrawal | 85% | Medium | Define OTP route explicitly |
| KYC | 80% | Low | Implement Dojah webhook |
| Sessions | 75% | Low | Add device trust enforcement |
| 2FA | 95% | Low | UI refinement only |
| Compliance | 80% | Medium | EU SCA compliance review |

---

## CRITICAL NEXT STEPS

1. **Define OTP Route:** Add `POST /security/withdrawals/otp` in routes/api.php
2. **Remove Legacy Withdrawals:** Delete methods in WalletController/NewTransactionController
3. **Test Notifications:** Run settlement/withdrawal notifications under load
4. **Dojah Webhook:** Implement real-time KYC status updates
5. **Email/SMS:** Configure notification channels beyond database
- `KycController` — `update()`, `show()`, `submit()` endpoints 
- `KycStatusNotification` — mail + database, covers pending/approved/rejected states 
- `KycProfileObserver` — exists (content not audited) 
- Encrypted storage of `bvn`, `nin`, `tin` via Laravel `encrypted` cast 
- Tier system: 0 (unverified) → 1 (BVN+NIN) → 2 (+ passport) → 3 (+ proof of address) 


### Dojah Integration Status
- `DojahController` exists 
- `config/services.php` has full Dojah config block (`public_key`, `secret_key`, `base_url`, `app_id`) 
- `vendor/konfig/dojah-php-sdk` is installed 
- Route `POST /kyc/verify-liveness` mapped to `DojahController::verifyLiveness()` (behind Sanctum) 
- **Active KYC provider is QoreID**, not Dojah — `QoreidService` handles BVN/NIN/face verification
- `ProcessKycVerification` Job dispatches to QoreID
- `KycController::update()` references `ProcessKycVerification::dispatch()` and imports the job correctly.

### Gaps
- `KycLevelMiddleware` exists but is malformed and not applied to routes.
- `kyc_verified` accessor on User checks `status = 'verified'` but `KycService` also accepts `status = 'approved'` — inconsistency in what counts as verified.
- Dojah config exists and SDK is installed but only liveness is wired — BVN/NIN routes via Dojah are not implemented (QoreID is the active service).

---

## 4. Session Audit Summary

### What Exists
- Sanctum token-based auth 
- `AuthController::login()` — logs activity, tracks device via `UserDevice` 
- Login response checks `google2fa_enabled` and returns `requires_2fa: true` before issuing token 
- `SecurityController::getActiveSessions()` — lists all active Sanctum tokens 
- `SecurityController::logoutOtherDevices()` — revokes all tokens except current 
- `SecurityController::changePassword()` — revokes all other tokens on password change 
- `ActivityLog` entries created on login, logout, failed login 
- `UserDevice` model tracks device name and IP with `last_active_at` 
- `UserDeviceController` — register, trust, revoke device endpoints 

### Session Expiration
- Sanctum token expiration is configured in `config/sanctum.php` to 43,200 minutes (30 days).
- Tokens may still remain valid until revoked for the duration of that expiration window.

### New Device Detection
- `UserDevice::updateOrCreate()` by `device_name` + `ip_address` — tracks devices 
- `AuthController::login()` creates device records and calls `NewDeviceLoginNotification`, but the `NewDeviceLoginNotification` class is not present in `app/Notifications`.

### Gaps
- No new device login notification to user.
- `AuthController` logs out of the temporary session after issuing token, which affects how session-based auth is audited.

---

## 5. Notification Audit Summary

### What Exists

             | Trigger | Notification | Channels |

| Withdrawal initiated | `WithdrawalInitiated` | database |
| Withdrawal OTP | `WithdrawalOtpNotification` | mail |
| KYC status change | `KycStatusNotification` | database + mail |
| Account suspended | `AccountSuspendedNotification` | exists  |
| Billing alert / fee due | `BillingAlertNotification` | exists |
| Fee charged | `FeeChargedNotification` | exists  |
| Trial ending | `TrialEndingNotification` | exists  |
| Email verification | `VerifyEmailNotification` | mail  |
| Advisory post | `NewAdvisoryNotification` | exists  |
| Admin broadcast | `AdminBroadcastNotification` | exists  |
| Inactivity warning | `InactivityWarningNotification` | exists  |

### Gaps


Trade executed - No `TradeExecutedNotification` — users are not notified when a trade fills |
Settlement completed - No notification when trade settlement completes |
New device login - `AuthController::login()` references `NewDeviceLoginNotification`, but the notification class file is not present in `app/Notifications` |
Withdrawal approved/rejected - `WithdrawalService` references `WithdrawalApprovedNotification` and `WithdrawalRejectedNotification`, but the notification class files are not present in `app/Notifications` |

---

## 6. 2FA Audit Summary

### What Exists

**Two separate 2FA implementations exist in parallel:**

#### Implementation A — Google2FA (PragmaRX/google2fa) — `TwoFactorController` 
- `enable2FA()` — generates secret via `Google2FA::generateSecretKey()`, stores as `google2fa_secret` (encrypted) 
- `confirm2FA()` — verifies TOTP code via `Google2FA::verifyKey()` 
- `verify2FA()` — post-login 2FA step, issues Sanctum token on success 
- `disable2FA()` — clears `google2fa_enabled` and `google2fa_secret` 
- Rate limiting on failed attempts (5 tries, 5-minute lockout) 
- Migration `2025_12_12`: adds `google2fa_secret` + `google2fa_enabled` to users table 
- `AuthController::login()` checks `google2fa_enabled` and returns `requires_2fa: true` 

#### Implementation B — OTP-based (TwoFactorService) — `Security\TwoFactorController`
- `setup()` — generates base32 secret, stores as `two_factor_secret`, generates recovery codes 
- `verify()` — verifies TOTP code via `Google2FA::verifyKey()` 
- `disable()` — password-confirmed disable 
- `status()` — returns `two_factor_enabled` + `confirmed_at` 
- Migration `2026_06_02`: adds `two_factor_enabled`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` 
- `TwoFactorToken` model for OTP tokens (6-digit, typed: login/withdrawal/settings) 

### Recovery Codes
- `TwoFactorService::generateRecoveryCodes()` — generates 8 hex codes 
- Stored as JSON in `two_factor_recovery_codes` column 
- `verifyRecoveryCode()` — consumes code and removes it from array 
- **No separate `recovery_codes` table** — stored directly on user row (per-spec a separate table was requested but not implemented)

### Critical Gaps
- `Security\TwoFactorController` still exists alongside the Google2FA path, so the system retains two parallel 2FA implementations and a nonstandard architectural surface.
- Two 2FA systems (`google2fa_*` vs `two_factor_*`) operate in parallel — login flow uses `google2fa_enabled`, setup UI uses `two_factor_enabled` — they are not the same field |
- `WithdrawalController::store()` checks either `google2fa_enabled` or `two_factor_enabled`, but the overall 2FA architecture is fragmented and inconsistent.
- Recovery codes stored in user row as JSON — no `recovery_codes` table
- Login flow (`AuthController`) only checks `google2fa_enabled`, not `two_factor_enabled` — users who set up 2FA via the Security controller are not challenged at login |

---

## 7. Dojah / KYC Enforcement Layer Summary

### What Exists
- Dojah SDK installed (`vendor/konfig/dojah-php-sdk`) 
- `config/services.php` Dojah block with `public_key`, `secret_key`, `base_url`, `app_id` 
- `DojahController::verifyLiveness()` route exists 
- Frontend widget (Register.vue) loads Dojah widget for face capture 
- Active KYC backend is **QoreID** (BVN, NIN, face matching via complex-verification) 

### What Is Missing
- `KycLevelMiddleware` exists and is registered, but the enforcement layer still needs a full review for consistency across all KYC-protected routes.
- `verification_level` column on users table — not present
- Trading / deposit / withdrawal blocked by KYC level — not implemented
- Dojah BVN/NIN API endpoints (`POST /api/kyc/bvn`, `POST /api/kyc/nin`) — not implemented
- `kyc_verifications` table — not present (QoreID stores results in `kyc_profiles` via `meta` JSON)

---

## Priority Action

 `Security\TwoFactorController` remains parallel to the Google2FA path, so the platform has duplicate 2FA architecture | `app/Http/Controllers/Api/Security/TwoFactorController.php`, `app/Http/Controllers/Api/TwoFactorController.php` |

 Unify 2FA fields (`google2fa_enabled` vs `two_factor_enabled`) | `AuthController`, `TwoFactorController`, User model |

 Review withdrawal route compliance consistency across `WalletController`, `NewTransactionController`, and `Security\WithdrawalController` | `routes/api.php`, `app/Http/Controllers/Api/WalletController.php`, `app/Http/Controllers/Api/NewTransactionController.php`, `app/Http/Controllers/Api/Security/WithdrawalController.php` |

 Fix malformed `KycLevelMiddleware` and apply it to routes | `app/Http/Middleware/KycLevelMiddleware.php` |

 Add `TradeExecutedNotification` | `app/Notifications/` |

 Add `SettlementCompletedNotification` | `app/Notifications/` |

 Add new device login notification | `AuthController::login()` |

 Review hourly `settlement:process` scheduling and Paystack clearing behavior | `routes/console.php` |

 Unify withdrawal flows (remove duplicate `/withdraw` vs `/security/withdrawals`) | `routes/api.php` |

 Add withdrawal approved/rejected notifications | `WithdrawalService` |

 Resolve KYC status/verification consistency (`approved` vs `verified`) | `app/Models/User.php`, `app/Services/KycService.php` |
