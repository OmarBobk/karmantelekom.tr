# Karman Telekom Systems Management

## 1. Project Overview

### 1.1 Purpose

Karman Telekom Systems Management is an internal web-based management system designed to operate the day-to-day business of Karman Telekom's two mobile phone stores.

The system will centralize inventory management, purchasing, sales, used-device tracking, repairs, customer debts, supplier debts, cashbox operations, expenses, and reporting.

The platform must provide reliable operational and financial records while remaining straightforward for the owner and employees to use.

### 1.2 Phase 1 Scope

Phase 1 delivers a complete internal management system for the two existing Karman Telekom stores.

The system must support:

- Store-specific inventory and operations.
- Centralized owner access to both stores.
- Employee accounts, roles, and permissions.
- Product catalog and supplier management.
- Purchasing and inventory receiving.
- Quantity-based and individually tracked inventory.
- Used-phone acquisition, repair, cost tracking, and profitability.
- Point of Sale (POS) and sales history.
- Customers and customer debts.
- Supplier debts and payment history.
- Store cashboxes, cashbox sessions, and employee accountability.
- Expense and revenue records.
- Store Network for product discovery, reservation, and coordination between the two stores.
- Operational and financial dashboards and reports.
- Auditable business transactions.

### 1.3 Future Extensibility

The architecture should allow the system to evolve into a solution serving additional retail businesses in the future.

This is an architectural constraint, not a requirement to build a full multi-tenant SaaS platform in Phase 1.

Phase 1 does not include:

- Self-service tenant registration.
- Subscription management or billing.
- Tenant onboarding workflows.
- A public marketplace for unrelated businesses.
- A generalized SaaS administration platform.

Future extensibility must not introduce unnecessary complexity into the initial implementation.

---

## 2. Core Business Principles

### 2.1 Financial and Inventory Integrity

Correctness of financial records, inventory quantities, device identity, cost calculations, and outstanding balances takes priority over feature count and implementation speed.

Incorrect financial or inventory records can directly cause business losses.

All implementation decisions must favor correctness, traceability, and safe failure.

### 2.2 Store Separation

Each store operates with its own inventory, cashbox, and authorized employees.

The owner must be able to manage both stores from one account.

Employees must only access the information and perform the operations permitted by their roles and store assignments.

### 2.3 Traceable Transactions

Important business operations must create traceable records.

The system must preserve the relationships between purchases, inventory movements, devices, sales, payments, debts, cashbox movements, and expenses.

Financial or inventory records must not be silently overwritten or deleted to correct historical mistakes.

Corrections must follow explicitly authorized workflows and preserve the original transaction history.

### 2.4 Server-Side Enforcement

Business rules, authorization, pricing, inventory validation, reservation validation, and financial calculations must be enforced on the server.

Frontend validation improves usability but must never be the only protection against invalid or unauthorized operations.

### 2.5 Incremental Development

The system must be implemented in small, testable increments.

Foundational domain models and business rules must be established before implementing features that depend on them.

Cursor and other coding agents must not invent business rules when requirements are ambiguous.

---

## 3. Stores, Users, Roles, and Permissions

### 3.1 Store Management

The system must represent the two existing Karman Telekom stores as distinct operational locations.

Each store must have its own:

- Inventory.
- Inventory movement history.
- Purchase and receiving records.
- Sales records.
- Cashbox and cashbox sessions.
- Store-specific expenses.
- Employee assignments.
- Operational reporting.

Transactions involving both stores must preserve the identity of each participating store.

### 3.2 Owner Access

The owner must be able to:

- Access both stores.
- View consolidated information.
- Compare store performance.
- Manage authorized users and permissions.
- Review financial and inventory records.
- Investigate discrepancies and audit histories.

### 3.3 Employee Access

Employees must have access according to their assigned roles and store permissions.

The system must prevent unauthorized cross-store access, including access attempted by manipulating URLs, request parameters, or submitted data.

### 3.4 Employee Accountability

Important business operations must identify the user who performed them.

Where relevant, records must also identify the responsible store, transaction timestamp, and related business transaction.

The owner must be able to investigate which employee performed a sale, recorded an expense, received a payment, approved a reservation, or made another significant change.

---

## 4. Product Catalog

### 4.1 General Requirements

The product catalog must be flexible enough to support different retail product categories without assuming that every product is a mobile phone.

The initial business primarily covers:

- New mobile phones.
- Used mobile phones.
- Mobile accessories.
- Repair-related products and parts where applicable.
- Other products sold by Karman Telekom.

### 4.2 Product Information

Product records may contain:

- Product name.
- Category.
- Brand.
- Model.
- Description.
- Relevant specifications.
- Images.
- Supported identifiers.
- Tracking requirements.
- Active or inactive status.

The exact fields must reflect the needs of the product category.

Product definitions must remain separate from store-specific inventory records.

### 4.3 Product Identification

The system should support product lookup using available identifiers, such as:

- Barcode.
- SKU.
- Brand and model.
- IMEI or serial number for individually tracked devices.

The identifier used for a lookup must not be confused with the identity of the product definition itself.

An IMEI identifies an individual device, not every device of the same model.

### 4.4 Product Catalog Container

The system must provide a reusable catalog of known product definitions.

When an employee adds an item to a store's inventory, the employee should be able to search the catalog using an available identifier.

If the product definition already exists, the employee should reuse it rather than create a duplicate.

If it does not exist, an authorized employee may create a new product definition.

Creating a catalog entry must not automatically create inventory or imply that stock has been received.

The catalog is a source of product information, not a second inventory.

---

## 5. Inventory Management

### 5.1 Store-Specific Inventory

Each store must have an independently managed inventory.

The system must support:

- Viewing available stock.
- Searching and filtering products.
- Viewing quantities and stock status.
- Viewing inventory valuation where authorized.
- Recording inventory movements.
- Investigating inventory discrepancies.
- Tracking product location and availability.
- Reviewing historical inventory changes.

The same product definition may be associated with inventory at both stores, but the quantities and movement histories must remain store-specific.

### 5.2 Inventory Tracking Types

The system must distinguish between at least two inventory tracking approaches.

#### Quantity-Tracked Products

Products such as cables, chargers, and accessories can be tracked by quantity.

The system must maintain reliable stock quantities and prevent invalid stock movements.

#### Individually Tracked Devices

Phones and other products that require individual identification must have their own device records.

Each device must have its own identity, lifecycle, location, and relevant transaction history.

The system must prevent the same device from being represented as multiple independent physical items merely because it appears in multiple workflows.

### 5.3 Inventory Movements

Inventory changes must be traceable.

Relevant movement types may include:

- Purchase receipt.
- Sale.
- Customer return.
- Supplier return.
- Store transfer.
- Approved inventory adjustment.
- Other explicitly authorized stock movements.

