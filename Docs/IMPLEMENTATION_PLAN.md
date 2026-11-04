# Karman Telekom — Implementation Plan

## 1. Purpose

This document defines the implementation sequence for the Karman Telekom Systems Management Platform.

It translates the requirements in `docs/PROJECT_SPEC.md` into manageable development phases, deliverables, dependencies, verification steps, and acceptance criteria.

The purpose is not to implement every feature as quickly as possible. The purpose is to build a reliable system incrementally, with particular attention to financial integrity, inventory accuracy, store isolation, and maintainability.

## 2. Relationship to Other Documents

- `docs/PROJECT_SPEC.md` defines what the application must do and the business rules it must respect.
- `docs/IMPLEMENTATION_PLAN.md` defines the order of implementation and the conditions for completing each phase.
- `docs/legacy-repository-feature-inventory.md` records the features and implementation risks discovered in the legacy repository.
- Additional business-rule documents may be introduced when a workflow becomes too detailed for the main specification.

If implementation reveals a conflict or ambiguity in the specification, stop and document it. Do not silently invent a business rule.

## 3. Implementation Principles

### 3.1 Build in small, verifiable increments

Each task must have a clearly defined scope, relevant tests, and measurable acceptance criteria.

Do not ask an AI coding agent to implement an entire phase in one operation.

### 3.2 Protect financial and inventory integrity

Sales, payments, debts, stock movements, device status changes, and cashbox movements must remain consistent.

Related changes must be committed atomically where they represent one business transaction.

Concurrency, duplicate submissions, and partial failures must be considered explicitly.

### 3.3 Establish shared foundations before dependent features

Inventory, device tracking, permissions, and transaction history must be established before implementing workflows that depend on them.

Store Network must use the existing product and device records rather than introduce a separate inventory model.

### 3.4 Enforce authorization on the server

Store restrictions and permissions must be enforced in backend business operations, not only in navigation menus or frontend components.

### 3.5 Avoid premature SaaS architecture

The system must support both existing Karman Telekom stores and remain extensible.

Do not implement tenant registration, subscription billing, tenant self-service, or other full SaaS infrastructure in Phase 1.

### 3.6 Reuse legacy knowledge, not legacy assumptions

The legacy repository is a source of feature ideas and historical business rules.

Its implementation must be reviewed before reuse. Existing code must not be copied merely because a similar feature already exists.

### 3.7 Test behavior, not just pages

A page rendering successfully does not prove that the underlying business workflow is correct.

Acceptance must be based on expected database state, authorization, financial calculations, inventory movements, and failure behavior.

---

## 4. Phase 0 — Discovery and Project Baseline

### Objective

Understand the existing KarmanTelekom project and establish a reliable starting point before implementing new features.

### Tasks

1. Inspect the current repository structure.
2. Identify the framework, PHP version, database, frontend stack, authentication system, and existing dependencies.
3. Inspect existing routes, models, migrations, business services, authorization policies, and tests.
4. Identify existing functionality that should be preserved.
5. Identify incomplete features, technical debt, security concerns, and conflicting business logic.
6. Review the legacy `systems_management` repository as a reference.
7. Confirm the development, testing, and deployment environments.
8. Review the current `PROJECT_SPEC.md` and remove duplicate or conflicting requirements.
9. Create or update `docs/legacy-repository-feature-inventory.md`.
10. Record unresolved business decisions without blocking unrelated foundation work.
11. Establish a baseline test suite and code-quality checks.

### Deliverables

- Repository assessment.
- Confirmed technical stack.
- Legacy feature inventory.
- Initial domain model proposal.
- Initial risk register.
- Updated project specification.
- Working automated test environment.

### Acceptance Criteria

- The existing application can be run and tested using documented instructions.
- The current architecture and important dependencies are understood.
- Existing functionality and migration risks are documented.
- The owner has reviewed the proposed architecture and implementation sequence.
- No application-wide rewrite has been started without an approved plan.

### Gate

**Do not proceed to major implementation until the existing project and its current state are understood.**

---

## 5. Phase 1 — Application Foundation, Stores, Users, and Permissions

### Objective

Establish the security and organizational foundations used by every subsequent feature.

### Tasks

