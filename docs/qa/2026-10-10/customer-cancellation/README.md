# Instant customer cancellation

The user's 10 October clarification authorizes instant cancellation from the private public-order page. Original company documents establish the deposit and Owner verification; the earlier confirmed deposit-retention rule is preserved. See [company rules](../../../company-rules.md).

The outlined secondary control sits below the payment/status card and above contact details. Native details expands an inline explanation, a required acknowledgement checkbox, and a final submit button. Verified deposits show the retained amount. Unpaid reported transfers, awaiting receipts, irregular payments, fully paid orders and completed orders cannot bypass Owner checks. No new financial entries or refunds are created. Shared cancellation logic retains the Owner authorization gate and rechecks current state under an order lock.

## Checks

- Guarded disposable SQLite: **31 tests, 418 assertions passed** for PublicOrderCancellationTest, OrderAndPaymentBusinessRulesTest and StaffPackageWorkspaceTest.
- JavaScript: **13 tests passed**.
- Browser: **52 checks passed**, using real CSRF/session-protected requests against the guarded temporary preview. Desktop 1440×900 and mobile 390×844 cover pending, approved unpaid, verified deposit and receipt reconciliation. Unpaid and paid-deposit cancellation complete immediately; repeat submissions redirect safely. Staff polling reflects cancellation without manual refresh.
- Desktop/mobile screenshots directly inspected: [Desktop collapsed](deposit-1440-closed.png), [Desktop confirmation](deposit-1440-open.png), [Mobile confirmation](deposit-390-open.png). Screenshots cover the full page, including content below the first viewport.

| Visual criterion | Result | Evidence |
| --- | --- | --- |
| Hierarchy and balance | Pass | Cancellation remains a secondary outlined control beneath primary actions. |
| Typography and readability | Pass | Amount and no-refund warning remain readable and wrap within the sidebar. |
| Spacing and alignment | Pass | Existing card padding, widths and gaps preserved. |
| Brand and component consistency | Pass | Existing checkout cards, cocoa text and rounded controls reused. |
| Responsive layout | Pass | No horizontal overflow; confirmation button remains in bounds at both sizes. |
| Content and state quality | Pass | Explicit final cancellation and deposit warnings; blocked states direct customers to the Owner. |
| Overall polish | Pass | Closed and expanded controls fit existing sidebar flow. |

Keyboard Enter expands the native disclosure; required confirmation and actual cancellation submissions are verified. Final submit has a 44px minimum height and visible keyboard focus styling. These checks do not constitute a full accessibility audit; measured contrast and 200% text resizing were not assessed.

No business database was used. Unrelated catalog working-tree edits were preserved. Preview fixture paths and private links refer only to synthetic disposable orders.
