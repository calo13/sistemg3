"""Generate the academic report from documented MemoryLab evidence.

Requires ReportLab, Pillow and pypdf. Run from any directory with Python.
Final PDF and editable Markdown are public deliverables. QA stays under tmp/.
"""
from pathlib import Path
import json
from xml.sax.saxutils import escape

from PIL import Image as PILImage
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.utils import ImageReader
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak,
    Image, KeepTogether, Flowable,
)
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'output/pdf/memorylab-informe-tecnico.pdf'
OUT.parent.mkdir(parents=True, exist_ok=True)
TMP = ROOT / 'tmp/pdfs'
TMP.mkdir(parents=True, exist_ok=True)
pdfmetrics.registerFont(TTFont('Arial', 'C:/Windows/Fonts/arial.ttf'))
pdfmetrics.registerFont(TTFont('Arial-Bold', 'C:/Windows/Fonts/arialbd.ttf'))
pdfmetrics.registerFontFamily('Arial', normal='Arial', bold='Arial-Bold')
PURPLE = colors.HexColor('#696cff')
INK = colors.HexColor('#263244')
MUTED = colors.HexColor('#5f6c7b')
PALE = colors.HexColor('#f1f2ff')
RULE = colors.HexColor('#dce1e9')
WIDTH, HEIGHT = A4
CONTENT = WIDTH - 96
ST = getSampleStyleSheet()
ST.add(ParagraphStyle(name='ReportBody', fontName='Arial', fontSize=10.2,
                     leading=14.6, textColor=INK, spaceAfter=8))
ST.add(ParagraphStyle(name='ReportTitle', fontName='Arial-Bold', fontSize=23,
                     leading=29, textColor=INK, spaceAfter=18))
ST.add(ParagraphStyle(name='ReportHeading', fontName='Arial-Bold', fontSize=13,
                     leading=18, textColor=INK, spaceBefore=8, spaceAfter=8))
ST.add(ParagraphStyle(name='ReportSmall', fontName='Arial', fontSize=8.4,
                     leading=11.5, textColor=MUTED, spaceAfter=6))
ST.add(ParagraphStyle(name='ReportCell', fontName='Arial', fontSize=8.8,
                     leading=12, textColor=INK))
ST.add(ParagraphStyle(name='ReportCellHead', fontName='Arial-Bold', fontSize=9,
                     leading=12, textColor=INK))
ST.add(ParagraphStyle(name='ReportCover', fontName='Arial-Bold', fontSize=40,
                     leading=46, textColor=PURPLE, spaceAfter=14))
ST.add(ParagraphStyle(name='ReportSubtitle', fontName='Arial', fontSize=20,
                     leading=27, textColor=INK, spaceAfter=22))

story = []
markdown = ['# MemoryLab: informe técnico', '',
            'Universidad Mariano Gálvez · Ingeniería en Sistemas · Sistemas Operativos 1 · Grupo 3', '',
            'Fecha de corte: 8 de octubre de 2026.', '']

def para(text, style='ReportBody'):
    story.append(Paragraph(escape(text), ST[style]))
    markdown.extend([text, ''])

def title(text):
    story.append(Paragraph(escape(text), ST['ReportTitle']))
    markdown.extend(['## ' + text, ''])

def heading(text):
    story.append(Paragraph(escape(text), ST['ReportHeading']))
    markdown.extend(['### ' + text, ''])

def table(headers, rows, widths):
    data = [[Paragraph(escape(str(v)), ST['ReportCellHead']) for v in headers]]
    data += [[Paragraph(escape(str(v)), ST['ReportCell']) for v in row] for row in rows]
    t = Table(data, colWidths=widths, hAlign='LEFT', repeatRows=1)
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), PALE),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LINEBELOW', (0, 0), (-1, 0), 0.8, RULE),
        ('LINEBELOW', (0, 1), (-1, -1), 0.35, RULE),
        ('LEFTPADDING', (0, 0), (-1, -1), 8),
        ('RIGHTPADDING', (0, 0), (-1, -1), 8),
        ('TOPPADDING', (0, 0), (-1, -1), 7),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
    ]))
    story.extend([t, Spacer(1, 9)])
    markdown.extend(['| ' + ' | '.join(headers) + ' |',
                     '| ' + ' | '.join(['---'] * len(headers)) + ' |'])
    markdown.extend('| ' + ' | '.join(str(v) for v in row) + ' |' for row in rows)
    markdown.append('')

