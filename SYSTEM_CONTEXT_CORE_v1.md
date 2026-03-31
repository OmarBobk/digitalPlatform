# karman.store — Full Project Context

Use this document to restore project context in long chat sessions (e.g. ChatGPT). Keep it versioned and update when the codebase or goals change.

---

## 1. Project identity

- **Codebase / repo name:** karman.store (workspace path).
- **Owner:** Omar.
- **Frontend brand:** İndirimGo (logo text in `resources/views/layouts/frontend/header.blade.php`). User-facing name may differ from repo name.
- **Type:** Laravel e‑commerce / wallet platform: catalog (categories, packages, products), wallet, orders, fulfillments, topups, refunds, settlements, loyalty, notifications.

---

## 2. Tech stack & versions

- **PHP:** 8.4.16 (project runtime baseline; align local/runtime expectations to this version).
- **Laravel:** 12.
- **Livewire:** 4.
- **Frontend:** Alpine.js, Tailwind CSS 4, Vite 7. Flux UI **free** components only (no Pro).
- **Auth:** Laravel Fortify (username + email; 2FA optional).
- **Permissions:** Spatie laravel-permission (roles + permissions).
- **Audit:** Spatie laravel-activitylog.
- **Realtime:** Laravel Reverb (broadcasting); Laravel Echo + Pusher JS.
- **Testing:** Pest 3, PHPUnit 11. SQLite in-memory for tests.
- **Code style:** Laravel Pint.
- **Optional:** Laravel MCP (routes/ai.php), Laravel Sail. **PWA:** erag/laravel-pwa (manifest, service worker; config/pwa.php).

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

