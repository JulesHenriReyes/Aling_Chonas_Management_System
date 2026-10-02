# Storefront, inventory, expenses and reports upgrade

Implemented on the existing Laravel / Blade / Alpine stack. The cocoa and cream identity, fixed package pricing, order history and existing payment verification flow are retained.

**Local deployment status, updated 2 October 2026:** the regular port-8000 MySQL database has now been backed up and upgraded, with original business values preserved and all three migrations recorded. The initial port-8123 fixture is stopped. See the [local Expenses repair](local-expenses-repair.md) for verification and private backup locations.

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

Choose **Select package**, select layers and quantity, then optional paid extras and design details. Extra quantities apply to the whole order line; included quantities scale with package quantity and do not incur a surcharge. Reference photos are design references, distinct from payment receipts; up to five JPG/PNG/WebP images of 5 MB each are accepted per line.

**Save package to order** returns to the catalog and order bag. Use **Edit**, **Remove** or select another card. **Continue to contact and pickup** opens the existing summary stage. Draft form fields survive refresh/back in the same browser session, and saved lines/uploads are stored in the server session. Final submission navigates to the same private payment link on repeat submission.

Normal phone cards remain two columns at 360/390/430 px; wider screens show three or four. With enlarged text, cards can use one column so names, prices and actions remain readable without clipping.

### Receiving, usage and stocktake

Receive a delivery **once into shared supplies**. Enter date, optional supplier/reference and notes; search and add each supply once, then enter quantities in its displayed unit. Review change and expected stock before posting. All rows commit together or none do. Repeated submission of the same form returns its existing operation.

**Record usage / waste** selects normal usage or waste/spoilage. Waste requires a reason. Both reject negative resulting stock. **Stocktake** accepts actual counted quantities, including zero, and requires an explanation. If stock changed while counting, reload current stock and recount before posting.

Use **Movement history** or a supply's details to inspect actor, effective date, posting time and reconciliation. To correct an operation, open it and post a reasoned **linked reversal**, then enter the corrected operation if needed. The reversal retains the original rows and is rejected if it would make stock negative. Legacy individual movements have their own correction page; grouped operations reverse together. Reversals cannot themselves be reversed.

Stock units are fixed after creation. Add a separate supply if a different unit is needed. No purchase-unit conversion, recipes or order-driven ingredient deductions are inferred. Receiving, waste, adjustment and stocktake do **not** create expenses. Record an actual invoice separately with its real amount.

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
| Retained cancellation deposits | Existing customer-cancellation rules and saved downpayment, on cancellation date. Bakery-failure cancellations do not retain deposits. |
| Valid expenses | Expense date, excluding voided records. |
| Operational result | Completed sales + retained cancellation deposits − valid expenses. |

Collections are displayed separately from sales and are never added to sales as another income stream. Operational result is not complete accounting profit because cost of goods sold and other accounting costs are unavailable. Current low-stock and outstanding-refund snapshots are explicitly outside the selected historical period.

Click **Inspect records** on a metric for paginated supporting entries. **View chart values** displays the actual chart data. **Export CSV** uses the same period/calculations and contains labelled sections for summary, trends, categories, methods, packages and snapshot/definition.

## Verification and remaining limits

See [verification evidence](workflow-verification.md), [requirement checklist](upgrade-checklist.md) and [screenshot gallery](workflow-screenshots.md).

- Initial browser verification used isolated SQLite and realistic bakery fixtures. Concurrent stock tests run two independent PHP processes against a disposable file database. The local deployment repair additionally verified backup restoration, migrations, schema/backfills and original records on a temporary MySQL copy. Concurrent MySQL load was not executed; the MySQL path retains transactions, stable row-lock ordering and uniqueness constraints.
- Legacy movements without original before/after quantities cannot prove historical balances before audit adoption. The explicit reconciliation baseline makes current stock reconcilable without inventing historical deliveries. Earlier expense edits cannot be reconstructed.
- Drafts are tied to the browser/server session; this is not a cross-device customer account cart. Existing session expiry settings apply.
- Tailwind's CDN and Google font loading remain existing application dependencies. No offline frontend rebuild was introduced.
- The preview's GCash QR is unset, so the payment page correctly requests bakery setup before money is sent. Existing payment verification behavior remains covered by the regression suite. No real payment was sent during verification.
