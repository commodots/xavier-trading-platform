# Notification Audit

**Date:** 2026-06-15 (Updated)
**Scope:** All notification classes in `app/Notifications/`
**Version:** v2 (Post-Fix Updates)

---

## What Exists

### Notification Classes

| Notification | File | Channels | Trigger |
|-------------|------|----------|---------|
| `TradeExecutedNotification` |  `app/Notifications/TradeExecutedNotification.php` | database + mail | Trade opened/closed |
| `SettlementCompletedNotification` |  `app/Notifications/SettlementCompletedNotification.php` | database | Trade settlement completes |
| `WithdrawalInitiated` |  `app/Notifications/WithdrawalInitiated.php` | database + mail | Withdrawal request created |
| `WithdrawalApprovedNotification` |  `app/Notifications/WithdrawalApprovedNotification.php` | database + mail | Withdrawal approved |
| `WithdrawalRejectedNotification` |  `app/Notifications/WithdrawalRejectedNotification.php` | database + mail | Withdrawal rejected |
| `WithdrawalOtpNotification` |  `app/Notifications/WithdrawalOtpNotification.php` | mail | Withdrawal OTP sent |
| `NewDeviceLoginNotification` |  `app/Notifications/NewDeviceLoginNotification.php` | database + mail | New device login detected |
| `BillingAlertNotification` |  `app/Notifications/BillingAlertNotification.php` | database | Billing events (charged, debt) |
| `FeeChargedNotification` |  `app/Notifications/FeeChargedNotification.php` | database | Fee charged |
| `TrialEndingNotification` |  `app/Notifications/TrialEndingNotification.php` | database | Trial ending |
| `KycStatusNotification` |  `app/Notifications/KycStatusNotification.php` | database + mail | KYC status change |
| `AccountSuspendedNotification` |  `app/Notifications/AccountSuspendedNotification.php` | database + mail | Account suspended |
| `InactivityWarningNotification` |  `app/Notifications/InactivityWarningNotification.php` | database | Inactivity warning |
| `NewAdvisoryNotification` |  `app/Notifications/NewAdvisoryNotification.php` | database | New advisory post |
| `AdminBroadcastNotification` |  `app/Notifications/AdminBroadcastNotification.php` | database | Admin broadcast |
| `VerifyEmailNotification` |  `app/Notifications/VerifyEmailNotification.php` | mail | Email verification |

---

## Trigger Verification

### Trade Executed
- **Trigger:** `TradeController::open()` and `TradeController::close()`
- **Notification:** `TradeExecutedNotification`
- **Status:**  Implemented — dispatched on trade open and close

### Settlement Completed
- **Trigger:** `SettlementService::processTradeSettlement()`
- **Notification:** `SettlementCompletedNotification`
- **Status:**  Implemented — dispatched after trade settlement

### Withdrawal Requested
- **Trigger:** `WithdrawalService::initiateWithdrawal()`
- **Notification:** `WithdrawalInitiated`
- **Status:**  Implemented

### Withdrawal Approved/Rejected
- **Trigger:** `WithdrawalService::approveWithdrawal()` / `rejectWithdrawal()`
- **Notification:** `WithdrawalApprovedNotification` / `WithdrawalRejectedNotification`
- **Status:**  Implemented

### Billing Events
- **Trigger:** `SubscriptionService::chargePlatformFee()`
- **Notification:** `BillingAlertNotification`
- **Status:**  Implemented — charged and debt notifications

### New Device Login
- **Trigger:** `AuthController::login()` and `TwoFactorController::verifyLogin()`
- **Notification:** `NewDeviceLoginNotification`
- **Status:**  Implemented

---

## Channel Configuration

| Channel | Status | Notes |
|---------|--------|-------|
| Database |  Active | All notifications use database channel |
| Mail |  **Active** | Critical notifications now use mail: withdrawals, KYC, new device login, account suspension, trade execution |
| SMS | Not configured | No SMS channel configured (requires Twilio/Africa's Talking integration) |
| Push | Not configured | No push notification channel |

### Mail Channel Coverage
Notifications with mail channel respect `$notifiable->notificationPreferences?->email` preference:
-  `TradeExecutedNotification` — database + mail
-  `WithdrawalInitiated` — database + mail
-  `WithdrawalApprovedNotification` — database + mail
-  `WithdrawalRejectedNotification` — database + mail
-  `WithdrawalOtpNotification` — mail only
-  `NewDeviceLoginNotification` — database + mail
-  `KycStatusNotification` — database + mail
-  `AccountSuspendedNotification` — database + mail
-  `VerifyEmailNotification` — mail only

---

## Bugs Found

| Issue | Severity | Status | Details |
|-------|----------|--------|---------|
| Email/SMS channels not configured | Medium | **PARTIALLY FIXED** | Mail channel added for critical notifications. SMS still not configured. |
| Notification preferences not enforced | Low | **FIXED** | All mail-enabled notifications check `$notifiable->notificationPreferences?->email` |
| No notification delivery tracking | Low | **OPEN** | No mechanism to track if notifications were delivered or read |

---

## Remaining Items

1. ~~**Email/SMS channels**~~ — **PARTIALLY RESOLVED** — Mail channel added for critical notifications
2. ~~**Notification preferences enforcement**~~ — **FIXED** — Preferences now checked before sending
3. **Notification delivery tracking** — no mechanism to track delivery status
4. **SMS channel** — requires Twilio/Africa's Talking integration