"""Build the usage manual from editable docs/manual-de-uso.md.

Bundled Python provides ReportLab, Pillow and pypdf. Screenshots are clipped
only on the PDF canvas; evidence files remain unchanged. No application or DB
operations are performed. Optional argument selects a different PDF output.
"""
from pathlib import Path
from xml.sax.saxutils import escape
import hashlib
import json
import re
import sys

from PIL import Image as PILImage
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import Flowable, KeepTogether, PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'docs/manual-de-uso.md'
OUT = (ROOT / sys.argv[1]).resolve() if len(sys.argv) > 1 else ROOT / 'output/pdf/memorylab-manual-de-uso.pdf'
if not OUT.is_relative_to(ROOT):
    raise ValueError('The PDF output must stay inside this workspace.')
OUT.parent.mkdir(parents=True, exist_ok=True)
TMP = ROOT / 'tmp/pdfs/manual'
TMP.mkdir(parents=True, exist_ok=True)

pdfmetrics.registerFont(TTFont('Arial', 'C:/Windows/Fonts/arial.ttf'))
pdfmetrics.registerFont(TTFont('Arial-Bold', 'C:/Windows/Fonts/arialbd.ttf'))
pdfmetrics.registerFontFamily('Arial', normal='Arial', bold='Arial-Bold')
PURPLE = colors.HexColor('#696cff')
INK = colors.HexColor('#263244')
MUTED = colors.HexColor('#5f6c7b')
RULE = colors.HexColor('#dce1e9')
PALE = colors.HexColor('#f1f2ff')
WIDTH, HEIGHT = A4
CONTENT = WIDTH - 88
styles = {
    'body': ParagraphStyle('ManualBody', fontName='Arial', fontSize=10.4, leading=14.2, textColor=INK, spaceAfter=7),
    'list': ParagraphStyle('ManualList', fontName='Arial', fontSize=10.4, leading=14.2, textColor=INK, leftIndent=15, firstLineIndent=-15, spaceAfter=6),
    'title': ParagraphStyle('ManualTitle', fontName='Arial-Bold', fontSize=21, leading=25, textColor=INK, spaceAfter=13),
    'cover': ParagraphStyle('ManualCover', fontName='Arial-Bold', fontSize=39, leading=45, textColor=PURPLE, spaceAfter=12),
    'heading': ParagraphStyle('ManualHeading', fontName='Arial-Bold', fontSize=12.6, leading=16, textColor=INK, spaceBefore=5, spaceAfter=7),
    'cell': ParagraphStyle('ManualCell', fontName='Arial', fontSize=9.2, leading=12.3, textColor=INK),
    'cellhead': ParagraphStyle('ManualCellHead', fontName='Arial-Bold', fontSize=9.3, leading=12.4, textColor=INK),
    'caption': ParagraphStyle('ManualCaption', fontName='Arial', fontSize=8.4, leading=11.2, textColor=MUTED, spaceAfter=6),
    'sources': ParagraphStyle('ManualSources', fontName='Arial', fontSize=8.4, leading=11.2, textColor=MUTED, spaceAfter=5),
}


def inline(value):
    value = escape(value)
    value = re.sub(r'\[([^\]]+)\]\((https?://[^)]+)\)', lambda m: f'<link href="{m.group(2)}" color="#696cff">{m.group(1)}</link>', value)
    value = re.sub(r'\*\*([^*]+)\*\*', r'<b>\1</b>', value)
    value = re.sub(r'`([^`]+)`', r'<font color="#4542a5">\1</font>', value)
    return value


