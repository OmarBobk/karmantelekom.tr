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

### Operational Store Access

- Admins can access every store without membership. Employees can access only stores they are currently assigned to. Guests and users with any other role have no access, even with a leftover membership.
- Roles and memberships are re-queried on every check, so removing a membership or the `employee` role takes effect on the next check.
- Store access grants no management or operational permissions. Store management still requires the `admin` role and the `manage_stores` permission.
- Use `Store::query()->accessibleTo($user)` to list or look up stores, and `$user->can('access', $store)` (`StorePolicy::access`) to authorize a single store. Never trust a submitted store ID without one of these checks.
- No global scope or middleware applies these checks. Every future store-owned workflow (inventory, purchasing, POS, cashbox, expenses, reporting) must explicitly scope queries and authorize actions with them, and add its own tests.

### Operational Store Setup on an Existing Database

`OperationalStoreSetupSeeder` prepares an existing database for operational stores. It is safe to run more than once:

- creates `KT01` and `KT02` only if missing (via `StoreSeeder`), leaving edited store details untouched;
- creates the `employee` role and the owner permissions (`manage_stores`, plus the Phase 2 `manage_catalog`, `manage_suppliers`, `view_inventory`, and `adjust_inventory`) for the default (`web`) guard if missing;
- grants those permissions to the existing `admin` role, without removing any of its other permissions;
- creates no users and changes no existing role memberships or permissions.

If the `admin` role does not exist, it aborts with an error before writing anything. Do not use `RolesAndPermissionsSeeder` or `DatabaseSeeder` on an existing database: they create users with fixed emails.

Run these steps on the target server after the change is reviewed and deployed. Take a database backup first.

1. Check migration status:

   ```bash
   php artisan migrate:status
   php artisan migrate:status --pending
   ```

   The store migrations are:

   - `2026_11_04_183446_create_stores_table`
   - `2026_11_04_191024_create_store_user_table`

   Both should be listed as `Pending`. Any other migration in the `--pending` output is unrelated to this setup. Do not run a plain `php artisan migrate` until those have been reviewed separately, because it would apply them too.

2. Preview, then apply only the store migrations:

   ```bash
   php artisan migrate --pretend --path=database/migrations/2026_11_04_183446_create_stores_table.php --path=database/migrations/2026_11_04_191024_create_store_user_table.php
   php artisan migrate --force --path=database/migrations/2026_11_04_183446_create_stores_table.php --path=database/migrations/2026_11_04_191024_create_store_user_table.php
   ```

   Run `php artisan migrate:status` again and confirm both store migrations show `Ran` and nothing else changed.

3. Run the targeted setup seeder:

   ```bash
   php artisan db:seed --class=OperationalStoreSetupSeeder --force
   ```

   `--force` is only needed when `APP_ENV=production`. The seeder clears the Spatie permission cache itself.

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

### Operational Catalog Definitions

- Operational catalog entries are `ProductDefinition` (`product_definitions`), shared by all stores and separate from the e-commerce `Product`, which is unchanged.
- A definition has a name, optional description and model, an active flag (default active), and a required `tracking_mode`: `quantity`, `serialized`, or `used_device` (`App\Enums\TrackingMode`).
- SKU and barcode are optional strings. They are trimmed, blank values are stored as `NULL`, and barcodes keep leading zeros. Each is unique within its own column, enforced by the database with case-sensitive (binary) comparison; the same value may appear as one definition's SKU and another's barcode.
- Lookups must name the identifier type: `ProductDefinition::findByIdentifier(CatalogIdentifierType::SKU, $value)` or `CatalogIdentifierType::BARCODE`. There is no "any identifier" lookup, so results are never ambiguous.
- IMEI and serial numbers identify individual units (`inventory_units`), not definitions.
- Creating a definition never creates stock or inventory movements.
- A definition may link to one operational brand and one flat operational category. Once any store has inventory history for a definition, its tracking mode can no longer change.