- **App logic:** `app/Actions/` (by domain: Orders, Fulfillments, Topups, Refunds, Users, Categories, Packages, Products, **Pricing** — e.g. `QuoteBuyNowCustomAmountLine`, PricingRules, Loyalty, Fortify), `app/Services/` (SystemEventService, UserAuditTimelineService, OperationalIntelligenceService, etc.), `app/DTOs/` (e.g. TimelineEntryDTO), `app/Models/`, `app/Enums/`, `app/Notifications/`, `app/Events/`, `app/Console/Commands/`, `app/Http/Middleware/`, `app/Policies/`.
- **Livewire:** `app/Livewire/` — Settings (Profile, Password, Appearance, DeleteUserForm, TwoFactor), Sidebar (SidebarToggleBadge, OperationsGroup, FinancialsGroup), NotificationBellDropdown, Users (UserModals, UsersTable), Actions (Logout). Cart: `livewire:cart.dropdown`.
- **Views:** `resources/views/` — `layouts/` (app, frontend, auth), `pages/` (backend/*, frontend/*), `components/`, `livewire/`, `flux/`, `errors/`, `partials/`.
- **Routes:** `routes/web.php` (main), `routes/settings.php`, `routes/channels.php`, `routes/console.php`, `routes/ai.php` (MCP).
- **Config:** `config/` — app, auth, fortify, permission, billing, loyalty, notifications, broadcasting, reverb, livewire, operational_intelligence (anomaly thresholds/windows), pwa (manifest, install button gated by permission install_pwa_app, livewire-app).
- **Lang:** `lang/en/`, `lang/ar/` (main, messages, notifications, validation, pagination).
- **Tests:** `tests/Feature/`, `tests/Unit/`, `tests/Pest.php`.
- **Docs:** `Docs/doc.md`, `Docs/DB.md`, `Docs/roles.md`, `NOTIFICATIONS.md`.

---

## 5. Architecture & patterns

- **TALL stack:** Blade + Livewire + Alpine + Tailwind. State: server in Livewire; UI-only state in Alpine (e.g. cart, modals, tabs). Prefer `wire:model.defer`/`lazy` and event-driven updates; avoid chatty `wire:model.live` unless needed.
- **No cart tables:** Cart lives in Alpine store + `localStorage` key `karman.cart.v1`. Checkout: payload sent to server → CreateOrderFromCartPayload → PayOrderWithWallet → CreateFulfillmentsForOrder (see `CheckoutFromPayload`, `resources/js/app.js`).
- **Custom amount products:** `Product.amount_mode = custom` (`ProductAmountMode`). Customer chooses `requested_amount` within configured min/max/step; cart and checkout include `requested_amount`. Server recomputes price from **per-unit `entry_price` × amount**; an active **pricing rule** must cover the **computed entry total** (same invariant as `PriceCalculator` — no matching rule → validation/error). Alpine cart treats custom lines as **quantity 1** for counts and checkout payload.
- **Fulfillment claim workflow:** Ownership is explicit via `claimed_by` / `claimed_at`. `ClaimFulfillment` runs in `DB::transaction()` with `lockForUpdate()` on fulfillment and actor, enforces queued + unclaimed precondition, applies an active processing-task cap (**max 5** per actor), sets claim fields, transitions to processing, then emits `FulfillmentListChanged('claimed')` in `DB::afterCommit()`.
- **Fulfillment listing scopes:** `GetFulfillments` supports `unclaimed` / `mine` / `all`, optional `claimedBy` and `handledByActorId`, and uses narrower select/eager-load sets for unclaimed queues vs full operational views.
- **Actions over controllers:** Domain operations in `app/Actions/*` (e.g. PayOrderWithWallet, CreateTopupRequestAction, ApproveTopupRequest, CompleteFulfillment). Controllers used sparingly (e.g. TopupProofController, TimezoneController). **`BuyNowCustomAmountQuoteController`** exists as a **stub** (not routed yet); see §12.
- **Form validation:** Form Request classes; validation rules and messages in requests. Check sibling Form Requests for array vs string rules.
- **Policies:** FulfillmentPolicy, OrderPolicy, UserPolicy. Use for authorization; gates/policies preferred over ad-hoc checks.
- **Single root in Livewire views:** Every Livewire component view must have one root element.
- **Named routes:** Use `route('name')` for links; no hardcoded URLs.

---

## 6. Authentication & access

- **Fortify:** Login/register with **username** (config: `fortify.php` `username` => `username`). Email verification, password reset, 2FA (optional) supported.
- **Backend access:** Middleware `backend` (EnsureBackendAccess) — user must have at least one of `config('permission.backend_permissions')`. If not, **404** (no 403) so backend routes stay hidden. Middleware `admin` (EnsureAdmin) exists but backend is permission-based.
- **backend_permissions:** manage_users, manage_sections, manage_products, manage_topups, view_sales, create_orders, edit_orders, delete_orders, view_orders, view_fulfillments, manage_fulfillments, view_refunds, process_refunds, view_activities, manage_settlements.
- **Fulfillment authorization model:** `FulfillmentPolicy` allows admins full access; non-admin access is constrained by permissions + ownership/state. `view` permits own claimed items or queued unclaimed items, `update` requires ownership (`claimed_by === user.id`), and `claim` requires queued + unclaimed (plus `manage_fulfillments`).
- **Bug access:** `manage_bugs` permission gates bug reporting and bug inbox/admin pages; it is included in backend permissions so permitted staff can access backend routes.
- **Other permissions:** `install_pwa_app` — admin only; controls whether the PWA “Install app” button is shown. View composer in AppServiceProvider (for `partials.head` and `partials.frontend.head`) sets `config('pwa.install-button')` from `auth()->user()?->can('install_pwa_app')` so erag/laravel-pwa only shows the button when the user has this permission.
- **Roles:** admin (all permissions), salesperson, supervisor, customer. Spatie middleware: `role`, `permission`, `role_or_permission`.
- **User model:** username, email, is_active, blocked_at, timezone, profile_photo, loyalty_tier (bronze/silver/gold), etc. Blocked users: BlockUser / UnblockUser actions + notifications.

---

## 7. Frontend structure

- **Layouts:** Guest/frontend: `layouts::frontend` (header with logo, search, cart dropdown, notification bell, user menu). Authenticated backend: `layouts::app` with sidebar (`layouts.app.sidebar`).
- **Frontend pages (Livewire):** Home (`pages::frontend.main`), Contact, Cart, Profile (edit), Wallet, Orders, Order details, Loyalty, Notifications. Route names: home, contact, cart, profile, profile.edit-information, wallet, loyalty, orders.index, orders.show, notifications.index.
- **Backend pages (Livewire):** Dashboard, Categories, Packages, Products, Pricing rules, Loyalty tiers, Admin orders (index/show), Activities, System events, Users (index/show, audit timeline), Fulfillments, Refunds, Topups, Customer funds, Settlements, Admin notifications; **admin-only:** Website settings (`/admin/website-settings`). All under `middleware(['auth','verified','backend'])`; website settings also `admin` role.
- **Recent backend UI updates:** Fulfillments table now shows compact **requirements** context from `orderItem.requirements_payload` (fallback `fulfillment.meta.requirements_payload`) in the list view, and Users Manager role labels are localized via translation keys (not raw role slugs).
- **Bug reporting UI:** Permission-aware quick bug report modal and admin bug inbox are available; report flow is multi-step with screenshot requirement and contextual metadata capture for debugging.
- **Cart (Alpine):** `Alpine.store('cart')` in `resources/js/app.js` — items, `amount_mode`, `custom_amount_min` / `max` / `step`, `requested_amount`, requirements_schema, validation, persist to localStorage. Cart dropdown: `livewire:cart.dropdown`. Checkout calls Livewire with cart payload; server validates and runs CheckoutFromPayload.
- **Custom amount UI:** Backend products Livewire (`pages/backend/products`) configures amount mode and bounds. Storefront: `components/main/⚡section-of-products`, `⚡buy-now-modal` (live quote / validation, `messages.custom_amount_no_pricing_rule` when no rule matches total), `pages/frontend/⚡cart`, orders list/details. **`components/order-card.blade.php`** + **`components/partials/order-card-line.blade.php`** for order cards (custom amount on lines).
- **RTL / locale:** `lang` en | ar; session locale; RTL when `app()->isLocale('ar')`. Header sets `dir` and `lang`. Logout resets to en (see Docs/doc.md).
- **Tailwind:** v4; `@import 'tailwindcss'`; `@theme` in `resources/css/app.css`. Accent: yellow (--color-accent). Dark mode: `dark:` and `.dark` class. Use gap for spacing in flex/grid.
- **Flux:** Free components only (avatar, badge, button, callout, checkbox, dropdown, field, heading, icon, input, modal, navbar, select, separator, skeleton, switch, text, textarea, tooltip, etc.). No Pro components.
- **Assets:** Vite entry: `resources/css/app.css`, `resources/js/app.js`. Echo in `resources/js/echo.js`. Run `npm run build` or `npm run dev` after frontend changes.
- **PWA:** erag/laravel-pwa. Manifest from `config/pwa.php` (name, short_name, description, theme_color, background_color, display, orientation, scope, start_url, icons 192+512); run `php artisan erag:update-manifest` after changes. Layouts: `@PwaHead` in head partials, `@RegisterServiceWorkerScript` before `@fluxScripts`. Config: install-button (overridden at runtime by `install_pwa_app` permission — only admins see the install button), manifest, debug (env APP_DEBUG), livewire-app.

---

## 8. Data models & enums (concise)

- **User:** roles, permissions, wallet (customer), loyalty_tier, blocked_at, timezone.
- **Category:** parent_id, name, slug, order, icon, is_active, image. Hierarchical.
- **Package:** category_id, name, slug, description, order, icon, image. Has package_requirements (key, label, type, validation_rules, is_required).
- **ProductAmountMode (enum):** `Fixed` | `Custom` (string values `fixed` / `custom`).
- **Product:** package_id, name, slug, serial, **amount_mode** (`ProductAmountMode`), **amount_unit_label** (optional), **custom_amount_min** / **custom_amount_max** / **custom_amount_step** (integers, custom mode), entry_price, retail_price, wholesale_price, is_active, order. **`entry_price`:** app cast `decimal:8`; DB **DECIMAL(18,8)** on MySQL/MariaDB/Postgres (migration `2026_03_28_183214_*`) so very small per-unit costs are not rounded to zero. Retail/wholesale accessors derive from `entry_price` via **PriceCalculator** + rules. Pricing: PriceCalculator, CustomerPriceService, PricingRule (role/tier; rules match on **effective** entry total for custom lines — see §9).
- **Order:** user_id, order_number, currency, subtotal, fee, total, status, paid_at, meta. Status enum: pending_payment, paid, processing, fulfilled, failed, refunded, cancelled.
- **OrderItem:** order_id, product_id, package_id, name, quantity, unit_price, entry_price, **amount_mode**, **requested_amount** (nullable int, custom lines), **amount_unit_label**, **pricing_meta** (JSON/array; custom lines include mode, requested_amount, entry_price, computed_entry_total), **line_total**, requirements_payload, status (pending, processing, fulfilled, failed). Snapshot of product at purchase; custom lines use **quantity 1** server-side with line total = final price for that amount.
- **Fulfillment:** order_item_id, **claimed_by** (nullable user id), **claimed_at** (nullable timestamp), status (queued, processing, completed, failed, cancelled), meta (delivered payload, refund state, **or** `type: custom_amount` with `amount` + `unit` for custom-amount order lines). **Custom amount:** **CreateFulfillmentsForOrder** creates **one** fulfillment per custom line (not one per quantity). FulfillmentLog for audit. Retry limited; refund request/approve/reject flow.
- **Fulfillment DB changes:** Migration `2026_03_30_154826_add_claim_columns_to_fulfillments_table` adds claim columns, index `fulfillments_status_claimed_by_created_at_idx`, and FK `fulfillments_claimed_by_foreign` (`nullOnDelete` with defensive fallback if FK creation fails on legacy schema).
- **Bug:** user_id, role, scenario, subtype, severity, status (open, in_progress, resolved, closed), trace_id (uuid, indexed), current_url, route_name, description, metadata, potential_duplicate_of (self-reference), with related steps/attachments/links.
- **Wallet:** user_id, type (customer | platform), balance, currency. Wallet::forUser($user), Wallet::forPlatform().
- **WalletTransaction:** wallet_id, type (topup, purchase, refund, adjustment, settlement), direction (credit, debit), amount, status (pending, posted, rejected), reference_type/id (polymorphic), idempotency_key (nullable, unique in DB). Purchase: `purchase:order:{order_id}`; refund: `refund:fulfillment:{id}` or order_item/order; settlement: `settlement:{id}`.
- **TopupRequest:** user_id, wallet_id, amount, currency, method (enum TopupMethod), status (pending, approved, rejected, cancelled), note. **Created only via CreateTopupRequestAction** (no WalletTransaction in model event). TopupProof: image for proof upload.
- **Settlement:** total_amount; pivot to fulfillments. Created by profit:settle; one platform wallet credit per settlement (idempotency).
- **Loyalty:** LoyaltyTier (bronze, silver, gold), LoyaltyTierConfig (per tier), LoyaltySetting (rolling_window_days). LoyaltySpendService: spend from fulfillments (excluding posted refunds). EvaluateLoyaltyCommand / EvaluateLoyaltyForUserAction.
- **Activity log:** Spatie; logs for orders, payments, etc. Events: ActivityLogChanged (admin.activities channel).
- **System events (observability):** Insert-only `system_events` table: event_type, entity_type/entity_id, actor_type/actor_id, meta, severity (info|warning|critical), is_financial, idempotency_key (async), created_at. **Source of truth remains wallet_transactions and wallet balance;** system_events is a mirror for timeline/audit. Model: update/delete throw (BadMethodCallException). **Invariant:** For every wallet balance mutation there is exactly one financial system_event (one POSTED wallet_transaction, one balance change, one row with is_financial=true). Financial events recorded inside same transaction; broadcast via DB::afterCommit(SystemEventCreated). Async events via PersistSystemEventJob with structured idempotency key `async:{event_type}:{entity_type}:{entity_id}[:suffix]`. Anomaly events (wallet.anomaly.*, refund.anomaly.*, fulfillment.anomaly.*) from OperationalIntelligenceService, severity warning/critical, idempotency by time bucket or date. See Docs/system_events_map.md.
- **User audit timeline:** Unified chronological timeline per user at `/admin/users/{user}/audit`. **UserAuditTimelineService** merges: wallet_transactions (financial truth), **non-financial system_events only** (no financial system_events to avoid duplication), orders, fulfillments. **TimelineEntryDTO:** type, title, description, occurredAt, severity, isFinancial, meta, sourceKey, eventType (nullable; from system_events for domain-safe filtering). Refund workflow shown via system_events (refund.requested, refund.approved) + wallet_transaction credit; wallet entries map to type `wallet_transaction` only. Type filter: only the relevant source is queried (no IN from wallet_transaction IDs for refund; join system_events → wallet_transactions → wallets). Date filters use index-safe `created_at >= startOfDay` / `<= endOfDay`. Same auth as view user (manage_users); non-admin gets 404.
- **Operational intelligence (anomaly detection):** **OperationalIntelligenceService** — deterministic, threshold-based; never mutates ledger; runs only inside DB::afterCommit() at invocation points. **detectWalletVelocity(WalletTransaction):** threshold POSTED tx within window (same wallet) → `wallet.anomaly.velocity_detected` (warning). **detectRefundAbuse(userId):** threshold refund.approved (non-financial) within window for user → `refund.anomaly.pattern_detected` (warning); count via join (system_events → wallet_transactions → wallets), no large IN. **detectFulfillmentFailure(Fulfillment):** threshold failed fulfillments (same provider or product) within window → `fulfillment.anomaly.failure_spike` (warning). **detectReconciliationDrift(Wallet, driftMeta):** when reconcile detects drift → `wallet.anomaly.drift_detected` (critical). All use SystemEventService::record(..., isFinancial=false); idempotency by time buckets. Thresholds/windows in **config/operational_intelligence.php** (wallet_velocity, refund_abuse, fulfillment_failure); env overrides (OI_*). Invocation: PayOrderWithWallet, ApproveRefundRequest, ApproveTopupRequest (velocity + refund abuse where applicable), FailFulfillment (failure spike), WalletReconcile (drift).
- **PushLog:** push dispatch telemetry table with notification_type, notification_id (widened to 191), trace_id (uuid), token_count, status, error, created_at.
- **Notifications:** Laravel notifications table; database + broadcast (+ FCM where configured). Private channel `private-App.Models.User.{id}`. Admin channels: admin.fulfillments, admin.topups, admin.activities, admin.system-events, admin.bugs.

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
- **Custom amount pricing (catalog, not wallet):** **CustomerPriceService::finalPriceForAmount** multiplies `requested_amount × entry_price` with **BCMath**, clones the product with `entry_price` set to that **computed entry total**, then runs the normal **priceFor** pipeline (PriceCalculator rules, loyalty, user_product_prices overrides). **CreateOrderFromCartPayload** validates amounts (min/max/step + internal hard cap), stores **pricing_meta**, logs `custom_amount_order` for observability. **PriceCalculator::calculate** accepts optional **rounding scale** (0–8). Ops implication: pricing rule **max_price** (and rule coverage) must include large **amount × unit entry** totals; UI copy `messages.custom_amount_no_pricing_rule` explains gaps.
- **Entry price precision (custom amount fidelity):** `products.entry_price` and `order_items.entry_price` are both widened to **DECIMAL(18,8)** on MySQL/Postgres so per-unit and snapped entry values retain sub-cent precision in amount-based flows. See migrations `2026_03_28_183214_widen_entry_price_precision_on_products_table` and `2026_03_31_095847_widen_entry_price_precision_on_order_items_table`.
- **Principles:** All wallet operations atomic; no balance change without posting transaction; profit from settlement only; notifications and financial event broadcast in DB::afterCommit.

---

## 10. Notifications & realtime

- **Channels:** Database + broadcast (Reverb). User: `private-App.Models.User.{id}`. Operational/admin channels: `admin.fulfillments` (requires `view_fulfillments` permission), `admin.topups` (admin role), `admin.activities` (admin role), `admin.system-events` (admin role), `admin.bugs` (requires `manage_bugs`).
- **Trigger:** After DB commit only (DB::afterCommit in Actions/Commands). No notification on rollback. Financial system_event broadcast also in DB::afterCommit.
- **Types:** Topup (requested, approved, rejected), Refund (requested, approved, rejected), Fulfillment (completed, failed, process failed), Wallet reconciled, Settlement created (if config), Loyalty tier changed, User blocked/unblocked, Payment failed. See NOTIFICATIONS.md.
- **Bug inbox realtime:** Bug lifecycle updates broadcast as `BugInboxChanged` on private channel `admin.bugs`; sidebar indicators and bug inbox pages refresh from these events.
- **Push dedup + hygiene:** push jobs deduplicate by notification_id (idempotent + short-window duplicate skip), log to `push_logs`, and purge invalid admin FCM tokens when provider returns UNREGISTERED/INVALID_ARGUMENT.
- **UI:** Admin: `/admin/notifications`. User: bell dropdown (latest 5), `/notifications`. Sidebar: **state badges** for topups/refunds/fulfillments via Operations/Financials indicators and mobile toggle dot; these indicate operational state, not notification count.

---

## 11. Localization

- **Locales:** en, ar. Session-driven; switch via route `language.switch` (`language/{locale}`).
- **Lang files:** main, messages, notifications, validation, pagination in `lang/en/` and `lang/ar/`. Use `__('key')` or `@lang`; never hardcode user-facing strings. **Custom amount:** `messages` includes `product_amount_mode`, `amount_mode_fixed` / `amount_mode_custom`, custom bounds labels, hints (`custom_amount_see_live_price`), and `custom_amount_no_pricing_rule`.
- **Role label convention:** Keep role slugs canonical in DB (`admin`, `supervisor`, `salesperson`, `customer`) and render UI labels through `messages.role_{slug}` with humanized fallback.

---

## 12. Testing

- **Framework:** Pest 3. Tests in `tests/Feature/` and `tests/Unit/`. Use `php artisan make:test --pest Name`.
- **Environment:** APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, BROADCAST_CONNECTION=null, QUEUE_CONNECTION=sync, SESSION_DRIVER=array.
- **Conventions:** Use factories; assert status with `assertSuccessful`, `assertForbidden`, `assertNotFound` etc. Livewire: `Livewire::test(Component::class)->call(...)->assertSet(...)`. Run minimal tests after changes: `php artisan test --compact --filter=...`.
- **Recent coverage:** `tests/Feature/BugReportingTest.php` verifies report validation/creation, realtime updates, and admin notification fanout; `tests/Feature/PushNotificationJobTest.php` verifies retry/backoff, dedup, long notification IDs, logging status, and invalid-token cleanup.
- **Custom amount coverage:** Tests such as `BuyNowModalTest`, `CheckoutFlowTest`, `FulfillmentActionsTest`, `OrdersPageTest`, `PriceCalculatorTest`, `ProductModelTest`, `ProductsPageTest`, `UserProductPriceFeatureTest` exercise custom-amount products end-to-end where applicable. **`tests/Feature/BuyNowCustomAmountQuoteTest.php`** is currently a **placeholder**. **`BuyNowCustomAmountQuoteController`** and **`App\Actions\Pricing\QuoteBuyNowCustomAmountLine`** are **stubs** until implemented and registered in `routes/web.php` (or equivalent).
- **Fulfillment claim coverage:** `tests/Feature/FulfillmentActionsTest.php` also covers claiming and ownership-sensitive fulfillment operations (claim, processing transition, retry/complete/fail flows with claim state updates).

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
- **pwa:** install-button (default true; overridden at render time by permission `install_pwa_app` — only users with this permission see the install button; admin only), manifest (name, short_name, description, theme_color, background_color, display, orientation, scope, start_url, icons 192+512), debug (env APP_DEBUG), livewire-app; `php artisan erag:update-manifest` to regenerate manifest.

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
- **Docs/system_events_map.md:** System events map — financial vs informational vs anomaly events, invariant (ledger + mirror), idempotency keys, broadcast, severity.
- **config/pwa.php:** PWA (erag/laravel-pwa): install-button (gated by permission `install_pwa_app` via view composer in AppServiceProvider — admin only), manifest (name, short_name, description, theme_color, background_color, display, orientation, scope, start_url, icons), debug (APP_DEBUG), livewire-app. Layouts: @PwaHead in head partials, @RegisterServiceWorkerScript before body close.
- **NOTIFICATIONS.md:** Notification triggers, channels, config, safety (afterCommit).
- **CLAUDE.md / .cursor/rules:** Laravel Boost, Pint, Pest, Livewire, Tailwind, Flux conventions; test enforcement; MCP usage.
- **User audit timeline:** UserAuditTimelineService (merged timeline, non-financial system_events only); TimelineEntryDTO; type/date filters; index-safe queries. Operational intelligence: OperationalIntelligenceService + config/operational_intelligence.php; anomaly detection in afterCommit only; no ledger mutation.

---

## 17. Goals & current priority

- Financial hardening and **ledger + event mirror** in place: purchase idempotency (`purchase:order:{id}`), platform wallet locked in profit:settle, wallet locked in reconcile, atomic topup via CreateTopupRequestAction, refund hard check (no double-post for same reference). **System events:** one financial system_event per balance mutation; recorded only on real state transition (no record on early return); insert-only; broadcast via DB::afterCommit; admin timeline prepends on new event (no full refresh). **User audit timeline:** unified per-user timeline (wallet_transactions + non-financial system_events + orders + fulfillments); financial truth from ledger only; type/date filters; configurable. **Operational intelligence:** anomaly detection (velocity, refund abuse, fulfillment failure, reconciliation drift) via OperationalIntelligenceService; configurable thresholds; runs in DB::afterCommit only; never blocks financial flow. Continue to maintain: atomic ops, no drift, reconciliation, profit from settlement only; ledger as sole source of truth.
- Bug and push observability are now first-class operational capabilities: permission-gated bug reporting/inbox, trace correlation across notifications/bugs/push logs, realtime bug inbox updates, push dedup hardening, and invalid-token cleanup.
- **Custom amount (metered-style) products:** Admin-configurable bounds and unit label; storefront buy-now + cart + checkout with server-side validation; order snapshot (**pricing_meta**); single fulfillment per line with **custom_amount** meta for operations. Migrations: `2026_03_27_120000_add_custom_amount_fields_to_products_table`, `2026_03_27_120100_add_custom_amount_fields_to_order_items_table`, `2026_03_28_183214_widen_entry_price_precision_on_products_table`.
- **Fulfillment operations hardening:** Claim/unclaimed supervisor workflow is first-class (ownership via `claimed_by`, task cap, scoped queues, realtime claim events, and policy-enforced ownership semantics for updates/actions). Migration: `2026_03_30_154826_add_claim_columns_to_fulfillments_table`.
- Keep: Fast, lightweight UI; minimal chatty Livewire; clear separation between server state and Alpine UI state.

---

## 18. Quick reference — routes (public / auth / backend)

- **Public:** `/` (home), `/cart`, `/contact`, `/404`, `language/{locale}`.
- **Auth + verified:** `/profile`, `/profile/edit`, `/wallet`, `/loyalty`, `/orders`, `/orders/{order_number}`, `/notifications`, `/topup-proofs/{proof}`.
- **Backend (auth + verified + backend):** `/dashboard`, `/categories`, `/packages`, `/products`, `/pricing-rules`, `/loyalty-tiers`, `/admin/orders`, `/admin/orders/{order}`, `/admin/activities`, `/admin/system-events`, `/admin/users`, `/admin/users/{user}`, `/admin/users/{user}/audit`, `/fulfillments`, `/refunds`, `/topups`, `/customer-funds`, `/settlements`, `/admin/notifications`, `/admin/bugs`, `/admin/bugs/{bug}`. **Admin-only:** `/admin/website-settings`.
- **Settings:** `/settings`, `/settings/profile`, `/settings/password`, `/settings/appearance`, two-factor route by Fortify.
