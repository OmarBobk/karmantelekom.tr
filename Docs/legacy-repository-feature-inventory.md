# Legacy Repository Feature Inventory

## 1. Purpose

This document inventories the features found in the legacy Laravel repository:

- Repository: `OmarBobk/systems_management`
- Target project: `karmantelekom.tr`
- Business: Karman Telekom phone stores
- Initial deployment: Internal business management for two stores
- Long-term direction: Architecture that can support additional stores and potentially other businesses in the future.

The legacy repository is a source of existing features, business rules, and implementation ideas. It is not automatically considered production-ready.

The new system must be designed around the actual requirements of Karman Telekom rather than copying the legacy implementation without review.

## 2. Evidence and Status Rules

Use the following status labels throughout this document:

- `FOUND_IN_CODE`: Relevant code or model exists in the inspected repository.
- `DOCUMENTED_ONLY`: Mentioned in project documentation, but implementation has not been verified.
- `PARTIALLY_IMPLEMENTED`: Some functionality exists, but the complete business workflow has not been verified.
- `NEEDS_REVIEW`: The feature exists, but its correctness, security, or completeness requires further investigation.
- `NOT_VERIFIED`: There is not enough evidence to determine whether the feature exists.
- `NEW_REQUIREMENT`: Required for Karman Telekom but not established as implemented in the legacy repository.
- `NOT_REQUIRED_FOR_MVP`: Not needed for the first usable release unless business requirements change.

A page, route, model, or component does not prove that a complete business workflow is implemented correctly.

## 3. Existing Feature Inventory

### 3.1 Dashboard and Administration

Observed functionality:

- Administrative dashboard.
- Product and category management routes.
- Product database section.
- User management and role/permission documentation.
- Routes for cashier, sales, debts, suppliers, expenses, income, invoices, and contributions.

Status: `FOUND_IN_CODE` for the main sections; individual workflows require testing.

Target decision:

- Keep the concept of a unified administration dashboard.
- Design navigation around the daily needs of the two stores.
- Show financial and inventory figures only to authorized users.
- Support store-specific filtering and consolidated reporting for authorized managers.

### 3.2 Product Catalog

Observed product fields and relationships include:

- Product name.
- Description.
- Product code.
- Quantity.
- Cost.
- Selling price.
- Serial number.
- Category.
- Brand.
- Supplier.
- Purchase bill.
- User who created the record.
- Product images.
- Flexible extra attributes, including color and size-related data.

Relevant legacy model: `app/Models/Product.php`.

Status: `FOUND_IN_CODE`, with validation and data-model concerns requiring review.

Target decision:

Retain the useful catalog concepts, but define separate concepts for:

1. Product model or type.
2. Stock quantity for ordinary quantity-based products.
3. Individually tracked devices such as used phones.
4. Product variants where applicable.

A product model such as "iPhone 13, 128 GB" must not be confused with a particular physical phone.

### 3.3 Categories and Brands

Observed functionality:

- Category management.
- Brand relationships.
- Product-to-category relationships.
- Product-to-brand relationships.

Status: `FOUND_IN_CODE`.

Target decision:

Retain these features. Categories and brands should be reusable across both stores, subject to the chosen product catalog design.

### 3.4 Inventory

Observed functionality:

- Product quantities.
- Product cost and selling price.
- Inventory summaries based on product quantities and costs.
- Product updates through Livewire components.
- Sales counters.
- Product-container-related components.

Status: `PARTIALLY_IMPLEMENTED` and `NEEDS_REVIEW`.

Important limitations:

- The legacy cashier changes product quantities while adding products to a cart.
- The sale-creation flow also decrements quantities.
- The inspected cashier uses a fixed cart identifier.
- Safe behavior under concurrent sales has not been established.
- Store-level stock separation has not been established.

Target decision:

Build inventory around explicit stock movements.

Required future capabilities:

