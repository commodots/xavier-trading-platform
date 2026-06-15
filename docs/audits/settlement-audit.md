# Settlement Audit

**Date:** 2026-06-11 (Updated)
**Scope:** SettlementService, SettleUnsettledTrades (Job), ProcessSettlement / ProcessSettlements (Commands), Trade model, Wallet model
**Version:** v2 (Post-Fix Updates)

---

## Check A — wallet->unsettled_balance vs wallet->available_balance

**Result: Uses uncleared/cleared columns, not available_balance**

When a trade closes, the system uses:
- `ngn_uncleared` / `usd_uncleared` (holds funds pending settlement)
- `ngn_cleared` / `usd_cleared` (settled, spendable funds)

There is **no** `available_balance` or `unsettled_balance` field anywhere. The wallet model uses a dual-column cleared/uncleared pattern.

**In `SettlementService::processTradeSettlement()`:**
- Buy settle: decrements `ngn_uncleared`/`usd_uncleared`, increments portfolio `cleared_quantity`
- Sell settle: decrements `ngn_uncleared`/`usd_uncleared`, increments `ngn_cleared`/`usd_cleared`

**In `SettleUnsettledTrades` Job:**
- Also uses `usd_uncleared → usd_cleared` and `ngn_uncleared → ngn_cleared`

**Resolved:** `SettleUnsettledTrades` Job now chooses the correct wallet bucket based on trade currency, so USD sell settlements no longer decrement `ngn_uncleared` incorrectly.

---

## Check B — settlement_date and is_settled in database

**Result: BOTH EXIST**

Migration `2026_06_02_create_settlement_and_withdrawal_tables.php` confirms:
- `settlement_date` — `timestamp`, nullable
- `is_settled` — `boolean`, default `false`

Trade model `$fillable` includes both fields. `$casts` correctly casts:
- `settlement_date` → `datetime`
- `is_settled` → `boolean`

The `Trade` model also has a `scopeUnsettled` query scope filtering `is_settled = false`.

**Resolved:** `SettlementService` now updates `settlement_status`, `is_settled`, and `settlement_date` when a trade is settled. This closes the earlier reprocessing vulnerability where service-settled trades could be picked up again by the `SettleUnsettledTrades` job.

---

## Check C — Scheduler for settlement:process

**Result: SCHEDULER EXISTS — but with a naming discrepancy**

In `routes/console.php`:
```php
Schedule::command('settlements:process')->dailyAt('08:00');
```

**Two commands exist with near-identical names:**

| Command | Class | Purpose |
|---------|-------|---------|
| `settlement:process` | `ProcessSettlement` | Fetches Paystack settlement data, clears ledger/wallet |
| `settlements:process` | `ProcessSettlements` | Processes T+2 trade settlements via `SettlementService` |

The scheduler runs `settlements:process` (plural) daily at 08:00, and `settlement:process` (singular) is also scheduled hourly for Paystack settlement clearing.

---

## What Exists

| Component | Status |
|-----------|--------|
| `SettlementService` |  Exists — full T+2 logic with DB transactions and pessimistic locking |
| `SettleUnsettledTrades` Job |  Exists — processes `is_settled = false` trades |
| `ProcessSettlement` Command (`settlement:process`) |  Exists — Paystack deposit clearing |
| `ProcessSettlements` Command (`settlements:process`) |  Exists — trade settlement via service |
| Scheduler entry |  `settlements:process` runs daily at 08:00, `settlement:process` runs hourly |
| `settlement_date` column |  In migrations and model |
| `is_settled` column |  In migrations and model |
| `settlement_status` column |  In model `$fillable` |
| `SettlementCompletedNotification` |  Exists — dispatched after trade settlement |
| `refreshBalance()` after settlement |  **FIXED** — Now called to prevent balance drift |
| `TradeService` |  Does not exist — no `app/Services/TradeService.php` |

---

## Settlement Flow (Current)

1. **Trade closes** → `TradeController::close()` sets `settlement_status: 'pending'`, `settlement_date: now()+2 days`
2. **Daily cron** → `settlements:process` runs `SettlementService::processDailySettlements()`
3. **Settlement** → For each pending trade older than 24h:
   - Wallet locked/uncleared balance → cleared balance
   - Portfolio uncleared_quantity → cleared_quantity
   - **`refreshBalance()` called** to recompute total balance from sub-columns
   - Trade marked as `is_settled: true`, `settlement_status: 'settled'`
   - `SettlementCompletedNotification` dispatched

---

## Bugs Found

| Issue | Severity | Status | Details |
|-------|----------|--------|---------|
| Naming conflict | Low | **OPEN** | `settlement:process` vs `settlements:process` — easy to confuse in ops/cron configuration |
| No partial settlement | Medium | **OPEN** | Partial asset liquidation scenarios not handled (e.g., partial fill on sell) |
| Settlement audit trail | Low | **OPEN** | Limited metadata — no admin context recorded when admin-initiated settlements occur |
| `wallet->locked` not refreshed after settlement | Low | **FIXED** | `SettlementService` now calls `refreshBalance()` after wallet modifications |

---

## Missing Items

1. **TradeService** — referenced in audit scope, does not exist. Trade logic lives in `OmsController`, `TradeController`, and `SettlementService` directly.
2. **Partial settlement support** — no mechanism to handle partial fills during settlement.