## Logging Map

### Payments
| Event | Logged in | Subject | Key properties |
| --- | --- | --- | --- |
| `topup.requested` | `resources/views/pages/frontend/⚡wallet.blade.php` | `TopupRequest` | `topup_request_id`, `wallet_id`, `user_id`, `amount`, `currency`, `method` |
| `topup.approved` | `app/Actions/Topups/ApproveTopupRequest.php` | `TopupRequest` | `topup_request_id`, `wallet_id`, `user_id`, `amount`, `currency`, `transaction_id` |
| `topup.rejected` | `app/Actions/Topups/RejectTopupRequest.php` | `TopupRequest` | `topup_request_id`, `wallet_id`, `user_id`, `amount`, `currency`, `transaction_id` |
| `wallet.credited` | `ApproveTopupRequest`, `ApproveRefundRequest` | `Wallet` | `wallet_id`, `user_id`, `amount`, `currency`, `transaction_id`, `source` |
| `wallet.debited` | `app/Actions/Orders/PayOrderWithWallet.php` | `WalletTransaction` | `wallet_id`, `order_id`, `transaction_id`, `amount`, `currency`, `direction` |
| `wallet.reconciled` | `app/Console/Commands/WalletReconcile.php` | `Wallet` | `wallet_id`, `user_id`, `stored_balance`, `expected_balance`, `diff` |
| `refund.requested` | `app/Actions/Orders/RefundOrderItem.php` | `WalletTransaction` | `transaction_id`, `order_id`, `order_item_id`, `fulfillment_id`, `wallet_id`, `amount`, `currency`, `note` |
| `refund.approved` | `app/Actions/Refunds/ApproveRefundRequest.php` | `WalletTransaction` | `transaction_id`, `idempotency_key`, `order_id`, `wallet_id`, `amount`, `currency`, `reason` |
| `refund.rejected` | `app/Actions/Refunds/RejectRefundRequest.php` | `WalletTransaction` | `transaction_id`, `order_id`, `order_item_id`, `fulfillment_id`, `amount`, `currency` |

### Orders
| Event | Logged in | Subject | Key properties |
| --- | --- | --- | --- |
| `order.created` | `app/Actions/Orders/CreateOrderFromCartPayload.php` | `Order` | `order_id`, `order_number`, `item_count`, `subtotal`, `fee`, `total`, `currency`, `status_to` |
| `order.paid` | `app/Actions/Orders/PayOrderWithWallet.php` | `Order` | `order_id`, `order_number`, `amount`, `currency`, `status_from`, `status_to`, `transaction_id`, `wallet_id` |
| `order.refunded` | `app/Actions/Refunds/ApproveRefundRequest.php` | `Order` | `order_id`, `order_number`, `status_from`, `status_to`, `amount`, `currency`, `transaction_id` |

### Fulfillment
| Event | Logged in | Subject | Key properties |
| --- | --- | --- | --- |
| `fulfillment.queued` | `app/Actions/Fulfillments/CreateFulfillmentsForOrder.php` | `Fulfillment` | `fulfillment_id`, `order_id`, `order_item_id`, `provider`, `status_to` |
| `fulfillment.processing` | `app/Actions/Fulfillments/StartFulfillment.php` | `Fulfillment` | `fulfillment_id`, `order_id`, `order_item_id`, `provider`, `status_from`, `status_to`, `attempts`, `actor`, `actor_id` |
| `fulfillment.completed` | `app/Actions/Fulfillments/CompleteFulfillment.php` | `Fulfillment` | `fulfillment_id`, `order_id`, `order_item_id`, `provider`, `status_from`, `status_to`, `actor`, `actor_id` |
| `fulfillment.failed` | `app/Actions/Fulfillments/FailFulfillment.php` | `Fulfillment` | `fulfillment_id`, `order_id`, `order_item_id`, `provider`, `status_from`, `status_to`, `reason`, `actor`, `actor_id` |
| `fulfillment.retry_requested` | `app/Actions/Fulfillments/RetryFulfillment.php` | `Fulfillment` | `fulfillment_id`, `order_id`, `order_item_id`, `status_from`, `status_to`, `retry_count`, `actor`, `actor_id` |
| `fulfillment.process_failed` | `app/Console/Commands/ProcessFulfillments.php` | `Fulfillment` | `fulfillment_id`, `order_id`, `order_item_id`, `provider`, `error` |

### Admin & Security
| Event | Logged in | Subject | Key properties |
| --- | --- | --- | --- |
| `admin.login` | `app/Actions/Fortify/LoginResponse.php` | `User` | `user_id`, `email` |
| `admin.logout` | `app/Providers/AppServiceProvider.php` | `User` | `user_id`, `email` |
| `user.login` | `app/Actions/Fortify/LoginResponse.php` | `User` | `user_id`, `email` |
| `user.logout` | `app/Providers/AppServiceProvider.php` | `User` | `user_id`, `email` |
| `user.registered` | `app/Actions/Fortify/CreateNewUser.php` | `User` | `user_id`, `email`, `username`, `country_code`, `timezone` |
| `category.deleted` | `app/Actions/Categories/DeleteCategoryTree.php` | `Category` | `root_category_id`, `deleted_count`, `deleted_ids` |
| `product.deleted` | `app/Actions/Products/DeleteProduct.php` | `Product` | `product_id`, `name`, `package_id` |
| `package.deleted` | `app/Actions/Packages/DeletePackage.php` | `Package` | `package_id`, `name`, `category_id` |