1. Configure authentication using the existing project conventions where appropriate.
2. Establish the initial roles and permission model.
3. Create or verify the store records and relationships.
4. Associate employees with their authorized stores.
5. Define owner access across both stores.
6. Establish server-side store-scoping rules.
7. Define audit-event conventions for important operations.
8. Create the initial application navigation and responsive layout.
9. Establish consistent validation, error handling, and testing conventions.

### Required Authorization Tests

- An owner can access both stores.
- An employee can access their assigned store.
- An employee cannot read another store's restricted records.
- Changing a URL or submitting a manipulated request does not bypass authorization.
- Unauthorized users cannot perform restricted operations.
- Permissions are enforced by backend policies or equivalent authorization mechanisms.

### Deliverables

- Working authentication.
- Store management foundation.
- Initial roles and permissions.
- Store-scoping conventions.
- Initial audit infrastructure.
- Tested application shell.

### Acceptance Criteria

All store-access tests pass, and the authorization pattern is reusable across inventory, purchasing, POS, finance, and Store Network.

### Approved Foundation Decisions

- Operational stores are modeled as `Store` (`stores`), separate from the existing B2B customer `Shop` records.
- Each store has a required, unique, stable `code`; names are required but not unique. Initial stores: `KT01` and `KT02`.
- The existing `admin` role represents the owner. Store management, including employee assignment, requires both the `admin` role and the `manage_stores` permission.
- Stores have no deletion or deactivation workflow yet.
- Internal staff use the `employee` role, which carries no operational permissions by default.
- An employee can be assigned to one or more stores (`store_user`). Only the owner manages assignments, and only users with the `employee` role can be assigned. Removing a membership remains possible after the user loses the role.
- Assignment grants store membership only; it does not grant inventory, sales, financial, or management permissions.
- The owner can access all stores without assignment.
- Assignment changes are recorded in the activity log (`store_assignment`) with the actor, target user, and store, atomically with the membership change. No-op requests are not logged.
- Existing `salesperson`, `shop_owner`, `customer`, and `Shop` behavior is unchanged. Spatie permission teams remain disabled.

---

## 6. Phase 2 — Product Catalog, Suppliers, and Inventory Foundations

### Objective

Create the product and inventory model on which purchasing, POS, used-device tracking, and Store Network will depend.

### Tasks

1. Define reusable product records.
2. Implement categories and brands.
3. Implement supplier records.
4. Define product attributes without assuming every product has the same fields.
5. Define inventory tracking modes:
    - Quantity-tracked products.
    - Serialized products.
    - Individually tracked used devices.
6. Separate product definitions from store-specific inventory.
7. Establish inventory movement records and references to originating transactions.
8. Implement product lookup by supported identifiers.
9. Define how duplicate catalog entries are detected.
10. Define the inventory cost-history approach.
11. Implement basic inventory views and authorized product-management operations.

### Design Requirements

- The same product definition can be used by both stores.
- Each store has its own inventory quantities and stock movements.
- Serialized devices have individual identities.
- Catalog lookup must not create stock automatically.
- Product attributes must be maintainable without turning every product field into an unrelated special case.
- Inventory history must not depend solely on a manually editable quantity field.

### Acceptance Criteria

- Products can be created, searched, and reused.
- Store inventory remains separate.
- Quantity-tracked products and serialized products behave differently where required.
- Inventory movements have identifiable reasons and references.
- Unauthorized users cannot manipulate restricted inventory records.
- Tests cover duplicate identifiers and invalid stock operations.

---

## 7. Phase 3 — Purchasing, Supplier Balances, and Inventory Receiving

### Objective

Ensure that stock enters the system through traceable purchase and receiving workflows.

### Tasks

1. Create purchase records associated with suppliers and receiving stores.
2. Record purchase items, quantities, unit costs, and totals.
3. Support purchase invoices and supplier references.
4. Define purchase lifecycle states.
5. Implement the inventory-receiving workflow.
6. Prevent duplicate receiving operations.
7. Record purchase payments.
8. Calculate outstanding supplier balances from recorded obligations and payments.
9. Support supplier payment history.
10. Define authorized purchase corrections and supplier-return handling.
11. Establish the foundations for weighted-average costing of quantity-tracked products.

### Financial Requirements

Supplier balances must be derived from purchase obligations, payments, and approved adjustments.

A purchase record, a payment to a supplier, and a stock receipt are distinct business events even when they occur close together.

The implementation must prevent duplicate stock receipts and duplicate payments.

The exact treatment of purchase discounts, freight, taxes, and other additional costs must be documented before those rules are implemented.

