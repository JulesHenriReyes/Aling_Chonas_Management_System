# Package inclusions and paid extras

Each layer option can include multiple reusable `add_ons` entries with a quantity
of 1–999 catalog units per package. Quantities count the named catalog unit: one
“Box of 6 cupcakes” means one box, not one cupcake. Duplicate items in an option
are rejected by validation and a composite database primary key.

Included items add no charge beyond the fixed layer-option price. Paid extras
remain separately configured through `add_on_product`; their quantities apply
to the entire order line, regardless of the number of packages. An item can be
both included and sold as an extra. No selected paid-extra packages means the
item can be used only for inclusions. The existing positive fixed-price field
remains required for all reusable catalog items, even when no paid offer is set.
Disabling a paid extra does not remove it from package inclusions.

## Migration and history

Run `php artisan migrate` when deploying. The additive migration
`2026_09_27_000001_add_package_included_items.php` creates
`package_option_inclusions` and adds nullable JSON
`order_details.included_items_snapshot`.

- No existing free-form inclusion text is parsed, replaced, or removed.
- The existing text field becomes optional. It stays visible until the owner
  edits it; owners must remove duplicated wording when converting text to rows.
- Existing orders keep their text and price snapshots; their new JSON field is
  null. New orders save item IDs, names, descriptions, and per-package quantities.
- The quote and order save read inclusions and prices from the database. Submitted
  inclusion quantities, text, snapshots, and prices have no authority.
- Reverting this migration drops the new structured catalog rows and JSON
  snapshots. Back up this data before a rollback after the feature is in use.

The editor requires JavaScript, as does the existing package selector. Its save
button stays disabled if the editor fails to initialize, protecting saved rows.

## Verification

`PackageInclusionsTest` covers owner edits and validation, public and staff order
creation, applicability, multiple packages and order lines, submitted-data
tampering, historical snapshots, and migration preservation. Node tests cover
the editor and unchanged paid-extra arithmetic. Browser checks use an isolated
SQLite preview at desktop 1440px and phone 390px; no preview orders are created
in the configured application database.