Each movement must identify its source or reason, affected item, quantity where applicable, store, timestamp, and responsible user.

The implementation must define valid movement types and transitions.

A stock quantity must not be changed independently of the corresponding business operation when that operation requires an inventory movement record.

### 5.4 Inventory Integrity

The system must prevent:

- Duplicate receiving of the same purchase quantities.
- Duplicate stock deductions for one sale.
- Unauthorized inventory adjustments.
- Negative stock where prohibited by the approved business rules.
- Conflicting operations on the same individually tracked device.
- Inventory changes without traceable reasons.

Inventory operations must be protected against duplicate submissions and concurrent requests.

### 5.5 Stock Entry Sources and Physical Counts

- New stock from a supplier enters through the existing purchase receipt workflow on its invoice. The invoice accompanies the goods. The inventory screen may link directly to purchasing for this action.
- Opening stock records goods already on hand during initial setup. For quantity-tracked stock it is allowed once per branch and product definition, before any movement for that pair. Individually tracked opening stock records each device and its identifier during setup. Unknown acquisition cost stays unknown. An owner completes initial setup once per branch; the server then refuses opening stock for that branch.
- A physical count adjustment requires the branch's inventory-adjustment permission and a reason, whether stock rises or falls. For quantity-tracked items, the employee enters the physically counted total, including zero. The server records the prior balance, counted total, signed difference, reason, branch, actor, and time in the movement history.
- A count is tied to the stock state at the start of the count. Check that state under the same lock that applies the movement. Any intervening stock movement makes the count stale, including offsetting movements that return the balance to its prior value. Ask the employee to verify the count again.
- Individually tracked discrepancies identify the exact existing device by IMEI or serial and follow its approved lifecycle rules. A device found without a verified source must be reviewed before it can become sellable.
- Use explicit actions for receipt, opening stock, and stock count adjustment. Keep provenance in the movement and device histories. Receipt movements link to the invoice and receipt for users with purchasing access.

---

## 6. Used-Device Management

### 6.1 Individual Device Records

Every individually tracked used phone must have its own device record.

The record must support, where applicable:

- IMEI.
- Serial number.
- Product model.
- Acquisition date.
- Acquisition source.
- Original purchase price.
- Current store.
- Physical location.
- Condition.
- Device status.
- Inspection history.
- Repair history.
- Additional cost records.
- Sale history.

Device identity and validation rules must be defined before implementation.

The system must prevent duplicate active device identities according to the approved identifier rules.

### 6.2 Device Lifecycle

The system must track the lifecycle of each device.

Potential states include:

- Acquired.
- Awaiting inspection.
- Under repair.
- Available for sale.
- Reserved.
- Sold.
- Returned.
- Transferred.
- Written off.

These are proposed states. The final allowed states and transitions must be documented before the lifecycle implementation is finalized.

Invalid transitions must be rejected.

A device must not be simultaneously available for conflicting operations, such as being sold and actively reserved.

### 6.3 Repair Management

The system must maintain a repair history for each device.

Each repair record should support:

- Device reference.
- Repair date.
- Problem description.
- Work performed.
- Parts used and their costs.
- Labor cost.
- Other attributable costs.
- Responsible employee or technician.
- Repair status.
- Relevant notes.

Repair records must remain linked to the device throughout its lifecycle.

Historical repair costs must not be silently overwritten.

### 6.4 Device-Level Cost Tracking

The system must track the cost of each used device individually.

Cost components must be recorded separately rather than collapsed into one manually editable total.

Potential cost components include:

- Original acquisition price.
- Additional acquisition costs.
- Repair parts.
- Repair labor.
- Other approved device-related costs.

The system must preserve both the individual cost components and the resulting calculated cost values.

### 6.5 Device Profitability

The owner must be able to determine the cost and realized profit of each individually tracked device.

The implementation must distinguish between:

- Original purchase price.
- Recorded additional costs.
- Approved inventory cost basis.
- Sale price.
- Applicable adjustments.
- Recognized cost at sale.
- Realized profit.

The exact cost-allocation formula, treatment of different expense categories, and handling of returns must be approved before implementing the final profit calculation.

The system must not silently assume that every expense is capitalized into inventory cost or that every recorded cash inflow represents profit.

### 6.6 Device History

The system must preserve a traceable history of each device, including relevant acquisition, inspection, repair, cost, reservation, transfer, sale, and return events.

The device history must remain accessible to authorized users even after the device is sold or otherwise removed from available inventory.

---

## 7. Brands and Suppliers

### 7.1 Brands

The system must allow authorized users to manage product brands and associate products with them.

### 7.2 Suppliers

Supplier records must support the management of suppliers from whom Karman Telekom purchases products and parts.

Supplier information should include relevant contact details and business references.

The system must support:

- Supplier purchase history.
- Purchase invoices.
- Payments to suppliers.
- Outstanding supplier balances.
- Supplier payment history.
- Relevant purchase adjustments and returns.

Supplier records must remain linked to their purchases and financial transactions.

---

## 8. Purchasing and Supplier Debts

### 8.1 Purchase Records

The system must support purchase records associated with a supplier and receiving store.

Purchase records must identify their items, quantities, unit costs, totals, and relevant invoice information.

The system must distinguish between recording a purchase, receiving stock, and paying the supplier.

These events may occur at different times and must not be treated as interchangeable.

### 8.2 Receiving Inventory

Receiving purchased products must update the appropriate store inventory through traceable movements.

The system must prevent duplicate receiving operations.

A failed receiving operation must not leave partially updated inventory or purchase records.

### 8.3 Supplier Debt Management

Recording supplier debts is a required Phase 1 feature.

The system must track:

- Amounts owed to each supplier.
- Purchases that create supplier obligations.
- Payments made to suppliers.
- Outstanding balances.
- Payment dates and references.
- Approved adjustments.
- Supplier transaction history.

Supplier balances must reconcile with recorded obligations, payments, and approved adjustments.

The system must not treat the creation of a purchase invoice as proof that the supplier has been paid.

### 8.4 Purchase Costing

For ordinary quantity-tracked products, the initial costing approach will be Weighted Average Cost.

The exact calculation rules, timing of recalculation, treatment of purchase-related additional costs, and handling of returns or corrections must be documented before implementation.

The system must preserve the underlying purchase and inventory transaction history needed to explain the resulting cost values.

The average cost must not be changed through an unexplained manual overwrite.

---

## 9. Point of Sale (POS)

### 9.1 Purpose

The POS must allow authorized employees to sell products efficiently while preserving accurate inventory and financial records.

### 9.2 Core Requirements

The POS must support:

