# Report export verification

The Reports page provides CSV and PDF downloads for the selected report period. Both exports use the existing financial report values and the same five presentation sections. Financial calculations, role permissions, and business data were preserved.

CSV includes business and period metadata, readable section headings, numeric amounts, UTF-8 text, consistent seven-cell records, and formula protection for dynamic labels. PDF uses an A4 landscape layout, readable summaries, repeated table headings, and page numbers.

Validation completed:

- Reporting regression suite: 16 tests and 419 assertions passed.
- Final export endpoint tests: 4 tests and 119 assertions passed.
- All CSV data rows and PDF amounts reconciled with the report source.
- All 16 rendered PDF pages were inspected: populated month 3 pages, empty month 3 pages, yearly report 2 pages, and long-name and large-amount stress report 8 pages.
- Access checks and selected-period download links passed.
- Before and after business-table fingerprints matched. Stress records were presentation fixtures only and were never inserted into the business database.
- Scoped Git whitespace checks passed. Unrelated changes were preserved.

`sample-audit.json` records report values and unchanged database fingerprints. `verification.json` records PDF text and pagination checks. The sample PDFs, CSVs, and page images are QA intermediates.
