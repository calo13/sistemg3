"""Build the beginner's guide; reads documentation only, without app/DB access."""
from pathlib import Path
from xml.sax.saxutils import escape
import hashlib
import json
import re

from pypdf import PdfReader
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle,
)

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "docs/guia-desde-cero.md"
OUTPUT = ROOT / "output/pdf/memorylab-guia-desde-cero.pdf"
QA = ROOT / "tmp/pdfs/desde-cero"
QA.mkdir(parents=True, exist_ok=True)
OUTPUT.parent.mkdir(parents=True, exist_ok=True)
for name, file in [("Arial", "arial.ttf"), ("Arial-Bold", "arialbd.ttf")]:
    pdfmetrics.registerFont(TTFont(name, str(Path("C:/Windows/Fonts") / file)))
pdfmetrics.registerFontFamily("Arial", normal="Arial", bold="Arial-Bold")

NAVY = colors.HexColor("#16324f")
MUTED = colors.HexColor("#526171")
WIDTH, HEIGHT = A4
CONTENT = WIDTH - 90
styles = {
    "title": ParagraphStyle("Title", fontName="Arial-Bold", fontSize=22,
                            leading=27, spaceAfter=10, textColor=NAVY),
    "h2": ParagraphStyle("Heading2", fontName="Arial-Bold", fontSize=17,
                         leading=22, spaceBefore=5, spaceAfter=10,
                         textColor=NAVY, keepWithNext=True),
    "h3": ParagraphStyle("Heading3", fontName="Arial-Bold", fontSize=11.5,
                         leading=15, spaceBefore=6, spaceAfter=7,
                         keepWithNext=True),
    "body": ParagraphStyle("Body", fontName="Arial", fontSize=10.6,
                           leading=14.5, spaceAfter=8),
    "list": ParagraphStyle("List", fontName="Arial", fontSize=10.6,
                           leading=14.5, leftIndent=14, firstLineIndent=-14,
                           spaceAfter=5),
    "cell": ParagraphStyle("Cell", fontName="Arial", fontSize=9.5, leading=12.5),
    "head": ParagraphStyle("Head", fontName="Arial-Bold", fontSize=9.5,
                           leading=12.5, textColor=colors.white),
}


def inline(text):
    return re.sub(r"\*\*([^*]+)\*\*", r"<b>\1</b>", escape(text))


def make_table(rows):
    columns = len(rows[0])
    ratios = {2: [.57, .43], 3: [.29, .25, .46], 4: [.23, .18, .25, .34]}[columns]
    if rows[0][0] == "Paso":
        ratios = [.09, .27, .64]
    elif rows[0][0] == "Offset en bytes":
        ratios = [.18, .47, .35]
    elif rows[0][0] == "Técnica":
        ratios = [.22, .28, .50]
    cells = [[Paragraph(inline(cell), styles["head" if r == 0 else "cell"])
              for cell in row] for r, row in enumerate(rows)]
    table = Table(cells, colWidths=[CONTENT * v for v in ratios], repeatRows=1)
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1),
         [colors.white, colors.HexColor("#f2f5f9")]),
        ("GRID", (0, 0), (-1, -1), .5, colors.HexColor("#d9d9d9")),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    return table


def page_chrome(canvas, doc):
    canvas.saveState()
    canvas.setFont("Arial", 8)
    canvas.setFillColor(MUTED)
    if doc.page > 1:
        canvas.drawString(45, HEIGHT - 23, "MemoryLab | Aprende desde cero")
    canvas.drawString(45, 24, "UMG | Guía práctica | 21 de octubre de 2026")
    canvas.drawRightString(WIDTH - 45, 24, str(doc.page))
    canvas.restoreState()


source = SOURCE.read_text(encoding="utf-8")
assert all(c not in source for c in ["\u2011", "\u2013", "\u2014"])
story, lines, index = [], source.splitlines(), 0
while index < len(lines):
    line = lines[index].strip()
    index += 1
    if not line:
        continue
    if line == "<!-- pagebreak -->":
        story.append(PageBreak())
        continue
    if line.startswith("|"):
        rows = []
        while True:
            row = [v.strip() for v in line.strip("|").split("|")]
            if not all(re.fullmatch(r":?-+:?", v) for v in row):
                rows.append(row)
            if index >= len(lines) or not lines[index].strip().startswith("|"):
                break
            line = lines[index].strip()
            index += 1
        assert all(len(r) == len(rows[0]) for r in rows)
        story.extend([make_table(rows), Spacer(1, 9)])
        continue
    prefix = next((p for p in ["### ", "## ", "# "] if line.startswith(p)), None)
    if prefix:
        key = {"# ": "title", "## ": "h2", "### ": "h3"}[prefix]
        story.append(Paragraph(inline(line[len(prefix):]), styles[key]))
    else:
        key = "list" if re.match(r"^\d+\. ", line) else "body"
        story.append(Paragraph(inline(line), styles[key]))

doc = SimpleDocTemplate(str(OUTPUT), pagesize=A4, leftMargin=45, rightMargin=45,
                       topMargin=43, bottomMargin=43,
                       title="MemoryLab: aprende desde cero",
                       author="Universidad Mariano Gálvez - Grupo 3")
doc.build(story, onFirstPage=page_chrome, onLaterPages=page_chrome)
reader = PdfReader(str(OUTPUT))
pages = [p.extract_text() or "" for p in reader.pages]
assert len(pages) == 6, f"Expected six pages, got {len(pages)}"
text = "\n".join(pages)
for required in ["Mi práctica 01", "admin@admin.com", "2476", "1124", "12784",
                 "692", "65536", "1024", "Page Faults", "Mi práctica segmentos 01"]:
    assert required in text, required
assert "\ufffd" not in text and "\u25a0" not in text
assert 4 // 1 == 4 and 6 // 1 == 6
assert 2 * 1024 + 428 == 2476 and 1024 + 100 == 1124
assert 16384 - (1200 + 800 + 600 + 1000) == 12784
assert 8192 - 7500 == 692
report = {"pdf": str(OUTPUT), "source": str(SOURCE), "pages": len(pages),
          "sha256": hashlib.sha256(OUTPUT.read_bytes()).hexdigest(),
          "source_sha256": hashlib.sha256(SOURCE.read_bytes()).hexdigest(),
          "page_text_characters": [len(p) for p in pages],
          "visual_review": "pending", "application_or_database_operations": False}
(QA / "build-qa.json").write_text(json.dumps(report, ensure_ascii=False, indent=2),
                                   encoding="utf-8")
print(json.dumps(report, ensure_ascii=False, indent=2))