- Product search and selection.
- Barcode or identifier lookup where applicable.
- Quantity-based cart items.
- Selection of individual tracked devices.
- Server-side price and total calculation.
- Discounts according to approved permissions and rules.
- Payment recording.
- Cash payments.
- Card payments.
- Mixed payments where supported by the approved workflow.
- Sale references and receipts.
- Customer association where applicable.
- Customer credit sales where authorized.
- Searchable sales history.

### 9.3 Sale Records

Each completed sale must preserve the relevant historical information, including:

- Store.
- Responsible employee.
- Sale timestamp.
- Items sold.
- Quantities.
- Applied prices and discounts.
- Payment allocations.
- Relevant customer.
- Device references where applicable.
- Related inventory movements.
- Related debt and cashbox records.

Historical sales must not depend on the current product price or current product description to reconstruct the original transaction.

### 9.4 Transaction Integrity

A completed sale must update its related records consistently.

Depending on the transaction, this may include:

- Sale and sale items.
- Inventory movements.
- Device status.
- Payment records.
- Customer debt.
- Cashbox movements.
- Audit events.

Related changes must be atomic where required by the business transaction.

The system must prevent duplicate sales caused by repeated submissions and must validate current stock and device availability at completion time.

### 9.5 Sales History

Authorized employees must be able to find and inspect relevant sales records.

The owner must be able to review sales across both stores.

Historical records must be protected against unauthorized modification or deletion.

### 9.6 Returns, Refunds, and Corrections

The system must eventually support the approved workflows for returns, refunds, voids, and corrections.

The exact rules remain an explicit business decision until approved.

Cursor must not invent refund eligibility, restocking rules, financial adjustments, or device-status transitions.

These workflows must preserve the original sale and create traceable corrective records.

---

## 10. Customers and Customer Debts

### 10.1 Customer Management

The system must support customer records and associate them with relevant sales and payments.

Only necessary customer information should be collected and made available to authorized users.

### 10.2 Customer Debt Tracking

The system must support sales in which customers owe all or part of the amount due, subject to approved permissions and business rules.

It must track:

- Original obligations.
- Outstanding balances.
- Customer payments.
- Payment dates and references.
- Relevant sales.
- Approved adjustments.
- Debt history.

### 10.3 Debt Integrity

Customer balances must reconcile with sales, payments, and approved adjustments.

Recording a customer debt payment must not create a new sale.

The system must prevent duplicate payment recording and preserve traceable relationships between payments and outstanding obligations.

Payment allocation rules for customers with multiple debts must be documented before implementing the affected workflow.

---

## 11. Cashbox Management

### 11.1 One Cashbox per Store

Each store must have one logical cashbox.

The cashbox represents the store's cash-management record and must not be duplicated for individual employees.

Employee activity must instead be attributed through user records and cashbox sessions.

### 11.2 Cashbox Sessions

The system must support opening and closing cashbox sessions.

Each session must record:

- Store.
- Responsible employee.
- Opening timestamp.
- Opening cash balance.
- Closing timestamp.
- Expected closing cash.
- Actual counted cash.
- Cash difference.
- Relevant notes.
- Approved corrections where applicable.

The session rules, including how multiple employees operate under one open session, are documented in §11.6.

### 11.3 Employee Accountability

Every relevant operation must identify the employee who performed it.

The system must preserve the distinction between:

- The store's cashbox.
- The cashbox session.
- The employee performing the operation.
- The business transaction associated with the operation.

The system must not attribute every transaction to the employee who opened the cashbox merely because that employee owns the session.

### 11.4 Cashbox Movements

Relevant cash movements may include:

- Cash received from sales.
- Cash received from customer debt payments.
- Cash paid for approved expenses.
- Cash paid to suppliers.
- Approved cash withdrawals or additions.
- Other explicitly authorized movements.

Each movement must reference its reason and related transaction where applicable.

Cash movements must not be confused with accounting profit or total business revenue.

Card payments must remain distinguishable from physical cash movements.

### 11.5 Reconciliation

The owner and authorized employees must be able to compare expected and actual cash balances.

Discrepancies must remain visible and auditable.

Corrections must follow approved procedures rather than silently replacing historical values.

### 11.6 Session Rules (decided 2026-11-07, Phase 6C1)

**Sessions**

- A branch has at most one open session. Only the owner (`manage_cashbox`) opens and closes it.
- Every employee with counter access works under the open session. Each movement records the employee who recorded it, not the session's opener.
- Opening requires an explicit count, zero included. If it differs from the previous session's closing count, a note is required, and the expected amount and difference are kept.
- Closing requires an explicit count. The expected amount is the opening count plus every movement in the session. A difference requires a note. The expected amount, count, and difference are kept, and a closed session is final.
- A close is refused when the expected amount changed after the owner was shown it, for example because a sale posted cash while the owner was counting. The owner counts again against the new figure.
- A close names the session and expected amount shown together. A form for an earlier session never closes a later one, even with the same expected amount. An identical retry returns the original close.
- Session counts and totals (opening count, expected and counted closing, differences) may reach the storage limit of 9,999,999,999,999.99 TRY, so a session holding many payments still closes. Each individual payment, payout, and transfer keeps the per-payment limit of 9,999,999,999.99 TRY. A session whose expected cash exceeds the storage limit cannot close until cash is withdrawn.

**Cash intake**

- Before staff are told to accept cash for a sale or a debt payment, a cash intake binds that amount to the open session.
- Taking cash requires an open session. Card-only and fully credited sales do not.
- In a mixed checkout, the approved card amount is kept. The intake binds only the remaining cash, and the sale's cart is then frozen.
- A pending intake survives reloads and failed submissions, and it blocks closing the session.
- A debt-payment intake reserves its amount of the customer's balance, so no other payment can consume that part.
- An intake ends in one of two ways. It completes when its sale or payment is recorded for exactly that amount. Or the owner releases it with a required note when completion is impossible, after confirming the cash was not kept. A release is logged and posts nothing.
- Confirming a sale or debt payment names the intake shown on the screen. It is checked under the sale or customer lock. A released intake is never swapped for a newer pending one: the screen shows the new intake and staff confirm again. A retry after a lost response returns the original sale or payment and posts nothing more.

**Movements**

- Recording a sale or debt payment posts exactly one movement per source, in the same transaction. A sale posts the net cash kept after change.
- Movements are append-only. Releasing intakes, payouts, and owner transfers (§11.7) are separate procedures, and so are corrections.
- Each movement states its direction. Amounts are always positive. Sales, debt payments, and owner deposits move cash in. Confirmed cash expenses (§12.1), supplier cash payouts (§12.2), and owner withdrawals move cash out. The expected amount is the opening count plus cash in minus cash out.

**Tracking start**