### Acceptance Criteria

- Purchases are linked to the correct supplier and store.
- Receiving goods changes inventory exactly once.
- Failed receiving operations leave no partial stock changes.
- Supplier balances reconcile with recorded purchases and payments.
- Purchase corrections are traceable.
- Tests cover duplicate submissions, invalid quantities, and partial failures.

---

## 8. Phase 4 — Individual Devices and Used-Phone Lifecycle

### Objective

Implement reliable device-level tracking before allowing serialized devices to be sold or shared across stores.

### Tasks

1. Create individual device records linked to product definitions.
2. Record IMEI and serial-number fields as applicable.
3. Define uniqueness and validation rules for device identifiers.
4. Record the acquisition source, purchase date, and original purchase cost.
5. Record device condition and inspection results.
6. Define valid device lifecycle states and transitions.
7. Record the current store and device location.
8. Implement repair records.
9. Record parts costs, labor costs, and other attributable costs separately.
10. Preserve device cost and repair history.
11. Define the calculation of device cost and realized profit.
12. Support device-level inventory movements and transfers.
13. Test device returns and authorized identity corrections.

### Costing Rules

Each used device must retain its individual acquisition cost and additional cost records.

The system must distinguish between:

- Original purchase price.
- Additional acquisition costs.
- Repair costs.
- Other approved device-related costs.
- Sale amount.
- Recognized cost basis.
- Realized profit.

The implementation must not assume that every recorded expense automatically becomes part of the device's inventory cost.

The owner must approve the relevant cost-allocation rules before implementation.

### Acceptance Criteria

- Two devices of the same model have separate records.
- Duplicate device identifiers are rejected or handled through an authorized correction workflow.
- Device status and location remain consistent with inventory movements.
- Repair history remains traceable.
- Cost records cannot be silently overwritten.
- Device-level profit follows the approved formula.
- Tests cover invalid lifecycle transitions, duplicate identifiers, and failed operations.

---

## 9. Phase 5 — Point of Sale and Sales Records

### Objective

Implement the main sales workflow on top of the verified catalog, inventory, and device foundations.

### Tasks

1. Implement product search and selection.
2. Support quantity-based cart items.
3. Support selection of individual serialized devices.
4. Calculate totals and discounts on the server.
5. Implement cash payments.
6. Implement card payments.
7. Implement mixed cash and card payments.
8. Record sale items and their historical financial values.
9. Update inventory through traceable movements.
10. Update device status after completed sales.
11. Generate sale references and receipts.
12. Implement searchable sales history.
13. Define authorized void, return, refund, and correction workflows.
14. Protect against duplicate sale submissions and concurrent attempts to sell the same item.

### Transaction Requirements

A successful sale must create all required records consistently, including:

- Sale.
- Sale items.
- Payment allocations.
- Inventory movements.
- Device status changes where applicable.
- Customer debt when credit is used.
- Relevant cashbox movements.
- Audit events.

Related changes must succeed or fail together where required by the business transaction.

The system must validate current stock and device availability when completing the sale, not only when the item is added to the cart.

### Acceptance Criteria

- Sales use server-calculated prices and totals.
- Mixed payments are validated.
- Quantity-based inventory is reduced correctly.
- Serialized devices cannot be sold twice.
- Duplicate submissions do not create duplicate sales.
- Failed sales leave no partial financial or inventory records.
- Completed sales cannot be silently edited or deleted.
- Tests cover concurrent sales, discounts, payment allocation, and corrections.

---

## 10. Phase 6 — Customers, Debts, Cashbox, and Expenses

### Objective

Provide reliable customer-credit and store-cash management.

### Tasks

#### Customers and Debts

1. Implement customer records.
2. Support credit sales.
3. Track the amount due from each customer.
4. Record customer payments.
5. Preserve payment history and references to the relevant debts.
6. Prevent duplicate debt payments.
7. Define payment allocation when customers have multiple outstanding sales.

#### Cashbox

1. Implement one cashbox per store.
2. Implement opening and closing sessions.
3. Associate each session with the responsible employee.
4. Record the employee responsible for every relevant transaction.
5. Record opening balance, expected closing balance, actual counted cash, and differences.
6. Record cash received and paid out.
7. Keep cash and card payments distinguishable.
8. Implement reconciliation and authorized corrections.