class EvidenceCrop(Flowable):
    """Preserve the original screenshot and its aspect ratio."""
    def __init__(self, filename, crop, maximum_height):
        super().__init__()
        self.path = (SOURCE.parent / filename).resolve()
        if not self.path.is_relative_to(ROOT / 'docs/evidencias'):
            raise ValueError('Evidence images must come from docs/evidencias.')
        with PILImage.open(self.path) as image:
            self.original_width, self.original_height = image.size
        self.crop = crop or (0, 0, self.original_width, self.original_height)
        x1, y1, x2, y2 = self.crop
        if not (0 <= x1 < x2 <= self.original_width and 0 <= y1 < y2 <= self.original_height):
            raise ValueError(f'Invalid evidence crop: {filename}')
        self.scale = min(CONTENT / (x2 - x1), maximum_height / (y2 - y1))
        self.width = (x2 - x1) * self.scale
        self.height = (y2 - y1) * self.scale
        self.hAlign = 'CENTER'

    def draw(self):
        canvas = self.canv
        x1, _, _, y2 = self.crop
        canvas.saveState()
        clip = canvas.beginPath()
        clip.rect(0, 0, self.width, self.height)
        canvas.clipPath(clip, stroke=0, fill=0)
        canvas.drawImage(ImageReader(str(self.path)), -x1 * self.scale,
                         -(self.original_height - y2) * self.scale,
                         self.original_width * self.scale,
                         self.original_height * self.scale, mask='auto')
        canvas.restoreState()


def widths_for(headers):
    count = len(headers)
    if count == 2:
        ratio = [0.53, 0.47] if headers[0] == 'Integrante' else [0.32, 0.68]
    elif count == 4:
        ratio = [0.45, 0.275, 0.275]  # Only three-column tables are used below.
    elif headers[0] == 'Orden':
        ratio = [0.09, 0.71, 0.20]
    elif headers[0] == 'Offset':
        ratio = [0.14, 0.33, 0.53]
    elif headers[0] == 'Concepto solicitado':
        ratio = [0.28, 0.27, 0.45]
    elif headers[0] == 'Técnica':
        ratio = [0.19, 0.35, 0.46]
    else:
        ratio = [0.44, 0.28, 0.28]
    if len(ratio) != count:
        raise ValueError(f'Unsupported manual table: {headers}')
    return [CONTENT * part for part in ratio]


def native_table(rows):
    header = rows[0]
    cells = [[Paragraph(inline(cell), styles['cellhead' if row_index == 0 else 'cell'])
              for cell in row] for row_index, row in enumerate(rows)]
    result = Table(cells, colWidths=widths_for(header), repeatRows=1, hAlign='LEFT')
    result.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), PALE),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LINEBELOW', (0, 0), (-1, 0), 0.8, RULE),
        ('LINEBELOW', (0, 1), (-1, -1), 0.35, RULE),
        ('LEFTPADDING', (0, 0), (-1, -1), 7),
        ('RIGHTPADDING', (0, 0), (-1, -1), 7),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ]))
    return result


source_text = SOURCE.read_text(encoding='utf-8')
if any(char in source_text for char in ('\u2011', '\u2013', '\u2014')):
    raise ValueError('Use ASCII hyphens in the editable manual.')