- Cash tracking starts in each branch when its first session opens. Earlier sales and payments remain as recorded and have no movements.
- From then on, cash always needs an open session and an intake.
- A cash sale or debt payment without an intake rechecks tracking with a locking read as its last lock, after its number sequence and in share mode on the store row. If the first session opened first, the write is refused and needs an intake.

**Locking**

- The store row is the cashbox's single lock point.
- Binding and posting cash take it in share mode as their last lock: after the attempt or customer row, the intake, and the number sequence.
- Opening and closing take it exclusively as their first statement, so a close sees every committed movement and refuses while any intake, payout, or owner transfer is pending.
- Starting, confirming, and releasing a cash payout also take it exclusively first, so payouts are serialized with each other and with closing, and the available cash they check includes every reservation.

### 11.7 Owner Deposits and Withdrawals (decided 2026-11-08, Phase 6C4)

The owner moves their own TRY cash into or out of the drawer from the branch Cashbox page. These are internal cashbox transfers. They are neither sales nor expenses, and reports must not count them as revenue or spending.

**Session**

- Only the owner (`manage_cashbox`) records, confirms, or releases a transfer.
- A transfer needs a positive amount, a reason, and the open session the page showed. If that session is no longer open, the request is refused and the page shows the current session.
- The transfer is saved as pending before the owner is told to move cash. The move instruction appears only on the screen that created it. After a reload or a retry, the screen asks the owner to check the drawer first.
- A pending withdrawal reserves its amount. It must fit within the available cash: the expected amount minus all pending expense payouts, supplier payouts, and withdrawals. A pending deposit reserves nothing.
- Pending transfers survive reloads and block closing.
- Confirming names the transfer and the session shown. It posts one append-only movement, a cash deposit (in) or a cash withdrawal (out), linked to the transfer. The transfer completes in the same transaction.
- A retry with the same request key returns the original transfer in its current state. A repeated confirmation returns the original movement. Either way, nothing new is written. Reusing a key with different details is refused.
- If the cash was never moved, or was moved back, the owner releases the transfer with the verified resolution and a required note. The release is logged and posts nothing.

**Locking**

- Starting, confirming, and releasing a transfer take the store row exclusively as their first statement, then lock the transfer.
- The available cash and the open session are read only after that lock. So a withdrawal is serialized with payouts and closing, and its check sees every committed reservation.
- A supplier cash payout locks the invoice before the store row (§12.2). A transfer never touches invoices, so the two meet only at the store row, and their lock order cannot invert.

### 11.8 Reconciliation of Closed Sessions (decided 2026-11-08, Phase 6 closeout)

- The owner (`manage_cashbox`) opens any closed session of a branch from its paginated history. A session of another branch, or a session that is still open, is not found. Access is checked on the server for every request.
- The page is read-only. It shows the stored snapshot: opening expected, counted, difference, and note, the opener and time; and expected closing, counted, difference, and note, the closer and time. These figures are never recomputed, so the page shows exactly what the close compared against.
- Movements are listed oldest first and paginated, each with its time, direction, signed amount, recorder, and source (sale, customer payment, expense, supplier payment, or owner transfer reason).
- Corrections of posted movements and approval of discrepancies are not available here; they stay pending until their rules are approved (§18.2).

### 11.9 Discrepancies in Period Reports (decided by Omar 2026-11-11, Phase 8 batch 2)

- A period report assigns a closed session to the Istanbul calendar date of its `closed_at`. A session opened on one day and closed on the next counts only on the closing day.
- Period discrepancy totals include only each session's stored `closing_difference`, split into overage (positive differences), shortage (negative differences, shown as positive amounts), and net (overage minus shortage).
- The stored `opening_difference` is not part of the period totals. It is shown separately for each session in the report's session list and on the session page (§11.8).

---

## 12. Expense Management

The system must provide an expense-management section for recording store expenses and other approved business costs.

Expense records should support:

- Store.
- Expense category.
- Amount.
- Date.
- Payment method.
- Responsible employee.
- Description.
- Supporting reference where applicable.
- Related supplier or device when relevant.

The system must distinguish general operating expenses from costs attributable to individual devices or inventory.

Expense classification and its effect on inventory valuation, device profitability, and financial reporting must follow approved business rules.

An expense record must not automatically create an inventory movement or change a device's cost basis without an explicitly defined rule.

### 12.1 General Branch Expenses (decided 2026-11-08, Phase 6C2)

**Recording**

- Only the owner (`manage_expenses`) records a branch's general expenses and sees their history, per branch.
- Each expense records a category, a description, an exact TRY amount, the branch's calendar date (no future dates), the payment method (cash, card, or bank transfer), the recording employee, and an optional supporting reference.
- Categories are a fixed list: rent, utilities, salaries and wages, supplies, maintenance and repairs, transport, marketing, taxes and fees, and other.
- Card and bank transfer expenses appear in expense history only and post nothing to the cashbox.
- Each form submission carries one request key, and a key records at most one submission across all methods, even under concurrent retries. An identical retry returns the original result and writes nothing. A different amount, detail, or method under the same key is refused as a conflict.
- Expenses are never edited or deleted. Supplier payouts (§12.2), device costs, and expense corrections are separate procedures.

**Cash expenses**

- A cash expense needs an open session. It first reserves its amount as a pending payout on the session the form was shown with, before staff are told to hand the cash out. A form shown with an earlier session is refused.
- The amount may not exceed the cash available: the expected amount minus pending payouts.
- Confirming names the payout and session displayed. It records the cash expense and one outgoing cash movement together, then completes the payout. A retry after a lost response returns the original expense and writes nothing more. A released payout is never confirmed or replaced.
- Pending payouts survive reloads and block closing the session.
- Only the submission that newly creates a payout tells staff to hand out cash. A retried submission instead shows the original payout's current state (pending, completed, or released), and a reload lists pending payouts with a prompt to verify whether cash was already handed out before confirming.
- The owner (`manage_cashbox`) releases a pending payout after verifying the cash was not handed out, or was handed back. The release records that resolution and a required note, is logged, and records no expense.

### 12.2 Supplier Cash Payouts (decided 2026-11-08, Phase 6C3)

