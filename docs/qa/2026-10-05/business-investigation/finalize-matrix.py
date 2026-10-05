"""One-time finalization of document statuses from recorded B/C evidence only."""
from pathlib import Path
import re
import xml.etree.ElementTree as ET

here = Path(__file__).resolve().parent
root = here.parents[3]
assert 'Exact evidence / remaining limit' not in (here/'matrix.md').read_text(encoding='utf-8-sig'), 'Matrix already finalized; preserve it'
regression = (here/'evidence/selected-test-methods.txt').read_text(encoding='utf-8-sig').splitlines()
qa_source = (here/'tests/BusinessInvestigationTest.php').read_text(encoding='utf-8-sig')
additions = re.findall(r'public function (test_\w+)\(', qa_source)
index = ['# Executed B/C evidence index', '',
         'These IDs identify the exact recorded test methods; they do not add tests or expand their scope. '
         'R = selected existing regression, Q = separate targeted QA. See [report](report.md) for commands/isolation/limits.', '']
for prefix, names, junit in [('R', regression, 'business-regression'), ('Q', additions, 'business-qa')]:
    cases = {n.attrib['name']: n for n in ET.parse(here/f'evidence/{junit}-junit.xml').getroot().findall('.//testcase')}
    assert len(cases) == len(names)
    index += [f'## {prefix} — recorded methods', '', '| ID | Exact method | Actual result | Test source |', '|---|---|---|---|']
    for i, name in enumerate(names, 1):
        case = cases[name]
        result = 'Verified fail' if case.find('failure') is not None or case.find('error') is not None else 'Verified pass'
        source = here/'tests/BusinessInvestigationTest.php' if prefix == 'Q' else root/(case.attrib['class'].replace('Tests\\', 'tests\\').replace('\\', '/')+'.php')
        assert source.is_file(), source
        index.append(f'| {prefix}{i:02} | `{name}` | {result} | [source](<{source.as_posix()}>) |')
    index += ['', f'Actual run: [output](evidence/{junit}.txt), [JUnit](evidence/{junit}-junit.xml).', '']
(here/'execution-evidence-index.md').write_text('\n'.join(index)+'\n', encoding='utf-8')

