"""Build the one-hour group exposition guide from its editable Markdown.

Uses the bundled Python PDF libraries. Reads documentation and fonts only;
never starts the application, creates scenarios, or changes database records.
"""
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
SOURCE = ROOT / "docs/guion-exposicion-60-minutos.md"
OUT = ROOT / "output/pdf/memorylab-guion-exposicion-60-minutos.pdf"
QA_DIR = ROOT / "tmp/pdfs/exposicion"
QA_DIR.mkdir(parents=True, exist_ok=True)
OUT.parent.mkdir(parents=True, exist_ok=True)

for face, file in [("Arial", "arial.ttf"), ("Arial-Bold", "arialbd.ttf"),
                   ("Arial-Italic", "ariali.ttf")]:
    pdfmetrics.registerFont(TTFont(face, str(Path("C:/Windows/Fonts") / file)))
pdfmetrics.registerFontFamily("Arial", normal="Arial", bold="Arial-Bold",
                             italic="Arial-Italic", boldItalic="Arial-Bold")

NAVY = colors.HexColor("#16324f")
BLACK = colors.HexColor("#000000")
MUTED = colors.HexColor("#526171")
RULE = colors.HexColor("#d9d9d9")
WHITE = colors.white
PALE = colors.HexColor("#f2f5f9")
WIDTH, HEIGHT = A4
LEFT = RIGHT = 45
CONTENT = WIDTH - LEFT - RIGHT

styles = {
    "title": ParagraphStyle("GuideTitle", fontName="Arial-Bold", fontSize=22,
                            leading=27, textColor=BLACK, spaceAfter=12),
    "h2": ParagraphStyle("GuideHeading2", fontName="Arial-Bold", fontSize=16,
                         leading=20, textColor=BLACK, spaceBefore=8,
                         spaceAfter=10, keepWithNext=True),
    "h3": ParagraphStyle("GuideHeading3", fontName="Arial-Bold", fontSize=11.6,
                         leading=15.5, textColor=BLACK, spaceAfter=9,
                         keepWithNext=True),
    "body": ParagraphStyle("GuideBody", fontName="Arial", fontSize=10.2,
                           leading=14, textColor=BLACK, spaceAfter=7),
    "speech": ParagraphStyle("GuideSpeech", fontName="Arial", fontSize=10.5,
                             leading=14.6, textColor=BLACK, spaceAfter=6),
    "list": ParagraphStyle("GuideList", fontName="Arial", fontSize=10.2,
                           leading=14, textColor=BLACK, leftIndent=15,
                           firstLineIndent=-15, spaceAfter=6),
    "cell": ParagraphStyle("GuideCell", fontName="Arial", fontSize=9,
                           leading=12, textColor=BLACK),
    "cellhead": ParagraphStyle("GuideCellHead", fontName="Arial-Bold",
                               fontSize=9, leading=12, textColor=WHITE),
}


def inline(value):
    value = escape(value)
    value = re.sub(r"\*\*([^*]+)\*\*", r"<b>\1</b>", value)
    value = re.sub(r"`([^`]+)`", r"<font color='#16324f'>\1</font>", value)
    return value


def table(rows):
    head = rows[0]
    count = len(head)
    if count == 2:
        ratios = [.47, .53]
    elif count == 3 and head[0] == "Integrante y carné":
        ratios = [.43, .18, .39]
    elif count == 3:
        ratios = [.37, .25, .38]
    elif count == 4:
        ratios = [.28, .24, .24, .24]
    elif count == 5:
        ratios = [.45, .12, .14, .17, .12]
    else:
        raise ValueError(f"Unsupported table: {head}")
    cells = [[Paragraph(inline(cell), styles["cellhead" if n == 0 else "cell"])
              for cell in row] for n, row in enumerate(rows)]
    result = Table(cells, colWidths=[CONTENT * r for r in ratios],
                   repeatRows=1, hAlign="LEFT")
    commands = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, PALE]),
        ("GRID", (0, 0), (-1, -1), .5, RULE),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]
    result.setStyle(TableStyle(commands))
    return result


class GuideDoc(SimpleDocTemplate):
    def afterFlowable(self, flowable):
        if isinstance(flowable, Paragraph) and flowable.style.name == "GuideHeading2":
            key = f"heading-{len(self.headings)}"
            label = flowable.getPlainText()
            self.canv.bookmarkPage(key)
            self.canv.addOutlineEntry(label, key, level=0)
            self.headings.append({"title": label, "page": self.page})


