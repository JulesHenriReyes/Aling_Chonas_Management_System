# Storefront, inventory, expenses and reports upgrade

Implemented on the existing Laravel / Blade / Alpine stack. The cocoa and cream identity, fixed package pricing, order history and existing payment verification flow are retained.

**Local deployment status, updated 2 October 2026:** the regular port-8000 MySQL database has now been backed up and upgraded, with original business values preserved and all three migrations recorded. The initial port-8123 fixture is stopped. See the [local Expenses repair](local-expenses-repair.md) for verification and private backup locations.

**Inventory follow-up, 8 October 2026:** ingredient stock now uses separate expiration-tracked entries and automatic FEFO allocations. The active database was backed up, restored/rehearsed on a clone, and migrated without changing original balances or history. Existing ingredient stock is held as **Expiry unknown** until the Owner verifies it. See [the expiration workflow and current verification evidence](inventory-expiration.md).

## Changed workflows

- **Public orders:** select a catalog card, customize that package on its own page, save it to the order bag, then continue to contact/pickup and the existing payment page. Add, edit and remove individual lines. Quantities, extras, design requests and reference images stay with their line. The server rechecks availability and pricing before saving or submitting. Stable line keys and a checkout submission key prevent duplicate lines/orders; removed lines cannot be resurrected by an old page.
- **Inventory:** the listing uses the full content area with search, category, stock/activity filters, sorting and pagination. Receive stock, usage/waste and stocktake open separate multi-row forms. Each operation posts all rows together, records the actor and timestamp, and shows before/after quantities in each supply's fixed unit. Stocktake compares the saved stock version with the current version before applying a count. Both staff roles retain access.
- **Expenses:** the listing contains filters, sortable columns, pagination and an active total over all matching records. Add/edit forms open on request. View pages provide deletion with a required reason. Deletion voids the record; its original creator, subsequent editors and before/after audit entries remain inspectable.
- **Reports:** choose one month, a month range, custom dates or a preset. The resolved inclusive business period appears above the report. Financial totals, zero-filled daily/monthly trends, exact chart table, category/method breakdowns, saved package performance, drill-downs and CSV use the same period and event calculations.

## Deploy without resetting data

Initial upgrade verification used `storage/app/workflow-preview-20261002.sqlite`; automated tests use disposable databases. The later local deployment repair applied the additive migrations to the configured MySQL database after backup and MySQL-copy verification. No reset or reseed was performed. The following instructions remain applicable when deploying to another environment.

1. Take and verify a database backup and retain the existing `storage/app` files, application key and environment configuration. Pause application writes while migrating so legacy baseline quantities represent one consistent adoption point.
2. Deploy the code with the existing Composer dependencies and PHP 8.2 or later. There are no new npm dependencies or replacement frontend framework.
3. From the application directory, run:

   ```powershell
   php artisan down
   php artisan migrate --force
   php artisan view:clear
   php artisan config:clear
   php artisan up
   ```

   Use `C:\xampp\php\php.exe` in place of `php` on this workstation if PHP is not on PATH. Rebuild the deployment's normal configuration/view caches after clearing them if that is part of its existing deployment process.
4. Confirm the public storage link and existing product images still work. Sign in as Owner and Assistant and check inventory, expenses and reports. Spot-check supply reconciliation and known report periods against the backup.

Only ordinary `migrate` is needed. **Do not run `migrate:fresh`, database reset commands or demo seeders against the live database.** The preview fixture script has explicit testing/SQLite/path guards and is not a production installer.

### Additive migrations

| Migration | Data handling |
|---|---|
| `2026_10_02_000001_add_stock_operations_and_expense_audits` | Adds grouped operations, movement metadata, stock versions, reconciliation baselines, expense void/editor metadata and read-only audits. Old quantities/movements/expenses remain unchanged. |
| `2026_10_02_000002_add_order_submission_key` | Adds a nullable unique checkout submission key. Existing orders retain their data and have a null key. |
| `2026_10_02_000003_add_report_indexes` | Adds completion/cancellation/refund indexes without rewriting business records. |

The first migration is deliberately **forward-only**: routine rollback must not discard newly recorded financial or movement history. To roll back a deployment, restore the verified pre-upgrade database/files backup together with the corresponding code, or implement a reviewed forward repair. Do not attempt a blind `migrate:rollback`.

Existing supply baselines are labelled `legacy_reconciliation`. Opening quantity equals the observed current quantity minus signed existing movements. They are not historical receipts. Existing expenses receive an adoption audit with an unknown actor and an explicit statement that earlier edit history is unavailable.

## Daily operation

### Public order drafts