proof = {
 'B01': ('Verified pass', 'R08/R15: active/inactive availability and invalid selection; SQLite server only.'),
 'B02': ('Verified pass', 'Route register + Q11/R22 establish staged endpoints/ordinary flow; browser usability Not tested (A).'),
 'B03': ('Verified pass', 'R09/R17/Q11: multi-line payload, quantities/themes, inclusions/extras; fixtures only.'),
 'B04': ('Verified pass', 'Q11: two distinct lines, edit first, remove second, total 3000 and correct saved theme; replay/back deferred D.'),
 'B05': ('Verified pass', 'R12/R20: matching order-detail association; file privacy/restrictions Not tested (D).'),
 'B06': ('Verified pass', 'R13/R15/R16/R17: server catalog prices, included quantities, independent paid extras and invalid selection.'),
 'B07': ('Verified pass', 'R06/R14/R18/R26: saved names/prices/inclusions survive edits; migration preservation Not tested (D).'),
 'B08': ('Verified pass', 'R20/R22 and shared-field source: normal staff ordering/customer selection and creator; narrowed role enforcement deferred D.'),
 'B09': ('Verified pass', 'R10/R11/R19 and Q12: recorded valid normalization/matching/create/edit cases; empty-contact failure separately B10.'),
 'B10': ('Verified fail', 'Q04/Q05; [staff](evidence/customer-phone.json), [public](evidence/public-phone.json); BC-002: empty saved contact.'),
 'B11': ('Verified pass', 'R01–R07: exact deposit/balance and recorded lifecycle transitions; no claim of exhaustive states/concurrency.'),
 'B12': ('Verified pass', 'Q09/R21: unverified money zero; reject/replace; account_checked required; verified 500 confirms. Privacy/replay deferred D.'),
 'B13': ('Verified fail', 'Q03; [observation](evidence/customer-cancellation.json); ledger retains 1000/no refund, reporting recognizes only 500 (BC-001).'),
 'B14': ('Verified pass', 'Q10: 1000 full pending refund, excluded until completed, net collections then zero; no real transfer/replay.'),
 'B15': ('Verified pass', 'Q07: explicit selected date retains four active statuses, excludes terminal statuses; no delivery module required (OD-3).'),
 'B16': ('Verified pass', 'Q01: ordinary receipt/usage/waste/current-version count by both fixture roles; quantities and kg/piece units preserved. Browser/direct auth deferred.'),
 'B17': ('Verified pass', 'Q08: excessive usage and missing waste reason rejected; missing count reason split into B27.'),
 'B18': ('Verified pass', 'Q08: unit change rejected; observed 15 minus historical net 5 gives opening 10; source legacy_reconciliation. Visual label/migration readiness deferred.'),
 'B19': ('Verified pass', 'Q01: one reversal linked to original grouped receipt, final quantities 8/8; repeated correction/history enforcement deferred D.'),
 'B20': ('Verified pass', 'OrderService/InventoryService + route/model trace: no configured recipe deduction/valuation; Q01 confirms no implicit expense. Source scope only for absence.'),
 'B21': ('Verified pass', 'Q02: Assistant creates 250, Owner edits to 300 then voids; three audits, preserved creator/editor and reason, report 300→0. Missing-reason/stale attempts untested.'),
 'B22': ('Verified pass', 'Filtered-expense and ordinary expense tests; exact IDs assigned below.'),
 'B23': ('Verified fail', 'Source-only CR-01: shared staff routes/gates grant broader management than OD-1. Direct Owner/Assistant/guest requests remain Not tested (D).'),
 'B24': ('Verified pass', 'Q12: normal Owner product/option/add-on/settings/customer/user create/update fixture; catalog deletions and complete CRUD coverage split into B28.'),
 'C01': ('Verified pass', 'R26 independent fixture: 2×1000 + 3×100 extras = 2300 saved sales/package totals; sampled MariaDB oracle also agrees.'),
 'C02': ('Verified pass', 'R25/R26/Q06/Q10: verified 3500 − completed refund 700 = 2800; refund-only net −500; explicit bounds and regular SELECT oracle.'),
 'C03': ('Verified fail', 'Q03 + independent CSV Decimal check; retained final payment omitted, 500 instead of 1000 (BC-001).'),
 'C04': ('Verified fail', 'Q02/Q03: valid expense/void component passes, but retention undercount makes overall operational result wrong. Same BC-001, not a new defect.'),
 'C05': ('Verified pass', 'R26/Q06 + Decimal CSV check: summary/trend/category/method/package sums agree in recorded fixtures; current-policy retention still fails C03. Actual chart rendering Not tested.'),
 'C06': ('Verified pass', 'R23/R26/Q03: query-period round-trip, HTTP totals/records/CSV agree; an internally matching wrong retention remains C03. Browser drill-down usability Not tested.'),
 'C07': ('Verified pass', 'R23–R25: recorded month/range/year/leap/preset/invalid/timezone/fractional boundary cases; no claim all combinations tested.'),
 'C08': ('Verified pass', 'R27/Q06: zero-filled long-period report and refund-only negative net reconcile.'),
 'C09': ('Verified pass', 'Source report/export definitions distinguish current/all-dates snapshots and operational result from complete profit; visual/chart interpretation Not tested (A).'),
 'C10': ('Verified pass', 'Q01/Q08 + source trace: quantities preserved per supply/unit; no configured cost valuation. Rendering/interpretation deferred A.'),
}
# Q IDs follow the source declaration order, as the generated index documents.
assert additions[0] == 'test_ordinary_receiving_usage_count_and_linked_correction_have_no_implicit_expenses'
assert additions[2] == 'test_expense_filtered_total_covers_matching_records'
assert additions[3] == 'test_fully_paid_customer_cancellation_recognizes_all_verified_retained_money'
# Use explicit name lookup so prose cannot silently drift from the recorded source.
q = {name: f'Q{i:02}' for i, name in enumerate(additions, 1)}
for key, (status, note) in list(proof.items()):
    # The initial shorthand above predates insertion of the filtered-expense method.
    note = re.sub(r'Q(\d{2})', lambda m: f'Q{int(m.group(1))+1:02}' if int(m.group(1)) >= 3 else m.group(0), note)
    proof[key] = (status, note)
proof['B22'] = ('Verified pass', f"{q['test_expense_filtered_total_covers_matching_records']}/{q['test_expense_creator_editor_void_and_active_totals']}: 25 matching×10 = 250 across pages; other category excluded; void total zero. Not a performance test.")

