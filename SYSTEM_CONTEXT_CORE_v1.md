# indirimgo.tr — Full Project Context

Use this document to restore project context in long chat sessions (e.g. ChatGPT). Keep it versioned and update when the codebase or goals change.

---

## 1. Project identity

- **Codebase / repo name:** indirimgo.tr (`package.json` name; workspace path).
- **Owner:** Omar.
- **Frontend brand:** İndirimGo (logo text in `resources/views/layouts/frontend/header.blade.php`). User-facing name may differ from repo name.
- **Type:** Laravel e‑commerce / wallet platform: catalog (categories, packages, products), wallet, orders, fulfillments, topups, refunds, settlements, loyalty, notifications.

---

## 2. Tech stack & versions

- **PHP:** 8.2+ (CI runs 8.4, 8.5).
- **Laravel:** 12.
- **Livewire:** 4.
- **Frontend:** Alpine.js, Tailwind CSS 4, Vite 7. Flux UI **free** components only (no Pro).
- **Auth:** Laravel Fortify (username + email; 2FA optional).
- **Permissions:** Spatie laravel-permission (roles + permissions).
- **Audit:** Spatie laravel-activitylog.
- **Realtime:** Laravel Reverb (broadcasting); Laravel Echo + Pusher JS.
- **Testing:** Pest 3, PHPUnit 11. SQLite in-memory for tests.
- **Code style:** Laravel Pint.
- **Optional:** Laravel MCP (routes/ai.php), Laravel Sail.

---

## 3. Key commands

- **Run app:** `composer run dev` (serve + queue + vite) or `php artisan serve` + `npm run dev`.
- **Build:** `npm run build`.
- **Tests:** `php artisan test` or `./vendor/bin/pest`; filter: `--filter=Name`.
- **Lint/format:** `vendor/bin/pint` (fix), `composer run test:lint` (check).
- **Migrations:** `php artisan migrate`.
- **Seed:** `php artisan db:seed` (DatabaseSeeder → RolesAndPermissionsSeeder; other seeders optional).

---

## 4. Directory layout (important paths)

