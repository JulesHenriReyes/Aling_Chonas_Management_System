from pathlib import Path
import csv, io, json, re, subprocess
from decimal import Decimal
from pypdf import PdfReader
import pdfplumber

root = Path(__file__).parent
samples = root / "samples"
audit = json.loads((root / "sample-audit.json").read_text(encoding="utf-8"))
poppler = r"C:\Users\User\.cache\codex-runtimes\codex-primary-runtime\dependencies\native\poppler\Library\bin\pdftoppm.exe"
results = {}
for name in ("populated", "empty", "monthly", "stress"):
    path = samples / f"{name}.pdf"
    reader = PdfReader(path)
    texts = [page.extract_text() for page in reader.pages]
    normalized = re.sub(r"\s+", " ", " ".join(texts))
    for page_index, text in enumerate(texts, 1):
        assert f"Page {page_index} of {len(texts)}" in text, (name, page_index, "missing footer")
        assert "Aling Chona Cakes & Cupcakes" in text, (name, page_index, "missing header")
    assert "Financial report" in normalized and "How to read this report" in normalized
    assert "not accounting profit" in normalized
    assert "\uFFFD" not in normalized and "\u25A0" not in normalized
    with pdfplumber.open(path) as pdf:
        for index, page in enumerate(pdf.pages, 1):
            for char in page.chars:
                if char["text"].strip():
                    assert char["x0"] >= 46 and char["x1"] <= page.width - 43, (name, index, "horizontal overflow", char["text"], char["x0"], char["x1"])
                    assert char["top"] >= 16 and char["bottom"] <= page.height - 12, (name, index, "vertical overflow")
    if name != "stress":
        rows = list(csv.reader(io.StringIO((samples / f"{name}.csv").read_text(encoding="utf-8-sig"))))
        assert all(len(row) == 7 for row in rows)
        report = audit["reports"][name]
        for section in report["sections"]:
            first = next(i for i, row in enumerate(rows) if row[0] == section["title"])
            assert rows[first + 1][:len(section["headers"])] == section["headers"]
            for i, expected in enumerate(section["rows"]):
                actual = rows[first + 2 + i]
                for column, (value, fmt) in enumerate(zip(expected, section["formats"])):
                    if fmt == "money":
                        assert Decimal(actual[column]) == Decimal(str(value)), (name, section["title"], "CSV amount mismatch")
                        assert f"{float(value):,.2f}" in normalized, (name, section["title"], "PDF missing amount", value)
                    elif fmt == "integer":
                        assert int(actual[column]) == int(value)
                    else:
                        assert actual[column] == str(value)
                        assert re.sub(r"\s+", " ", str(value)) in normalized, (name, section["title"], value)
            if section["title"].endswith("financial trends"):
                for row in section["rows"]:
                    assert normalized.count(row[0]) == 1, (name, "missing or duplicated trend row", row[0])
    else:
        assert "Niño Celebration Package" in normalized and "3,475,802,507.80" in normalized
        assert "-1,524,196,743.19" in normalized
        assert all(f"Package {index} with" in normalized for index in range(1, 36))
    subprocess.run([poppler, "-r", "120", "-png", str(path), str(samples / f"{name}-verified-page")], check=True)
    results[name] = {"pages": len(texts), "all_values_present": True, "headers_footers_valid": True, "text_inside_page": True}
assert audit["business_data_unchanged"]
(root / "verification.json").write_text(json.dumps(results, indent=2), encoding="utf-8")
print(json.dumps(results, indent=2))

