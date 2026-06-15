# KYC Audit

**Date:** 2026-06-15 (Updated)
**Scope:** KycController, KycProfile, DojahKycController, KycLevelMiddleware, KycService
**Version:** v2 (Post-Fix Updates)

---

## What Exists

### KycProfile Model (`app/Models/KycProfile.php`)

| Field | Type | Notes |
|-------|------|-------|
| `user_id` | foreignId | Links to users table |
| `status` | string | `pending`, `approved`, `rejected`, `verified` |
| `bvn` | string (encrypted) | Bank Verification Number |
| `nin` | string (encrypted) | National Identification Number |
| `tin` | string (encrypted) | Tax Identification Number |
| `id_type` | string | `intl_passport`, `national_id`, `drivers_license`, `voters_card`, `nin_slip`, `proof_of_address` |
| `photo` | string | Path to uploaded photo |
| `document` | string | Path to uploaded document |
| `tier` | integer | 0–3 (determined by KycService) |
| `level` | string | `none`, `basic`, `identity`, `biometric` |
| `verified_at` | timestamp | When verification completed |
| `daily_limit` | float | Set based on tier from KycSetting |

### KycController (`app/Http/Controllers/Api/KycController.php`)

| Method | Route | Description |
|--------|-------|-------------|
| `show` | GET `/user/kyc/show` | Get KYC data (masked) |
| `submit` | POST `/user/kyc/submit` | Submit KYC documents (delegates to `update()`) |
| `update` | — | Document upload handler (called by `submit()`) |

### ProfileController KYC Methods (`app/Http/Controllers/Api/ProfileController.php`)

| Method | Route | Description |
|--------|-------|-------------|
| `submitKyc` | POST `/profile/kyc` | Submit KYC verification (BVN/NIN/TIN + documents) |
| `getKyc` | GET `/profile/kyc` | Get KYC data (masked) |
| `getKycFull` | GET (admin only) | Get full KYC data (unmasked) — admin role required |

### DojahKycController (`app/Http/Controllers/Api/DojahKycController.php`) — **PRIMARY PROVIDER**

| Method | Route | Description |
|--------|-------|-------------|
| `verifyBvn` | POST `/kyc/bvn` | Verify BVN via Dojah |
| `verifyNin` | POST `/kyc/nin` | Verify NIN via Dojah |
| `verifySelfie` | POST `/kyc/selfie` | Verify selfie via Dojah |
| `verifyLiveness` | POST `/kyc/verify-liveness` | Liveness check via Dojah |
| `status` | GET `/kyc/status` | Get KYC verification status |

### KycLevelMiddleware (`app/Http/Middleware/KycLevelMiddleware.php`)

- Parameterized middleware: `kyc:0`, `kyc:1`, `kyc:2`, `kyc:3`
- Checks `$user->kyc->tier` against required level
- Returns 403 if insufficient tier
- **Applied to routes** in `routes/api.php`:
  - `kyc:0` — Market reads (open to unverified)
  - `kyc:1` — Deposits, trading
  - `kyc:2` — Withdrawals, crypto withdrawals

### KycService (`app/Services/KycService.php`)

- `determineTier()` — determines tier based on KYC profile completeness
- `maskPii()` — masks sensitive data for display
- `VERIFIED_STATUSES = ['verified', 'approved']` — canonical verified statuses

---

## KYC States

| State | Description |
|-------|-------------|
| `pending` | Documents submitted, awaiting verification |
| `approved` | Verification passed |
| `rejected` | Verification failed |
| `verified` | Alternative status (used by some code paths) |

**Decision:** Both `approved` and `verified` are valid states. `KycService::VERIFIED_STATUSES` handles both consistently.

---

## Tier Levels

| Level | Requirement | Access |
|-------|-------------|--------|
| 0 | Registered (no KYC) | Market reads only |
| 1 | Email verified | Deposits, trading |
| 2 | BVN + NIN | Withdrawals, crypto |
| 3 | Face verified | Full access |

---

## Dojah Integration Status (PRIMARY)

| Component | Status |
|-----------|--------|
| Dojah SDK installed |  `vendor/konfig/dojah-php-sdk` |
| Config block |  `config/services.php` has `public_key`, `secret_key`, `base_url`, `app_id` |
| Liveness check |  `POST /kyc/verify-liveness` → `DojahKycController::verifyLiveness()` |
| BVN verification |  `POST /kyc/bvn` → `DojahKycController::verifyBvn()` |
| NIN verification |  `POST /kyc/nin` → `DojahKycController::verifyNin()` |
| Selfie verification |  `POST /kyc/selfie` → `DojahKycController::verifySelfie()` |
| ProcessKycVerification job |  Uses `DojahService` for verification |
| Real-time webhook |  No webhook from Dojah — manual polling only |
| `kyc_verifications` table |  Exists — stores per-type verification results |

---

## QoreID Status (DEPRECATED)

| Component | Status |
|-----------|--------|
| `QoreidService` |  Deprecated — retained for backward compatibility |
| `BvnService` |  Deprecated — uses QoreID API |
| `IdentityVerificationService` |  Deprecated — uses QoreID API |
| `QoreidWebhookController` |  Deprecated — retained for backward compatibility |
| `ProcessQoreidWebhook` job |  Deprecated — retained for backward compatibility |

---

## Route Enforcement (from `routes/api.php`)

```
Route::middleware('kyc:0')  → Market reads, advisories, search
Route::middleware('kyc:1')  → Deposits, trading (POST /orders, /trade/open, /trade/close, /deposit)
Route::middleware('kyc:2')  → Withdrawals (/security/withdrawals), crypto withdrawals
```

---

## Duplicate KYC Endpoints (Known Issue)

Two endpoints exist for KYC submission:

| Endpoint | Controller | Purpose |
|----------|------------|---------|
| `POST /user/kyc/submit` | `KycController::submit()` | Document uploads (passport, license, etc.) |
| `POST /profile/kyc` | `ProfileController::submitKyc()` | Identity verification (BVN/NIN/TIN + documents) |

**Recommendation:** Consolidate into a single endpoint. `ProfileController::submitKyc()` is more comprehensive and should be the canonical endpoint. Deprecate `KycController::submit()` with a redirect.

**Risk:** Low — both endpoints dispatch `ProcessKycVerification` job. Consolidation requires frontend coordination.

---

## Bugs Found

| Issue | Severity | Status | Details |
|-------|----------|--------|---------|
| Status inconsistency | Medium | **RESOLVED** | Both `approved` and `verified` valid via `VERIFIED_STATUSES` |
| No Dojah webhook | Low | **OPEN** | Manual polling only — no real-time KYC status updates |
| KYC resubmission | Low | **OPEN** | No validation for resubmission after rejection |
| Duplicate KYC endpoints | Low | **OPEN** | Both `KycController::submit()` and `ProfileController::submitKyc()` exist |
|  **FIXED** | Dojah is now primary, QoreID deprecated |

---

## Missing Items

1. ~~**`kyc_verifications` table**~~ — **EXISTS** — stores per-type verification results
2. **Dojah webhook handler** — no real-time status updates from Dojah
3. **KYC resubmission limit** — no rate limiting on KYC submissions after rejection
4. ~~**Unified KYC status**~~ — **RESOLVED** — Both `approved` and `verified` are valid