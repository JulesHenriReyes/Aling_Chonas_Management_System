# Design change verification — 11 October 2026

Implemented the user-approved post-submission design review using existing database columns/tables and private durable files. Business source documents and `docs/company-rules.md` were checked first. No business database was migrated or used for fixtures.

The design brief was to place a quiet secondary request action beside the saved design, keep financial/cancellation controls in place, show an explicit preparation hold, and provide a focused before/proposed comparison with stacked columns on phones. Existing cream/cocoa surfaces, type and controls were retained. Pending review is prominent; past decisions stay collapsed. Current design, submission feedback, required acknowledgement, upload feedback and inline review validation are present.

## Verification results

| Check | Result | Evidence |
| --- | --- | --- |
| Complete guarded SQLite regression | 233 tests, 2,975 assertions, zero failures/errors | [JUnit](phpunit.xml) |
| New design workflow coverage | Submission/idempotency, role gates, phase eligibility, hold enforcement, approval/decline/withdrawal, private references, cancellation, stale proposals, crash recovery, all-package rollback, no schema changes, recycled-ID isolation | [Feature tests](../../../../tests/Feature/DesignChangeTest.php) |
| Actual browser workflow | 41 checks passed; real CSRF/session handling and no script errors | [Results](browser-results.json), [script](browser-verification.mjs) |
| InnoDB concurrency | Five cases with ten separate workers; both workers demonstrably waited for the held order row lock | [Results](mariadb-results.json), [script](mariadb-concurrency.php) |
| Targeted accessibility and long content | Nine checks passed; keyboard summaries, visible input focus, contrast, 255-character unbroken theme, long instructions, zoom geometry | [Results](accessibility-results.json), [script](accessibility-verification.mjs) |
| Syntax and diff | Changed PHP implementation files linted; both scripts passed Node syntax checks; `git diff --check` passed | Run during final verification |

The full suite exposed two stale existing expectations: a cancellation test used the UTC date rather than the report's business date, and the tooltip test expected older link guidance. Those fixtures were corrected without changing reporting or cancellation policy. The private-link guidance now explicitly includes design requests.

The MariaDB fixture ran on a separate guarded temporary data directory at port 33317. Actual PDO schema, server port and data-directory identity were checked before migrations or fixture mutations. A duplicate-approval race exposed a short `applying` window after commit; review/withdrawal now settle that journal while holding both locks. The five final cases passed: duplicate submission, duplicate approval, two different submissions, approval versus withdrawal, and submission versus readiness.

Browser checks confirmed that the list receives pending badges without manual refresh, the Design Changes queue contains the right order, staff cannot advance preparation/readiness while held, and approval/withdrawal/decline automatically reach the other page. A customer cancellation confirmation stays open when a decision arrives and offers a separate update action. Uploaded test photos are a small synthetic PNG; no actual customer images/data were used.

## Rendered review

Baseline and final screenshots were captured from the actual application at 1440 × 900 and 390 × 844, then inspected directly. Two focused refinement rounds corrected the staff acknowledgement's label styling and long unbroken saved-design text wrapping. Screenshot capture also resets scroll/focus so sticky headers and a focused skip link do not distort full-page evidence. A transient resize was resolved by waiting for the responsive layout to settle; the payment table correctly scrolls inside its container.

| Criterion | Verdict | Evidence |
| --- | --- | --- |
| Hierarchy and balance | Pass | Secondary request beside saved design; hold and decision actions grouped with comparison |
| Typography/readability | Pass | Existing brand type; readable form labels and before/proposed copy; long content inspected |
| Spacing/alignment | Pass | Consistent panel padding and form gaps; checkbox copy aligns without forced bold styling |
| Brand/components | Pass | Existing cream/cocoa colors, rounded cards and button patterns |
| Responsive layout | Pass | Desktop comparison columns stack on phone; no document overflow; controls fit |
| Content/states | Pass | Pending, approved, declined, withdrawn/closed, upload and preserved-input update states exercised |
| Overall polish | Pass | No broken images or browser script errors in the final workflow |

Latest inspected evidence: [desktop saved order](saved-order-1440.png), [mobile saved order](saved-order-390.png), [desktop editor](editor-1440.png), [mobile editor](editor-390.png), [desktop staff comparison](staff-comparison-1440.png), [mobile staff comparison](staff-comparison-390.png), [mobile customer request](customer-pending-390.png), [approved](approved-mobile.png), [declined](declined-mobile.png), [long proposed content](long-content-mobile.png), [long approved content](long-approved-mobile.png), [200% desktop zoom geometry](editor-200-percent.png).

Targeted contrast measurements were 13.36:1 for form labels, 6.8:1 for helper text and 14.43:1 for primary-button text, above the 4.5:1 ordinary-text target. The zoom check used 720 CSS pixels rendered at 2× device scale to assess 200% desktop zoom geometry. This is a targeted assessment, not a full WCAG audit or verification of every viewport/browser.

## Performance and operating limits

| Ten displayed orders | Median metadata read + summary | 95th percentile | State bytes per order |
| --- | --- | --- | --- |
| No history | 3.832 ms | 9.193 ms | No state file |
| Ten past requests per order | 8.823 ms | 15.919 ms | 7,836 |
| One hundred past requests per order | 29.305 ms | 48.830 ms | 77,316 |

[Measured results](performance-results.json), [benchmark](performance.php). One hundred and one samples per scenario use fresh application reads with warm OS file caching; photos are excluded. These local metadata timings do not measure end-to-end page load, PHP startup, SQL, network, HTML size, production traffic or cold/distant storage.

The status response contains a 64-character revision in a small JSON object; the feature test asserts it stays below 128 bytes. Visible pages poll every five seconds and skip overlapping calls. This adds server requests even when nothing changes, though it avoids recurring full-page downloads and photo loading. The list retains its existing polling behavior. Hosting/traffic can still affect actual speed.

The private metadata and upload directories must survive deployment and be included in backups. Multiple app servers need shared storage supporting the same locks and atomic replacement. Historical and abandoned draft photos use disk space; no undocumented retention/deletion rule has been introduced. See [storage contract](../../../design-change-storage.md).

Unrelated working-tree edits to the icon component were preserved. Verification servers are stopped after completion; screenshots, scripts and result files remain available for review.