- Only TRY cash paid to a supplier from a branch whose cash tracking started (§11.6) moves cashbox cash. Before the branch's first session, TRY cash supplier payments are recorded at once, as before. USD payments and non-cash payments stay in the supplier ledger only.
- Tracked TRY cash first reserves a pending supplier cash payout on the session the payment form was shown with. Its amount may not exceed the drawer cash available after all pending payouts, nor the invoice outstanding minus pending supplier cash payouts. Every other payment on the invoice is also limited by those pending payouts.
- The payment form's request key and reference cover payments and payouts alike. Replays are resolved before any tracking or balance check, so payments recorded before tracking still replay.
- Confirming names the payout and session displayed (`pay_suppliers`). It records the supplier payment, its ledger entry, and one outgoing cash movement linked to both, then completes the payout. A retry returns the original payment. Only the submission that creates a payout tells staff to hand out cash; reloads show pending payouts with a prompt to verify first.
- Pending supplier payouts block closing the session and reversing the invoice. The owner (`manage_cashbox`) releases one with a resolution and note, logged; nothing is recorded as paid.
- A supplier payment handed out from the cashbox is not reversed through the payment reversal, since its cash already left the drawer. Correcting it is a separate procedure.

### 12.3 Expense Traceability (decided 2026-11-08, Phase 6 closeout)

- An expense may reference one supplier and one device, both optional. The references are kept on the pending cash payout and copied to the expense when it is confirmed, and expense history shows them.
- A supplier must be active, and the owner must be allowed to see suppliers (`manage_suppliers`). A device must belong to the same branch, found by exact IMEI or serial through the branch's device lookup, and the owner must be allowed to see its inventory (`view_inventory`). The server checks both.
- The references are part of the submission fingerprint only when set. A submission without references keeps its earlier fingerprint, so older requests still replay. A retry under the same key with a different or missing reference is refused as a conflict.
- An existing request is resolved, after the owner and branch checks, before supplier and device eligibility. An identical retry by the same owner returns the original result even after the supplier was deactivated or the device moved; a new request checks current eligibility.
- Lock order, as at checkout: the linked device's unit, then the linked supplier in share mode, then the store row. Eligibility is checked under those locks. Confirming a linked payout locks its unit before the store row and rechecks the payout's stored reference under lock.
- The links are for traceability only. They create no inventory movement and change no device cost basis or supplier balance. Device costs are still recorded through the device-cost workflow (§6), and supplier obligations through purchasing (§8).

---

## 13. Revenue and Financial Reporting

The system must provide reliable records and reports for sales, payments, expenses, debts, and related financial activity.

It must distinguish between:

- Sales revenue.
- Cash received.
- Card payments.
- Customer debt collections.
- Supplier payments.
- Inventory acquisition costs.
- Operating expenses.
- Device-related costs.
- Refunds and adjustments.
- Realized profit.

These concepts must not be treated as interchangeable.

Revenue and profit reports must use documented calculation rules and traceable underlying records.

The exact accounting treatment, tax handling, reporting conventions, and other unresolved financial rules must be approved before implementing the affected calculations.

The owner must be able to investigate the underlying transactions that contribute to important financial totals.

---

## 14. Store Network

### 14.1 Purpose

Store Network is an internal product-discovery, reservation, and coordination feature connecting Karman Telekom's two stores.

It allows employees at one store to discover eligible products and individually tracked devices that the other store has explicitly made available for sharing.

Its purpose is to help employees serve customers using inventory available across both stores.

Store Network is not a second inventory, a second product catalog, or an independent source of stock quantities.

It operates on the existing product, inventory, and device records.

### 14.2 Phase 1 Scope

Store Network is included in Phase 1.

However, its implementation depends on the successful establishment of:

1. Store-level authorization and isolation.
2. The product catalog.
3. Reliable inventory records and movements.
4. Individually tracked device records.
5. Device availability and lifecycle rules.
6. Concurrency-safe reservation handling.
7. Audit records.

The feature must not be implemented as a standalone listing system disconnected from these foundations.

### 14.3 Publishing Listings

Authorized employees may publish eligible products or individual devices to Store Network.

Listings may expose:

- Product name and model.
- Relevant specifications.
- Device condition.
- Available images.
- Asking price.
- Owning store.
- Current availability status.

The system must not expose restricted internal information such as acquisition cost, repair expenses, internal profit, or customer information unless explicitly authorized by an approved business rule.

A listing must reference the original product or device record.

Publishing or viewing a listing must not duplicate inventory, change the owning store, or independently modify stock quantities.

### 14.4 Search and Discovery

Authorized employees must be able to search and filter shared listings using relevant attributes, such as:

- Product name and model.
- Category and brand.
- Price range.
- Device condition.
- Relevant specifications.
- Owning store.
- Availability status.

Listings must not be presented as currently available without checking their underlying inventory or device state.

### 14.5 Reservation Requests and Approval

An employee at one store must be able to request a temporary reservation for an eligible item owned by the other store.

The owning store must explicitly approve or reject the request through an authorized employee.

Submitting a request alone does not reserve the item.

The system must record:

- Item or device reference.
- Requesting store.
- Requesting employee.
- Owning store.
- Request timestamp.
- Approval decision.
- Approving employee.
- Reservation status.
- Expiration timestamp when applicable.
- Relevant notes.

**Implemented rules (Phase 7).** Any employee with counter access to a branch may request a device another branch has shared. The request records the listing revision the employee saw. It leaves the device on sale and visible until approval. Repeating an open request returns it unchanged. Only a user with the owner-only `manage_reservations` permission at the owning branch can approve or reject, and rejection needs a reason. Approval rechecks the request under the device locks: the listing must still be at the requested revision and published by the owning branch, and the branch must still hold the device. The device must still be sellable, with no other active reservation and no checkout of it that has started payment. A device has at most one active reservation, enforced by a unique database key. Withdrawing a listing does not affect an approved reservation. A pending request whose listing was withdrawn or republished cannot be approved.

### 14.6 Reservation Expiration

Approved reservations must expire automatically according to a configurable duration.

The owner must be able to configure the default reservation duration.

The exact default duration remains to be decided.

Expiration must be enforced on the server and must not depend on a browser remaining open or an employee manually refreshing a page.

An expired reservation must be treated as inactive even if a background job has not yet updated its displayed status.

After expiration, the item may become available for another request, provided no other valid business condition prevents it.

**Implemented rules (Phase 7).** Each owning branch sets its own hold duration in minutes, from 15 to 10,080. There is no system default, and approvals are refused until the owner sets it. The hold starts at approval. Every check compares `expires_at` with the current time, so no scheduled job is needed. Stored statuses become `expired` only when a later action needs the device. A device in transit stays unavailable after its hold expires until the receiving branch confirms receipt. Approved reservations cannot be extended. When a linked sale starts payment, it claims the reservation exclusively. Until that payment is completed or its funds are resolved, every other checkout of the device is refused, even after the hold time passes.

### 14.7 Reservation and Inventory Integrity

The system must prevent conflicting reservations and sales of the same individually tracked device.

Reservation approval and sale completion must revalidate the current item state and protect against concurrent operations.

The implementation must handle situations in which:

