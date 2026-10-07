# Ingredient expiration and stock entries

Implemented 8 October 2026 on the existing Laravel, Blade and Alpine inventory workflow. The current supply definitions, units, quantities, reconciliation baselines, operations and legacy movements are preserved. Orders do not deduct ingredients automatically.

## Staff workflow

**Existing ingredients must be verified before baking.** The migration creates an opening entry for each nonzero supply balance. Ingredient dates are **Expiry unknown**, not invented receipts or expiration dates. The Owner opens **Inventory → Review opening stock**, selects the entries checked against the actual supplies, enters their verified expiration dates and a reason, and saves. Split an entry across dates when necessary; its portions must equal the complete remaining quantity. This records a linked, zero-net transfer into dated entries and retains the original opening entry and history. Do not stock these quantities in again.

- **Stock in:** keep the existing multi-selection and inline supply creation. New definitions start at zero. Enter a quantity and expiration date for each ingredient; packaging shows **Not applicable**. Each save creates a new entry even when another entry has the same name and date. One row per supply is retained in each submission. Expiration must be on or after the stock-in date; a backdated receipt already expired today is recorded as held stock.
- **Used for baking:** choose supplies and quantities. The authenticated preview shows usable stock and proposed allocations. The server consumes earliest expiry first, then oldest stock-in date, then entry ID, splitting across entries as needed. Verified opening stock with an unknown stock-in date sorts first when expiry dates tie. Staff cannot select a preferred baking entry. Packaging uses oldest entries first.
- **Waste / spoilage:** enter quantities against the actual entries and a reason. Expired and unknown stock can be discarded. A date passing does not discard anything automatically.
- **Count remaining stock:** count every remaining ingredient entry, including held stock. The server derives the aggregate balance and rejects missing entries or a stale stock version. A count cannot change an expiry date. Packaging retains aggregate counting: decreases consume oldest entries; increases create an undated adjustment entry.
- **Corrections:** reverse the original movement's allocations, then post the corrected operation. A receipt cannot be reversed using another entry's quantity. Restored ingredients retain their original expiry and can remain held. Dates are read-only after posting. Legacy ingredient corrections without allocations require the Owner: reductions explicitly select reconciliation entries; restored quantities have unknown expiry and must be verified before baking. Reversals match original movement and allocation IDs, including operations with several movements for one supply.

Ingredient stock is usable through its expiration date in `BAKERY_BUSINESS_TIMEZONE` (default `Asia/Manila`). At the next business midnight it becomes unavailable, while physical quantities and history stay unchanged. **Expiring soon** includes the next seven calendar days by default (`inventory.expiry_warning_days`). Low-stock warnings, dashboard/report counts and availability filters use combined usable quantity once per supply.

## Inventory interface

The list keeps supply-based pagination and five columns: **Supply · Remaining · Expiration · Status · Actions**. Ingredient entries repeat the supply name with category and stock-in information underneath. Packaging remains summarized. Remaining entries appear by default; depleted entries are accessible through details/history. Zero-stock supplies remain visible and can be stocked in.

Expiry badges and linked alert counts distinguish available, expiring soon, expired and unknown stock. **Use first** identifies the earliest usable ingredient entry. The detail drawer exposes the entry's original and remaining quantities, source, dates, allocation activity, supply on-hand and usable totals. Dates and history are read-only.

Movement history keeps compact rows. The quantity button expands batch details in a separate full-width row without changing column widths or the main movement's height. Receipt details show **New batch**, received quantity and labelled expiry; the separate **Total on hand** column represents the supply's aggregate before/after balance. Keyboard Enter/Space toggles the disclosure, with `aria-expanded` and its controlled row exposed to assistive technology. See [the layout evidence](qa/2026-10-08/ingredient-expiry/order-and-batch-ui-evidence.json).

Stock forms stack labelled fields below 640px and retain entered quantities after validation or a preview refresh. Incompatible fields and previews clear when changing stock-out methods. The existing cocoa/cream palette, type, navigation, buttons, modal and drawer are retained; added styles are scoped to inventory. Labels accompany status colors. Field errors have an accessible summary; drawer Escape closes and restores focus.

## Data and write protections

`Supply` remains the single definition. `stock_entries` hold stock-in/source/expiry/original/remaining metadata; `stock_allocations` link each movement to signed entry changes and before/after values, with the original allocation ID when reversed. Entry metadata and allocation records are immutable through the application.

`Supply.current_quantity` equals the sum of all entry remaining quantities, including held stock. Usable quantity excludes expired or unknown-date ingredient entries. `StockEntryLedger` plans and reconciles allocations inside `InventoryService`'s transaction. Stable supply locks, entry locks, quantity precision, maximum/negative checks, stock versions and duplicate-submit keys remain enforced. SQLite obtains its writer lock before reading balances; MariaDB uses row locks. Baking saves recheck availability and business date after the preview. Alternate stock-writing endpoints use the same service and expiry enforcement. Ingredient category changes to packaging are blocked after stock history exists.

The additive migration `2026_10_08_000001_add_inventory_stock_entries` does not rewrite earlier migrations or reseed the database. Its locked opening-stock backfill is idempotent. It is forward-only: after entry-tracked movements are posted, fix forward rather than deploying code that bypasses tracking.

## Active rollout evidence

