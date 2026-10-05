from pathlib import Path
import hashlib, json, subprocess, zipfile, xml.etree.ElementTree as ET

here = Path(__file__).resolve().parent
root = here.parents[3]
(here/'evidence').mkdir(exist_ok=True)
(here/'tests').mkdir(exist_ok=True)
for directory in ['storage/framework/views','storage/framework/sessions','storage/framework/cache','storage/logs']:
    (here/'runtime'/directory).mkdir(parents=True, exist_ok=True)
sources=[]
ns={'w':'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
for sid,name in [('M1','Milestone 1.docx'),('M2','Milestone 2 (Revised).docx'),('M3','Milestone 3 upd.docx')]:
    path=Path(r'C:\Users\User\Desktop\Company informations')/name
    with zipfile.ZipFile(path) as z:
        document=ET.fromstring(z.read('word/document.xml'))
        paragraphs=[{'id':f'{sid}-P{i:03}', 'text':''.join(p.itertext()) if False else ''.join(t.text or '' for t in p.findall('.//w:t',ns))}
            for i,p in enumerate(document.findall('.//w:body//w:p',ns),1)]
    paragraphs=[p for p in paragraphs if p['text'].strip()]
    sources.append({'id':sid,'path':str(path),'sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'paragraphs':paragraphs})
(here/'evidence/company-original-sources.json').write_text(json.dumps(sources,ensure_ascii=False,indent=2),encoding='utf-8')
tracked=subprocess.check_output(['git','ls-files'],cwd=root,text=True).splitlines()
def manifest():
    return {p:hashlib.sha256((root/p).read_bytes()).hexdigest() for p in sorted(set(tracked+['.env'])) if (root/p).is_file()}
(here/'evidence/production-file-baseline.json').write_text(json.dumps(manifest(),indent=2),encoding='utf-8')
base=(here.parent/'test-bootstrap.php').read_text(encoding='utf-8').replace('dirname(__DIR__, 3)','dirname(__DIR__, 4)')
(here/'test-bootstrap.php').write_text(base,encoding='utf-8')
xml=(here.parent/'phpunit-audit.xml').read_text(encoding='utf-8').replace('../../../tests/','../../../../tests/').replace('/2026-10-05/runtime/','/2026-10-05/business-investigation/runtime/')
(here/'phpunit-business.xml').write_text(xml,encoding='utf-8')
print(json.dumps({'date':'2026-10-05','revision':subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip(),
    'original_docs':[{'id':s['id'],'sha256':s['sha256'],'paragraphs':len(s['paragraphs'])} for s in sources], 'tracked_file_count':len(tracked)},indent=2))
