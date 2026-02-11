# Notifications System

## Overview

Notifications are account-centric, stored via Laravel Notifications (database + broadcast). They are triggered only after DB commit. Spatie Activity Log remains audit-only and unchanged.

## Architecture

- **Recipients:** Admins = users with role `admin` only. User notifications go to the relevant user (order owner, topup owner, etc.).
- **Channels:** Every notification uses `database` and `broadcast` (Reverb private channel `private-App.Models.User.{id}`).
- **Immutability:** Notifications are immutable; only `read_at` is updated.
- **Sidebar indicators (admin):** Red dots on Topups, Refunds, and Fulfillments use **state queries** (pending/failed counts), not notification counts.

## Trigger Points (after commit)

| Event | Recipient | Notification class |
|-------|-----------|--------------------|
| Topup requested | Admins | `TopupRequestedNotification` |
| Topup approved | Topup owner | `TopupApprovedNotification` |
| Topup rejected | Topup owner | `TopupRejectedNotification` |
| Refund requested | Admins | `RefundRequestedNotification` |
| Refund approved | Order owner | `RefundApprovedNotification` |
| Refund rejected | Order owner | `RefundRejectedNotification` |
| Fulfillment completed | Order owner | `FulfillmentCompletedNotification` |
| Fulfillment failed | Order owner | `FulfillmentFailedNotification` |
| Fulfillment process failed (command) | Admins | `FulfillmentProcessFailedNotification` |
| Wallet reconciled (drift ≠ 0) | Admins | `WalletReconciledNotification` |
| Settlement created | Admins (if `config('notifications.settlement_created_enabled')`) | `SettlementCreatedNotification` |
| Loyalty tier changed | User | `LoyaltyTierChangedNotification` |
| User blocked | User | `UserBlockedNotification` |
| User unblocked | User | `UserUnblockedNotification` |
| Payment failed | User | `PaymentFailedNotification` |

## Routes & UI

- **Admin:** `/admin/notifications` — list with filters (type, unread), bulk mark as read, click-through to source.
- **User:** `/notifications` — full list; bell dropdown in header shows latest 5 (unread) and mark as read on click.

## Configuration

- `config/notifications.php`: `settlement_created_enabled` (default `false`) — when true, admins receive a notification when a settlement batch is created.
- Broadcasting: default connection is Reverb (`config/broadcasting.php`). Set `BROADCAST_CONNECTION=reverb` and run Reverb + queue worker for realtime.

## Database

- Table: `notifications` (Laravel default schema).
- Indexes: `(notifiable_type, notifiable_id, created_at)` and `(notifiable_type, notifiable_id, read_at)` for list/unread performance.

## Safety

- Notifications are dispatched inside `DB::afterCommit()` where the action runs in a transaction, so they are not sent if the transaction rolls back.
- Idempotent paths (e.g. approve topup when already approved) return early and do not run the afterCommit callback, so no duplicate notifications.