- Stock received from purchases.
- Stock sold.
- Customer returns.
- Supplier returns, if required.
- Stock adjustments with reasons.
- Transfers between stores.
- Stock counts and reconciliation.
- An audit trail for stock-changing operations.

The inventory balance must be derived from controlled business operations rather than arbitrary edits.

### 3.5 Point of Sale (POS / Cashier)

Observed functionality:

- Add products to a cart.
- Store product details and prices in cart items.
- Track quantities.
- Calculate line totals and profits.
- Complete cash sales.
- Start a debt-based checkout flow.
- Create sale records.
- Update sales counters.
- Create related income records.

Relevant legacy component: `app/Http/Livewire/Backend/CashierComponent.php`.

Status: `FOUND_IN_CODE`, but the checkout workflow is `NEEDS_REVIEW`.

Important legacy concerns:

- Cart isolation is not suitable for multiple independent employees and stores.
- Inventory is decremented during cart operations and again during sale creation.
- Sale identifiers are manually calculated in the inspected flow.
- The inspected sale creation does not clearly wrap all related writes in one database transaction.
- Payment handling has not been established for cash, card, and mixed payments.
- Cancellation and failure recovery require review.

Target decision:

Retain the POS feature concept, but implement a reliable checkout workflow for Karman Telekom.

The new POS must eventually support:

- Product search and barcode entry, if required.
- Cart quantity changes.
- Discounts subject to permissions.
- Cash payment.
- Card payment.
- Mixed cash and card payment.
- Customer-linked sales where needed.
- Debt-based sales.
- Sale receipts.
- Returns and cancellations.
- Employee attribution.
- Store attribution.
- Correct stock and financial records.

All critical checkout operations must succeed together or roll back together.

### 3.6 Sales History

Observed functionality:

- Sales listing.
- Date filtering.
- Sorting by date, price, identifier, and profit.
- Editing sales.
- Deleting sales.

Relevant legacy component: `app/Http/Livewire/Backend/SaleComponent.php`.

Status: `FOUND_IN_CODE`, with financial-integrity concerns.

Target decision:

Retain the sales-history and reporting concepts.

Do not copy unrestricted sale editing or direct deletion into the new system.

Corrections should use controlled cancellation, return, or adjustment workflows that preserve an audit trail.

### 3.7 Customers and Debts

Observed functionality:

- Debtor records.
- Customer name and phone information.
- Debt records associated with customers.
- A distinction between paid and unpaid debt records.
- A cashier flow for debt-based sales.

Relevant legacy models include `Debtor` and `Debt`.

Status: `PARTIALLY_IMPLEMENTED`.

Needs further verification:

- Recording partial payments.
- Recording multiple payments against one debt.
- Due-date management.
- Customer account statements.
- Overdue debt reporting.
- Refunds and debt corrections.
- Store attribution.
- Audit trail.

Target decision:

Retain customer debt management and design a complete payment and collection workflow.

### 3.8 Suppliers and Purchasing

Observed functionality:

- Supplier records.
- Supplier-to-brand relationships.
- Purchase bill model.
- Bill-to-supplier relationships.
- Product relationships to purchase bills.
- Product-container-related management components.

Relevant legacy areas include `Seller`, `Bill`, and the product and container components.

Status: `PARTIALLY_IMPLEMENTED`.

Needs further verification:

- Purchase invoice totals.
- Receiving stock against a purchase.
- Partial deliveries.
- Purchase returns.
- Supplier balances and outstanding payments.
- Cost allocation.
- Store-specific receiving.
- Purchase correction and cancellation.

Target decision:

Retain supplier and purchasing concepts, but define purchasing as a complete workflow from supplier invoice to received stock and recorded payment or outstanding balance.

### 3.9 Invoices

Observed functionality:

- Invoice model.
- Invoice customer relationship.
- Invoice records.
- Invoice totals based on record prices and costs.
- Invoice-related sales relationships.
- Invoice management route and PDF-export route.

Status: `FOUND_IN_CODE` for the core structures; business purpose and complete behavior need verification.

