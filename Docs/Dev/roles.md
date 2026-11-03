### User Roles
- Admin
  - can see both retail and both type products in retail price.
- Salesperson (Belong To US)
  - can see both retail and both type products in retail price.
- Customer Salesperson
  - Can see the catalog and the customer permissions.
- Shop Owner
  - if a user hits https://sub.app.com, they should be redirected to 404 Error Page.
  - can see both retail and both type products in retail price
- Customer.
  - can see only retail products
- Unauthorized
  - if a user hits https://sub.app.com, they should be redirected to 404 Error Page.


### New Update
- Create "customer_salesperson" with "view_catalog" permission


- Create a table called wholesale_products:
  - product_id
  - min_qty
  - max_qty
  - price
  - currency_id
- Create CRUD in the backend to manage the products
- show them in the frontend catalog
- 