source_lines = source_text.splitlines()
story = []
crop = None
maximum_height = 100
in_sources = False
page_titles = []
screenshots = []
i = 0
while i < len(source_lines):
    line = source_lines[i].strip()
    if not line:
        i += 1
        continue
    if line == '<!-- pagebreak -->':
        story.append(PageBreak())
        in_sources = False
        i += 1
        continue
    crop_match = re.fullmatch(r'<!-- crop: ([0-9,]+); max-height=([0-9]+) -->', line)
    if crop_match:
        crop = tuple(map(int, crop_match.group(1).split(',')))
        maximum_height = int(crop_match.group(2))
        i += 1
        continue
    image_match = re.fullmatch(r'!\[([^\]]+)\]\(([^)]+)\)', line)
    if image_match:
        filename = image_match.group(2)
        screenshots.append(filename)
        story.append(KeepTogether([
            EvidenceCrop(filename, crop, maximum_height), Spacer(1, 4),
            Paragraph(inline(image_match.group(1)), styles['caption']),
        ]))
        crop = None
        maximum_height = 100
        i += 1
        continue
    if line.startswith('|'):
        rows = []
        while i < len(source_lines) and source_lines[i].strip().startswith('|'):
            row = [cell.strip() for cell in source_lines[i].strip().strip('|').split('|')]
            if not all(re.fullmatch(r':?-+:?', cell) for cell in row):
                rows.append(row)
            i += 1
        story.extend([native_table(rows), Spacer(1, 7)])
        continue
    if line.startswith('# '):
        story.append(Paragraph(inline(line[2:]), styles['cover']))
        i += 1
        continue
    if line.startswith('## '):
        title = line[3:]
        page_titles.append(title)
        story.append(Paragraph(inline(title), styles['title']))
        i += 1
        continue
    if line.startswith('### '):
        heading = line[4:]
        in_sources = heading == 'Fuentes del manual'
        story.append(Paragraph(inline(heading), styles['heading']))
        i += 1
        continue
    is_list = bool(re.match(r'^\d+\. ', line))
    paragraph = [line]
    i += 1
    while i < len(source_lines) and source_lines[i].strip() and not source_lines[i].strip().startswith(('#', '|', '<!--', '![')) and not re.match(r'^\d+\. ', source_lines[i].strip()):
        paragraph.append(source_lines[i].strip())
        i += 1
    style = 'sources' if in_sources else 'list' if is_list else 'body'
    story.append(Paragraph(inline(' '.join(paragraph)), styles[style]))


def page_chrome(canvas, document):
    canvas.saveState()
    canvas.setTitle('MemoryLab - Manual de uso y secuencia de demostración')
    canvas.setAuthor('Universidad Mariano Gálvez - Grupo 3')
    canvas.setSubject('Guía práctica de paginación, segmentación y exposición académica')
    canvas.setStrokeColor(RULE)
    canvas.setLineWidth(0.55)
    canvas.line(44, 34, WIDTH - 44, 34)
    canvas.setFillColor(MUTED)
    canvas.setFont('Arial', 8)
    canvas.drawString(44, 21, 'MemoryLab · Manual de uso · Sistemas Operativos 1 · Grupo 3')
    canvas.drawRightString(WIDTH - 44, 21, f'{document.page} / 8')
    canvas.restoreState()


doc = SimpleDocTemplate(str(OUT), pagesize=A4, rightMargin=44, leftMargin=44,
                        topMargin=42, bottomMargin=45, title='MemoryLab - Manual de uso',
                        allowSplitting=1)
doc.build(story, onFirstPage=page_chrome, onLaterPages=page_chrome)
reader = PdfReader(str(OUT))
if len(reader.pages) != 8:
    raise ValueError(f'The manual must contain eight pages; got {len(reader.pages)}.')
texts = [page.extract_text() or '' for page in reader.pages]
expected = ['Enmer Antonio Buch Xinic', 'Iniciar demostración', 'PAGE_HIT', '5548',
            '4096 B', 'request chrome 3', 'Reiniciar demostración', '14 minutos']
for number, (text, required) in enumerate(zip(texts, expected), 1):
    if required not in text:
        raise ValueError(f'Expected content missing on page {number}: {required}')
if len(set(screenshots)) != 4:
    raise ValueError('The manual must contain four distinct real screenshots.')
if not all(len(text.strip()) > 300 for text in texts):
    raise ValueError('Unexpected empty or nearly empty page.')
qa = {
    'pdf': str(OUT), 'editable_source': str(SOURCE), 'page_count': len(reader.pages),
    'page_text_characters': [len(text) for text in texts], 'screenshots': screenshots,
    'sha256': hashlib.sha256(OUT.read_bytes()).hexdigest(),
    'source_sha256': hashlib.sha256(SOURCE.read_bytes()).hexdigest(),
    'visual_review': 'pending', 'application_or_database_operations': False,
}
(TMP / 'manual-build-qa.json').write_text(json.dumps(qa, ensure_ascii=False, indent=2), encoding='utf-8')
print(json.dumps(qa, ensure_ascii=False, indent=2))