class CroppedImage(Flowable):
    """Clip an existing screenshot in the PDF without changing its source."""
    def __init__(self, path, crop, max_h):
        Flowable.__init__(self)
        self.path = path
        self.crop = crop
        with PILImage.open(path) as im:
            self.image_size = im.size
        x1, y1, x2, y2 = crop
        self.scale = min(CONTENT / (x2 - x1), max_h / (y2 - y1))
        self.width = (x2 - x1) * self.scale
        self.height = (y2 - y1) * self.scale
        self.hAlign = 'CENTER'

    def draw(self):
        c = self.canv
        x1, y1, x2, y2 = self.crop
        iw, ih = self.image_size
        c.saveState()
        clip = c.beginPath()
        clip.rect(0, 0, self.width, self.height)
        c.clipPath(clip, stroke=0, fill=0)
        c.drawImage(ImageReader(str(self.path)), -x1 * self.scale,
                    -(ih - y2) * self.scale, iw * self.scale,
                    ih * self.scale, mask='auto')
        c.restoreState()


def shot(filename, caption, max_h=230, crop=None):
    path = ROOT / 'docs/evidencias' / filename
    with PILImage.open(path) as im:
        w, h = im.size
    scale = min(CONTENT / w, max_h / h)
    img = CroppedImage(path, crop, max_h) if crop else Image(str(path), w * scale, h * scale)
    img.hAlign = 'CENTER'
    story.append(KeepTogether([img, Spacer(1, 5), Paragraph(escape(caption), ST['ReportSmall'])]))
    markdown.extend([f'![{caption}](evidencias/{filename})', ''])

def source(label, url):
    story.append(Paragraph(f'<link href="{escape(url)}" color="#696cff">{escape(label)}</link>', ST['ReportSmall']))
    markdown.extend([f'[{label}]({url})', ''])

class Diagram(Flowable):
    """Native vector diagram with explicit relationship lines and labels."""
    def __init__(self, kind):
        Flowable.__init__(self)
        self.kind = kind
        self.width = CONTENT
        self.height = 145 if kind == 'architecture' else 186

    def draw(self):
        c = self.canv
        def node(x, y, w, h, label, detail=''):
            c.setFillColor(PALE)
            c.setStrokeColor(RULE)
            c.roundRect(x, y, w, h, 5, stroke=1, fill=1)
            c.setFillColor(INK)
            c.setFont('Arial-Bold', 10)
            c.drawCentredString(x + w / 2, y + h / 2 + (4 if detail else -3), label)
            if detail:
                c.setFont('Arial', 8)
                c.drawCentredString(x + w / 2, y + h / 2 - 10, detail)
        def line(x1, y1, x2, y2, label=''):
            c.setStrokeColor(PURPLE)
            c.setLineWidth(1.2)
            c.line(x1, y1, x2, y2)
            if label:
                c.setFillColor(MUTED)
                c.setFont('Arial', 8)
                c.drawCentredString((x1 + x2) / 2, (y1 + y2) / 2 + 6, label)
        if self.kind == 'architecture':
            line(122, 96, 188, 96, 'acciones')
            line(312, 96, 378, 96, 'consulta')
            node(0, 72, 122, 48, 'Interfaz', 'Sneat + Bootstrap')
            node(188, 72, 124, 48, 'Livewire', 'estado y validación')
            node(378, 72, 121, 48, 'Services', 'permisos y algoritmos')
            node(188, 0, 124, 44, 'MySQL / Eloquent', 'escenarios y eventos')
            # Services, not the view, owns the transactional persistence path.
            line(438, 72, 438, 22)
            line(438, 22, 312, 22, 'transacción')
        else:
            line(249, 147, 76, 106)
            line(249, 147, 249, 106)
            line(249, 147, 423, 106)
            line(249, 106, 76, 40)
            line(249, 106, 249, 40)
            line(249, 106, 423, 40)
            line(423, 82, 76, 40)
            node(177, 145, 144, 34, 'scenarios')
            node(4, 80, 144, 42, 'memory_configurations', 'una por escenario')
            node(177, 80, 144, 42, 'processes', 'pertenencia al escenario')
            node(351, 80, 144, 42, 'memory_frames', 'numeración y ocupación')
            node(4, 16, 144, 42, 'pages', 'proceso y marco opcional')
            node(177, 16, 144, 42, 'segments', 'proceso, base y límite')
            node(351, 16, 144, 42, 'simulation_events', 'escenario, actor, proceso')

