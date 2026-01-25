#1
- lang is ar user logout -> automatically lang is en 



udpate the category to be: 
#DONE: if user delete some category automatically  the child categories will be deleted
#DONE: categories table: user should be able to toggle the category status.
#DONE: create category form: the placeholder of the order field should be the highest and the smallest category order that exists in db
#DONE: order should be unique.




scenario mapped to tables

- User adds Google Play 100$ to cart → carts, cart_items
- User logs in → cart attaches to user_id (shopping cart will not be stored in the db it is handled by alpinejs)
- Checkout sees total = 105$
- User goes to profile → selects payment method → creates topup_requests (105$)
- User sends dekont on WhatsApp → you attach proof → topup_proofs
- You approve → create wallet_transaction credit (posted) + update wallets.balance
- User sees balance 105$
- User clicks “complete checkout” → create wallet_transaction debit referencing order_id + mark order paid
- Fulfillment runs → fulfillments + logs
