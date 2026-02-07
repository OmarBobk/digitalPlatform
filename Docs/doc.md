#1
- lang is ar user logout -> automatically lang is en 


#BUG
- ###DONE if a non-admin user hit /admin he will see forbidden but he should be redirected to 404 error page
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
  - Notifications with Laravel Verb
- Frontend:
  - wallet transaction in /wallets should be more described
  - Wallet /wallet Request topups form borders remove the ring
  - ###DONE /orders Redesign