The configured connection was confirmed as MariaDB 10.4.32, database `aling_chona_db`, port 3306. A SQL backup was restored onto the guarded temporary MariaDB server on port 33317. The clone migration preserved original business records, reconciled every supply and passed an idempotent resume rehearsal. Inventory writes were paused through application maintenance mode during the active migration, and the app was resumed only after preservation and reconciliation checks passed.

All **12 original supplies** kept their IDs, categories, units, quantities and stock versions. Their **12 opening entries** reconcile with zero mismatches. Original baselines, operations, movements, orders, payments, expenses and other business records passed row-count and sorted-content hash comparisons. Transient sessions/cache and migration metadata were excluded from business-record comparisons. Ten ingredient entries now require Owner verification; two packaging entries remain usable. No actual bakery stock movement or guessed expiration date was posted during QA.

The verified pre-migration backup is `C:\Users\User\AppData\Local\Temp\bakery-expiry-backup-20261007-165625-10151d22\before-expiry.sql` (56,443 bytes), SHA-256 `78441cafddc3ed454a2a3842df9a1dd1901f19fa2d48e741f80be776dac74265`. Keep this private backup in durable backup storage according to the bakery's normal retention process. See [rollout evidence](qa/2026-10-08/ingredient-expiry/rollout-evidence.json). The summed quantities in that JSON are reconciliation checksums across different units, not a meaningful combined stock measure.

The [final read-only audit](qa/2026-10-08/ingredient-expiry/post-rollout-audit.json) confirms the app is up, the migration is recorded, original business tables are unchanged, no supply/entry mismatches exist, and the retained backup checksum still matches. There are no newly posted active stock allocations; only the migration's preserved opening balances exist.

For another deployment, verify a fresh backup by restoring it to an isolated clone, rehearse the migration, pause writes, apply the additive migration, compare original records and each supply's entry totals, then resume. Do not run `migrate:fresh`, seeders, or routine rollback on the active database. A whole-database restore is suitable only before new entry-tracked writes and with the matching code; afterward use a reviewed forward correction.

## Verification

| Final suite | Result |
|---|---|
| SQLite regression | **173 tests, 2,148 assertions passed** — [output](qa/2026-10-08/ingredient-expiry/sqlite-tests.txt) |
| MariaDB-compatible regression | **168 tests, 2,078 assertions passed** — [output](qa/2026-10-08/ingredient-expiry/mariadb-tests.txt) |
| JavaScript behavior | **5 tests passed** — [output](qa/2026-10-08/ingredient-expiry/javascript-tests.txt) |

Automated evidence is in [the QA folder](qa/2026-10-08/ingredient-expiry/). The final PHP suites cover ordering, payments, expenses, reports, authorization and stock protections alongside expiry cases. The MariaDB suite excludes only five tests whose purpose requires a separately constructed SQLite schema/database; all five pass in the SQLite suite. A separate MariaDB contention rehearsal holds a real supply lock, verifies that independent workers wait, then proves usage cannot overspend and duplicate receipt submissions resolve to one operation with matching entry totals.

| Requirement | Evidence |
|---|---|
| Separate receipts, identical expiry, original quantities, duplicate replay | `IngredientExpiryTest` receipts and alternate endpoint cases |
| Required ingredient dates; packaging exemption; zero definition creation | Ingredient expiry enforcement on grouped and individual endpoints; supply-creation regressions |
| FEFO splitting, preferred-entry rejection, opening tie order | Authenticated preview and committed allocation assertions |
| Expired/unknown exclusion, inclusive expiry day and business midnight | Business-date and crafted-request tests; quantities and transaction count remain unchanged at midnight |
| Waste/count/verification/reversal reconciliation | Signed allocations, immutable dates, zero-net split verification, spent receipt rejection and restored expiry tests |
| Packaging aggregate counts and undated increases | Dedicated packaging count increase/reduction test |
| Stale previews/counts, preserved input, rollback | Stale business date/version, old-input assertions, failing-last-line atomicity |
| Owner-only review and legacy reconciliation | Assistant authorization rejection and explicit Owner-selected entry reconciliation |
| Resumable migration and original-data preservation | Migration test plus restored active backup, clone resume and active record hashes |
| Concurrent usage and duplicate submissions | SQLite process tests and [MariaDB lock evidence](qa/2026-10-08/ingredient-expiry/mariadb-concurrency.json) |
| Owner/Assistant at 390, 768, 1440px | [Responsive evidence](qa/2026-10-08/ingredient-expiry/responsive-evidence.json) and role/page/width screenshots |
| Keyboard, long names and field retention | Drawer focus/Escape, labelled dates, native waste validation and resize checks in the responsive evidence |

The PHP test output and JUnit files record exact final counts. JavaScript behavior tests pass (5 tests); changed PHP/JavaScript parse, all Blade templates compile, and whitespace checks pass. Browser evidence uses a guarded disposable SQLite fixture, except the explicitly named `active-*` read-only screenshots. Temporary viewport overrides were reset after QA.

Representative views: [Owner desktop inventory](qa/2026-10-08/ingredient-expiry/owner-inventory-1440.jpg), [Assistant mobile stock-in](qa/2026-10-08/ingredient-expiry/assistant-stock-in-390.jpg), [Assistant baking preview](qa/2026-10-08/ingredient-expiry/assistant-baking-768.jpg), [active inventory after migration](qa/2026-10-08/ingredient-expiry/active-inventory-after-migration.jpg), and [active Owner opening review](qa/2026-10-08/ingredient-expiry/active-opening-review.jpg).