### Operational Brands, Categories, and Suppliers

- Operational brands (`catalog_brands`, `CatalogBrand`) and flat categories (`catalog_categories`, `CatalogCategory`) are separate from the e-commerce `Category` and the legacy brand fields. Names are unique; entries are deactivated rather than deleted. Dynamic category specifications are deferred.
- Suppliers are `OperationalSupplier` (`operational_suppliers`): name, contact person, phone, email, tax number, address, notes, and an active flag. A tax number, when given, is unique. The stale legacy `App\Models\Supplier` reference is not reused. Purchasing and supplier balances are Phase 3.

### Store Inventory Foundation

- Quantity definitions keep one `inventory_balances` row per store and definition. Quantities are whole numbers and never go below zero (also enforced by an unsigned column).
- Serialized and used-device definitions keep one `inventory_units` row per physical unit, with the status `available` or `removed`. Serialized units have the condition `new`; used devices use `a`, `b`, `c`, or `for_parts`. Further device lifecycle states belong to Phase 4.
- Unit identifiers live in `inventory_unit_identifiers`:
  - Every unit needs at least one IMEI or serial number. A second IMEI is accepted for dual-SIM devices.
  - An IMEI is a 15-digit string with a valid Luhn check digit and is unique system-wide.
  - A serial number is trimmed and unique within its product definition.
  - Both rules are enforced by a unique index on a generated `uniqueness_scope` column with binary collation, so they include removed units and secondary IMEIs.
  - Removal keeps the unit and its identifiers.
- `inventory_movements` is append-only (the model rejects updates and deletes). Each row records the store, definition, unit (when applicable), actor, type, reason, note, time, signed quantity change, and either the quantity after the change or the unit status before and after.
- Movement types and reasons:
  - `opening_balance`: reason `opening_balance`.
  - `adjustment_increase`: reason `count_correction` or `found`.
  - `adjustment_decrease`: reason `count_correction`, `damaged`, or `lost`. A note is required.
- Opening balance:
  - Allowed once per store and quantity definition, and only before any stock history in that store. A unique generated `opening_balance_scope` column backs this rule.
  - Registering a unit records an `opening_balance` movement of +1.
  - Removing a unit records an `adjustment_decrease` of −1.
- All stock changes go through `App\Actions\Inventory\InventoryLedger`:
  - In one transaction, it locks the product definition row first, then the balance or unit row.
  - Within that lock it rechecks the tracking mode and the inventory history.
  - It recalculates the quantity from the locked row and writes the stock change and the movement together.
  - Definition edits take the same definition-row lock, so a tracking-mode change cannot race a stock write.
- Idempotency:
  - Every stock form submits a stable `idempotency_key`, which is unique in `inventory_movements`.
  - An identical retry by the same actor returns the original movement.
  - Reusing a key with a different actor, store, operation, target, or normalized payload is rejected.
  - A unique-key race is resolved after the losing transaction has rolled back.
- Inventory costs and valuation, purchasing, POS, debts, transfers, and reservations are not part of this foundation.

### Phase 2 Access

- New screens live under `/operations` on the admin subdomain: `catalog`, `catalog/settings`, `suppliers`, `inventory`, `inventory/{store}`, `inventory/{store}/products/{productDefinition}`, and `inventory/{store}/units/{unit}`.
- In this milestone every route and Livewire action is owner-only:
  - It requires the `admin` role plus the relevant permission:
    - `manage_catalog` for definitions, brands, and categories;
    - `manage_suppliers` for suppliers;
    - `view_inventory` to read stock;
    - `adjust_inventory` to change it.
  - Inventory access also requires the existing store access check.
  - Livewire components re-authorize on every request.
  - Units and movements are always loaded through their store, so a unit URL under a different store returns 404.
- `OperationalStoreSetupSeeder` grants these four permissions, together with `manage_stores`, to the existing `admin` role only. Legacy admin middleware and routes are unchanged.
- A user who has recorded inventory movements cannot delete their own account, because the history must be kept.

