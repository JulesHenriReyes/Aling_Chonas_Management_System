from pathlib import Path
import json
import xml.etree.ElementTree as ET

directory = Path(__file__).parent
evidence = directory/'evidence'
read_json = lambda name: json.loads((evidence/name).read_text(encoding='utf-8-sig'))
preservation = read_json('preservation-reconciliation.json')
assert not preservation['business_differences'] and preservation['env_hash_unchanged']
assert not preservation['historical_cancellation_exceptions'] and not preservation['historical_overpayments']
assert not preservation['dependency_removals']

tests = {}
for filename, expected in [('final-regression-junit.xml',123),('final-presentation-junit.xml',11),('mariadb-acceptance-junit.xml',23)]:
    root = ET.parse(evidence/filename).getroot()
    cases = root.findall('.//testcase')
    assert len(cases)==expected, filename
    assert not root.findall('.//failure') and not root.findall('.//error') and not root.findall('.//skipped'), filename
    tests[filename] = {'tests':len(cases),'assertions':sum(int(case.get('assertions','0')) for case in cases)}

records = []
for width in [360,390,430,768,1024,1440]:
    grid = read_json(f'browser-grid-{width}.json')
    assert not grid['failures'], width
    records += [r for r in grid['records'] if 'screenshot' in r]
for name in ['browser-interactions.json','browser-terminal-states.json','browser-additional-evidence.json','redacted-payment-captures.json']:
    data = read_json(name)
    assert data['outcome'].startswith(('All','PASS')), name
    records += [r for r in data.get('records',data.get('results',[])) if 'screenshot' in r]
navigation = read_json('navigation-controls-after.json')
assert len(navigation)==8
for item in navigation:
    assert not item['small'] and item['escapeFocusRestored'] and item['pageOverflow']<=1
records += navigation
for record in records:
    assert (evidence/record['screenshot']).is_file(), record['screenshot']
    assert record['width'] in [360,390,430,768,1024,1440] and record['height']==844
    assert 'role' in record and 'browser' in record and 'state' in record and 'url' in record
    if '/order/payment/' in record['url']: assert '[synthetic-token-redacted]' in record['url']
contention = read_json('mariadb-contention.json')
assert len(contention['cases'])==8 and all(c['held_lock_blocked_both_workers'] for c in contention['cases'].values())
assert len(read_json('composer-audit.json')['advisories']['league/commonmark'])==2
assert read_json('composer-validation-exits.json')=={'StrictExit':1,'ValidateExit':0}
report = (directory/'report.md').read_text(encoding='utf-8')
for index in range(18): assert f'IP-{index:02}' in report
assert 'Deferred by Owner' in report
result = {'outcome':'PASS','tests':tests,'browser_capture_records':len(records),
    'unique_registered_screenshots':len({r['screenshot'] for r in records}),
    'regular_business_tables_unchanged':preservation['business_tables_checked'],
    'mariadb_contention_cases':8,'mariadb_worker_count':16,
    'scope':'IP-00 through IP-17 accepted; IP-07 deliberately deferred; limitations recorded in report.md'}
(evidence/'acceptance-register.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps(result))