text = (here/'matrix.md').read_text(encoding='utf-8-sig')
text = text.replace('**OD-3** pickup only, external delivery coordination.', '**OD-3** pickup only, external delivery coordination; **OD-4** phones currently used, with tablet/desktop responsive support still required.')
text = text.replace('Initial statuses below are resolved against exact fresh evidence before delivery.', 'Statuses below describe only the recorded evidence. **Verified pass** is bounded to the stated server/source case; it is not a full module, UI, security or reliability pass. R/Q method IDs resolve in [execution-evidence-index.md](execution-evidence-index.md).')
text = text.replace('| Verification / execution boundary | Status |', '| Verification / execution boundary | Status | Exact evidence / remaining limit |')
text = text.replace('|---|---|---|---|---|---|\n| B01', '|---|---|---|---|---|---|---|\n| B01')
out = []
for line in text.splitlines():
    match = re.match(r'\| ((?:B|C)\d+) \|', line)
    if match and match[1] in proof:
        cells = line.split('|')[1:-1]
        status, note = proof[match[1]]
        cells[5] = f' {status} '
        line = '|'+'|'.join(cells)+f'| {note} |'
    out.append(line)
text = '\n'.join(out)+'\n'
text = text.replace('Shared grouped receipt/usage/waste/count with displayed fixed units', 'Shared grouped receipt/usage/waste/count preserves fixed per-supply units')
text = text.replace('Reject negative resulting stock, require waste/count explanation', 'Reject negative resulting stock and require waste explanation')
text = text.replace('Owner catalog/options/add-ons/settings and user-management operations discovered', 'Normal Owner catalog/options/add-ons/settings and user/customer create/update work')
text = text.replace('Source inventory + selected normal option/inclusion tests; remaining CRUD cases honest gaps', 'Dedicated normal Owner HTTP create/update fixture; other CRUD/security checks not implied')
new_rows = f'''| B25 | Dashboard today's pickups uses the bakery calendar | M1-P020/042; M3-P131; configured pickup timezone | DashboardController@index | Dedicated frozen UTC/Manila boundary fixture | Verified fail | {q['test_dashboard_today_pickups_uses_the_bakery_calendar']}; [observation](evidence/dashboard-pickup-date.json); selects yesterday (BC-003). Actual browser presentation deferred A. |
| B26 | Past pickup dates rejected according to bakery calendar | Existing CatalogOrderRules contract; M1-P020/035; M3-P131 | CatalogOrderRules; OrderService | Dedicated public HTTP frozen-calendar fixture | Verified fail | {q['test_new_public_order_rejects_a_past_bakery_pickup_date']}; [observation](evidence/past-pickup-date.json); previous bakery day accepted (BC-004). Staff boundary not executed. |
| B27 | Missing physical-count reason rejected | UP-2 §3 | InventoryService::post notes required_if | Source rule present; isolated missing-note count request still needed | Not tested | Successful reasoned count covered in {q['test_ordinary_receiving_usage_count_and_linked_correction_have_no_implicit_expenses']}; missing count note not submitted in this phase. Do not infer runtime rejection from the source alone. |
| B28 | Other catalog/user/customer CRUD and missing expense-reason variants | M3-P366; UP-2 §4 | Discovered routes/controllers/services | Separate scoped fixtures needed for deletion/other unexercised variants | Not tested | Normal create/update and reasoned expense sequence pass; this is an explicit coverage gap, not a confirmed defect. Destructive checks only on disposable fixtures. |

'''
text = text.replace('## Other-agent execution register — all initially Not tested (user-deferred)', new_rows+'## Other-agent execution register — Not tested (user-deferred)')
# Avoid separating the extra B rows from the B/C table with a blank line.
text = text.replace('\n\n| B25', '\n| B25')
text = text.replace('| Decision / dependency |', '| Decision / dependency | Status |')
text = text.replace('|---|---|---|---|---|\n| H4', '|---|---|---|---|---|---|\n| H4')
text = re.sub(r'(?m)^(\| H(?:4|A\d+|D\d+|F\d+) \|.+) \|$', r'\1 | Not tested |', text)
(here/'matrix.md').write_text(text, encoding='utf-8')
print('Finalized evidence index and matrix; no tests or product files executed/changed.')