**Cancellation rule, clarified by the user on 8 October 2026:** the Owner may cancel an unpaid order or a booking with exactly its verified 50% deposit. The deposit stays in the original payment ledger and becomes retained cancellation income once, on the cancellation date. It remains a collection on its original payment date; it does not become completed sales. Fully paid and completed orders remain protected. Reported transfers must still be checked before closing an unpaid request. This clarification supersedes the earlier implementation that blocked deposit cancellations. See [company rules and source distinctions](company-rules.md).

Choose **Select package**, select layers and quantity, then optional paid extras and design details. Extra quantities apply to the whole order line; included quantities scale with package quantity and do not incur a surcharge. Reference photos are design references, distinct from payment receipts; up to five JPG/PNG/WebP images of 5 MB each are accepted per line.

**Save package to order** returns to the catalog and order bag. Use **Edit**, **Remove** or select another card. **Continue to contact and pickup** opens the existing summary stage. Draft form fields survive refresh/back in the same browser session, and saved lines/uploads are stored in the server session. Final submission navigates to the same private payment link on repeat submission.

Normal phone cards remain two columns at 360/390/430 px; wider screens show three or four. With enlarged text, cards can use one column so names, prices and actions remain readable without clipping.

### Owner-created staff orders

Use **Orders → Create staff order**, choose a package card, and customize its layer option, quantity, free inclusions, paid extras, theme and design instructions. **Save package to order** adds a stable line to the saved summary. Different designs of the same package remain separate lines. **Edit**, **Remove** and **Add another package** retain the other lines and their photos. The three steps are Choose package, Customize, and Customer and pickup; layouts adapt to the admin content width and stack on narrow screens without overlaying fields.

**Continue to customer and pickup** opens customer search, inline customer creation, the pickup-time picker and the itemized checkout. Use Up/Down then Enter to select a customer; Escape closes suggestions. Field errors appear beside affected inputs. Details and unfinished customer-entry values are saved in the session and a browser draft scoped to the Owner, session and order draft. Editing, reload and Back preserve the selected customer, pickup and notes. Catalog price or availability changes return staff to the affected package while retaining their work.

**Create staff order** records the creating Owner and counts as approval of the design, schedule and capacity. It opens **Confirmed — awaiting deposit**, with no second staff confirmation and no invented payment. Record the exact 50% deposit separately using **Cash** or **GCash**; GCash requires its reference. Preparation still requires that deposit. The balance is collected at actual pickup through the existing pickup/payment action. Public requests continue to require staff review before payment.

Staff reference photos are limited to five JPG/PNG/WebP images per package, 5 MB each. Temporary files use `staff_drafts`; final staff uploads use `staff_references`. Neither disk is public. Draft previews require the current active Owner, session, draft and line; saved reference previews require authenticated active staff and matching order/image membership. Removal and successful creation clean temporary files without clearing public drafts. Existing saved staff selections are adopted when the flow opens. A server-issued submission key uses the existing unique column; repeated and concurrent submissions return the original order even after draft cleanup. No migration is introduced for this workflow.

### Supplies, stock in and stock out

Create each supply once through **Add supply**, then use **Stock in** for quantities bought. Type the supply name and choose its category and stock unit. The saved inventory records are the only ingredient list used by the stock forms. New supplies start at zero. **Save & stock in** opens the stock form with the new supply selected; **Save supply only** just creates its catalogue entry. Existing supplies retain their recorded units and quantities.

For the bakery's ingredients, use Flour, Sugar and Cocoa in kg; Egg in pieces; Evaporated milk in cans; Vegetable oil in kg; and Baking powder and Baking soda in g. Initial seed data uses these names and units when setting up a fresh database. The app has no separate ingredient-template dropdown to maintain.

In **Stock in**, enter date, optional supplier and notes. Use **Find a supply** to type a name or open the dropdown button. The list stays open as supplies are selected and marks them Added. Click outside, press Escape or use the button to close it. **Add a new supply** creates a missing catalogue entry on the same page and adds it to the batch with zero stock. Enter quantities beside their units and an expiration date for each ingredient; packaging is exempt. Every receipt creates a separate entry, including identical expiration dates. All stock rows commit together or none do. Repeated submission of the same stock form returns its existing operation. Cancelling Stock in leaves any newly created catalogue entry at zero.

**Stock out** selects Used for baking, Waste / spoilage or Count remaining stock. Baking previews and consumes the earliest-expiring usable entries automatically; expired and unknown ingredients are held. Waste targets actual entries and requires a reason. Ingredient counts include every remaining entry; packaging retains aggregate counting. Counts accept zero and require an explanation. Stale stock/date snapshots are rejected while entered values are retained. Expiration changes availability, not physical quantities, and never creates automatic waste.

Use **Movement history** or a supply's details to inspect actor, dates, posting time, entry allocations and reconciliation. To correct an operation, post a reasoned **linked reversal**, then enter the corrected operation. A reversal undoes the original allocations and retains their expiration dates; receipt reversals cannot borrow stock from another entry. Legacy ingredient corrections without allocations require Owner-selected reconciliation. Opening quantities must be verified through **Review opening stock**, not received again. Posted expiry dates are read-only. Reversals cannot themselves be reversed. See [full stock-entry rules](inventory-expiration.md).