def diagram(kind, caption):
    story.extend([Diagram(kind), Paragraph(escape(caption), ST['ReportSmall']), Spacer(1, 7)])
    markdown.extend([caption, ''])

def next_page():
    story.append(PageBreak())

def footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(RULE)
    canvas.line(48, 39, WIDTH - 48, 39)
    canvas.setFillColor(MUTED)
    canvas.setFont('Arial', 8)
    canvas.drawString(48, 27, 'MemoryLab  /  Universidad Mariano Gálvez  /  Grupo 3')
    canvas.drawRightString(WIDTH - 48, 27, str(doc.page))
    if doc.page > 1:
        canvas.setFont('Arial-Bold', 8)
        canvas.setFillColor(PURPLE)
        canvas.drawString(48, HEIGHT - 32, 'INFORME TÉCNICO  /  8 OCTUBRE 2026')
    canvas.restoreState()

# 1. Cover and ownership.
story.append(Spacer(1, 32))
story.append(Paragraph('MEMORYLAB', ST['ReportCover']))
story.append(Paragraph('Simulador interactivo de<br/>administración de memoria', ST['ReportSubtitle']))
para('Informe técnico del proyecto', 'ReportHeading')
for identity in ['Universidad Mariano Gálvez', 'Ingeniería en Sistemas', 'Sistemas Operativos 1', 'Grupo 3']:
    para(identity)
story.append(Spacer(1, 18))
table(['Integrante', 'Carné'], [
    ['Enmer Antonio Buch Xinic', '1990-22-15514'],
    ['Lester Emilio Pedro Juan', '6590-23-7214'],
    ['Deivid Alberto Guerra Carpio', '0905-24-23552'],
    ['Rebeca Alizon Najarro Duarte', '3190-23-11451'],
    ['Carlos Lopez Urizar', '6590-24-18604'],
], [CONTENT * .7, CONTENT * .3])
story.append(Spacer(1, 22))
para('Fecha de corte: 8 de octubre de 2026. La aplicación funciona en Apache local. Este informe describe la implementación, las demostraciones y las verificaciones conservadas en el repositorio.')
para('Contenido: objetivos y arquitectura; modelo MySQL; paginación; traducción y segmentación; comparación y demanda; permisos y pruebas; uso de IA y conclusiones; guía de demostración y referencias.', 'ReportSmall')