Multiple employees may operate during the same business day, but the system must preserve individual action attribution and follow the approved session rules.

#### Expenses

1. Implement expense categories.
2. Record store-specific expenses.
3. Record payment method and responsible employee.
4. Link expenses to suppliers or devices where applicable.
5. Distinguish general expenses from costs attributable to individual devices.
6. Prevent duplicate financial entries.

### Acceptance Criteria

- Customer debt balances reconcile with sales, payments, and adjustments.
- Supplier obligations remain distinct from customer debts.
- Debt payments do not create new sales.
- Each store has its own cashbox records.
- Every relevant transaction identifies its responsible employee.
- Cashbox differences are visible and auditable.
- Cash and card movements are not confused.
- Failed operations do not leave inconsistent financial records.

---

## 11. Phase 7 — Store Network

### Objective

Allow employees to discover, request, and temporarily reserve products or devices made available by the other store.

### Dependencies

Store Network depends on:

- Store isolation and permissions.
- Product catalog.
- Store inventory.
- Individual device records.
- Device availability rules.
- Audit infrastructure.
- Reservation concurrency protection.

### Tasks

1. Define eligibility rules for shareable products and devices.
2. Implement listing publication and removal.
3. Display approved information while protecting internal costs and profit.
4. Implement search and filtering.
5. Show current availability.
6. Implement reservation requests.
7. Notify or otherwise surface requests to the owning store.
8. Allow authorized employees at the owning store to accept or reject requests.
9. Create active reservations with expiration timestamps.
10. Enforce expiration on the server.
11. Prevent conflicting reservations and sales.
12. Implement cancellation and completion workflows.
13. Link reservations to related device transfers or sales.
14. Preserve the original owning store and current physical location.
15. Record all important actions in the audit trail.

### Reservation Rules

- A request does not reserve an item until the owning store approves it.
- An active reservation prevents conflicting reservations and sales according to the approved workflow.
- Expired reservations must be treated as inactive even if a background job has not yet refreshed their displayed status.
- Reservation approval must recheck current item availability.
- A shared listing must reference the original product or device record.
- Publishing or viewing a listing must not change inventory.
- The system must handle simultaneous reservation attempts safely.

### Financial Scope

The initial workflow must record which store owns the item, which store requested it, and which employee performed each action.

The rules for sales attribution, internal store settlement, and employee incentives must be approved before implementing any corresponding financial calculation.

### Acceptance Criteria

- A store can publish an eligible listing.
- Employees at the other store can discover it.
- Sensitive cost and profit information remains protected.
- The owning store controls reservation approval.
- Reservations expire automatically.
- Concurrent operations cannot reserve or sell the same device inconsistently.
- Transfers and sales preserve correct inventory and device history.
- Tests cover simultaneous requests, expired reservations, and unavailable devices.

---

## 12. Phase 8 — Dashboards, Reports, and Audit Review

### Objective

Provide reliable operational and financial visibility after the underlying transaction workflows are established.

### Tasks

1. Implement store-specific dashboards.
2. Implement a consolidated owner dashboard.
3. Report sales by store and date.
4. Report sales profit using the approved calculation rules.
5. Report inventory and low-stock information.
6. Report outstanding customer debts.
7. Report outstanding supplier balances.
8. Report store expenses.
9. Report cashbox balances and reconciliation differences.
10. Provide used-device cost, repair, and profit reports.
11. Provide Store Network activity and reservation history.
12. Implement filters and appropriate export capabilities where required.
13. Verify that all reports respect permissions and store isolation.

### Reporting Rules

Reports must derive from authoritative business records.

The application must not treat customer debt payments as new sales revenue or treat every cashbox movement as income.

Inventory purchases, device costs, operating expenses, refunds, and payments must follow their documented financial classifications.

### Acceptance Criteria

- Store-level reports reconcile with underlying transactions.
- Consolidated reports equal the sum of applicable store-level figures.
- Cost and profit data are only visible to authorized users.
- Report filters use consistent date and store rules.
- Financial totals can be traced back to their source records.
- Tests verify calculations and access permissions.

---

## 13. Phase 9 — Production Readiness and Rollout

### Objective

Verify that the system can be used safely for real daily operations in both stores.

### Tasks