Stock units are fixed after creation. Convert purchases into the recorded unit before entry: for example, a bag labelled 25 kg adds 25 kg of flour. Use the actual labelled weight or count; tray sizes and can capacities are not assumed. Add a separate supply if a different unit is needed. No recipes or order-driven ingredient deductions are inferred. Stock in, waste, adjustment and stocktake do **not** create expenses. Record an actual invoice separately with its real amount.

### Expenses and audit history

**Add expense** records description, category, actual amount and expense date. **Edit** preserves the original creator and records the editor and before/after values; an edit reason can be supplied. Stale edits are rejected. **Delete expense** requires a reason and voids the expense rather than removing its history. Use the **Voided** or **All records** filter to inspect it. **Audit history** exposes creation, edit and void entries; before/after values expand on request and cannot be edited through staff routes.

The listing total sums active expenses across all matching pages. Voided entries remain visible when requested but contribute zero to active/report totals. Owner and Assistant retain the existing management permissions; inactive staff are rejected on the server.

### Reading reports correctly

Use **Apply period** after selecting the period. Month/range/custom filters persist in the URL and propagate to drill-downs and exports. Preset links retain an `as_of` date so moving between views does not shift the selected business period. Datetime bounds are converted consistently from `BAKERY_BUSINESS_TIMEZONE` (defaults to the existing pickup timezone, Asia/Manila) to the application's storage timezone. Ending business dates are inclusive, including fractional seconds late that day.

| Figure | Source / date |
|---|---|
| Completed sales | Saved order line prices plus saved paid extras once, on order completion date. |
| Gross verified collections | Payment ledger entries on payment date; uploaded/unverified/rejected receipt images do not count. |
| Completed refunds | Completed refund records on refund completion date. |
| Net collections | Gross verified collections minus completed refunds. |
| Retained cancellation deposits | Customer cancellations and the original verified 50% deposit, counted once on the cancellation date. Fully paid/completed orders remain protected. |
| Valid expenses | Expense date, excluding voided records. |
| Operational result | Completed sales + retained cancellation deposits − valid expenses. |

Collections are displayed separately from sales and are never added to sales as another income stream. Operational result is not complete accounting profit because cost of goods sold and other accounting costs are unavailable. Current low-stock and outstanding-refund snapshots are explicitly outside the selected historical period.

Click **Inspect records** on a metric for paginated supporting entries. **View chart values** displays the actual chart data. **Export CSV** uses the same period/calculations and contains labelled sections for summary, trends, categories, methods, packages and snapshot/definition.

## Verification and remaining limits

See [verification evidence](workflow-verification.md), [requirement checklist](upgrade-checklist.md) and [screenshot gallery](workflow-screenshots.md).

- Initial browser verification used isolated SQLite and realistic bakery fixtures. The 8 October expiration follow-up additionally passes the SQLite and MariaDB regression suites, proves MariaDB row-lock contention and duplicate-submit behavior with independent processes, and verifies active backup restoration, migration resume and original-record preservation. See [current inventory evidence](inventory-expiration.md).
- Legacy movements without original before/after quantities cannot prove historical balances before audit adoption. The explicit reconciliation baseline makes current stock reconcilable without inventing historical stock entries. Earlier expense edits cannot be reconstructed.
- Drafts are tied to the browser/server session; this is not a cross-device customer account cart. Existing session expiry settings apply.
- Tailwind's CDN and Google font loading remain existing application dependencies. No offline frontend rebuild was introduced.
- The preview's GCash QR is unset, so the payment page correctly requests bakery setup before money is sent. Existing payment verification behavior remains covered by the regression suite. No real payment was sent during verification.
# Pickup time selection

Customer and staff checkout share a compact clock picker with AM/PM, hour and minute selectors. Pickup hours default to **8:00 AM–6:00 PM**, inclusive, in the configured bakery pickup timezone. AM offers 8:00–11:59; PM offers 12:00–5:59 and exactly 6:00 PM. Every allowed minute is available.

Click **Done** to save a complete selection. Escape, closing the picker, or clicking outside discards unfinished changes. Changing a selector preserves compatible values and clears incompatible ones instead of rounding the requested time. Old input, saved checkout details and public browser drafts retain the canonical `HH:mm` time. Out-of-range drafts require a new selection while keeping other fields.

`BAKERY_PICKUP_OPENS_AT` and `BAKERY_PICKUP_CLOSES_AT` configure the same-day `HH:mm` range. The component, help text, request validation and order-creation service use the same range. Existing orders are preserved; no database migration or new endpoint is required. Without the picker script, the native time field remains available and server validation still applies. Existing date, review and capacity rules remain unchanged.