def chrome(canvas, doc):
    canvas.saveState()
    canvas.setFont("Arial", 8)
    canvas.setFillColor(MUTED)
    if doc.page > 1:
        canvas.drawString(LEFT, HEIGHT - 23, "MemoryLab | Guion de exposición de una hora")
    canvas.drawString(LEFT, 24, "Universidad Mariano Gálvez | Sistemas Operativos 1 | Grupo 3")
    canvas.drawRightString(WIDTH - RIGHT, 24, str(doc.page))
    canvas.restoreState()


source = SOURCE.read_text(encoding="utf-8")
if any(c in source for c in ("\u2011", "\u2013", "\u2014")):
    raise ValueError("Use ASCII hyphens in the editable source.")
lines = source.splitlines()
story = []
i = 0
while i < len(lines):
    line = lines[i].strip()
    if not line:
        i += 1
        continue
    if line == "<!-- pagebreak -->":
        story.append(PageBreak())
        i += 1
        continue
    if line.startswith("|"):
        rows = []
        while i < len(lines) and lines[i].strip().startswith("|"):
            row = [c.strip() for c in lines[i].strip().strip("|").split("|")]
            if not all(re.fullmatch(r":?-+:?", c) for c in row):
                rows.append(row)
            i += 1
        if any(len(row) != len(rows[0]) for row in rows):
            raise ValueError("Unequal table row lengths.")
        story.extend([table(rows), Spacer(1, 8)])
        continue
    level = next((prefix for prefix in ("### ", "## ", "# ")
                  if line.startswith(prefix)), None)
    if level:
        style = {"# ": "title", "## ": "h2", "### ": "h3"}[level]
        story.append(Paragraph(inline(line[len(level):]), styles[style]))
        i += 1
        continue
    speech = line.startswith(">")
    paragraph = [line.lstrip("> ") if speech else line]
    i += 1
    while i < len(lines):
        nxt = lines[i].strip()
        if not nxt or nxt == ">" or nxt.startswith(("#", "|", "<!--")):
            break
        if speech != nxt.startswith(">") or re.match(r"^\d+\. ", nxt):
            break
        paragraph.append(nxt.lstrip("> ") if speech else nxt)
        i += 1
    text = " ".join(paragraph).strip()
    if not text:
        continue
    kind = "speech" if speech else "list" if re.match(r"^\d+\. ", text) else "body"
    story.append(Paragraph(inline(text), styles[kind]))

doc = GuideDoc(str(OUT), pagesize=A4, leftMargin=LEFT, rightMargin=RIGHT,
               topMargin=43, bottomMargin=43, allowSplitting=1,
               title="Guion de exposición de MemoryLab para una hora",
               author="Universidad Mariano Gálvez - Grupo 3",
               subject="Cinco participantes, explicación literal y demostraciones de memoria")
doc.headings = []
doc.build(story, onFirstPage=chrome, onLaterPages=chrome)
reader = PdfReader(str(OUT))
texts = [p.extract_text() or "" for p in reader.pages]
full = "\n".join(texts)
required = ["Enmer Antonio Buch Xinic", "Lester Emilio Pedro Juan",
            "Deivid Alberto Guerra Carpio", "Rebeca Alizon Najarro Duarte",
            "Carlos Lopez Urizar", "2476", "1124", "12784", "692",
            "58:40", "Page Faults: 6", "P0, P1, P3, P2"]
for value in required:
    if value not in full:
        raise ValueError(f"Required guide content missing: {value}")
if any("\ufffd" in t or "\u25a0" in t for t in texts):
    raise ValueError("Unexpected replacement glyph in PDF text.")
spoken = {}
speaker = None
for line in lines:
    m = re.match(r"## (Enmer|Lester|Deivid|Rebeca|Carlos) de ", line)
    if m:
        speaker = m[1]
    elif line.startswith("## Hoja rápida"):
        speaker = None
    if speaker and line.startswith(">"):
        spoken[speaker] = spoken.get(speaker, 0) + len(re.findall(r"\S+", line[1:]))
qa = {
    "pdf": str(OUT), "editable_source": str(SOURCE),
    "page_count": len(texts), "page_text_characters": [len(t) for t in texts],
    "speech_words_by_person": spoken,
    "speech_minutes_at_110_125_wpm": {k: [round(v / 125, 2), round(v / 110, 2)]
                                        for k, v in spoken.items()},
    "section_pages": doc.headings,
    "sha256": hashlib.sha256(OUT.read_bytes()).hexdigest(),
    "source_sha256": hashlib.sha256(SOURCE.read_bytes()).hexdigest(),
    "application_or_database_operations": False,
    "visual_review": "pending",
}
(QA_DIR / "build-qa.json").write_text(json.dumps(qa, ensure_ascii=False, indent=2),
                                      encoding="utf-8")
print(json.dumps({k: qa[k] for k in ("page_count", "speech_words_by_person",
                                    "speech_minutes_at_110_125_wpm")},
                 ensure_ascii=False, indent=2))
