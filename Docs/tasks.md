# Main Tasks for the Project
- Step 1:
  - the Shop Owners should be able to browse the products.
  - the shop owners should be able to placce an order.
  - salespersons should be able to see products and markup.
  - admin should be able to manage the products and orders.
- Step 2: Add 10-15 products
  - Categories:


### Frontend ###
- Place the announcement at the top of the main page.
- Design the marquee using https://magicui.design/docs/tailwind-v4.
- Put this code in the footer : "<x-language-selector :currentLanguage="$currentLanguage" />"


### TODO Backend:
- Register process.
- **DONE** Every Product should have two prices [Wholesale, Retail].
- **DONE** add is_retail_active and is_wholesale_active to product table.
- **DONE** if a user wrote the wholesale TRY Price automatically, USD Price will be calculated and the opposite is the same.
- Suppliers.
- Categories CURD.
- sections-component:
    - **DONE** in the edit modal in the search product field, it should be rested when the modal is closed.
    - **DONE** in the edit modal in the product table when use reorder the product X to order 1 now all the products should be reordered.
    - **DONE** make an enum for the section positions.
- **DONE** Discuss: should we create two section types ? retail and wholesale?
- Ordering System.
- Settings.
- Analytics.
- Add products to the cart
- Shopping Cart:
    - #Bugg: when a shop owner is not logged in, he is able to add a retail products to the cart
    - and after that, lets say that he logged in (now he doesn't have the right privileges to add retail products) he
    - still able to see the retail products that are in the shopping cart (he added earlier when he was not logged in).
- Create an announcement Manager.
- Create a marquee.
- Update the analytics id.

# When to Sync Cart Data.
    - ###DONE### User Logs In:
        - Why: To merge guest cart (from localStorage) into the user’s stored cart.
        - What to Do:
            - If local cart has items → sync them to DB.
            - Merge with DB cart if exists (handle duplicates or quantity summing).
            - Remove retail items if user is shop_owner or salesperson.
    - ###DONE User Proceeds to Checkout:
        - Why: To ensure the cart is stored in DB and validated (e.g., stock check).
        - What to Do: Push latest cart from localStorage to DB before checkout begins.
    - ###DONE  User Navigates Away (Optional)
        - Why: A safety net to persist cart data before the tab closes or navigates.
        - How: Use window.addEventListener('beforeunload').
    - ###DONE When User Logs Out
        - Why: To clear or reset sync flags (syncedUserId) to avoid stale data on next login.
        - What to Do: Clear sync flags and optionally clear cart (based on use case).
    - ###DONE When Logged-In User Changes Device
        - Why: User opens site on a new device; need to load their cart from DB.
        - What to Do: Pull cart from DB and push it into localStorage.
