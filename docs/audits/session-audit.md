# Session Audit

**Date:** 2026-06-15
**Scope:** AuthController, Sanctum, Auth middleware, TwoFactorController, UserDevice

---

## What Exists

### AuthController (`app/Http/Controllers/Api/AuthController.php`)

| Method | Route | Description |
|--------|-------|-------------|
| `register` | POST `/register` | Delegates to OnboardingController |
| `login` | POST `/login` | Authenticates user, checks 2FA, issues Sanctum token |
| `profile` | GET `/user` | Returns authenticated user |
| `logout` | POST `/logout` | Deletes current access token |

### Login Flow

1. **Validate** email + password
2. **Authenticate** `Auth::attempt($credentials)`
3. **Log** failed attempt to `ActivityLog` (with error logging)
4. **Update** `last_active_at` on success
5. **Check** `subscription_status` — reactivate if inactive
6. **Log** successful login to `ActivityLog`
7. **Check 2FA** — if `google2fa_enabled`, return `requires_2fa: true` without issuing token
8. **Issue token** — `createToken('auth_token|...')` with Sanctum
9. **Track device** — `UserDevice::firstOrCreate()` by `device_name` + `ip_address`
10. **Notify** — `NewDeviceLoginNotification` if device is new
11. **Logout** — `Auth::logout()` (clears session, token remains valid)

### Sanctum Integration

- Token-based authentication via `auth:sanctum` middleware
- Token expiration configured in `config/sanctum.php` (43,200 minutes = 30 days)
- Bearer token validation
- Token format: `auth_token|{userAgent}|{ip}`

### TwoFactorController (`app/Http/Controllers/Api/Security/TwoFactorController.php`)

| Method | Route | Description |
|--------|-------|-------------|
| `setup` | POST `/security/2fa/setup` | Generate 2FA secret + QR code |
| `verify` | POST `/security/2fa/verify` | Verify TOTP code during setup |
| `disable` | POST `/security/2fa/disable` | Disable 2FA (requires password) |
| `status` | GET `/security/2fa/status` | Get 2FA status |
| `verifyLogin` | POST `/login/verify-2fa` | Verify 2FA during login flow |

### UserDevice Model (`app/Models/UserDevice.php`)

- Tracks `user_id`, `device_name`, `ip_address`, `last_active_at`
- `firstOrCreate()` by `device_name` + `ip_address`
- New device detection → `NewDeviceLoginNotification`

### UserDeviceController (`app/Http/Controllers/Api/Security/UserDeviceController.php`)

| Method | Route | Description |
|--------|-------|-------------|
| `index` | GET `/security/devices` | List user's devices |
| `register` | POST `/security/devices/register` | Register a device |
| `trust` | POST `/security/devices/{device}/trust` | Trust a device |
| `revoke` | DELETE `/security/devices/{device}` | Revoke a device |

### ActivityLog

- Created on: login, logout, failed login
- Fields: `user_id`, `activity`, `details`, `ip_address`, `user_agent`
- Error logging added to catch blocks (previously silent)

---

## Active Sessions

- Sanctum tokens represent active sessions
- `SecurityController::getActiveSessions()` lists all active Sanctum tokens
- `SecurityController::logoutOtherDevices()` atomically deletes other tokens
- `SecurityController::changePassword()` revokes all other tokens on password change

---

## Session Expiration

- Sanctum token expiration: **43,200 minutes (30 days)** per `config/sanctum.php`
- Tokens remain valid until revoked for the duration of that expiration window
- No automatic session cleanup mechanism

---

## New Device Detection

- `UserDevice::firstOrCreate()` by `device_name` + `ip_address`
- `AuthController::login()` creates device records and calls `NewDeviceLoginNotification`
- `TwoFactorController::verifyLogin()` also tracks devices after 2FA verification
- `NewDeviceLoginNotification` exists in `app/Notifications/`

---

## 2FA Enforcement

| Check | Location | Status |
|-------|----------|--------|
| Login 2FA challenge | `AuthController::login()` | Checks `google2fa_enabled`, returns `requires_2fa: true` |
| 2FA verification | `TwoFactorController::verifyLogin()` | Verifies TOTP, issues token on success |
| Withdrawal 2FA | `WithdrawalController::store()` | Requires `google2fa_enabled` |
| Setup/Disable | `TwoFactorController` | Setup, verify, disable, status endpoints |

---

## Bugs Found

| Issue | Severity | Details |
|-------|----------|---------|
| `Auth::logout()` after token issue | Low | `AuthController::login()` calls `Auth::logout()` after issuing token — clears session but token remains valid. This is intentional for API-only auth but may confuse session-based auditing. |
| No session cleanup | Low | No mechanism to clean up expired Sanctum tokens automatically |
| Device trust not enforced | Low | `UserDeviceController::trust()` exists but is not enforced in any middleware |
| Geo-blocking not implemented | Low | No IP-based anomaly detection or geo-blocking |

---

## Missing Items

1. **Automatic session cleanup** — no job to purge expired Sanctum tokens
2. **Device trust enforcement** — `is_trusted` flag exists but is not enforced
3. **Geo-blocking** — no IP-based anomaly detection
4. **IP-based anomaly detection** — not implemented