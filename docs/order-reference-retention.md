# Completed-order reference retention

The user confirmed **completed orders only** on 11 October 2026. This supersedes the earlier suggestion to include cancelled orders or sweep unfinished drafts. The milestone company documents describe retaining order/payment records, but do not prescribe a photo-retention period.

## What expires

- At **60 elapsed days after `completed_at`**, regular customer/staff reference photos, approved design-change uploads, historical design references and abandoned design uploads belonging to that completed receipt become eligible.
- At **365 elapsed days after `completed_at`**, the private proposed themes/instructions, decisions, reviewer names and reasons expire.
- An order must still have `status = completed` and a valid completion timestamp when its row is locked. Pending, confirmed, preparing, ready-for-pickup and cancelled orders are never swept, even if they contain an old completion timestamp.
- A retention hold or an unresolved `pending`/`applying` design request protects the entire order.
- Shared files remain while another order or unexpired history needs them. Catalog images are also protected. Payment receipts are outside the allowed cleanup disks/directories.

No database tables, columns or migrations are added. Cleanup **does not update or delete database rows**, including the existing image metadata. Order details, images' original names/paths, financial records and reports remain unchanged; expired photos no longer appear as preview links.

## Private state and safety

The existing receipt identity and private design state also serve orders without a design-change request. Optional `retention` metadata records `photos_expired_at`, `history_expired_at`, a complaint `hold`, and `pending_deletions`.

Expiration and the retry list are saved atomically before file deletion. Failed/interrupted deletion is retried on the next run. One year cleanup leaves a small valid state with an empty request history and an expiration date; it does not remove the registration or lock files. A normal missing/corrupt registered record continues to fail closed.

The job uses a filesystem mutex, locks each eligible database order row before its private file lock, and rechecks completion, age and holds. Missing/corrupt registered history stops deletion because the unknown record might protect a shared file. Unknown or recycled receipt identities remain protected.

Only exact recorded `order_images/<id>/...` and `staff/<id>/...` files, plus this receipt's `design-changes/<identity>/...` private namespace, are candidates. Allowed files are JPG, JPEG, PNG and WebP on local reference disks. Paths are resolved against the actual disk root; traversal, redirected directories, links and non-reference destinations are refused. Empty directories are removed with non-recursive `rmdir` inside the verified order namespace. Foreign namespaces, other business files and the metadata/lock directories are never recursively deleted.

Customer/staff pages show expiry messages instead of broken thumbnails. Protected expired-photo routes return HTTP 410. Old public static URLs can return the web server's normal 404 once deleted. The small live revision includes expiry and hold information, so open pages notice cleanup using the existing refresh behavior.

## Commands and daily scheduler

Preview eligible files without expiring history or deleting uploads:

~~~sh
php artisan orders:prune-references
~~~

Apply the configured policy:

~~~sh
php artisan orders:prune-references --apply
~~~

Output includes orders inspected, expiration counts, eligible/deleted files, file bytes, protected files, skipped orders and errors. The apply command logs its summary to the existing Laravel log. An error returns a nonzero status; an overlapping run exits successfully without cleanup. Preview may create the job's lock file, but does not change saved order metadata, photos or database rows. Shared-file estimates are conservative because preview does not persist simulated expirations.

Protect an order before its photos expire, for example while investigating a complaint:

~~~sh
php artisan orders:reference-hold ORD-EXACT-ORDER-NUMBER --reason="Customer complaint under review"
php artisan orders:reference-hold ORD-EXACT-ORDER-NUMBER --release
~~~

These commands are for trusted server operators. They store the hold in private files, never database fields. A hold cannot restore photos that already expired.

Laravel schedules the apply command at **02:00 in `bakery.business_timezone`**. The hosting server must invoke `php artisan schedule:run` every minute. On Windows, configure Task Scheduler with the deployed PHP executable, the absolute deployed `artisan` path and the project directory; run hidden and use an account that can access the durable uploads. On Linux, use the host's standard Laravel cron entry. No queue worker or Codex automation is required.

Only register the scheduler against the intended deployed checkout/storage. Do not install a production-deletion task against disposable QA fixtures or run an apply command as a verification step against business data. Review a preview before enabling the deployment's scheduler.

## Backups and operations

Back up the database, reference uploads and private state together, and preserve them across deployments. Restoration should use a matching state/database/upload snapshot. Restoring only photo files after expiration does not restore their previews.

The live-server policy does not erase independent backups; those follow their own agreed rotation. Photos needed for repeat orders should be retained under a hold before expiry or managed separately with the owner's agreement.

Run errors and unexplained holds should be investigated from Laravel logs; do not delete registration/hold markers to force cleanup. No automatic cleanup of active/cancelled orders, orphaned receipt identities or general staff/public draft storage is included.