Important distinction:

The legacy project contains invoice-related models, but we must determine which represent supplier purchases, customer invoices, or another workflow before mapping them into the new system.

Target decision:

Define separate, unambiguous document types for purchasing and customer sales if both are required.

### 3.10 Expenses and Income

Observed functionality:

- Expense records.
- Income records.
- Relationships to users.
- Relationships to sales, bills, or contributions in the relevant models.

Status: `FOUND_IN_CODE`, but the complete accounting workflow is not verified.

Target decision:

Retain expense tracking and financial reporting concepts.

For the new system, define how sales, payments, refunds, expenses, and transfers affect the financial records. Avoid counting the same transaction more than once.

### 3.11 Cashbox

Observed functionality:

- A cashbox page/component.
- Documentation mentioning money in the box, income, and outgoing money.

Status: `DOCUMENTED_ONLY` for some expected capabilities; full cashbox operations are not established.

Target decision:

Implement a complete cashbox workflow if required by the business:

- Opening balance.
- Cash received.
- Cash paid out.
- Cash expenses.
- Cash withdrawals and deposits.
- Expected balance.
- Physical cash count.
- Closing balance.
- Difference and reconciliation.
- Employee and store attribution.
- Audit trail.

Card payments must not be treated as physical cash.

### 3.12 Contributions and Profit Allocation

Observed functionality:

- Contributor records.
- Contribution records.
- Profit and cost relationships.
- Contribution-related expense records.
- Profit allocation logic in the cashier workflow.

Status: `FOUND_IN_CODE`, but the accounting rules require review.

Target decision:

First confirm whether Karman Telekom needs partner/investor accounting in the new application.

If it does, document the exact rules for capital, profit share, expenses, withdrawals, and historical balances before implementing the feature.

Do not copy legacy calculations without validating the intended business rules.

### 3.13 Users, Roles, and Permissions

Observed documentation describes roles such as:

- Superadmin.
- Supervisor.
- User.

Documented permissions cover products, categories, brands, suppliers, users, roles, and permissions.

Status: `DOCUMENTED_ONLY` for some role definitions and `NEEDS_REVIEW` for enforcement.

Target decision:

Design explicit permissions for:

- Viewing and editing products.
- Managing purchases.
- Performing sales.
- Applying discounts.
- Processing returns.
- Recording expenses.
- Viewing and collecting debts.
- Opening and closing cashboxes.
- Viewing profits and financial reports.
- Managing employees and access.
- Managing store configuration.

Access control must be enforced server-side.

The new system must distinguish between permissions to access a feature and permissions to access data belonging to a particular store.

### 3.14 Public Storefront and API

Observed legacy storefront routes and documentation refer to:

- Home page.
- Shop page.
- Product pages.
- Slider/banner content.
- Contact and FAQ pages.
- Login and registration.
- Cart.
- API routes for authentication, products, categories, brands, and suppliers.

Status: `FOUND_IN_CODE` for parts of the implementation; complete storefront behavior and security require review.

Target decision:

Treat the public storefront as a separate product decision.

Do not let public e-commerce requirements delay the internal POS and inventory system unless the current business specifically needs online ordering.

### 3.15 Database Backup

Observed functionality:

- A scheduled database-backup command.
- SQL dump generation.
- Upload of the dump to an external service.

Status: `FOUND_IN_CODE`, with security and operational concerns.

Target decision:

Implement protected automated backups, including database and uploaded files, using secrets stored outside source code.

Backups must be retained securely, and restoration must be tested.

Do not expose database credentials, personal access tokens, webhook URLs, or other secrets in source code or documentation.

## 4. Important Features Not Yet Established

The following capabilities are required or potentially valuable for Karman Telekom, but their complete implementation was not established in the inspected legacy files.

### 4.1 Multi-Store Management

- One administration panel for both stores.
- Separate stock per store.
- Separate cashbox per store.
- Store-specific purchases and sales.
- Store-specific employee permissions.
- Transfers between stores.
- Consolidated reporting for authorized managers.