- Two employees request the same device.
- Two reservation approvals occur concurrently.
- A device is sold while a reservation request is pending.
- A device is transferred while a reservation is active.
- A reservation expires while another operation is being processed.

The system must not leave the item in an inconsistent state if one of these operations fails.

**Implemented rules (Phase 7).** An active reservation refuses ordinary checkout of its device at review, at terminal or cash payment start, and at finalization. A checkout linked to the reservation may sell the device at the branch currently holding it: the owning branch before dispatch, or the requesting branch after receipt. Reservation actions, terminal start, cash intake, and finalization all lock the product definition and then the device, in that order, so the first to commit wins. Approval is refused if payment has already started. A payment that starts first is refused once the reservation is approved. A linked payment that started before expiry may still complete or go through the existing recovery flow, and no new reservation can take the device in the meantime. Finalization closes the reservation as fulfilled and records the sale in the same transaction. A retried finalization returns the original sale with no new payments, movements, or audit events. Cancellation needs a reason. It is open to the requesting branch's counter staff or the owning branch's reservation managers until dispatch, and refused once a checkout of the device has started payment.

### 14.8 Fulfillment and Store Coordination

Store Network must preserve the distinction between:

- The owning store.
- The requesting store.
- The store currently holding the item.
- The employee who requested the reservation.
- The employee who approved the reservation.
- The store and employee responsible for completing the sale.

Possible fulfillment arrangements include collection from the owning store or an authorized transfer to the requesting store.

A reservation does not itself transfer ownership, move stock, or complete a sale.

Actual transfers and sales must use their corresponding business workflows and create the appropriate inventory and financial records.

**Implemented rules (Phase 7).** The reservation can be fulfilled by pickup, where the owning branch sells the device under the reservation, or by transfer. For a transfer, a reservation manager at the owning branch dispatches the device against an active, undispatched reservation. That writes a `transfer_out` movement of −1 at the owning branch, sets the device to `in_transit`, and withdraws its listing. The device stays on the owning branch's record and cannot be sold anywhere. Counter staff at the requesting branch then confirm receipt. That writes a `transfer_in` movement of +1 there, moves the device record to the requesting branch, and makes it `available`. If the hold is still running, the device can be sold there only under the reservation. If the hold expired in transit, the reservation closes as expired and the device goes on sale normally. The reservation keeps the original owning branch, the requesting branch, and the actor and time of each step. The device record shows where the device is now. Both movements go through `InventoryLedger` with keys unique to the reservation, so a retried step writes nothing.

### 14.9 Cross-Store Financial Rules

The system must record enough information to identify the stores and employees involved in a cross-store transaction.

However, the rules for sales attribution, internal store settlement, profit allocation, and employee incentives are not yet finalized.

Cursor must not invent these rules.

The affected financial workflows must remain incomplete until the owner approves the relevant business rules.

### 14.10 Auditability

Important Store Network events must be traceable, including:

- Listing publication.
- Listing changes and removal.
- Reservation requests.
- Approval or rejection.
- Cancellation.
- Expiration.
- Related transfers.
- Related sales.

Each event must identify the relevant user, store, item, timestamp, and associated transaction where applicable.

**Implemented rules (Phase 7).** The `store_network` activity log records each of these as an event on the reservation:

- `reservation_requested`
- `reservation_approved`
- `reservation_rejected`
- `reservation_cancelled`
- `reservation_dispatched`
- `reservation_received`, which notes whether receipt released an expired hold
- `reservation_fulfilled`, which names the sale
- `reservation_hold_changed`, recorded when an owner changes a branch's hold duration

Each event records the acting user, the branch acting, the owning and requesting branches, and the device. Commissions, settlement, and attribution remain out of scope (§14.9).

## 14A. Storefront Publishing

### 14A.1 Branch Sharing and Publication

- A branch separately chooses what to share within Store Network and what to make eligible for storefront publication. For the storefront, it specifies an online quantity for quantity-tracked stock or selects a specific individually tracked device.
- A user with a dedicated storefront-publishing permission reviews eligible stock in a single screen and decides whether to publish for retail, wholesale, or both. The publisher can link, publish, and unpublish listings. Any pricing permission is separate. Preserve the existing storefront audience rules for wholesale and retail.
- The operational product definition identifies the item. The legacy storefront product is the presentation record. Match existing listings explicitly, keep their URLs, media, translations, and order references, and create a new storefront listing when there is no match. Storefront categories and wholesale customer shops retain their existing roles.
- Publishing does not change the branch's on-hand stock. A published listing uses the branch's current sellable inventory and its online allocation. When both channels offer an item, they draw from the same allocation. Recheck availability if the branch changes its allocation or sells, transfers, or reserves the item elsewhere.

### 14A.2 Online Orders and Stock Reservation

- In the initial release, a submitted order for branch stock is pending branch confirmation and does not yet reserve stock. Show the pending status to the customer.
- The fulfilling branch confirms only after the server rechecks the online allocation and physical availability. Confirming the order and creating the stock hold are atomic and retry-safe. The hold must prevent a conflicting POS sale or Store Network reservation for the same stock.
- Activate online ordering for linked branch stock after confirming the reservation works across retail, wholesale, Store Network, and POS. Keep existing storefront records and order history throughout the transition.

Omar approved these rules on 2026-11-09.

- **Who may order.** The first release accepts orders only from signed-in users who can already order for a shop they are authorized to use. The server checks both shop access and the listing's publication channel for that user.
- **Branch and cart.** The buyer chooses one branch that can fulfill the entire cart. A cart that mixes legacy products with branch stock is refused with a clear message, and its contents are kept. Branch orders use the existing checkout. WhatsApp "Order now" stays on the legacy path.
- **Submission.** The server computes and stores prices. Submission creates a pending order without a stock hold and is safe to retry. It consumes the cart atomically: a second submission of the same cart contents creates no second order, whatever its submission key, and submission never removes cart items added after it read the cart. An identical retry returns the original order even after its cart was emptied.
- **Confirmation.** Only the selected branch can confirm, through an authorized operator. Confirmation atomically rechecks publication, allocation, stock, and device eligibility, then creates the holds. Under the same locks it rechecks that the storefront product is active, still linked to the order's product definition, and published to the order's channel; otherwise no hold is created. The branch chooses the exact eligible device at confirmation. The order is confirmed at the agreed price. A different price needs the buyer's explicit acceptance before confirmation. Branch order status changes go through guarded actions, so the legacy order status editor cannot bypass holds.
- **Price proposals.** The buyer accepts the exact proposal shown, including its line prices and proposed devices. Acceptance is validated under the order lock: a stale proposal is refused and the current one is shown, and a retry of an already accepted proposal changes nothing and never accepts a newer one. After acceptance the branch hands over the accepted devices, or proposes again.
- **Stock protection.** Holds use the existing inventory lock order and apply in storefront availability, POS, and Store Network. If an online confirmation wins, a competing POS attempt for the same quantity or device is refused before cash intake or terminal start. If POS payment started first, confirmation respects that claim. Finalization rechecks.
- **Pickup and payment.** Payment happens in full at pickup, through a POS sale linked to the confirmed order. The server refuses customer credit for that sale at review, payment start, and finalization; ordinary POS credit sales are unchanged. Completing the sale fulfills the order and consumes its holds atomically. The pickup window is set by the branch and starts at confirmation.
- **Cancellation and expiry.** The buyer may cancel while the order is pending. After confirmation, the branch or the owner may cancel with a reason. Cancellation or expiry releases the hold once no payment is unresolved. A started payment stays protected through the existing recovery flow.
- **Shortages.** A physical count is recorded even when it falls below the held quantity. Affected orders are flagged for review, and pickup and payment start are blocked until the shortage is resolved.

