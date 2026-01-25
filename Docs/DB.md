- users
  - name
  - email
  - email_verified_at
  - password
  - username
  - is_active
  - blocked_at
  - last_login_at
  - timezone
  - meta
  - profile_photo
  - phone
  - country_code
- categories (subscriptions, games)
  - id
  - parent_id (nullable)
  - name
  - slug
  - order
  - icon
  - is_active
  - image
- packages (Pubg, tiktok)
  - id
  - category_id
  - name
  - slug
  - description
  - is_active
  - order
  - icon
- products
  - id
  - package_id
  - name
  - retail_price
  - wholesale_price
  - is_active
  - order
- package_requirements
  - id
  - package_id
  - key (player_id, username, phone)
  - label ("Player ID")
  - type (enum: string, number, select)
  - is_required
  - validation_rules (string nullable) (Laravel-style: required|numeric)
  - order

Create The Wallets Model and wallet transactions model with these data:
- wallets:
  - user_id
  - balance (default 0)
  - timestamps
- wallet_transactions
  - wallet_id
  - type enum: purchase, topup, refund, adjustment.
  - direction enum: cash, debit
  - amount
  - status enum: pending, approved, rejected
  - reference_type nullable (polymorphic string: Order, TopupRequest, etc.)
  - reference_id nullable (bigint)
  - meta json nullable (notes, admin_id, etc.)

Products belongs to package so create the related methods in the model

act like a senior designer and design the Products manager page to manage the products using Laravel, Livewire, alpinjs, Tailwind and flux best practices.
the style and colors should match and follow the design pattern and the general colors that has been used before and don't forget to use the same colors
pay attention for the ui / ux princeples and high performance and quality the page speed should be the light speed


the placeholder of the order field should be the higher and the smallest product order that is exist in db


until now I have created these tables: users, categories, packages, packages_requirements, products.
now I want your help to complete my database schema so I am going to tell you the senario first:
an user enter into the platform - browse it - now he would like to buy google play card the one of 100 $ -> he add it into the shopping 