Status: `NEW_REQUIREMENT` unless later inspection finds existing implementation.

### 4.2 Individually Tracked Used Phones

Each physical used phone should have its own record, including, as applicable:

- IMEI and/or serial number.
- Brand and model.
- Storage and color.
- Condition.
- Purchase date.
- Seller or previous owner.
- Purchase cost.
- Repair and preparation costs.
- Photos and inspection notes.
- Warranty information.
- Current store and status.
- Sale price.
- Sale date.
- Profit.

Status: `NEW_REQUIREMENT` pending further repository inspection.

### 4.3 Repair Management

Potential requirements:

- Repair orders.
- Customer and device details.
- Reported problem.
- Technician assignment.
- Status tracking.
- Parts consumed.
- Labor cost.
- Customer charges.
- Warranty on repair.
- Payment and collection status.

Status: `NEW_REQUIREMENT` unless later inspection identifies an existing implementation.

### 4.4 Payment Methods and Reconciliation

The new system needs explicit support for the payment methods actually used by Karman Telekom.

Potential requirements:

- Cash.
- Card.
- Mixed payments.
- Partial payments.
- Refunds.
- Debt collection.
- Payment-method reporting.

Status: `NEW_REQUIREMENT` until verified and approved.

### 4.5 Returns and Audit Trail

Potential requirements:

- Customer returns.
- Sale cancellation.
- Stock correction.
- Purchase correction.
- Cashbox reconciliation.
- Recorded reason and responsible employee.
- Historical records that cannot be silently erased.

Status: `NEW_REQUIREMENT` or `NEEDS_REVIEW`, depending on the feature.

## 5. Legacy Technical Risks to Resolve Before Reuse

The inspected repository contains concerns that must be addressed before code is reused in production:

1. Fixed cart identifier in the cashier flow.
2. Stock decrement during cart operations and again during sale creation.
3. Manual sale identifier generation.
4. Financial writes without clearly established transaction boundaries in the inspected checkout code.
5. Direct sale editing and deletion.
6. Unrestricted model mass assignment in several inspected models.
7. API routes whose authentication and authorization behavior requires verification.
8. A diagnostic API route that exposes the submitted bearer token through a debugging response.
9. A backup command containing an external webhook URL directly in source code.
10. Legacy Laravel and Livewire dependencies requiring compatibility and security review.
11. Insufficient evidence of automated tests for core financial and inventory workflows.
12. No established evidence yet of complete branch isolation.

These findings are reasons to audit and selectively reuse the repository, not reasons to discard every existing feature.

## 6. Recommended Reuse Strategy

### Reuse the Business Concepts

- Product catalog.
- Categories and brands.
- Supplier records.
- Purchasing and invoice concepts.
- POS and sales history.
- Customer debt management.
- Expenses and income.
- User roles and permissions.
- Financial reporting concepts.

### Reuse Only After Refactoring

- Checkout and stock management.
- Sales and profit calculations.
- Invoice workflows.
- Cashbox and financial records.
- Contribution and profit allocation.
- Authentication and authorization.
- API endpoints.
- Backup implementation.

### Design Specifically for Karman Telekom

- Multi-store architecture.
- Per-store inventory.
- Per-store cashbox and reporting.
- Used-device lifecycle tracking.
- Repair workflow, if required.
- Payment reconciliation.
- Store transfers.
- Audit trail.
- Secure backup and recovery.

## 7. Scope of This Document

This document is a feature inventory, not the final product specification.

The next document should define:

- Approved business requirements.
- User roles and permissions.
- Store isolation rules.
- Data entities and relationships.
- Business workflows.
- Financial calculation rules.
- MVP scope.
- Acceptance criteria.
- Testing requirements.
- Implementation milestones.

Cursor must not treat a feature listed here as approved for implementation until its requirements and acceptance criteria are defined in the canonical project specification.