### Phase 2 Setup on an Existing Database

Run on the target server after review and deployment, with a fresh database backup. The Phase 1 store migrations and setup must already be applied.

1. Run `php artisan migrate:status --pending`. These eight migrations should be pending:

   - `2026_11_05_110647_create_catalog_brands_table`
   - `2026_11_05_110648_create_catalog_categories_table`
   - `2026_11_05_110649_create_operational_suppliers_table`
   - `2026_11_05_110650_create_inventory_balances_table`
   - `2026_11_05_110651_create_inventory_units_table`
   - `2026_11_05_110652_create_inventory_unit_identifiers_table`
   - `2026_11_05_110653_create_inventory_movements_table`
   - `2026_11_05_110654_add_catalog_links_to_product_definitions_table`

   The P2-01 migration `2026_11_05_104640_create_product_definitions_table` must already show `Ran`. If it is still pending, add its `--path` first in the commands below.

2. Preview, then apply only these migrations:

   ```bash
   php artisan migrate --pretend --path=database/migrations/2026_11_05_110647_create_catalog_brands_table.php --path=database/migrations/2026_11_05_110648_create_catalog_categories_table.php --path=database/migrations/2026_11_05_110649_create_operational_suppliers_table.php --path=database/migrations/2026_11_05_110650_create_inventory_balances_table.php --path=database/migrations/2026_11_05_110651_create_inventory_units_table.php --path=database/migrations/2026_11_05_110652_create_inventory_unit_identifiers_table.php --path=database/migrations/2026_11_05_110653_create_inventory_movements_table.php --path=database/migrations/2026_11_05_110654_add_catalog_links_to_product_definitions_table.php
   php artisan migrate --force --path=database/migrations/2026_11_05_110647_create_catalog_brands_table.php --path=database/migrations/2026_11_05_110648_create_catalog_categories_table.php --path=database/migrations/2026_11_05_110649_create_operational_suppliers_table.php --path=database/migrations/2026_11_05_110650_create_inventory_balances_table.php --path=database/migrations/2026_11_05_110651_create_inventory_units_table.php --path=database/migrations/2026_11_05_110652_create_inventory_unit_identifiers_table.php --path=database/migrations/2026_11_05_110653_create_inventory_movements_table.php --path=database/migrations/2026_11_05_110654_add_catalog_links_to_product_definitions_table.php
   ```

3. Grant the new owner permissions with the targeted seeder (safe to rerun; it creates no users):

   ```bash
   php artisan db:seed --class=OperationalStoreSetupSeeder --force
   ```

The migrations require MySQL or MariaDB with generated-column and `CHECK` constraint support (MariaDB 10.4 is verified).

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

### Implemented Purchasing Scope

- **Money.** Amounts are stored as `DECIMAL(15,2)` and calculated in integer minor units (`App\Services\Purchasing\Money`); floats are never used. Currencies are TRY and USD only, and each invoice, its payments, and its ledger entries share one currency. Unit prices go up to 99,999,999.99, invoice totals up to 9,999,999,999.99, and an invoice has at most 200 lines. Inputs accept Arabic digits and a decimal comma.
- **Prices and charges.** A unit price is the price billed per unit after discount, and includes KDV when the invoice is marked "prices include KDV". Additional charges are only those the same supplier bills on the same invoice; they increase the supplier obligation and are not spread into item cost. Separate tax accounting, landed cost, and weighted-average valuation are deferred.
- **Lifecycle.** An invoice is `draft`, `posted`, `cancelled`, or `reversed`:
  - Drafts carry a revision number. Saving or posting with an older revision is rejected, so a stale tab cannot overwrite newer work; the saved revision is what posts.
  - Posting requires a supplier invoice number, an invoice date, at least one line, and an active supplier. It snapshots the supplier name and tax number, locks the lines, and writes one `purchase_posted` ledger entry.
  - Drafts can be cancelled. A posted invoice can be reversed only before receipt and with no active payments; reversal requires a reason and writes an opposite ledger entry.
  - A reversed invoice can be copied to one open correction draft linked to it.
  - Purchases, payments, receipts, and ledger entries are never deleted.
