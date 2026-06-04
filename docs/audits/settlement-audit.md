# Settlement Audit

**Date:** 2026-06-03 
**Scope:** SettlementService, SettleUnsettledTrades (Job), ProcessSettlement / ProcessSettlements (Commands), Trade model, Wallet model

---

## Check A — wallet->unsettled_balance vs wallet->available_balance

**Result: uses uncleared/cleared columns, not available_balance**

When a trade closes, the system uses:
- `ngn_uncleared` / `usd_uncleared` (holds funds pending settlement)
- `ngn_cleared` / `usd_cleared` (settled, spendable funds)

There is **no** `available_balance` or `unsettled_balance` field anywhere. The wallet model uses a dual-column cleared/uncleared pattern.

... in `SettlementService::processTradeSettlement()`:
- Buy settle: decrements `ngn_uncleared`/`usd_uncleared`, increments portfolio `cleared_quantity`
- Sell settle: decrements `ngn_uncleared`/`usd_uncleared`, increments `ngn_cleared`/`usd_cleared`

... in `SettleUnsettledTrades` Job:
- Also uses `usd_uncleared → usd_cleared` and `ngn_uncleared → ngn_cleared`

**Bug Found:** `SettleUnsettledTrades` Job has a **logic error** on sell trades — it decrements `ngn_uncleared` instead of `usd_uncleared` for USD sells. The currency selection is hardcoded incorrectly:
```php
// BUG in SettleUnsettledTrades::handle()
if ($trade->type === 'buy') {
    // decrements usd_uncleared 
} else {
    // ALWAYS decrements ngn_uncleared — wrong for USD sell trades 
}
```

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

**Discrepancy Found:** `SettlementService` marks trades as settled using `settlement_status = 'settled'` but does **not** set `is_settled = true`. The `SettleUnsettledTrades` Job sets `is_settled = true`. These two settlement paths are **inconsistent** — one trade settled via `SettlementService` will still show `is_settled = false` in the database.

---

## Check C — Scheduler for settlement:process

**Result: SCHEDULER EXISTS — but with a naming discrepancy**

In `routes/console.php`:
```php
Schedule::command('settlements:process')->dailyAt('08:00');
```

**Two commands exist with near-identical names:**


`settlement:process` | `ProcessSettlement` | Fetches Paystack settlement data, clears ledger/wallet |
`settlements:process` | `ProcessSettlements` | Processes T+2 trade settlements via `SettlementService` |

The scheduler runs `settlements:process` (plural). `settlement:process` (singular) is **not scheduled** — it handles Paystack deposit clearing and would need to be added to the scheduler if Paystack auto-clearing is required.

---

## What Exists


| `SettlementService` | Exists — full T+2 logic with DB transactions and pessimistic locking |
| `SettleUnsettledTrades` Job | Exists — processes `is_settled = false` trades |
| `ProcessSettlement` Command (`settlement:process`) | Exists — Paystack deposit clearing |
| `ProcessSettlements` Command (`settlements:process`) | Exists — trade settlement via service |
| Scheduler entry | `settlements:process` runs daily at 08:00 |
| `settlement_date` column | In migrations and model |
| `is_settled` column | In migrations and model |
| `settlement_status` column | In model `$fillable` |
| TradeService | Does not exist — no `app/Services/TradeService.php` |

---

## Missing Items

1. **TradeService** — referenced in audit scope, does not exist. Trade logic lives in `OmsController`, `TradeController`, and `SettlementService` directly.
2. `settlement:process` (Paystack clearing) is **not scheduled**.

---

## Bugs Found
 `SettleUnsettledTrades::handle()` | Sell trades always decrement `ngn_uncleared` regardless of currency — USD sell trades will incorrectly deduct from NGN uncleared balance |

`SettlementService::processTradeSettlement()` | Does not set `is_settled = true` on the trade record — only sets `settlement_status = 'settled'`. The `SettleUnsettledTrades` job uses `is_settled` as its filter, so trades settled via service will be reprocessed by the job |
Naming conflict | `settlement:process` vs `settlements:process` — easy to confuse in ops/cron configuration |
