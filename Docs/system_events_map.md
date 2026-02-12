# System Events Map

## Invariant: Ledger + Event Mirror Consistency

**For every wallet balance mutation there is exactly one financial system_event** (one POSTED `wallet_transactions` row, one wallet balance change, one `system_events` row with `is_financial = true` and matching `event_type`). No balance change without event; no financial event without balance change.

**Source of truth:** `wallet_transactions` and `wallets.balance`. `system_events` is observability only. No logic must derive balance or financial state from `system_events`.

---

## Financial Events (is_financial = true)

**Recorded inside the same DB transaction as the balance change. Broadcast via `DB::afterCommit()`.**

| event_type                  | Entity       | Actor    | Balance change                    | Hook |
|----------------------------|-------------|----------|-----------------------------------|------|
| `wallet.purchase.debited`  | Order       | User     | Wallet decrement (customer)       | PayOrderWithWallet |
| `wallet.refund.credited`   | WalletTransaction | Admin | Wallet increment (customer)       | ApproveRefundRequest |
| `wallet.topup.posted`      | TopupRequest| Admin    | Wallet increment (customer)       | ApproveTopupRequest |
| `platform.profit.recorded` | Settlement  | null     | Platform wallet increment         | ProfitSettleCommand |

**Not financial (workflow only):** `profit.settlement.executed` → `is_financial = false` (recorded async after commit). Settlement execution is one balance change; only `platform.profit.recorded` mirrors it.

---

## Informational Events (is_financial = false)

**Dispatched via `PersistSystemEventJob` after commit. Idempotency: structured key `async:{event_type}:{entity_type}:{entity_id}[:suffix]` (no hash of meta).**

| event_type             | Entity           | Actor | Idempotency suffix | Hook |
|------------------------|------------------|-------|--------------------|------|
| `order.created`         | Order            | User  | —                  | CreateOrderFromCartPayload (after commit) |
| `refund.requested`     | WalletTransaction| User  | —                  | RefundOrderItem (after commit) |
| `refund.approved`      | WalletTransaction| Admin | —                  | ApproveRefundRequest (after commit) |
| `fulfillment.created`  | Fulfillment      | User  | —                  | CreateFulfillmentsForOrder (after commit) |
| `admin.rejected.refund`| WalletTransaction| Admin | —                  | RejectRefundRequest (after commit) |
| `admin.rejected.topup` | TopupRequest     | Admin | —                  | RejectTopupRequest (after commit) |
| `tier.upgraded`        | User             | null  | new_tier           | EvaluateLoyaltyForUserAction (after commit) |
| `profit.settlement.executed` | Settlement | null | —                  | ProfitSettleCommand (after commit) |

---

## Broadcast

- **Financial:** Insert in transaction → `DB::afterCommit(() => event(new SystemEventCreated($event->id)))`.
- **Async:** Job inserts → `event(new SystemEventCreated($event->id))` (no transaction in worker).

Admin UI: on `system-event-created`, **prepend** new event to list and trim to 50 (no full list refresh).

---

## Severity

- Default: `info`.
- Financial events: `info`.
- Reconciliation drift: `warning` (if recorded).
- System failure: `critical`.
