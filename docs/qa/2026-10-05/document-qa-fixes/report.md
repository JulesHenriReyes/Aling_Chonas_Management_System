# QA document fixes — 5 October 2026

Source: [IT12 - Order Management System QA Oct 3, 2026](https://docs.google.com/document/d/1sA0E_35aXFOUezmh9SGt1jXtiu5MC8dYoxt9wa8PmCA/edit).
The Google Doc was read as the requirements source and remains unchanged.

All five requested changes were checked and implemented:

| Requirement | Result |
| --- | --- |
| Replace the inventory Actions control with an icon menu | A labelled, 44px hamburger control opens Edit, Details and History. Native disclosure semantics and existing keyboard/Escape behavior are retained. |
| Remove Reorder at from the inventory list | The column is hidden. The saved reorder threshold, editing and automatic low-stock calculation remain intact. |
| Sorting only for Supply and Category | Those are the only sortable list headings. Existing bookmarked sort URLs continue to work. |
| Simpler Stock in / Stock out buttons | Stock in handles receipts. One Stock out entry offers usage, waste or a remaining-stock count. Counts retain their absolute-quantity and concurrency checks. Switching between reduction and count clears quantities for deliberate re-entry. |
| Failed customer receipt uploads redirect to staff pages | Reproduced with dashboard, login and supplies as prior pages. Validation and service errors now return to the specific private order URL, retain the reference number and show the existing errors. JSON validation responses remain 422. |

The changes do not modify InventoryService, payment calculation, receipt ownership
identities, permissions or database schema. Invalid receipt submissions leave no
new payment, receipt record or stored file.

Validation:

- Full guarded SQLite regression: **145 tests, 1,967 assertions**, passed.
- Final focused UI and receipt redirect regression after the mobile table-label
  correction: **8 tests, 173 assertions**, passed.
- Actual Chrome browser verification on isolated synthetic data: **68 checks,
  nine screenshots, zero failures**. Owner and Assistant views at 390px and
  1440px, keyboard menus, stock mode changes, posted usage/waste/count/stock-in
  balances, customer validation and guest inventory denial were exercised.
- Representative desktop, mobile count and customer upload screenshots were
  visually inspected. The accessible table header uses an aria-label to avoid
  an absolutely positioned hidden label causing mobile page overflow.
- Changed PHP and JavaScript files pass syntax checks. Whitespace checks for
  the affected files pass. Other ongoing storefront changes were preserved.

Local database reads were performed in read-only transactions. Before/after
snapshots show identical row counts in all 30 tables and identical hashes for
all 28 tables other than sessions and users. Those two authentication tables
changed during concurrent local use; comparison with the earlier private backup
identified only `remember_token` as a changed user field. Orders, customers,
payment proofs, payments, refunds, supplies, stock movements, catalog, expenses
and migration history retained their hashes. Automated mutations were restricted
to guarded memory databases or the temporary preview SQLite database.

Evidence is in `regression-final.xml`, `focused-final.xml`, `browser-results.json`,
`screenshots/`, `data-before.json`, `data-after.json` and `isolation.jsonl`.
