# Receipt ownership after partial table truncation

Verified and repaired locally on 5 October 2026.

The local database reused order ID 2 for an order created on 5 October. Receipt
ID 3, created on 28 September, still referenced order ID 2. Seven receipt rows
survived the reset; six had no current parent order. Customer names were not
responsible for receipt attachment.

Orders now receive a random permanent `receipt_key` on creation. Receipt uploads
save that identity alongside `order_id`. The PaymentProof model requires both
to match for relationships, queue counts, URL binding and review operations.
Reusing a numeric order ID cannot expose, block upload with, or verify the
previous order's receipt, including reuse in the same timestamp second.

The additive migration binds existing receipts only where a parent order exists
and the receipt's creation time is known and is at or after the order's creation
time. Orphaned, older or unknown-history receipts remain unbound for reconciliation.
Their rows, review status, references and files are retained.

The migration was tested on disposable SQLite and MariaDB databases, then applied
only to the regular local `aling_chona_db` database after a verified full backup.
`backup-metadata.json` contains the private backup location and hash; private
contacts, credentials and receipt paths are excluded from this report.

`local-repair.json` confirms all pre-existing row values across all 30 tables
were preserved. Only the new identity fields and the migration-register row were
added. All seven original receipt records and files remain preserved and unbound.
The actual staff order views for orders 2 and 3 rendered with zero receipts and
no receipt image links.

Validation: the full SQLite suite passed (141 tests, 1,899 assertions). The selected
MariaDB suite passed (32 tests, 410 assertions), including migration preservation,
ID reuse, normal uploads and payment verification. JUnit results are saved beside
this report. Additional public-page and stale-action checks passed in the final
focused `ReceiptOrderIdentityTest` run (3 tests, 43 assertions).

For future test-data resets, reset dependent order records consistently with
foreign-key constraints respected. This safeguard covers receipt ownership;
manual partial truncation can still leave unrelated dependent data inconsistent.
On another checkout/database, apply
`2026_10_05_000002_bind_receipts_to_order_identity.php` with the code before use.