Omar approved these further rules on 2026-11-10.

- **Pickup window.** The window defaults to 48 hours and is capped at 336 hours. It starts at confirmation.
- **Rejection.** The selected branch may reject a pending order. A reason is required and shown to the buyer, and the rejecting user and time are recorded. Buyer cancellation of pending orders and branch cancellation of confirmed orders keep their rules above.
- **Channel pricing.** Each order is bound to the sales channel the buyer is authorized for and the products are published to: wholesale for a wholesale buyer when every product is published to wholesale, otherwise retail. Items are priced at the published price for that channel: the retail TRY price, or the active wholesale TRY tier covering the ordered quantity. The storefront, cart quote, checkout, submission, confirmation, and price proposals share one server-side pricing rule, and POS pickup charges the agreed price. A used device is priced individually: the storefront shows "from" the cheapest eligible device, and checkout shows the branch's exact quote before submission. A different price requires the buyer's acceptance of a new proposal. An item without an authoritative price in the channel cannot be ordered in that channel.

### 14A.3 Release Order

Deliver this after Store Network. Start with the publisher workflow and verified legacy links. Online orders against branch stock ship behind the `STOREFRONT_BRANCH_ORDERS` flag, which defaults to off. Omar approves the rollout before it is switched on.

---

## 15. Dashboard and Reporting

### 15.1 Owner Dashboard

The owner must have a consolidated dashboard covering both stores.

It should provide access to important operational and financial indicators, including:

- Sales.
- Inventory.
- Low-stock information.
- Used-device status and costs.
- Customer debts.
- Supplier debts.
- Expenses.
- Cashbox balances and discrepancies.
- Relevant profitability indicators.
- Store Network activity.

### 15.2 Store-Level Views

Authorized employees must be able to access the operational information required for their store and role.

Store-specific views must not disclose restricted information from the other store.

### 15.2A Finance Overview (decided 2026-11-11, Phase 8 batch 2)

- The owner finance overview has four sections, each shown only for the branches where the owner holds that section's existing permission: expenses (`manage_expenses`), cashbox (`manage_cashbox`), customer debts (`receive_customer_payments`), and supplier balances (`manage_purchases` or `pay_suppliers`, for every accessible branch). The page opens when at least one section is visible.
- Expenses are reported by the business date entered on each expense (inclusive Istanbul dates), not by when cash left the drawer. Only confirmed expenses count; pending and released cash payouts are not expenses.
- Closed-session discrepancies follow §11.9. Live cashbox figures are the Cashbox page's own figures; pending deposits and cash intakes do not raise expected or available cash.
- Supplier balances come from the supplier ledger, reversals included, per currency, cross-checked against open posted invoices. Pending supplier cash payouts are reported separately and do not reduce a balance.
- Customer debt is original debt less allocations, and the debtor count is the number of customers with a positive balance. Pending cash payments are reported as waiting and do not reduce the debt.

### 15.3 Reporting Integrity

Reports must derive from authoritative business records.

The system must not calculate profit by treating every incoming payment as revenue or every outgoing payment as an expense.

Reports must respect the approved financial classification and costing rules.

Consolidated totals must reconcile with the applicable underlying store records.

---

## 16. Audit Trail and Historical Integrity

The system must provide traceability for significant business actions.

Relevant events include:

- Product and inventory changes.
- Purchase and receiving operations.
- Device registration and lifecycle changes.
- Repair and cost records.
- Sales and payment operations.
- Customer and supplier debt transactions.
- Cashbox sessions and movements.
- Expense records.
- Inventory transfers.
- Store Network listings and reservations.
- Financial corrections and approved adjustments.
- Changes to sensitive permissions.

Audit records must identify the responsible user, timestamp, affected record, and relevant transaction.

Sensitive operations must not be made unauditable merely because the initiating employee has elevated permissions.

The implementation must distinguish between ordinary editable operational data and historical financial or inventory records that require controlled corrections.

---

## 17. Data Integrity and Security Requirements

### 17.1 Atomic Operations

Related database changes must be performed atomically where required by the business transaction.

A failed sale, stock receipt, payment, or reservation approval must not leave partially committed business records.

### 17.2 Idempotency and Duplicate Prevention

Operations that can be submitted repeatedly must be protected against duplicate effects.

Examples include:

- Completing a sale.
- Receiving a purchase.
- Recording a payment.
- Approving a reservation.
- Completing an inventory transfer.

### 17.3 Concurrency

Operations involving limited stock, individually tracked devices, active reservations, and financial balances must be safe under concurrent requests.

Application-level checks alone are insufficient when database constraints, transactions, or locking are required to prevent conflicting operations.

### 17.4 Authorization

Access to records and business actions must be authorized on the server.

The system must validate both the user's permissions and the relevant store or record scope.

### 17.5 Financial Precision

Money calculations must use appropriate fixed-precision representations rather than binary floating-point arithmetic.

The application's currency, monetary precision, rounding conventions, and supported payment rules must be documented before financial workflows are finalized.

### 17.6 Testing

Automated tests must cover business rules, authorization, financial calculations, inventory movements, duplicate submissions, concurrency-sensitive workflows, and failure cases.

A successful page render is not sufficient evidence that a business operation is correct.

---

## 18. Decisions That Remain Open

The following decisions have intentionally not been finalized.

Cursor must not silently choose business rules for these areas.

### 18.1 Financial and Costing Decisions

- Exact currency and monetary precision rules.
- Rounding conventions.
- Treatment of taxes.
- Allocation of freight and additional purchase costs.
- Detailed weighted-average costing calculations.
- Treatment of supplier returns and purchase corrections.
- Which device-related expenses become part of inventory cost.
- Final device-level profit formula.
- Customer payment allocation across multiple debts. Phase 6B2 implemented oldest sale first; this needs the owner's confirmation.
- Supplier payment allocation and adjustments.
- Refund, exchange, return, and void rules.
- Discount limits and approval requirements.
- Financial reporting and accounting conventions.

