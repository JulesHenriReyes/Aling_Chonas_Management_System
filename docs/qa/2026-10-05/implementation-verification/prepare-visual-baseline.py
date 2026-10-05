from pathlib import Path
import tempfile
import subprocess
import json
import hashlib

root = Path(__file__).resolve().parents[4]
destination = Path(tempfile.mkdtemp(prefix='bakery-implementation-baseline-views-'))
revision = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root).decode().strip()
paths = subprocess.check_output(['git', 'ls-tree', '-r', '--name-only', revision, 'resources/views', 'public/css/bakery-ui.css', 'public/js/bakery-ui.js'], cwd=root).decode().splitlines()
hashes = {}
for name in paths:
    content = subprocess.check_output(['git', 'show', f'{revision}:{name}'], cwd=root)
    target = destination / name
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(content)
    hashes[name] = hashlib.sha256(content).hexdigest()
manifest = {'revision':revision, 'directory':str(destination), 'purpose':'Visual reference using original tracked view/CSS/JS files with current read-only controller data and disposable fixtures; does not claim old backend behavior', 'hashes':hashes}
(Path(__file__).parent / 'evidence/visual-baseline.json').write_text(json.dumps(manifest,indent=2))
print(f'Exported {len(paths)} tracked presentation files to disposable visual-reference directory.')