# 2. Scope and architecture.
next_page()
title('1. Objetivos y arquitectura')
para('MemoryLab permite observar cómo un proceso utiliza memoria bajo paginación y segmentación. El estudiante configura una memoria pequeña, solicita páginas, traduce direcciones y compara técnicas con datos persistidos. La interfaz muestra el motivo de cada resultado y su efecto en RAM y almacenamiento secundario.')
heading('Alcance académico')
para('El objetivo general es explicar la administración de memoria mediante operaciones repetibles. Los objetivos específicos son identificar páginas y marcos; distinguir Page Hit y Page Fault; aplicar base y límite a un segmento; y relacionar fragmentación y capacidad con la asignación contigua y no contigua.')
para('Los tamaños, direcciones y procesos pertenecen a una simulación educativa. MemoryLab no inspecciona las tablas de páginas del sistema operativo, no reserva físicamente la RAM declarada y no ejecuta procesos del equipo. La terminal reconoce un conjunto cerrado de comandos educativos.')
heading('Separación de responsabilidades')
diagram('architecture', 'Figura 1. La interfaz delega las acciones a Livewire. Los Services autorizan y ejecutan operaciones transaccionales mediante Eloquent y MySQL.')
para('Laravel atiende rutas y autenticación. Jetstream/Fortify aporta el acceso y Livewire mantiene los formularios y paneles. Bootstrap 5 y Sneat Free definen la presentación. JavaScript anima resultados del servidor y controla la pantalla completa. Los algoritmos y el estado autoritativo permanecen en los Services.')
table(['Componente', 'Versión comprobada'], [
    ['PHP / Laravel', '8.2.12 / 12.69.3'],
    ['Jetstream / Livewire / Fortify', '5.5.3 / 3.8.10 / 1.41.0'],
    ['Bootstrap / Sneat Free / Vite', '5.3.8 / 2.0.0 / 6.4.4'],
    ['MySQL Community Server', '8.0.44, motor InnoDB'],
], [CONTENT * .53, CONTENT * .47])

# 3. Schema and persistence.
next_page()
title('2. Modelo de datos e integridad')
para('El dominio utiliza siete tablas. scenario_id separa las simulaciones. Las referencias compuestas impiden vincular una página, un segmento, un evento o un marco con un proceso de otro escenario. Las cuentas y los permisos utilizan las tablas de autenticación correspondientes.')
diagram('schema', 'Figura 2. Relaciones principales del dominio. Todas las entidades pertenecen a un escenario. Pages referencia un proceso y, cuando reside en RAM, un marco. Segmentos y eventos también referencian procesos. El diagrama completo está en docs/modelo-memoria.md.')
table(['Tabla', 'Responsabilidad'], [
    ['scenarios', 'Nombre, modo PAGING/SEGMENTATION/CONTIGUOUS, estado y demostración.'],
    ['memory_configurations', 'RAM, tamaño de página y capacidad secundaria, expresados en bytes.'],
    ['processes', 'Nombre, tamaño y estado del proceso.'],
    ['memory_frames', 'Marcos consecutivos desde cero dentro del escenario.'],
    ['pages', 'Página lógica, marco nullable y fecha loaded_at para FIFO.'],
    ['segments', 'Nombre, base, tamaño y estado ACTIVE/RELEASED.'],
    ['simulation_events', 'Actor, tipo, fecha UTC con microsegundos y contexto JSON.'],
], [CONTENT * .32, CONTENT * .68])
para('La auditoría del esquema confirmó 12 claves foráneas, 6 restricciones CHECK y 7 claves únicas. frame_id único permite como máximo una página por marco. Configuración, proceso y número de página o segmento mantienen sus propias unicidades. Los Services validan además geometría, capacidad, pertenencia y solapamientos entre registros.')
para('Las operaciones de escritura toman el bloqueo de la misma fila del escenario dentro de una transacción. El reinicio libera asignaciones y termina procesos, pero conserva escenario, configuración e historial. Una cuenta eliminada deja el actor histórico en null en lugar de eliminar su evidencia.')