### 18.2 Cashbox Decisions

Decided in §11.6: multiple employees work under one owner-opened session, and each movement is attributed to its recorder. Opening and closing differences need a note and stay visible. Owner deposits and withdrawals are decided in §11.7, and read-only reconciliation in §11.8. Still open, and not implemented until approved:

- Corrections of posted transactions: cash movements, expenses, supplier cash payouts, customer payments, and closed sessions. Today these records are append-only and final.
- Approval requirements for cashbox discrepancies. Today a difference needs only the owner's note.
- Session handover procedures.

### 18.3 Inventory and Device Decisions

- Exact identifier validation rules.
- Treatment of duplicate or incorrect IMEIs.
- Final device lifecycle states and transitions.
- Store transfer approval and receiving rules.
- Negative-stock policy for quantity-tracked products.
- Detailed treatment of returned or defective devices.

### 18.4 Store Network Decisions

- Default reservation duration. *Applied in Phase 7: no default. Each owning branch's owner sets the duration, and approvals are refused until then (§14.6).*
- Which product categories are eligible for sharing.
- Whether listing publication requires additional approval.
- Detailed reservation cancellation and extension rules. *Applied in Phase 7: cancellation needs a reason and is allowed until dispatch or payment start. There are no extensions (§14.7).*
- Exact fulfillment and transfer workflow. *Applied in Phase 7: pickup at the owning branch, or dispatch and confirmed receipt (§14.8).*
- Sales attribution when one store finds the customer and the other owns the device.
- Internal settlement or profit allocation between stores.
- Employee incentives or referral commissions.

### 18.5 General Rule for Unresolved Decisions

Omar approves business rules before the affected work is implemented. Follow [the collaboration agreement](COLLABORATION_WORKFLOW.md#1-responsibilities-and-decisions) to group open decisions, and record approved outcomes in the relevant project file. Continue independent work when it can proceed within approved rules.

---

## 19. Phase 1 Dependencies and Implementation Priorities

All listed capabilities belong to the initial system scope, but they must be implemented in a dependency-aware sequence.

### Priority 1 — Secure Foundations

- Authentication.
- Stores and employee assignments.
- Roles and permissions.
- Server-side store isolation.
- Audit conventions.

### Priority 2 — Product and Inventory Foundations

- Product catalog.
- Categories and brands.
- Suppliers.
- Store-specific inventory.
- Inventory movement history.
- Identifier and tracking rules.

### Priority 3 — Purchasing and Cost Foundations

- Purchase records.
- Supplier obligations and payments.
- Inventory receiving.
- Weighted-average costing foundations.
- Individual device acquisition and cost records.

### Priority 4 — Device Lifecycle and Repair Management

- Individual device records.
- IMEI and serial-number validation.
- Device status and location.
- Repair history.
- Separate device cost components.
- Approved device cost calculation.

### Priority 5 — Sales and Financial Operations

- POS.
- Sales history.
- Payment recording.
- Customer debts.
- Cashbox sessions and movements.
- Expenses.
- Returns and corrections according to approved rules.

### Priority 6 — Store Network

Complete the stock entry and physical count rules in §5.5 before this work.

- Shared listings.
- Cross-store discovery.
- Reservation requests.
- Owner-approved reservations.
- Automatic expiration.
- Concurrency protection.
- Transfer and sale integration.

Store Network must not introduce an independent inventory or device-identity system.

Storefront publishing (§14A) follows Store Network and precedes online order activation against branch stock.

### Priority 7 — Consolidated Management and Reporting

- Owner dashboard.
- Store-level reports.
- Inventory reporting.
- Debt reporting.
- Cashbox reconciliation.
- Device profitability reporting.
- Store Network activity reporting.

### Priority 8 — Production Readiness

- End-to-end verification.
- Security review.
- Financial and inventory reconciliation.
- Backup and recovery testing.
- Deployment procedures.
- Employee acceptance testing.

The exact task breakdown and execution sequence must be documented in `IMPLEMENTATION_PLAN.md`.

---

## 20. Acceptance Criteria for Phase 1

Phase 1 is considered complete when the owner can reliably manage both stores and the approved daily workflows satisfy the following conditions.

### Inventory

- Each store has accurate, separately scoped inventory.
- Product definitions can be reused without duplicating physical stock.
- Inventory changes are traceable.
- Individual devices have distinct identities and histories.

### Purchasing and Supplier Debts

- Purchases and stock receipts are traceable.
- Supplier obligations and payments are recorded accurately.
- Outstanding supplier balances reconcile with their underlying transactions.

### Used Devices

- Each tracked device has an individual record.
- Acquisition and repair costs remain separately identifiable.
- Device lifecycle and location are accurate.
- Device profitability follows an approved calculation rule.

### Sales and Customer Debts

- Sales create consistent financial and inventory records.
- Duplicate sales are prevented.
- Payments are traceable.
- Customer balances reconcile with sales, payments, and approved adjustments.

### Cashbox and Expenses

- Each store has one logical cashbox.
- Cashbox sessions identify their responsible employees.
- Relevant transactions identify the employees who performed them.
- Cash movements remain distinguishable from card payments.
- Discrepancies are visible and auditable.

### Store Network

- Employees can discover eligible products shared by the other store.
- Listings reference existing inventory and device records.
- The owning store controls reservation approval.
- Active reservations prevent conflicting operations.
- Reservations expire automatically.
- Transfers and sales preserve correct inventory and financial records.

### Security and Auditability

- Store isolation is enforced on the server.
- Unauthorized operations are rejected.
- Important actions are traceable.
- Financial and inventory corrections preserve historical records.

### Overall Reliability

- Critical automated tests pass.
- Failed operations do not leave inconsistent records.
- Financial and inventory reports reconcile with authoritative transactions.
- Both stores can complete their approved daily workflows.
- Unresolved business rules are documented rather than silently assumed.

---

## 21. Final Principle

Karman Telekom Systems Management must be designed around the integrity of the business, not merely around a collection of screens and features.

The system must make it possible to determine:

- What the business owns.
- Where each item is located.
- What each device cost.
- What was repaired and how much it cost.
- What was sold and for how much.
- What customers owe.
- What the business owes suppliers.
- What cash was received or paid.
- Which employee performed each important operation.
- How each store contributes to the overall business.

The initial implementation must remain focused on the two Karman Telekom stores while establishing reliable foundations for future expansion.

No feature is considered complete merely because its interface exists. Its business rules, authorization, data integrity, failure handling, and relevant tests must also be verified.
