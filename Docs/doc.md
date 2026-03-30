#1
- lang is ar user logout -> automatically lang is en 


#BUG
- ###DONE if a non-admin user hit /dashboard he will see forbidden but he should be redirected to 404 error page
- ###DONE If user click on the logo on the main page it should redirect him to home page
- ###DONE after login / register user should be redirected to home page not dashboard
- ###DONE packages/index -> create form : when there is an error and the language is arabic the field name is displaying in English instead in arabic
- ###DONE Design the 404 error page.
- ###DONE Don't let user to submit a new top-up request if he has already appending one
- ###DONE wallet balance should be displayed next to the shopping cart icon.
- ###DONE /wallet index is not responsive for the mobile screen
- ###DONE dashboard sidebar has a lot of links so group them and search online to get a better naming for the groups
- ###DONE when displaying products on the frontend, there should be next to "add to cart" button another "Buy now" button which is going to open a model to record the order info like the package requirements and the quantity and checkout
- ###DONE /checkout add the package requirements and fill it to the order
- ###DONE Main Page Design: turn the user menu into links and display them in the navbar
- ###DONE create log on register new user
- ###DONE "Buy now" after user click "pay now" button the modal should be closed and the successfull message will be appeared as a notification and
- ###DONE Topups when customer requesting topups he need to upload a proof which is an image.
- ###DONE Topups when admin approving topups he should scan the proof first
- ###DONE Activities when admin is login there is two logs registering
- ###DONE Refund when admin is mark a fulfillment as failed the customer will see two buttons next to his order item failed status "refund" or "retry" and customer is allowed to ask for retry "two times only"
- ###DONE /categories, /packages, /products filters should be hidden by default
- ###DONE Fulfillments: fulfillment details -> Order details: should contain the price (prefer to get the price from the transaction) + username (instead of email) and on the fulfillments details modal I want you to display also the Delivered payload
- ###DONE Fulfillments: when admin mark a fulfillment as faild and does not refund it and does not mark it as a retry customer should see two buttons "refund" and "retry" if customer click on refund admin will see a new refund request on the refunds page and the fulfillment will be marked as "refund requested" if admin accept the refund the fulfillment should be marked as "refunded" if customer click retry fulfillment should be marked as "retry requested"
- ###DONE fulfillments: when admin is marking a fulfillment as completed give him a toggle button that would automatically write DONE in the delivered payload if he checked it the 
- ###DONE fulfillments: fulfillment details there is no need to display the quantity and the total price
- ###DONE when a customer is incrementing the products that are in the shopping cart, the dropdown not the page the dropdown will immediately closed
- Backend:
  - ###DONE Users Manager
  - ###DONE Notifications with Laravel Verb
  - ###DONE if admin is on the fulfillment page and a new fulfillment came, admin should refresh the page so he can see the new fulfillment now how to fix this behavior
  - ###DONE Activities: The table header should be sticky
  - ###DONE: Activities: don't use "User updated by admin" description use a detailed description so we can understand what happened exactly, and properties column should not be empty it should display the updated property.
  - ###DONE: Fulfillments: when the fulfillment status is "Failed Refunded"
  - ###DONE: on the sidebar toggle button should appear the badge count
  - ###DONE: Fulfillments: the requirements should be displayed instead the provider column.
  - ###DONE: Right now there are general price rules that are applied, this should be the default, but admin should be able to apply a different price  for a certain user.
  - ###DONE: Users Manager: Translate the roles to arabic.
  - ###DONE: ###MAJOR### PWA erag/laravel-pwa
    - ###DONE: Stop rotating screen on phone.
    - ###DONE: PWA application button should only appear if the user has permission for install_pwa_app
  - ###DONE: ###MAJOR### Record bug system.
  - ###TODO: Contactus: the messages that come from this form where we should handle them.
  - ###TODO: ###MAJOR### Record website views and how many users are logged in and who are they
  - ###TODO: ###MAJOR### Users hierarchy. 
  - ###TODO:
  - 
- Frontend:
  - ###DONE wallet transaction in /wallets should be more described
  - ###DONE Wallet /wallet Request topups form borders remove the ring
  - ###DONE /orders Redesign
  - ###DONE Register form: mask the phone number field
  - ###DONE: Topups: when customer want to request a new topup he should see a toggle button if checked it then he need to upload the proof file if not then he can request the topup without uploading the proof










**🔒 Ticket → Audit → Fix → Lock**


You a senior prompt Generator with 20+ years exp
first you can scan the uploaded file to understand my system.
second lets go Ask → Plan → Agent → Review → Fix to Make Cursor implements this feature as a senior Laravel 12, Livewire 4, Tailwind and Alpinejs
now sometime cursor is overengineering so tell him what you to tell to not do that.
and by there are a lot of places where there a better path for performance for high quality code and fast and even best practices that cursor doesn't take let give you an example 
"that day we where had a unit price and an amount field and what we need is when user type in the amount at the sametime he should  the Estimated Total which is (unit price * amount)
there is a lot of choices to make it happened but what cursor did he used wire:model.live so when ever user change the amount the estimated will be updated
now Cursor did give the right result but the most expensive way I mean when ever user change the amount livewire will resend a sup-request
while we can get the unit price in one request and do the math and display the Estimated Total using alpine and this way would cost us only one request.
Now I want Cursor to do it like that take the best approach in everything code readability, maintance, high quality, better performance and all what Expert Developer are care about
"

1. Ask → understand system
2. Ask (Plan Mode) → generate implementation plan
3. You refine the plan
4. Agent → implement
5. Review → fix issues



General Engineering Rules:

* Prefer SIMPLE over FLEXIBLE
* Prefer CLEAR over ABSTRACT
* Prefer LOCAL logic over GLOBAL systems
* Avoid premature optimization patterns (queues, events, microservices)
* Avoid unnecessary layers (Repositories, Services if Action is enough)

Frontend:

* Use Alpine for calculations, UI state, instant updates
* Avoid Livewire reactivity for simple UI interactions

Backend:

* Use Actions directly
* Avoid creating extra classes without strong reason

Golden Rule:
If a feature can be built in 1 simple way, DO NOT build 3 "future-proof" ways