- **Invoice numbers.** Numbers are trimmed and case-folded for comparison and are unique per supplier. A database claim table (`supplier_invoice_numbers`) holds each number for its correction chain, so only a correction of the reversed invoice can reuse it.
- **Receiving.** A posted invoice is received once and in full (`purchase_receipts.purchase_id` is unique):
  - Quantity lines increase store stock.
  - Serialized and used-device lines create one unit per item, with identifiers validated by the Phase 2 rules, and record each unit's acquisition cost.
  - Each line's movements use the type `purchase_receipt` and reference the receipt line.
  - Everything runs in one transaction through `InventoryLedger`. Locks are taken in a fixed order (invoice, then product definitions by id, then balances), and any failure rolls back the whole receipt.
  - Partial receipts and supplier returns are deferred.
- **Payments.** A payment applies to one posted invoice, in the invoice currency, and cannot exceed the outstanding amount:
  - Its reference is required and unique per invoice, including reversed payments.
  - A retry with the same submission key or the same reference and details returns the original payment; a reference reused with different details is rejected without revealing the other payment.
  - Reversal keeps the payment and writes an opposite ledger entry.
- **Supplier balances.** `supplier_ledger_entries` is append-only, with at most one entry per event (a generated unique scope). A supplier's balance per currency and store is the ledger sum and must equal the outstanding total of its posted invoices; the account page shows both and flags any difference. The statement's running balance is ordered by entry time, then id.
- **Inventory links.** Movement history links a receipt movement to its invoice, and a unit shows its acquisition price, for users allowed to view purchases. Stock value is shown as unavailable.
- **Tracking modes.** A product's tracking mode is locked once it has inventory history or appears on a posted invoice.

### Phase 3 Access

- Screens under `/operations`: `purchases`, `purchases/{purchase}`, `purchases/{purchase}/edit`, `purchases/{purchase}/receive`, and `suppliers/{supplier}/account`.
- Every route and Livewire action is owner-only (`admin` role) and requires store access plus:
  - `manage_purchases` to create, edit, post, cancel, reverse, or copy;
  - `receive_purchases` to receive;
  - `pay_suppliers` to record or reverse payments.
  Any of the three allows viewing invoices; the supplier account needs `manage_purchases` or `pay_suppliers`.
- `OperationalStoreSetupSeeder` grants the three permissions to the existing `admin` role only. No employee permissions or assignment rules were added.
- A user with purchases, receipts, payments, or supplier ledger entries cannot delete their account.

### Phase 3 Setup on an Existing Database

Not run against any application database. Run on the target server after review and deployment, with a fresh database backup. The Phase 1 and Phase 2 migrations and setup must already be applied.

1. Run `php artisan migrate:status`. All Phase 2 migrations (`2026_11_05_104640` and `2026_11_05_110647` to `2026_11_05_110654`) must show `Ran`, and these eight must be pending:

   - `2026_11_05_122656_create_purchases_table`
   - `2026_11_05_122657_create_purchase_lines_table`
   - `2026_11_05_122658_create_supplier_invoice_numbers_table`
   - `2026_11_05_122659_create_purchase_receipts_table`
   - `2026_11_05_122700_create_purchase_receipt_lines_table`
   - `2026_11_05_122701_create_purchase_receipt_units_table`
   - `2026_11_05_122702_create_supplier_payments_table`
   - `2026_11_05_122703_create_supplier_ledger_entries_table`

