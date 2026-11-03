# Developing Karmantelekom to Systems Manager

## Overview
- ### *Phase 1:*
  - Internal Management System for KarmanTelekom Phase 1 delivers an internal system built to run the day-to-day operations of KarmanTelekom’s two stores.
  - ### Core objectives 
    - **Per-store separation.** Each store has its own inventory, cash register, and user permissions, so staff only see and do what their role at their store allows.
    - **Unified management.** The owner can manage both stores from a single dashboard, with a consolidated view across locations.
    - **Built to grow.** The architecture should allow the system to later serve other retail shops on a subscription basis. This is a design constraint only: Phase 1 does not include building a full multi-tenant SaaS platform (billing, onboarding, tenant self-service, etc.).
  - ### Top priority: financial and stock integrity
    - Accuracy of sales, inventory, profit calculations, and customer debts comes before everything else, including features, speed of delivery, and UI polish. Errors in these areas translate directly into real financial loss, so every design and implementation decision should favor correctness, auditability, and safe failure over convenience.


### Features:
- Dashboard that can manage and see all stores.
- Products We need to design a new Product Model to be flexible the necessary results are "it is not important what if it is a phone store or a clothes store the product model will match and serve them ."
- Brands and Suppliers.
- Stock: Products display, quantities, total cost ...etc
- Cashier POS
- Secure and safe Sales Records page.
- Debts and Debtors
- Purchase invoices: Invoices that are related to the suppliers and the purchase information
- Stock Database (Container): when the store manager is going to add a new product to his shop, the first thing is going to enter is the serial number and the system will bring the product information if found it in the container.
  - every a new product (created for the first time and not exists in the container) will be created in the container.
- Expenses Manager: this section will record the store and users expenses.
- Revenue Section: record revenues from the sales.
- The Safe
- Role and Permissions manger
- Store Page for every branch.
- 