# 4. Paging.
next_page()
title('3. Paginación y solicitudes de CPU')
para('La paginación divide el espacio lógico en unidades de tamaño fijo y representa la memoria física con marcos del mismo tamaño. La tabla de páginas permite localizar el marco de cada página. Esta base conceptual se presenta en OSTEP, capítulo 18.')
source('Referencia conceptual: OSTEP, Paging: Introduction', 'https://pages.cs.wisc.edu/~remzi/OSTEP/vm-paging.pdf')
para('En MemoryLab, 1 KB equivale a 1024 bytes. Con RAM de 16 KB y páginas de 1 KB existen 16 marcos. Un proceso de 6 KB tiene 6 páginas. Un tamaño no múltiplo reserva la última página completa. Al crear un proceso, todas sus páginas comienzan en secundaria con frame_id null.')
heading('Resolución de una solicitud')
table(['Resultado', 'Operación del servidor'], [
    ['Page Hit', 'La página ya tiene marco. Registra PAGE_REQUEST y PAGE_HIT, conserva ubicación y antigüedad FIFO.'],
    ['Page Fault con espacio', 'Registra el fallo, elige el marco libre de menor número y carga la página.'],
    ['Page Fault con RAM llena', 'Elige globalmente el residente más antiguo por loaded_at e id, libera su marco y carga la página solicitada.'],
], [CONTENT * .31, CONTENT * .69])
para('La resolución valida el actor y es idempotente para una solicitud ya completada. Un reinicio posterior invalida pendientes anteriores. El modo paso a paso muestra la consulta, el fallo o hit, la carga y el acceso físico sobre el mismo algoritmo del modo automático.')
shot('fase-25-presentacion-escritorio.png', 'Figura 3. Modo presentación: CPU, tabla de páginas, RAM y secundaria comparten el estado real del escenario.', 255)
para('Simplificación explícita: cada página ocupa RAM o secundaria de forma exclusiva. La reserva secundaria se calcula con páginas sin marco por tamaño de página. El simulador comprueba la capacidad al crear y reemplazar páginas. El historial conserva la solicitud, el resultado y la víctima del reemplazo.', 'ReportSmall')

# 5. Address and segmentation.
next_page()
title('4. Direcciones y segmentación')
heading('Traducción de dirección lógica')
para('Para una dirección lógica válida, la página se obtiene con la división entera entre el tamaño de página. El desplazamiento es el residuo. Si la página reside en RAM, la dirección física es marco × tamaño de página + desplazamiento. El servicio exige 0 ≤ dirección lógica < tamaño real del proceso y consultar una traducción no carga páginas ni genera eventos.')
table(['Ejemplo didáctico', 'Cálculo'], [
    ['Página de 1024 bytes, dirección lógica 1124', 'Página 1 y desplazamiento 100.'],
    ['Entrada de tabla: página 1 en marco 5', 'Dirección física = 5 × 1024 + 100 = 5220.'],
    ['Página sin marco', 'Sin dirección física. Una solicitud de CPU puede resolver el fallo.'],
], [CONTENT * .53, CONTENT * .47])
heading('Base y límite de un segmento')
para('La segmentación organiza un proceso en regiones lógicas de tamaño variable. Base y límite permiten validar y traducir un acceso. En la implementación, cada segmento activo ocupa un intervalo contiguo [base, base + tamaño), aunque los segmentos de un proceso pueden estar separados. El capítulo 16 de OSTEP explica este modelo conceptual.')
source('Referencia conceptual: OSTEP, Segmentation', 'https://pages.cs.wisc.edu/~remzi/OSTEP/vm-segmentation.pdf')
table(['Condición', 'Resultado implementado'], [
    ['0 ≤ offset < tamaño', 'Dirección física = base + offset. Un evento SEGMENT_ACCESS.'],
    ['offset ≥ tamaño', 'SEGMENTATION_FAULT y dirección física null. RAM y estados se conservan.'],
    ['Base 1000, tamaño 1200, offset 100', 'Acceso válido a 1100. Último offset válido: 1199.'],
    ['Base 1000, tamaño 1200, offset 1200', 'Fallo por límite. Caso verificado en navegador.'],
], [CONTENT * .50, CONTENT * .50])
para('La asignación comprueba límite de RAM, suma de tamaños del proceso, número de segmento y ausencia de solapamientos activos. Permite regiones adyacentes. El mapa intercala segmentos y huecos libres. El selector utiliza el primer segmento activo para evitar seleccionar uno liberado.')
para('La segmentación de MemoryLab utiliza bases no negativas y desplazamientos crecientes. No incluye crecimiento descendente de stack, permisos por segmento, TLB ni niveles de tablas. Estos límites mantienen el ejemplo matemático verificable.', 'ReportSmall')