2. Preview, then apply only these migrations:

   ```bash
   php artisan migrate --pretend --path=database/migrations/2026_11_05_122656_create_purchases_table.php --path=database/migrations/2026_11_05_122657_create_purchase_lines_table.php --path=database/migrations/2026_11_05_122658_create_supplier_invoice_numbers_table.php --path=database/migrations/2026_11_05_122659_create_purchase_receipts_table.php --path=database/migrations/2026_11_05_122700_create_purchase_receipt_lines_table.php --path=database/migrations/2026_11_05_122701_create_purchase_receipt_units_table.php --path=database/migrations/2026_11_05_122702_create_supplier_payments_table.php --path=database/migrations/2026_11_05_122703_create_supplier_ledger_entries_table.php
   php artisan migrate --force --path=database/migrations/2026_11_05_122656_create_purchases_table.php --path=database/migrations/2026_11_05_122657_create_purchase_lines_table.php --path=database/migrations/2026_11_05_122658_create_supplier_invoice_numbers_table.php --path=database/migrations/2026_11_05_122659_create_purchase_receipts_table.php --path=database/migrations/2026_11_05_122700_create_purchase_receipt_lines_table.php --path=database/migrations/2026_11_05_122701_create_purchase_receipt_units_table.php --path=database/migrations/2026_11_05_122702_create_supplier_payments_table.php --path=database/migrations/2026_11_05_122703_create_supplier_ledger_entries_table.php
   ```

3. Grant the new owner permissions with the targeted seeder (safe to rerun; it creates no users). Do not run `RolesAndPermissionsSeeder` or general seeders.

   ```bash
   php artisan db:seed --class=OperationalStoreSetupSeeder --force
   ```

The migrations need MySQL or MariaDB with generated-column, `CHECK` constraint, and window-function support (MariaDB 10.4 is verified).

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

### Implemented Device Lifecycle Scope

- **Records.** Phase 4 extends the existing records instead of adding a separate device table:
  - Units (`inventory_units`) gain a `lifecycle_revision`.
  - Identifier slots (`inventory_unit_identifiers`) gain an exact-lookup index.
  - Movements (`inventory_movements`) record lifecycle events.
  - New append-only tables hold the detail: `device_inspections`, `device_repairs`, `device_cost_entries`, and `inventory_unit_identifier_corrections`.
- **Statuses.** A unit is `available`, `inspection`, `in_repair`, or `removed`:
  - Only `available` counts as available stock.
  - `removed` is final.
  - Phase 2 registration and Phase 3 receipts still create `available` units.
- **Transitions.** These apply only to store-owned used devices:

  | Action | From | To |
  | --- | --- | --- |
  | Inspect: ready | available, inspection | available |
  | Inspect: needs repair | available, inspection | inspection |
  | Start repair | inspection | in_repair |
  | Complete repair | in_repair | inspection |
  | Cancel repair | in_repair | inspection |
  | Remove | available, inspection | removed |

  - A device graded `for_parts` cannot be marked ready.
  - Removal is blocked while a repair is open.
  - An external repair can only be closed after confirming that the device is physically back.
  - Each device has at most one open repair (a generated unique scope).
- **Events.** Each action writes exactly one movement through `InventoryLedger`, using the same idempotency key, fingerprint replay, and lock order (definition, unit, then repair, cost, or identifier rows):
  - Inspection, repair, cost, and identifier events have zero quantity.
  - A database `CHECK` restricts zero-quantity rows to those unit event types.
  - The product page's stock history lists only movements that change quantity.
  - The unit page timeline lists everything, paginated on the server.
- **Stale forms.** Every lifecycle or condition change increments `lifecycle_revision`:
  - Forms submit the status and revision they were opened with, and both are checked under the unit lock, so a device that left and returned to the same status is still detected.
  - A stale form writes nothing and keeps the operator's input. It stays bound to the old state, so resubmitting it is rejected again and can never act on a newer repair.
  - Cost writes, cost corrections, and identifier corrections never rebind lifecycle forms. A successful lifecycle action adopts only the state its own movement produced, not a fresh read of the device.
  - The form shows the device's current status and open repair. The operator must choose "Continue with current state" (or reload the page) to adopt the new revision. Doing so clears the physical-return confirmations, which must be confirmed again for the current repair.
  - A replay with the same key returns the original result before this check, so a successful submission whose response was lost is not repeated.
