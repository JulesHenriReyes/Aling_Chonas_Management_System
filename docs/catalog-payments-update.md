# Catalog and GCash payment update

## Implemented

- Owner-managed cake packages with separate catalog photos, descriptions, availability, and explicitly priced layer options. Each option states its included contents.
- Separately managed paid add-ons with name, purpose, photo, fixed price, availability, and applicable packages.
- One package picker and pricing service for public and staff orders. Buyers review the server-calculated itemized total before submission. Submission rejects a changed catalog total and asks for another review.
- Saved package names, layer counts, inclusions, unit prices, add-on names, descriptions, quantities, and prices. Catalog edits cannot reprice an existing order. Manual order-price editing and its route are removed.
- Themes, special requests, per-package reference images, general reference images, customer matching, pickup scheduling, and staff cash payments remain available.
- Persistent public payment pages use random 256-bit tokens. They show order details, exact deposit, verified payments, production state, and refund state. Copy/save link actions and QR-image download are included.
- Owner-managed GCash QR and account details. An unconfigured account is shown explicitly; no real payment details were fabricated.
- Private JPG/PNG/WebP receipts, limited to 5 MB. Receipt submission creates an awaiting-verification record, not a payment. Rejection preserves the receipt and reason and permits replacement through the same link.
- Staff checks the actual account transaction and enters its amount and reference. Exact matching acceptance records one deposit and confirms the order. Preparing is a separate action. Duplicate references and repeat verification are rejected transactionally.
- Readiness timestamps are separate from completion. Pickup deadlines use `Asia/Manila`, configurable through `BAKERY_PICKUP_TIMEZONE`, while existing stored timestamps are preserved.
- Bakery-failure cancellation creates a pending full refund of all verified payments. Staff confirms completion only after returning the money manually. Reason, amount, method, staff, date, reference, original payments, and receipts are retained.
- Collections are verified payments minus completed refunds, by their respective dates. A refund-only period can therefore have negative net collections. Pending refunds are shown separately. Bakery-failure cancellations contribute no retained cancellation income.
- Active staff and Owner restrictions, private response cache/referrer headers, receipt validation, and public submission rate limits are enforced. The existing cocoa-and-cream styling and connected metric cards remain; no colored emoji icons were added.

## Existing records and migration

The additive migration was applied without resetting or reseeding the user database. Every original column in the business tables was compared before and after migration.

Preserved: 2 staff accounts, 7 customers, 6 products, 5 orders, 5 order lines, 4 design images, 4 payments, 10 supplies, and the existing inventory/expense tables.

Private pre-upgrade backup:

`storage/app/upgrade-backups/before-catalog-20260924-063324-21144.json`

This directory is ignored by Git and is outside the public storage directory.

Historical amounts and layers remain unchanged. Product names were copied into snapshots; unknown inclusions and readiness times were not invented. Existing public orders received private tokens. Historical customer cancellations keep their classification. Duplicate historical GCash references remain in payment history and are reserved against reuse.

For historical orders without a readiness timestamp, staff must consult bakery records before declaring a missed deadline. Completion time is never used as proof of late readiness.

## Owner setup

1. Open **Products** and configure each cake package’s available layer options, fixed prices, included contents, and catalog photo. Existing products remain stored; they appear in new order entry once an available layer option exists.
2. Configure paid add-ons and their applicable packages. Extra quantities apply to the entire selected package line, beyond the included contents.
3. Open **GCash settings** from the catalog and supply the real business account name, number, and QR image. Check that the QR opens that account.
4. Prices use increments of ₱0.02 so an exact half can always be paid in whole centavos.

## Verification

Command:

```powershell
& 'C:\xampp\php\php.exe' vendor/phpunit/phpunit/phpunit
```

Result: **65 tests, 452 assertions, all passing**.

Coverage includes catalog inclusions versus extras, public/staff price tampering, snapshots, catalog photos, Owner permissions, exact deposits, private links across refresh, receipt rejection/replacement, duplicate references, inactive staff, receipt rate limits and file validation, staff cash payments, lifecycle transitions, readiness deadlines, full refunds, reporting dates, historical records, and migration upgrade/rollback preservation. Existing customer, inventory, authorization, reporting, and QA tests also pass.

`php artisan view:cache` succeeded. `php artisan migrate:status` shows the additive migration applied. The UI source emoji scan returned no matches.

Browser checks used an isolated SQLite preview, not the user's business records:

- Package selection, included contents, paid extras, server-reviewed total, submission, persistent private link, and refresh.
- A ₱2,000 package plus a ₱300 extra produced a ₱2,300 total and ₱1,150 deposit.
- Staff receipt image rendering, deposit acceptance, confirmation, separate Preparing action, and Ready for pickup; buyer states reflected those changes.
- Desktop and mobile layouts, including 1440px, 390px, and 320px viewport checks. No page-wide overflow was found in checked ordering/payment/settings pages. Staff tables retain their own horizontal scroll region.
- Visible form labels, mobile navigation focus containment, Escape closing/focus return, and copy-link feedback.

Browser file upload was blocked by the Chrome extension's file-URL permission. Upload/storage/replacement were verified through HTTP tests; a clearly marked synthetic receipt fixture was used for the subsequent browser review flow. No real GCash transfer or refund was performed. Screenshot capture was intermittent; successful captures and DOM measurements were used for layout verification. Temporary viewport overrides were reset.

## Existing policy requiring clarification

Customer cancellation continues to retain the deposit under the existing policy. The existing system does not define the refund treatment of a final balance collected before a customer cancellation; that behavior was not changed. Bakery-failure cancellation has an explicit full-refund rule for all verified payments.