# 6. Comparison and stress.
next_page()
title('5. Comparación y alta demanda')
para('La fragmentación externa aparece cuando el espacio libre total se reparte entre huecos que no permiten una asignación contigua suficientemente grande. La fragmentación interna corresponde al espacio reservado que queda sin utilizar dentro de una unidad. OSTEP, capítulo 17, fundamenta la discusión sobre administración del espacio libre.')
source('Referencia conceptual: OSTEP, Free-Space Management', 'https://pages.cs.wisc.edu/~remzi/OSTEP/vm-freespace.pdf')
heading('Ejemplo controlado del comparador')
para('El comparador puro usa RAM de 16384 bytes, bloques ocupados A de 0 a 4095 y B de 7168 a 10239, y huecos de 3072 y 6144 bytes. Hay 9216 bytes libres, pero el mayor hueco mide 6144. La solicitud predeterminada es 7168 bytes.')
table(['Técnica', 'Resultado para 7168 bytes'], [
    ['Contigua', 'Rechaza: el proceso completo supera el mayor hueco.'],
    ['Paginación, páginas de 1024', 'Acepta: distribuye 7 páginas entre los marcos libres.'],
    ['Segmentación del ejemplo', 'Acepta: Código de 4096 en base 10240 y Datos de 3072 en base 4096.'],
], [CONTENT * .39, CONTENT * .61])
para('Para 7500 bytes, paginación reserva 8192 y deja 692 bytes internos sin uso. Una solicitud de 9217 bytes falla en las tres técnicas. La segmentación distribuye dos segmentos ilustrativos con first fit. El comparador revierte el intento parcial que falla y no modifica escenarios, eventos ni tablas.')
heading('Demanda simulada')
para('El módulo de alta demanda crea un lote limitado y solicita sus páginas mediante los mismos Services. Registra hasta 64 muestras reales de progreso. La transacción revierte el lote completo si faltan permisos o capacidad. No mide consumo de RAM del equipo ni rendimiento de Linux.')
para('Caso verificado: 3 procesos de 2 KB, páginas de 1 KB y RAM de 2 KB producen 6 Page Faults y 4 reemplazos FIFO. Al final, RAM utiliza 2048 bytes y secundaria 4096. El escenario contiene 23 eventos, incluidos sus 2 eventos de preparación.')
shot('fase-21-demanda-escritorio.png', 'Figura 4. Indicadores reales después del lote: RAM, marcos, procesos, Page Faults y secundaria. Encuadre de la captura de escritorio.', 175, (285, 358, 1342, 554))

# 7. Permissions and tests.
next_page()
title('6. Permisos y verificación')
table(['Rol', 'Capacidad'], [
    ['Administrador', '13 permisos: cuentas, configuración, escenarios, simulación, procesos, accesos, reinicio, historial y consultas.'],
    ['Operador', '8 permisos: procesos, solicitudes de páginas, segmentación y ejecución, además de las cuatro consultas.'],
    ['Observador', '4 permisos de consulta: memoria, tablas, simulaciones y resultados. El registro público asigna este rol.'],
], [CONTENT * .27, CONTENT * .73])
para('Las acciones protegidas y los servicios operativos recargan al usuario y vuelven a comprobar permisos. Los componentes autorizados invocan los cálculos puros y las estadísticas. La administración impide quitar el último Administrador. Observador puede consultar periódicamente resultados de otra cuenta sin repetir el acceso.')
heading('Resultado integral, fase 27')
table(['Verificación', 'Evidencia al 8 de octubre'], [
    ['Suite de pruebas con MySQL separado', '655 aprobadas, 1 omitida condicional, 4575 aserciones, 96,78 segundos.'],
    ['Estilo y sintaxis', 'Pint aprobado y 117 archivos PHP con sintaxis correcta.'],
    ['Dependencias y assets', 'Composer validate --strict --no-check-publish y compilación Vite aprobados.'],
    ['Persistencia', 'Siete tablas InnoDB. 12 FK, 6 CHECK y 7 claves únicas.'],
    ['Navegador', 'Tres roles, acciones y resultados, recursos locales, consulta sin mutación y anchos de 320 a 1920 según módulo.'],
], [CONTENT * .42, CONTENT * .58])
para('La omisión pertenece a la prueba condicional de Jetstream para registro deshabilitado: el registro está habilitado en esta configuración. RegistrationDisabledTest verifica de forma independiente el bloqueo de GET/POST y la ausencia de enlaces al desactivar el registro.')
para('Las pruebas cubren límites, referencias entre escenarios, autorizaciones revocadas, revalidación tras operaciones intercaladas, atomicidad, idempotencia y FIFO. No incluyen contención simultánea entre conexiones paralelas. Las verificaciones de navegador comparan firmas de las siete tablas para confirmar consultas sin cambios. Las fixtures temporales se eliminan al terminar y la base de trabajo se conserva.')
heading('Demostraciones iniciales')
para('El seeder explícito preparó paginación de 16 KB y segmentación de 16 KB usando una cuenta Administrador existente. Repetirlo dejó el estado idéntico. La base local conserva 2 usuarios, 3 escenarios, 5 procesos, 32 marcos, 15 páginas, 4 segmentos y 26 eventos. No crea cuentas de integrantes.')