- **Costs.** Parts, labor, additional acquisition, and other costs are separate entries:
  - Amounts are exact `DECIMAL(15,2)` values in TRY or USD, and each currency is totalled separately.
  - An unknown amount (`NULL`) is distinct from a recorded `0.00`.
  - Entries are never edited. A correction reverses the original and records an optional replacement in one movement.
  - Recording a part cost does not consume stock.
  - Invoice acquisition prices are unchanged, and units without a purchase receipt show their acquisition cost as unknown.
  - Declared acquisition costs, recognized cost basis, and realized profit are deferred to the sales phase.
- **Identity correction.** A typo is fixed in the existing identifier slot under lock:
  - A reason is required, and the before and after values are kept in `inventory_unit_identifier_corrections`.
  - The old value is released.
  - A value held by any other unit is rejected, including removed units and secondary IMEIs.
- **Lookup.** Exact IMEI or serial search runs across the user's stores, or within one store from the store page:
  - Both IMEI slots are searched.
  - Serials keep leading zeros and case.
  - Pasted IMEIs may contain spaces, hyphens, or Arabic digits.
  - One current match opens the device page. Several current matches are listed so the operator can choose.
  - A value that was corrected away is shown only as a historical match, which must be opened explicitly.
- **Deferred.** Transfers, customer and supplier returns, and sale-time costing are deferred.

### Phase 4 Access

- Screens: `/operations/inventory/{store}/units/{unit}` (device page), plus the lookup on `/operations/inventory` and `/operations/inventory/{store}`.
- Every action is owner-only (`admin` role) and requires store access plus:
  - `service_devices` to inspect, start, complete, or cancel repairs;
  - `manage_device_costs` to record or correct costs, and to see any cost amount, cost entry, or cost event;
  - `correct_unit_identity` to correct identifiers;
  - `adjust_inventory` to remove a unit, as before.
- Completing a repair without cost access is allowed; the repair then shows "costing pending".
- The invoice link keeps the Phase 3 purchase permissions. The acquisition amount on device screens also needs `manage_device_costs`.
- `OperationalStoreSetupSeeder` grants the three new permissions to the existing `admin` role only.

### Phase 4 Setup on an Existing Database

Applied only to the local development database (`dev.store`) and the isolated test database. Before running on a target server, review and deploy the code and take a fresh database backup. The Phase 1 to Phase 3 migrations and setup must already be applied.

1. Run `php artisan migrate:status`. All Phase 3 migrations (`2026_11_05_122656` to `2026_11_05_122703`) must show `Ran`, and these seven must be pending. All are additive. The second one replaces the movement quantity `CHECK` constraint with a wider one that still accepts every existing row.

   - `2026_11_05_180922_add_lifecycle_revision_to_inventory_units_table`
   - `2026_11_05_180923_allow_unit_events_in_inventory_movements_table`
   - `2026_11_05_180924_add_lookup_index_to_inventory_unit_identifiers_table`
   - `2026_11_05_180925_create_device_inspections_table`
   - `2026_11_05_180926_create_device_repairs_table`
   - `2026_11_05_180927_create_device_cost_entries_table`
   - `2026_11_05_180928_create_inventory_unit_identifier_corrections_table`

