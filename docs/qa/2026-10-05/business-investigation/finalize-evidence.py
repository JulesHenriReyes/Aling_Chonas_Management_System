from pathlib import Path
from decimal import Decimal
import csv, hashlib, json, subprocess, xml.etree.ElementTree as ET

here=Path(__file__).resolve().parent
root=here.parents[3]
baseline=json.loads((here/'evidence/production-file-baseline.json').read_text(encoding='utf-8'))
changed=[p for p,h in baseline.items() if not (root/p).is_file() or hashlib.sha256((root/p).read_bytes()).hexdigest()!=h]
outcomes={}
for kind in ['business-regression','business-qa']:
    suite=ET.parse(here/f'evidence/{kind}-junit.xml').getroot().find('testsuite')
    outcomes[kind]=dict(suite.attrib)
    outcomes[kind]['exit_status']=int((here/f'evidence/{kind}-exit.txt').read_text(encoding='utf-8-sig').strip())
    outcomes[kind]['failed_methods']=[n.attrib['name'] for n in suite.findall('.//testcase') if n.find('failure') is not None or n.find('error') is not None]
with (here/'evidence/customer-cancellation.csv').open(encoding='utf-8-sig',newline='') as file: rows=list(csv.DictReader(file))
summary=next(r for r in rows if r['section']=='summary')
metrics=['sales','gross_collections','refunds_completed','payment_collections','cancellation_income','expenses','operational_net_income','completed_order_count']
reconciliation={k:{'csv_summary':str(Decimal(summary[k])), 'csv_trend_sum':str(sum((Decimal(r[k]) for r in rows if r['section']=='trend'),Decimal(0)))} for k in metrics}
for k,values in reconciliation.items():
    assert Decimal(values['csv_summary'])==Decimal(values['csv_trend_sum']),k
observation=json.loads((here/'evidence/customer-cancellation.json').read_text())
assert Decimal(summary['cancellation_income'])==Decimal(str(observation['actual_drilldown_total']))
csv_result={'dataset':'Synthetic 1000 verified payment on a cancelled 1000 order; not company prices/records','metrics':reconciliation,
    'internal_reconciliation':'pass','current_owner_policy_expected_retention':'1000','actual_retention':summary['cancellation_income'],
    'business_policy_status':'Verified fail'}
(here/'evidence/csv-independent-decimal-check.json').write_text(json.dumps(csv_result,indent=2),encoding='utf-8')
result={'production_code_config_changed':changed,'current_revision':subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip(),
    'test_outcomes':outcomes,'csv_internal_reconciliation':'pass','csv_business_policy':'Verified fail'}
(here/'evidence/final-verification.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
assert not changed,'Production files changed during the audit'
print(json.dumps(result,indent=2))
