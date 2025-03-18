### Change the routes:
- http://sub.dev.store ---> dashboard
- http://sub.dev.store/dashboard ---> nothing
- http://dev.store/dashboard ---> nothing


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


### TODO Frontend
- show the tags on the product card Frontend.


#testing the shopping cart process
- when cu