2. Preview, then apply only these migrations:

   ```bash
   php artisan migrate --pretend --path=database/migrations/2026_11_05_180922_add_lifecycle_revision_to_inventory_units_table.php --path=database/migrations/2026_11_05_180923_allow_unit_events_in_inventory_movements_table.php --path=database/migrations/2026_11_05_180924_add_lookup_index_to_inventory_unit_identifiers_table.php --path=database/migrations/2026_11_05_180925_create_device_inspections_table.php --path=database/migrations/2026_11_05_180926_create_device_repairs_table.php --path=database/migrations/2026_11_05_180927_create_device_cost_entries_table.php --path=database/migrations/2026_11_05_180928_create_inventory_unit_identifier_corrections_table.php
   php artisan migrate --force --path=database/migrations/2026_11_05_180922_add_lifecycle_revision_to_inventory_units_table.php --path=database/migrations/2026_11_05_180923_allow_unit_events_in_inventory_movements_table.php --path=database/migrations/2026_11_05_180924_add_lookup_index_to_inventory_unit_identifiers_table.php --path=database/migrations/2026_11_05_180925_create_device_inspections_table.php --path=database/migrations/2026_11_05_180926_create_device_repairs_table.php --path=database/migrations/2026_11_05_180927_create_device_cost_entries_table.php --path=database/migrations/2026_11_05_180928_create_inventory_unit_identifier_corrections_table.php
   ```

3. Grant the new owner permissions with the targeted seeder (safe to rerun; it creates no users). Do not run `RolesAndPermissionsSeeder` or general seeders.

   ```bash
   php artisan db:seed --class=OperationalStoreSetupSeeder --force
   ```

Existing units keep their status and start at revision 0. The migrations were verified on MariaDB 10.4, including rolling back all seven and re-applying them.

### Phase 4 Rollback

Rollback is supported only while no device lifecycle data has been recorded. Once any lifecycle history exists, fix forward with a new migration instead of rolling back.

- **The guard.** Every Phase 4 `down()` first calls `DeviceLifecycleRollbackGuard` and refuses before any DDL runs, so no table, column, index, or constraint is dropped. Recorded data means any of:
  - rows in the four history tables;
  - unit event movements;
  - units in `inspection` or `in_repair`;
  - units with `lifecycle_revision` above 0.
- **Covered commands.** The guard covers every path that calls `down()`:
  - batch rollback (`migrate:rollback`, with or without `--batch`);
  - `--step`;
  - targeted `--path` rollbacks of a single migration;
  - `migrate:reset` and `migrate:refresh`.
- **Not covered.** `migrate:fresh` drops every table without calling `down()`. It must never be run on a database with real data.

Procedure:

1. Take a fresh database backup.
2. Check for recorded lifecycle data. An empty array means rollback is allowed; any listed count means stop and fix forward.

   ```bash
   php artisan tinker --execute 'dump(app(App\Services\Inventory\DeviceLifecycleRollbackGuard::class)->recordedLifecycleData());'
   ```

3. Run `php artisan migrate:status` and note the batch that holds the seven Phase 4 migrations. Roll back that batch, limited to the Phase 4 files:

   ```bash
   php artisan migrate:rollback --force --batch=<phase-4-batch> --path=database/migrations/2026_11_05_180922_add_lifecycle_revision_to_inventory_units_table.php --path=database/migrations/2026_11_05_180923_allow_unit_events_in_inventory_movements_table.php --path=database/migrations/2026_11_05_180924_add_lookup_index_to_inventory_unit_identifiers_table.php --path=database/migrations/2026_11_05_180925_create_device_inspections_table.php --path=database/migrations/2026_11_05_180926_create_device_repairs_table.php --path=database/migrations/2026_11_05_180927_create_device_cost_entries_table.php --path=database/migrations/2026_11_05_180928_create_inventory_unit_identifier_corrections_table.php
   ```

4. To reapply, run step 2 of the setup above.

Verified on an isolated MariaDB 10.4 database:

- With an inspection, an open repair, and two unit events recorded, all of these were refused and left the schema, migration records, and history intact:
  - `migrate:rollback`;
  - `--batch`;
  - targeted `--path` rollbacks of the first and second migrations;
  - `--step=1`;
  - `migrate:reset`;
  - `migrate:refresh`.
- On an empty schema, rolling back all seven and reapplying them succeeded.

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
