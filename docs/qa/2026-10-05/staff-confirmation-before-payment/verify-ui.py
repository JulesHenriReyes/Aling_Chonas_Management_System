from pathlib import Path
import json

evidence = Path(__file__).parent / 'verification' / 'evidence'
grid = json.loads((evidence / 'browser-grid.json').read_text())
checkout = json.loads((evidence / 'browser-checkout.json').read_text())
deposit = json.loads((evidence / 'browser-deposit-current.json').read_text())
interactions = json.loads((evidence / 'browser-interactions.json').read_text())
assert len(grid['records']) == 186 and not grid['failures']
assert len(checkout['records']) == 30 and not checkout['failures']
assert len(deposit['records']) == 6 and not deposit['failures']
assert len(interactions) == 4 and len(checkout['interactions']) == 3
records = [r for r in grid['records'] if not (r['role'] == 'owner' and r['state'] == 'approved' and r['scale'] == 1)]
records += deposit['records'] + checkout['records']
assert len(records) == 216
for r in records:
    assert r['overflow'] <= 1 and r['interLoaded']
    assert (evidence / r['screenshot']).is_file()
assert all(r['passed'] for r in interactions + checkout['interactions'])
result = {'captures': len(records), 'workflow_interactions': 7, 'failures': 0,
    'widths': sorted({r['width'] for r in records}), 'roles': sorted({r['role'] for r in records}),
    'text_200_percent_captures': sum(r['scale'] == 2 for r in records),
    'note': 'Six final deposit-caption captures supersede the earlier Owner approved captures.',
    'records': records, 'interactions': interactions + checkout['interactions']}
(evidence / 'ui-final-register.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps({k: v for k, v in result.items() if k not in ['records', 'interactions']}, indent=2))
