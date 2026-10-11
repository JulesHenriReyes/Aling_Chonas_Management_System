# Customer design changes without schema changes

Customers use their private order link to propose themes/colors, instructions and reference-photo additions/removals. Submitting one request per order pauses preparation. The active Owner or Assistant reviews the complete proposal, approves it at the saved price and pickup schedule, or declines it with a customer-visible explanation. Customers can withdraw a pending proposal. Changes remain available during pending, confirmed and preparing, and close when ready, completed or cancelled. An ordinary order confirmation, preparation transition, or staff reference attachment cannot bypass a pending request.

These are the user's confirmed demo follow-up choices, recorded separately from source-document requirements in [company-rules.md](company-rules.md).

## Existing database only

There are **no new migrations, tables or columns**. Submission, decline and withdrawal store no new database records. Approval updates `order_details.themes`, `order_details.special_request` and the existing `order_images` rows. Removing a current image deletes its active row while retaining its original file and its before/proposed metadata for history. Payments, proofs, totals, package contents, layers, quantities, extra items, customer information and pickup values remain unchanged.

## Durable private storage

- `storage/app/order-design-changes/orders/<identity>/state.json` holds a versioned history for that order. `identity` is SHA-256 of the existing receipt key, so a recycled numeric order ID cannot inherit another order's history.
- Each order has a private lock file and registration marker. A small `pending/<identity>.json` marker supports the staff Design Changes filter without reading every historical order.
- `storage/app/order-design-references/design-changes/<identity>/<draft>/<image>.<extension>` holds uploaded JPG, PNG and WebP photos. Up to five retained/new photos per package are allowed; each upload is limited to 5 MB.
- Unsubmitted draft fields and upload identifiers are saved in the browser's server session. Submitted proposals and history are durable files and survive logout/session expiry. Draft autosave alone does not pause work.
- New uploads are served through the private customer link or authenticated staff routes, with no-store responses. Existing older public/staff uploads keep their original storage classification.

State files use temporary-file writes, flushes, and atomic replacement. Every writer locks the existing order row before taking the order's file lock. This serializes submission, review and withdrawal against preparation and readiness transitions.

Approval first durably records an `applying` journal, commits all affected existing details/images in one database transaction, then finalizes the decision. Interrupted approval recovery compares the actual saved design against semantic before/proposed fingerprints: a committed proposal becomes approved, a rolled-back proposal returns to pending, and an unexpected design remains held for review. Concurrent duplicate approvals also settle the journal under both locks before returning the original decision. A stale proposal cannot overwrite a separately changed design.

Cancellation commits through the existing payment/cancellation safeguards and closes the pending proposal afterward. Closing storage errors are reported and cannot undo a successful cancellation. Terminal orders are excluded from the active review queue even if a closure marker needs repair.

## Deployment and backups

Preserve **both private directories** across deployments; they must not live in a disposable release folder. Back them up with the database and the existing public/staff reference files. Replacing an active reference retains its historical file until the completed-order retention policy applies. The user confirmed 60-day photo and 365-day design-history expiration for completed orders only; see [order-reference-retention.md](order-reference-retention.md). Active/cancelled orders and their drafts are excluded. Existing image metadata and payment/receipt records remain unchanged. Expired history leaves a small valid private state rather than deleting the registration marker.

One app server can use normal durable local storage. Multiple app instances require shared durable storage supporting file locks and atomic replacement, as well as the same database. Object storage alone does not satisfy this metadata-file locking contract.

The PHP user needs read/write access to these private directories. Refresh an existing deployment's cached configuration to include the new `design_references` disk. Match PHP upload limits to the supported 5 MB per file (a five-photo batch needs a post limit above 25 MB including form overhead). No database migration command is required for deploying this feature.

If a registered state file is missing, corrupt or has a mismatched identity, preparation fails closed with a visible restore message. Restore the matching file/marker and reference files from a consistent backup; do not delete the marker to bypass the hold. Losing the entire storage volume cannot be detected as a previous request on an otherwise blank order, so durable storage and complete backups are essential.

## Performance

Normal list rendering reads one small state file per displayed order and caches it for that request. The active queue reads only pending markers. Image bytes are separate and comparisons load thumbnails lazily. The private customer page and staff detail page check a small JSON revision every five seconds while visible; they reload only when order/payment/design information changes. Open forms and dialogs preserve user input and show an update notice. The existing five-second orders-list refresh continues to update hold badges and filtered rows.

Local isolated measurements for metadata reads/summaries across ten displayed orders were 3.832 ms median with no history, 8.823 ms with ten prior requests per order, and 29.305 ms with one hundred. These figures exclude PHP bootstrapping, SQL, network, HTML rendering and photo downloads. Polling adds requests while pages are visible, so hosting capacity and traffic still matter. See the [QA report](qa/2026-10-11/design-changes/report.md) for evidence and exact scope.