# 8. IA and conclusions.
next_page()
title('7. Uso de IA y conclusiones')
para('Codex participó como asistente de análisis, implementación y verificación. El usuario estableció tecnologías, secuencia y datos académicos; después autorizó completar todas las fases sin confirmaciones adicionales. La evidencia incluye la solicitud original y documentos de cada fase con decisiones, correcciones y resultados. Este informe no atribuye automáticamente a los integrantes una revisión manual que no está registrada.')
table(['Evidencia conservada', 'Decisión o corrección comprobable'], [
    ['solicitud-inicial.md y avance.md', 'Stack obligatorio, 32 fases y autorización para continuar.'],
    ['fase-3.md y capturas de registro', 'La revisión HTTP no detectó el logo sobredimensionado. Se sustituyeron clases incompatibles y se comprobó visualmente en escritorio y móvil.'],
    ['fase-8.md y modelo-memoria.md', 'MySQL oficial, relaciones compuestas y reglas del esquema.'],
    ['fase-15.md, fase-19.md y fase-23.md', 'FIFO por fecha de carga, selección de segmentos activos y validación de filtros y fechas.'],
    ['fase-25.md y fase-26.md', 'Resultados persistidos para Observador y seeder idempotente que conserva cuentas y escenarios anteriores.'],
    ['fase-27.md', 'Suite integral y verificación de integridad, sintaxis y compilación.'],
], [CONTENT * .38, CONTENT * .62])
heading('Conclusiones técnicas')
para('1. La tabla de páginas relaciona páginas lógicas con marcos. Una consulta a una página ausente requiere una operación de carga antes de obtener dirección física. El modelo hace visible esa diferencia y conserva el contexto del resultado.')
para('2. La validación del desplazamiento debe preceder a la suma de la base. Para tamaño 1200, el offset 1199 es válido y 1200 falla. El acceso fuera del límite conserva la asignación y registra el motivo del fallo.')
para('3. El espacio libre total no garantiza una asignación contigua. La comparación de 7168 bytes, con un hueco máximo de 6144, muestra por qué páginas o segmentos separados pueden aprovechar huecos distintos.')
para('4. Permisos, referencias compuestas y transacciones coordinan la simulación compartida. Las pruebas automatizadas y la revisión visual cubren aspectos diferentes. El error inicial del registro mostró la necesidad de comprobar ambos.')
para('5. La VM es una ampliación opcional. La instalación local funciona y la guía incluye Apache/Nginx, PHP y MySQL. Una instalación Linux posterior puede comparar sus métricas reales con la simulación, conservando la distinción entre ambos sistemas.')

