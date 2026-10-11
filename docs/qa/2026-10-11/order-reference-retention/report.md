# Completed-order reference retention verification

Implemented the user's 11 October 2026 restriction: **only orders still marked completed** are eligible. The 60-day photo and 365-day design-history periods use the existing completion timestamp. The source company documents contain no retention duration; these periods are user decisions.

## Applied design brief

Customers and staff should understand why an old reference photo is unavailable while continuing to read the saved order, payment ledger and design decision. Reuse the bakery's existing muted text, cards, spacing and history disclosures. Put retention guidance beside the order items and replace expired comparison thumbnails with clear text. Keep the current desktop columns and mobile stack.

The pre-build critique identified broken previews and an apparently missing history as the main risks. The implementation adds expiry states for both, preserves the ordinary saved theme/instructions, and adds no action buttons. One small refinement added a bottom gap to the retention paragraph so it separates from the first package heading.

## Results

| Verification | Result |
| --- | --- |
| Full guarded PHPUnit suite | **252 tests, 3,150 assertions**, zero failures/errors; [JUnit](phpunit.xml) |
| Customer/staff browser checks | **50 checks**, zero JavaScript errors; [results](browser-results.json) |
| Changed-text accessibility checks | **12 checks**; [results](accessibility-results.json) |
| Real MariaDB contention | **4 cases, 5 workers** passed; [results](mariadb-results.json) |
| Windows junction refusal | Target bytes unchanged; no expiration recorded; [results](junction-results.json) |
| PHP syntax and Git whitespace | Changed/new retention PHP files pass syntax checks; no whitespace errors |
| Database integrity | Tests compare all database tables and schema before/after cleanup: no rows or schema changed |

Feature tests cover completion status and exact age boundaries; missing/future completion dates; ordinary customer/staff references; approved, historical and abandoned design uploads; preview mode; one-year history expiration; pending/applying requests; holds and release; shared files and catalog reuse; unreadable/missing registered history; unsafe paths; recycled identities; interrupted deletion retry; duplicate jobs; protected links; expiry UI/JSON; live revisions; and daily scheduling.

MariaDB tests hold actual InnoDB rows while cleanup waits. They verify that a status changed to cancelled, a newly added hold, or a newly moved completion date is rechecked after acquiring the row lock. Concurrent jobs delete a file once; the other job exits through the filesystem mutex.

The Windows check creates a junction wholly inside temporary synthetic storage. Cleanup refuses it before saving expiration or unlinking bytes. The junction entry is then removed non-recursively, and the target's hash is checked again.

## Rendered review

Actual customer and authenticated staff routes were captured at **1440 × 900** and **390 × 844**, using full-page screenshots. Baseline images show the synthetic photo before cleanup; final images cover photo expiry, history expiry and an active review hold. Every captured image was inspected directly.

| Criterion | Verdict and evidence |
| --- | --- |
| Hierarchy and balance | Pass: status, order contents and financial totals retain their emphasis; expiry guidance stays beside references |
| Typography and readability | Pass: long package names and expiry/decline text wrap in desktop and mobile captures |
| Spacing and alignment | Pass: the paragraph's bottom gap separates it from package details; existing card alignment is preserved |
| Brand and component consistency | Pass: existing bakery colors, type, borders and disclosure treatments are reused |
| Responsive layout | Pass at the two tested sizes: no page overflow or broken images; the existing payment table retains its contained scrolling |
| Content and state quality | Pass: expired photos/history have explicit explanations; held photos remain present; declined reasons remain until history expiry |
| Overall polish | Pass for affected regions: complete notice states and no unresolved visual defects in the inspected captures |

Changed notice text is `#68584b` on white: measured contrast **6.80:1**, exceeding the WCAG 2.2 AA normal-text threshold of 4.5:1. At 200% root text size the notice grows from 13 px to 26 px and wraps inside its mobile container. On customer and staff pages, Tab reaches the existing history summary, its focus outline is visible, and Enter opens it. This is a scoped assessment, not an accessibility audit of the entire application.

Latest inspected examples:

- Photo expiry: [customer desktop](customer-photos-1440.png), [customer mobile](customer-photos-390.png), [staff desktop](staff-photos-1440.png), [staff mobile](staff-photos-390.png).
- History expiry: [customer desktop](customer-history-1440.png), [customer mobile](customer-history-390.png), [staff desktop](staff-history-1440.png), [staff mobile](staff-history-390.png).
- Review hold: [customer desktop](customer-hold-1440.png), [customer mobile](customer-hold-390.png), [staff desktop](staff-hold-1440.png), [staff mobile](staff-hold-390.png).

## Isolation and deployment

All mutating verification uses the actual-PDO disposable database guard, temporary reference/private storage, synthetic customers and synthetic one-pixel PNGs. The browser uses real CSRF and sessions after isolation is proven. MariaDB uses a fresh temporary data directory and dedicated port 33317, verified against the running server before schema creation and shutdown. Business data and existing real uploads were never cleaned.

The preview needed two fixture corrections before capture succeeded: the login selector was aligned with the actual button and the router was given a contained mapping for temporary public images. A required catalog field was added to the MariaDB fixture. These were verification setup problems.

Active and cancelled orders are deliberately excluded, including their unused drafts. Shared references, review holds and unresolved design requests can defer deletion. Existing order/image metadata, receipts, payments and reports are preserved.

The daily 02:00 Laravel job is registered in the repository. The deployment must still invoke `php artisan schedule:run` every minute. No production scheduler was installed or apply command run. See [operation and scheduler setup](../../../order-reference-retention.md).