- **App logic:** `app/Actions/` (by domain: Orders, Fulfillments, Topups, Refunds, Users, Categories, Packages, Products, PricingRules, Loyalty, Fortify), `app/Services/` (SystemEventService, UserAuditTimelineService, OperationalIntelligenceService, etc.), `app/DTOs/` (e.g. TimelineEntryDTO), `app/Models/`, `app/Enums/`, `app/Notifications/`, `app/Events/`, `app/Console/Commands/`, `app/Http/Middleware/`, `app/Policies/`.
- **Livewire:** `app/Livewire/` (Settings, Sidebar, NotificationBellDropdown, Users/UserModals).
- **Views:** `resources/views/` — `layouts/` (app, frontend, auth), `pages/` (backend/*, frontend/*), `components/`, `livewire/`, `flux/`, `errors/`, `partials/`.
- **Routes:** `routes/web.php` (main), `routes/settings.php`, `routes/channels.php`, `routes/console.php`, `routes/ai.php` (MCP).
- **Config:** `config/` — app, auth, fortify, permission, billing, loyalty, notifications, broadcasting, reverb, livewire, operational_intelligence (anomaly thresholds/windows).
- **Lang:** `lang/en/`, `lang/ar/` (main, messages, notifications, validation, pagination).
- **Tests:** `tests/Feature/`, `tests/Unit/`, `tests/Pest.php`.
- **Docs:** `Docs/doc.md`, `Docs/DB.md`, `Docs/roles.md`, `NOTIFICATIONS.md`.

---

## 5. Architecture & patterns

- **TALL stack:** Blade + Livewire + Alpine + Tailwind. State: server in Livewire; UI-only state in Alpine (e.g. cart, modals, tabs). Prefer `wire:model.defer`/`lazy` and event-driven updates; avoid chatty `wire:model.live` unless needed.
- **No cart tables:** Cart lives in Alpine store + `localStorage` key `karman.cart.v1`. Checkout: payload sent to server → CreateOrderFromCartPayload → PayOrderWithWallet → CreateFulfillmentsForOrder (see `CheckoutFromPayload`, `resources/js/app.js`).
- **Actions over controllers:** Domain operations in `app/Actions/*` (e.g. PayOrderWithWallet, CreateTopupRequestAction, ApproveTopupRequest, CompleteFulfillment). Controllers used sparingly (e.g. TopupProofController, TimezoneController).
- **Form validation:** Form Request classes; validation rules and messages in requests. Check sibling Form Requests for array vs string rules.
- **Policies:** FulfillmentPolicy, OrderPolicy, UserPolicy. Use for authorization; gates/policies preferred over ad-hoc checks.
- **Single root in Livewire views:** Every Livewire component view must have one root element.
- **Named routes:** Use `route('name')` for links; no hardcoded URLs.

---

## 6. Authentication & access

- **Fortify:** Login/register with **username** (config: `fortify.php` `username` => `username`). Email verification, password reset, 2FA (optional) supported.
- **Backend access:** Middleware `backend` (EnsureBackendAccess) — user must have at least one of `config('permission.backend_permissions')`. If not, **404** (no 403) so backend routes stay hidden. Middleware `admin` (EnsureAdmin) exists but backend is permission-based.
- **backend_permissions:** manage_users, manage_sections, manage_products, manage_topups, view_sales, create_orders, edit_orders, delete_orders, view_orders, view_fulfillments, manage_fulfillments, view_refunds, process_refunds, view_activities, manage_settlements.
- **Roles:** admin (all permissions), salesperson, supervisor, customer. Spatie middleware: `role`, `permission`, `role_or_permission`.
- **User model:** username, email, is_active, blocked_at, timezone, profile_photo, loyalty_tier (bronze/silver/gold), etc. Blocked users: BlockUser / UnblockUser actions + notifications.

---

## 7. Frontend structure

- **Layouts:** Guest/frontend: `layouts::frontend` (header with logo, search, cart dropdown, notification bell, user menu). Authenticated backend: `layouts::app` with sidebar (`layouts.app.sidebar`).
- **Frontend pages (Livewire):** Home (`pages::frontend.main`), Cart, Wallet, Orders, Order details, Loyalty, Notifications. Route names: home, cart, wallet, loyalty, orders.index, orders.show, notifications.index.
- **Backend pages (Livewire):** Dashboard, Categories, Packages, Products, Pricing rules, Loyalty tiers, Admin orders (index/show), Activities, System events, Users (index/show, audit timeline), Fulfillments, Refunds, Topups, Customer funds, Settlements, Admin notifications. All under `middleware(['auth','verified','backend'])`.
- **Cart (Alpine):** `Alpine.store('cart')` in `resources/js/app.js` — items, requirements_schema, validation, persist to localStorage. Cart dropdown: `livewire:cart.dropdown`. Checkout calls Livewire with cart payload; server validates and runs CheckoutFromPayload.
- **RTL / locale:** `lang` en | ar; session locale; RTL when `app()->isLocale('ar')`. Header sets `dir` and `lang`. Logout resets to en (see Docs/doc.md).
- **Tailwind:** v4; `@import 'tailwindcss'`; `@theme` in `resources/css/app.css`. Accent: yellow (--color-accent). Dark mode: `dark:` and `.dark` class. Use gap for spacing in flex/grid.
- **Flux:** Free components only (avatar, badge, button, callout, checkbox, dropdown, field, heading, icon, input, modal, navbar, select, separator, skeleton, switch, text, textarea, tooltip, etc.). No Pro components.
- **Assets:** Vite entry: `resources/css/app.css`, `resources/js/app.js`. Echo in `resources/js/echo.js`. Run `npm run build` or `npm run dev` after frontend changes.

---

## 8. Data models & enums (concise)

- **User:** roles, permissions, wallet (customer), loyalty_tier, blocked_at, timezone.
- **Category:** parent_id, name, slug, order, icon, is_active, image. Hierarchical.
- **Package:** category_id, name, slug, description, order, icon, image. Has package_requirements (key, label, type, validation_rules, is_required).
- **Product:** package_id, name, slug, serial, entry_price, retail_price, wholesale_price, is_active, order. Pricing: PriceCalculator, CustomerPriceService, PricingRule (e.g. by role or tier).
- **Order:** user_id, order_number, currency, subtotal, fee, total, status, paid_at, meta. Status enum: pending_payment, paid, processing, fulfilled, failed, refunded, cancelled.
- **OrderItem:** order_id, product_id, package_id, name, quantity, unit_price, entry_price, status (pending, processing, fulfilled, failed). Snapshot of product at purchase.
- **Fulfillment:** order_item_id, status (queued, processing, completed, failed, cancelled), meta (delivered payload, refund state). FulfillmentLog for audit. Retry limited; refund request/approve/reject flow.
- **Wallet:** user_id, type (customer | platform), balance, currency. Wallet::forUser($user), Wallet::forPlatform().
- **WalletTransaction:** wallet_id, type (topup, purchase, refund, adjustment, settlement), direction (credit, debit), amount, status (pending, posted, rejected), reference_type/id (polymorphic), idempotency_key (nullable, unique in DB). Purchase: `purchase:order:{order_id}`; refund: `refund:fulfillment:{id}` or order_item/order; settlement: `settlement:{id}`.
- **TopupRequest:** user_id, wallet_id, amount, currency, method (enum TopupMethod), status (pending, approved, rejected, cancelled), note. **Created only via CreateTopupRequestAction** (no WalletTransaction in model event). TopupProof: image for proof upload.
- **Settlement:** total_amount; pivot to fulfillments. Created by profit:settle; one platform wallet credit per settlement (idempotency).
- **Loyalty:** LoyaltyTier (bronze, silver, gold), LoyaltyTierConfig (per tier), LoyaltySetting (rolling_window_days). LoyaltySpendService: spend from fulfillments (excluding posted refunds). EvaluateLoyaltyCommand / EvaluateLoyaltyForUserAction.
- **Activity log:** Spatie; logs for orders, payments, etc. Events: ActivityLogChanged (admin.activities channel).
- **System events (observability):** Insert-only `system_events` table: event_type, entity_type/entity_id, actor_type/actor_id, meta, severity (info|warning|critical), is_financial, idempotency_key (async), created_at. **Source of truth remains wallet_transactions and wallet balance;** system_events is a mirror for timeline/audit. Model: update/delete throw (BadMethodCallException). **Invariant:** For every wallet balance mutation there is exactly one financial system_event (one POSTED wallet_transaction, one balance change, one row with is_financial=true). Financial events recorded inside same transaction; broadcast via DB::afterCommit(SystemEventCreated). Async events via PersistSystemEventJob with structured idempotency key `async:{event_type}:{entity_type}:{entity_id}[:suffix]`. See Docs/system_events_map.md.
- **User audit timeline:** Unified chronological timeline per user at `/admin/users/{user}/audit`. **UserAuditTimelineService** merges: wallet_transactions (financial truth), **non-financial system_events only** (no financial system_events to avoid duplication), orders, fulfillments. **TimelineEntryDTO:** type, title, description, occurredAt, severity, isFinancial, meta, sourceKey, eventType (nullable; from system_events for domain-safe filtering). Refund workflow shown via system_events (refund.requested, refund.approved) + wallet_transaction credit; wallet entries map to type `wallet_transaction` only. Type filter: only the relevant source is queried (no IN from wallet_transaction IDs for refund; join system_events → wallet_transactions → wallets). Date filters use index-safe `created_at >= startOfDay` / `<= endOfDay`. Same auth as view user (manage_users); non-admin gets 404.
- **Operational intelligence (anomaly detection):** **OperationalIntelligenceService** — deterministic, threshold-based; never mutates ledger; runs only inside DB::afterCommit() at invocation points. **detectWalletVelocity(WalletTransaction):** threshold POSTED tx within window (same wallet) → `wallet.anomaly.velocity_detected` (warning). **detectRefundAbuse(userId):** threshold refund.approved (non-financial) within window for user → `refund.anomaly.pattern_detected` (warning); count via join (system_events → wallet_transactions → wallets), no large IN. **detectFulfillmentFailure(Fulfillment):** threshold failed fulfillments (same provider or product) within window → `fulfillment.anomaly.failure_spike` (warning). **detectReconciliationDrift(Wallet, driftMeta):** when reconcile detects drift → `wallet.anomaly.drift_detected` (critical). All use SystemEventService::record(..., isFinancial=false); idempotency by time buckets. Thresholds/windows in **config/operational_intelligence.php** (wallet_velocity, refund_abuse, fulfillment_failure); env overrides (OI_*). Invocation: PayOrderWithWallet, ApproveRefundRequest, ApproveTopupRequest (velocity + refund abuse where applicable), FailFulfillment (failure spike), WalletReconcile (drift).
- **Notifications:** Laravel notifications table; database + broadcast. Private channel `private-App.Models.User.{id}`. Admin channels: admin.fulfillments, admin.topups, admin.activities, admin.system-events.

---

## 9. Financial core (wallet & money)

- **Ledger-style:** Wallet has stored `balance`; balance is also derivable from sum of **posted** transactions (credit +amount, debit -amount). Reconcile with `php artisan wallet:reconcile [--user=] [--dry-run]`: each wallet is processed inside `DB::transaction()` with `lockForUpdate()` before computing expected balance and updating; drift is fixed and admins notified (WalletReconciledNotification).
- **Ledger + event mirror:** Every wallet balance mutation is mirrored by exactly one financial `system_events` row (is_financial=true, matching event_type). SystemEventService::record(..., isFinancial: true) is called only on the path that actually performs the write (never on early-return “already posted” / idempotency). No logic may derive balance or financial state from system_events; ledger (wallet_transactions, wallets.balance) is the only source of truth.
- **No WalletService:** Mutations in Actions/Commands inside `DB::transaction()` with `lockForUpdate()` on wallet/order/transaction where needed. When posting a transaction, balance is updated in the same transaction (increment/decrement). SystemEventService never starts or wraps a transaction; it throws if isFinancial=true and transactionLevel()===0.
- **Transaction types:** topup, purchase, refund, adjustment, settlement. Direction: credit | debit. Status: pending, posted, rejected.
- **Order payment:** PayOrderWithWallet locks order and wallet, looks up by idempotency_key `purchase:order:{order_id}` (then by reference_type/reference_id). If posted → return (no system_event). If pending → post and set key, decrement balance, then record `wallet.purchase.debited` (financial) and broadcast after commit.
- **Topup creation:** CreateTopupRequestAction creates TopupRequest and pending WalletTransaction (type topup) atomically in one `DB::transaction()`. Use this action for all topup request creation (e.g. frontend wallet page). TopupRequest model does not create WalletTransaction in an event.
- **Profit:** Not a transaction type. Profit = selling price − cost per fulfillment; settled in batch: `php artisan profit:settle [--dry-run] [--until=YYYY-MM-DD]` creates Settlement, loads platform wallet with `lockForUpdate()` inside the transaction, credits platform wallet (type settlement), idempotency_key = `settlement:{id}`. Records `platform.profit.recorded` (financial) only; `profit.settlement.executed` is informational (async after commit). Eligibility: completed fulfillments without posted refund (same logic as LoyaltySpendService).
- **Refund:** ApproveRefundRequest: before posting, hard check for any other posted refund (same fulfillment/order_item/order); if found, return that transaction (no system_event). Then idempotency_key and post credit to customer wallet (type refund), record `wallet.refund.credited` (financial), update fulfillment/order state. Refund state in fulfillment meta.
- **Principles:** All wallet operations atomic; no balance change without posting transaction; profit from settlement only; notifications and financial event broadcast in DB::afterCommit.

---

## 10. Notifications & realtime

- **Channels:** Database + broadcast (Reverb). User: `private-App.Models.User.{id}`. Admin: admin.fulfillments, admin.topups, admin.activities, admin.system-events (SystemEventCreated).
- **Trigger:** After DB commit only (DB::afterCommit in Actions/Commands). No notification on rollback. Financial system_event broadcast also in DB::afterCommit.
- **Types:** Topup (requested, approved, rejected), Refund (requested, approved, rejected), Fulfillment (completed, failed, process failed), Wallet reconciled, Settlement created (if config), Loyalty tier changed, User blocked/unblocked, Payment failed. See NOTIFICATIONS.md.
- **UI:** Admin: `/admin/notifications`. User: bell dropdown (latest 5), `/notifications`. Sidebar indicators (topups, refunds, fulfillments) use **state counts** (pending/failed), not notification count.

---

## 11. Localization

- **Locales:** en, ar. Session-driven; switch via route `language.switch` (`language/{locale}`).
- **Lang files:** main, messages, notifications, validation, pagination in `lang/en/` and `lang/ar/`. Use `__('key')` or `@lang`; never hardcode user-facing strings.

---

## 12. Testing

- **Framework:** Pest 3. Tests in `tests/Feature/` and `tests/Unit/`. Use `php artisan make:test --pest Name`.
- **Environment:** APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, BROADCAST_CONNECTION=null, QUEUE_CONNECTION=sync, SESSION_DRIVER=array.
- **Conventions:** Use factories; assert status with `assertSuccessful`, `assertForbidden`, `assertNotFound` etc. Livewire: `Livewire::test(Component::class)->call(...)->assertSet(...)`. Run minimal tests after changes: `php artisan test --compact --filter=...`.

---

## 13. CI / GitHub Actions

- **Workflow:** `.github/workflows/tests.yml` on push/PR to develop, main, components. Matrix: PHP 8.4, 8.5. Steps: checkout, setup PHP + Node, npm i, composer install (with Flux credentials from secrets), .env from .env.example, key:generate, npm run build, `./vendor/bin/pest`.
- **Lint:** `.github/workflows/lint.yml` (if present) — run Pint or similar.

---

## 14. Config summary

- **app:** APP_NAME from env; locale/fallback/faker.
- **auth:** guards, providers (users).
- **fortify:** guard, username, email, 2FA, views (Livewire auth views).
- **permission:** backend_permissions list; Spatie tables.
- **billing:** currency (USD), currency_symbol, checkout_fee_fixed.
- **loyalty:** rolling_window_days (env LOYALTY_ROLLING_WINDOW_DAYS).
- **notifications:** settlement_created_enabled.
- **operational_intelligence:** wallet_velocity (threshold, window_seconds), refund_abuse (threshold, window_minutes), fulfillment_failure (threshold, window_minutes). Env: OI_WALLET_VELOCITY_*, OI_REFUND_ABUSE_*, OI_FULFILLMENT_FAILURE_*.
- **broadcasting / reverb:** default connection; Reverb for realtime.

---

## 15. Conventions & don’ts

- **PHP:** Strict types where appropriate; return type declarations; constructor property promotion; curly braces for control structures. Use `config()` not `env()` outside config files.
- **DB:** Prefer Eloquent and relationships; avoid raw DB where avoidable; eager load to prevent N+1. Modify columns in migrations with full attribute list (Laravel 12).
- **Naming:** Descriptive (e.g. `isRegisteredForDiscounts`). Enums: TitleCase keys (e.g. PendingPayment).
- **Do not:** Add new packages without approval; create docs/README unless asked; use Flux Pro components; use deprecated Tailwind v3 utilities (see project rules).
- **Performance:** Prefer event-driven Livewire; Alpine for UI-only state; avoid duplicating business state in Alpine; cache analytics/heavy work (see 500-packages.mdc).

---

## 16. Docs & references

- **Docs/doc.md:** Known bugs/features, language behavior, done/doing list.
- **Docs/DB.md:** Schema notes, orders/order_items, wallet/transaction design.
- **Docs/roles.md:** Role list (admin, supervisor, salesperson, customer).
- **Docs/system_events_map.md:** System events map — financial vs informational, invariant (ledger + mirror), idempotency keys, broadcast, severity.
- **NOTIFICATIONS.md:** Notification triggers, channels, config, safety (afterCommit).
- **CLAUDE.md / .cursor/rules:** Laravel Boost, Pint, Pest, Livewire, Tailwind, Flux conventions; test enforcement; MCP usage.
- **User audit timeline:** UserAuditTimelineService (merged timeline, non-financial system_events only); TimelineEntryDTO; type/date filters; index-safe queries. Operational intelligence: OperationalIntelligenceService + config/operational_intelligence.php; anomaly detection in afterCommit only; no ledger mutation.

---

## 17. Goals & current priority

- Financial hardening and **ledger + event mirror** in place: purchase idempotency (`purchase:order:{id}`), platform wallet locked in profit:settle, wallet locked in reconcile, atomic topup via CreateTopupRequestAction, refund hard check (no double-post for same reference). **System events:** one financial system_event per balance mutation; recorded only on real state transition (no record on early return); insert-only; broadcast via DB::afterCommit; admin timeline prepends on new event (no full refresh). **User audit timeline:** unified per-user timeline (wallet_transactions + non-financial system_events + orders + fulfillments); financial truth from ledger only; type/date filters; configurable. **Operational intelligence:** anomaly detection (velocity, refund abuse, fulfillment failure, reconciliation drift) via OperationalIntelligenceService; configurable thresholds; runs in DB::afterCommit only; never blocks financial flow. Continue to maintain: atomic ops, no drift, reconciliation, profit from settlement only; ledger as sole source of truth.
- Keep: Fast, lightweight UI; minimal chatty Livewire; clear separation between server state and Alpine UI state.

---

## 18. Quick reference — routes (public / auth / backend)

- **Public:** `/` (home), `/cart`, `/404`, `language/{locale}`.
- **Auth + verified:** `/wallet`, `/loyalty`, `/orders`, `/orders/{order_number}`, `/notifications`, `/topup-proofs/{proof}`.
- **Backend (auth + verified + backend):** `/dashboard`, `/categories`, `/packages`, `/products`, `/pricing-rules`, `/loyalty-tiers`, `/admin/orders`, `/admin/activities`, `/admin/system-events`, `/admin/users`, `/admin/users/{user}/audit` (user audit timeline), `/fulfillments`, `/refunds`, `/topups`, `/customer-funds`, `/settlements`, `/admin/notifications`.
- **Settings:** `/settings`, `/settings/profile`, `/settings/password`, `/settings/appearance`, two-factor route by Fortify.