# 9. Reproduction and references.
next_page()
title('8. Guía de demostración y fuentes')
heading('Recorrido recomendado')
table(['Paso', 'Acción y observación'], [
    ['1. Acceso', 'Entrar con una cuenta ya autorizada y abrir Demostración. Preparar o seleccionar la pareja de escenarios.'],
    ['2. Paginación', 'Seleccionar Chrome. Página 0 ya reside en RAM: solicitarla produce Hit. Página 3 comienza en secundaria: solicitarla produce Fault y carga.'],
    ['3. Traducción', 'Consultar una dirección lógica válida y contrastar página, desplazamiento y marco. Una página ausente devuelve dirección física null.'],
    ['4. Segmentación', 'Seleccionar Editor y Código, base 1000 y tamaño 1200. Offset 100 devuelve 1100; offset 1200 muestra fallo.'],
    ['5. Comparación', 'Solicitar 7168 bytes y explicar hueco máximo, marcos y segmentos. Revisar después 7500 y su fragmentación interna.'],
    ['6. Cierre', 'Mostrar historial, terminal y /presentation. Reiniciar solo el escenario elegido cuando corresponda. El historial conserva la operación.'],
], [CONTENT * .27, CONTENT * .73])
para('URL local: http://localhost/sistemg3/public/. La guía de instalación y los ejemplos de servidor están en docs/despliegue.md y deploy/. La regeneración del informe usa scripts/artifacts/build-report.py con ReportLab y fuentes Arial. La instalación y ejecución del proyecto se documentan en README.md.', 'ReportSmall')
heading('Referencias conceptuales')
source('Arpaci-Dusseau y Arpaci-Dusseau. OSTEP, capítulo 18: Paging: Introduction.', 'https://pages.cs.wisc.edu/~remzi/OSTEP/vm-paging.pdf')
source('Arpaci-Dusseau y Arpaci-Dusseau. OSTEP, capítulo 16: Segmentation.', 'https://pages.cs.wisc.edu/~remzi/OSTEP/vm-segmentation.pdf')
source('Arpaci-Dusseau y Arpaci-Dusseau. OSTEP, capítulo 17: Free-Space Management.', 'https://pages.cs.wisc.edu/~remzi/OSTEP/vm-freespace.pdf')
heading('Fuentes del proyecto')
para('Requisitos: docs/evidencias/solicitud-inicial.md. Arquitectura y datos: docs/modelo-memoria.md y config/memorylab.php. Evidencias de implementación: docs/evidencias/fase-1.md a fase-28.md y sus capturas. Uso de IA: docs/uso-ia.md. Código de referencia: app/Services, app/Livewire, database/migrations y tests/Feature.', 'ReportSmall')
para('Las cifras de pruebas corresponden al cierre de la fase 27. Los ejemplos de direcciones distinguen cálculos didácticos de casos verificados en navegador. Las capturas incluidas pertenecen a la aplicación real. No se afirma despliegue externo ni ejecución de una VM.', 'ReportSmall')

doc = SimpleDocTemplate(str(OUT), pagesize=A4, rightMargin=48, leftMargin=48,
                        topMargin=52, bottomMargin=53, title='MemoryLab - Informe técnico',
                        author='Grupo 3 - Universidad Mariano Gálvez',
                        subject='Simulador interactivo de administración de memoria')
doc.build(story, onFirstPage=footer, onLaterPages=footer)
reader = PdfReader(OUT)
texts = [page.extract_text() or '' for page in reader.pages]
if len(reader.pages) != 9:
    raise RuntimeError(f'Expected 9 pages, got {len(reader.pages)}. Adjust layout before delivery.')
for phrase in ['Enmer Antonio Buch Xinic', '1990-22-15514', '655 aprobadas', 'SEGMENTATION_FAULT', '4575']:
    if phrase not in '\n'.join(texts):
        raise RuntimeError(f'Missing report content: {phrase}')
(ROOT / 'docs/informe-tecnico.md').write_text('\n'.join(markdown), encoding='utf-8')
qa = {'pages': len(reader.pages), 'text_chars_by_page': [len(t) for t in texts],
      'bytes': OUT.stat().st_size, 'output': str(OUT)}
(TMP / 'report-check.json').write_text(json.dumps(qa, ensure_ascii=False, indent=2), encoding='utf-8')
print(json.dumps(qa, ensure_ascii=False))