1. Review authentication and authorization.
2. Review store-level data isolation.
3. Run the critical financial and inventory test suite.
4. Test concurrent sales and reservation operations.
5. Test backup creation and restoration.
6. Review logging and error reporting.
7. Review database constraints and indexes.
8. Review deployment configuration and secrets management.
9. Verify that sensitive data is not exposed in logs or error responses.
10. Test common workflows with realistic sample data.
11. Prepare a deployment and rollback procedure.
12. Train employees on the approved workflows.
13. Conduct user acceptance testing in both stores.
14. Document known limitations and approved deferred features.

### Acceptance Criteria

- Critical automated tests pass.
- Both stores can complete the approved daily workflows.
- Sales, stock, customer debts, supplier balances, and cashbox records reconcile.
- Unauthorized cross-store access is blocked.
- Backup restoration has been verified.
- Known limitations are documented.
- The owner approves the production rollout.

---

## 14. Cross-Phase Quality Gates

The following checks apply throughout development.

### Security Gate

Before merging a feature, verify its authorization rules and relevant negative tests.

### Data Integrity Gate

Before merging a financial or inventory feature, verify that its operations are atomic where required and safe under concurrent requests.

### Regression Gate

Run the relevant existing tests after each change. Run the broader suite at appropriate integration milestones.

### Documentation Gate

Update the relevant specification or business-rule document when an approved business rule changes.

### Completion Gate

A phase is complete only when:

- Its deliverables exist.
- Its acceptance criteria are satisfied.
- Relevant automated tests pass.
- Known limitations are documented.
- The owner can review and approve the result.

A completed UI without verified business behavior does not satisfy this gate.

---

## 15. Cursor Execution Protocol

Cursor must work in small, reviewable tasks.

For each task:

1. Read the relevant sections of `PROJECT_SPEC.md` and this plan.
2. Inspect the current implementation before proposing changes.
3. Identify the exact scope and dependencies.
4. Explain the proposed design and potential risks.
5. List the tests that will be added or updated.
6. Implement only the agreed task.
7. Run the relevant automated tests and quality checks.
8. Review the resulting changes for regressions and authorization gaps.
9. Report modified files, test results, remaining risks, and unresolved decisions.
10. Stop and request approval when a business rule is unclear.

Cursor must not implement multiple unrelated phases in a single task.

Cursor must not report tests as passing unless they were actually executed successfully.

Cursor must not silently change database structures, delete existing functionality, or make financial assumptions outside the approved scope.

When a task reveals a missing requirement, document the issue and request a decision before implementing behavior that could affect financial records or inventory.

---

## 16. Deferred Decisions Register

The following decisions do not need to block foundation work, but must be resolved before implementing their affected workflows.

| Decision | Required before |
|---|---|
| Exact currency and monetary precision rules | Purchase, POS, and finance calculations |
| Tax treatment and applicable reporting requirements | Final pricing and production financial reports |
| Detailed weighted-average costing rules | Purchase costing and stock valuation |
| Treatment of device-related costs and repair expenses | Device profit implementation |
| Customer payment allocation across multiple debts | Debt payment workflow |
| Supplier payment allocation and adjustments | Supplier balance workflow |
| Returns, exchanges, and refund rules | POS corrections and returns |
| Discount limits and approval rules | POS pricing and permissions |
| Cashbox session operating rules | Cashbox implementation |
| Store transfer approval and receiving rules | Inventory transfer workflow |
| Required IMEI and serial-number validation rules | Serialized device registration |
| Store Network reservation duration and configuration | Reservation implementation |
| Cross-store sale attribution and financial settlement | Store Network sale completion |
| Employee incentives for cross-store referrals | Any commission or incentive feature |
| Exact reports required for daily operations | Dashboard and reporting phase |

Each decision must have an owner-approved outcome or an explicitly documented deferral before its affected workflow is considered complete.

---

## 17. Definition of Overall Completion

The initial system is ready when both Karman Telekom stores can reliably use it for the approved daily workflows.

The owner must be able to trust that:

- Each store's inventory is accurate and separated.
- Every sale has traceable financial and inventory effects.
- Customer debts and supplier balances reconcile.
- Cashbox movements are attributable to the responsible employees.
- Each used device has its own identity, cost history, repair history, and sale history.
- Store Network reservations do not create duplicate sales or inconsistent stock.
- Consolidated reporting agrees with the underlying store records.
- Important changes can be audited.
- Failed operations do not leave partial or contradictory business records.

The implementation is complete only when the business workflows are verified, not merely when all planned screens have been created.
