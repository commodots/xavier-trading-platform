# Security & Compliance Gap Analysis

**Date:** 2026-06-03
**Platform:** Xavier Trading Platform  
**Scope:** Settlement, Withdrawal, KYC, Session, Notification, 2FA, Dojah Integration

---

## 1. Settlement Audit Summary

### What Exists
- `SettlementService` — T+2 settlement with DB transactions and pessimistic locking 
- `SettleUnsettledTrades` Job — processes `is_settled = false` trades 
- `ProcessSettlements` command (`settlements:process`) scheduled daily at 08:00 
- `ProcessSettlement` command (`settlement:process`) for Paystack deposit clearing — exists but **not scheduled** 
- `settlement_date`, `is_settled`, `settlement_status` all present in DB and Trade model 
- Wallet uses `ngn_cleared`/`ngn_uncleared`/`usd_cleared`/`usd_uncleared` — no `available_balance` 
- `TradeService` — **does not exist** 

### Gaps & Bugs
`SettleUnsettledTrades` Job uses hardcoded `ngn_uncleared` for all sell trades — USD sells deduct from wrong curency bucket 
`SettlementService` sets `settlement_status = 'settled'` but never sets `is_settled = true` — Job will reprocess already-settled trades 
`settlement:process` (Paystack clearing) not scheduled 

---

## 2. Withdrawal Audit Summary

### What Exists
- `WithdrawalController` — full CRUD with admin approve/reject 
- `WithdrawalService` — initiate, approve, reject, complete, fail, fraud detection 
- `WithdrawalProtectionService` — checks debt, suspension, cleared balance 
- Daily limits via `WithdrawalLimit` model (NGN 500k / USD 2,500) 
- Fraud pattern detection (3 withdrawals in 1h, 3× average amount) 
- Rate limiting via `RateLimitService` 
- Audit logging via `AuditService` 
- `WithdrawalOtpNotification` — OTP email exists 

### Gaps & Bugs
No KYC level check on withdrawal — any authenticated user can withdraw 
No 2FA enforcement on withdrawal route 
`deductFromWallet()` not wrapped in DB transaction 
Two withdrawal flows exist (`/withdraw` with OTP, `/security/withdrawals` without OTP) — inconsistent 
`WithdrawalProtectionService` does not check daily limits 



## 3. KYC Audit Summary

### What Exists
- `KycProfile` model with states: `pending`, `approved`/`verified`, `rejected` 
- `KycService` — PII masking, tier determination (0–3), QoreID webhook data extraction 
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
- `KycController::update()` references `ProcessKycVerification::dispatch()` but is missing the `use` import

### Gaps
`KycController::update()` calls `ProcessKycVerification::dispatch()` without importing the class — will throw fatal error at runtime 
 No `KycLevelMiddleware` exists — verification levels are not enforced on routes |
`kyc_verified` accessor on User checks `status = 'verified'` but `KycService` also accepts `status = 'approved'` — inconsistency in what counts as verified |
Dojah config exists and SDK is installed but only liveness is wired — BVN/NIN routes via Dojah are not implemented (QoreID is the active service) |

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
- Sanctum token expiration: controlled by `config/sanctum.php` `expiration` value
- Default in Laravel Sanctum is `null` (tokens never expire unless manually revoked)
- **No explicit expiration is set in the codebase** — tokens are permanent until logout

### New Device Detection
- `UserDevice::updateOrCreate()` by `device_name` + `ip_address` — tracks devices 
- **No alert/notification is sent when a new device logs in** 

### Gaps
 Sanctum token expiration is `null` — stolen tokens are valid forever 
 No new device login notification to user 
 `AuthController` logs out of the temporary session after issuing token but it means the session-based auth guard is not used at all 

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
New device login - No notification when login from a new device/IP |
Withdrawal approved/rejected - Admin action does not trigger a user-facing notification |

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
- `verify()` — **does not actually validate the TOTP code** — accepts any token as valid 
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
 `Security\TwoFactorController::verify()` accepts any token without cryptographic validation — 2FA can be bypassed |
Two 2FA systems (`google2fa_*` vs `two_factor_*`) operate in parallel — login flow uses `google2fa_enabled`, setup UI uses `two_factor_enabled` — they are not the same field |
 No 2FA enforcement on withdrawal (`WithdrawalController::store()` does not check either flag) |
 Recovery codes stored in user row as JSON — no `recovery_codes` table
 Login flow (`AuthController`) only checks `google2fa_enabled`, not `two_factor_enabled` — users who set up 2FA via the Security controller are not challenged at login |

---

## 7. Dojah / KYC Enforcement Layer Summary

### What Exists
- Dojah SDK installed (`vendor/konfig/dojah-php-sdk`) 
- `config/services.php` Dojah block with `public_key`, `secret_key`, `base_url`, `app_id` 
- `DojahController::verifyLiveness()` route exists 
- Frontend widget (Register.vue) loads Dojah widget for face capture 
- Active KYC backend is **QoreID** (BVN, NIN, face matching via complex-verification) 

### What Is Missing
- `KycLevelMiddleware` — not implemented
- `verification_level` column on users table — not present
- Trading / deposit / withdrawal blocked by KYC level — not implemented
- Dojah BVN/NIN API endpoints (`POST /api/kyc/bvn`, `POST /api/kyc/nin`) — not implemented
- `kyc_verifications` table — not present (QoreID stores results in `kyc_profiles` via `meta` JSON)

---

## Priority Action

 `Security\TwoFactorController::verify()` accepts any OTP | `app/Http/Controllers/Api/Security/TwoFactorController.php` |

 Unify 2FA fields (`google2fa_enabled` vs `two_factor_enabled`) | `AuthController`, `TwoFactorController`, User model |

`SettleUnsettledTrades` wrong currency on sell trades | `app/Jobs/SettleUnsettledTrades.php` |

 `SettlementService` does not set `is_settled = true` | `app/Services/SettlementService.php` |

 Add KYC check to `WithdrawalController::store()` | `app/Http/Controllers/Api/Security/WithdrawalController.php` |

Add 2FA check to `WithdrawalController::store()` | `app/Http/Controllers/Api/Security/WithdrawalController.php` |

Fix missing `use ProcessKycVerification` in `KycController` | `app/Http/Controllers/Api/KycController.php` |

Set Sanctum token expiration | `config/sanctum.php` |

 Add `TradeExecutedNotification` | `app/Notifications/` |

Add `SettlementCompletedNotification` | `app/Notifications/` |

Add new device login notification | `AuthController::login()` |

Implement `KycLevelMiddleware` | `app/Http/Middleware/` |

Schedule `settlement:process` (Paystack clearing) | `routes/console.php` |

Wrap `deductFromWallet()` in DB transaction | `WithdrawalService` |

 Unify withdrawal flows (remove duplicate `/withdraw` vs `/security/withdrawals`) | `routes/api.php` |

 Add withdrawal approved/rejected notifications | `WithdrawalService` |
