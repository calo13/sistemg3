import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import crypto from 'node:crypto';

// Editable lecture slides. No application or database operations are performed.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const deps = process.env.MEMORYLAB_RUNTIME ?? 'C:/Users/Urizar/.cache/codex-runtimes/codex-primary-runtime/dependencies';
const skill = process.env.MEMORYLAB_PRESENTATIONS_SKILL ?? 'C:/Users/Urizar/.codex/plugins/cache/openai-primary-runtime/presentations/26.1020.11127/skills/presentations';
process.env.RUNTIME_NODE_MODULES ??= path.join(deps, 'node/node_modules');
const { Presentation, PresentationFile, FileBlob } = await import(pathToFileURL(path.join(deps, 'node/node_modules/@oai/artifact-tool/dist/artifact_tool.mjs')).href);
const { finalizePresentation } = await import(pathToFileURL(path.join(skill, 'container_tools/artifact_tool_utils.mjs')).href);
const version = process.argv[2] ?? 'v1';
if (!/^v\d+$/.test(version)) throw new Error('Use a revision name such as v1.');
const work = path.join(root, 'tmp/exposition-presentation', version);
const renderDir = path.join(work, 'renders');
const checkedDir = path.join(work, 'checked');
for (const dir of [work, renderDir, checkedDir]) await fs.mkdir(dir, { recursive: true });

const guidePath = path.join(root, 'docs/guion-exposicion-60-minutos.md');
const guide = await fs.readFile(guidePath, 'utf8');
const php = await fs.readFile(path.join(root, 'config/memorylab.php'), 'utf8');
const members = [...php.matchAll(/\['name'\s*=>\s*'([^']+)',\s*'carnet'\s*=>\s*'([^']+)'\]/g)].map(m => [m[1], m[2]]);
if (members.length !== 5) throw new Error('Five configured members are required.');
const names = ['Enmer', 'Lester', 'Deivid', 'Rebeca', 'Carlos'];
const fullNames = Object.fromEntries(names.map((n, i) => [n, members[i][0]]));
const chunks = new Map();
for (const match of guide.matchAll(/^## (Enmer|Lester|Deivid|Rebeca|Carlos) de (\d+) a (\d+) minutos\r?\n([\s\S]*?)(?=^<!-- pagebreak -->|$(?![\s\S]))/gm)) {
  const content = match[4].trim();
  const paragraphs = [];
  let spokenLines = [];
  const flushSpeech = () => {
    if (spokenLines.length) paragraphs.push(spokenLines.join(' '));
    spokenLines = [];
  };
  for (const line of content.split(/\r?\n/)) {
    if (/^>\s*\S/.test(line)) spokenLines.push(line.replace(/^>\s?/, ''));
    else flushSpeech();
  }
  flushSpeech();
  const actions = content.split(/\r?\n\r?\n/).filter(block => /^\*\*(HACER|COMPROBAR|PAUSA|SI HAY PREGUNTAS)/.test(block)).join('\n\n').replace(/\*\*/g, '');
  chunks.set(`${match[1]}-${Number(match[2])}`, { speaker: match[1], start: Number(match[2]), end: Number(match[3]), paragraphs, actions });
}
if (chunks.size !== 20) throw new Error(`Expected 20 script blocks; found ${chunks.size}.`);
if (chunks.get('Carlos-57').paragraphs.length !== 7) throw new Error('Closing notes require four conclusions, two review answers and a farewell.');

const C = { navy: '#16324f', red: '#c63d44', ink: '#20334b', muted: '#526171', line: '#d9d9d9', pale: '#f2f5f9', white: '#ffffff', green: '#25724d' };
const presentation = Presentation.create({ slideSize: { width: 1280, height: 720 } });
const tableOwners = [];
const manifest = [];

function text(slide, value, x, y, width, height, options = {}) {
  const shape = slide.shapes.add({ geometry: 'textbox', position: { left: x, top: y, width, height }, fill: 'none', line: { fill: 'none', width: 0 } });
  shape.text = value;
  shape.text.style = { typeface: 'Arial', fontSize: 28, color: C.ink, autoFit: 'none', wrap: true, insets: { left: 0, right: 0, top: 0, bottom: 0 }, ...options };
  return shape;
}

function add(title, key, subtitle = '', opts = {}) {
  const block = chunks.get(key);
  if (!block) throw new Error(`Missing script block ${key}.`);
  const slide = presentation.slides.add();
  const dark = opts.dark ?? false;
  slide.background.fill = dark ? C.navy : C.white;
  if (!opts.cover) text(slide, title, 72, 42, 1136, 76, { fontSize: 44, bold: true, color: dark ? C.white : C.navy });
  if (subtitle) text(slide, subtitle, 74, 121, 1132, 58, { fontSize: 25, color: dark ? '#dce5ef' : C.muted });
  const index = presentation.slides.items.length;
  const time = opts.time ?? `${String(block.start).padStart(2, '0')}:00 a ${String(block.end).padStart(2, '0')}:00`;
  text(slide, `${fullNames[block.speaker]}     ${time}     ${index} de 24`, 72, 674, 1136, 27, { fontSize: 16, color: dark ? '#dce5ef' : C.muted });
  const speech = opts.paragraphs ?? block.paragraphs;
  const action = opts.actions ?? block.actions;
  const notes = [
    `EXPOSITOR: ${fullNames[block.speaker]}`,
    `TIEMPO: ${time}`,
    `GUION: ${block.speaker}, tramo ${block.start} a ${block.end} minutos.`,
    '', 'HACER Y COMPROBAR', action,
    '', 'DECIR EN VOZ ALTA', speech.join('\n\n'),
    '', 'CAMBIO DE PANTALLA', opts.next ?? 'Alternar con la aplicación cuando lo indique el guion. Verificar escenario y proceso.',
    '', 'FUENTES', 'docs/guion-exposicion-60-minutos.md', 'config/memorylab.php', ...(opts.sources ?? []),
  ].join('\n');
  slide.speakerNotes.textFrame.setText(notes);
  manifest.push({ slide: index, title, speaker: block.speaker, time, guide_block: key, spoken_paragraphs: speech.length });
  return slide;
}

function table(slide, rows, x, y, widths, height, size = 26) {
  const owner = presentation.slides.items.length;
  if (!tableOwners.includes(owner)) tableOwners.push(owner);
  const t = slide.tables.add({ rows: rows.length, columns: rows[0].length, left: x, top: y, width: widths.reduce((a, b) => a + b, 0), height, columnWidths: widths, values: rows });
  t.styleOptions = { headerRow: true, bandedRows: false };
  t.borders.assign({ style: 'solid', fill: C.line, width: 1 });
  t.cells.block({ row: 0, column: 0, rowCount: rows.length, columnCount: rows[0].length }).assign({ margins: { left: 14, right: 14, top: 9, bottom: 8 } });
  for (let r = 0; r < rows.length; r++) {
    t.rows[r].height = height / rows.length;
    for (let c = 0; c < rows[0].length; c++) {
      const cell = t.getCell(r, c);
      cell.fill = r === 0 ? C.navy : r % 2 ? C.white : C.pale;
      cell.text.style = { typeface: 'Arial', fontSize: size, color: r === 0 ? C.white : C.ink, bold: r === 0, autoFit: 'none', wrap: true };
    }
  }
  return t;
}

const intro = chunks.get('Enmer-0').paragraphs;
{
  const s = add('MemoryLab', 'Enmer-0', '', { cover: true, paragraphs: intro.slice(0, 1), next: 'Diapositiva 2 para presentar al equipo.', sources: ['public/images/UMG-SOURCE.md', 'https://umg.edu.gt/img/Umg.png'] });
  text(s, 'MemoryLab', 72, 118, 770, 100, { fontSize: 74, bold: true, color: C.navy });
  text(s, 'Administración de memoria', 76, 238, 750, 126, { fontSize: 45, color: C.navy });
  text(s, 'Universidad Mariano Gálvez\nIngeniería en Sistemas\nSistemas Operativos 1  Grupo 3', 77, 437, 800, 141, { fontSize: 29 });
  const logo = await fs.readFile(path.join(root, 'public/images/umg-logo.png'));
  s.images.add({ blob: new Uint8Array(logo), contentType: 'image/png', alt: 'Escudo original de la Universidad Mariano Gálvez de Guatemala', fit: 'contain', position: { left: 922, top: 196, width: 260, height: 261 } });
}
{
  const s = add('Equipo de trabajo', 'Enmer-0', 'Cinco integrantes con participación en la exposición', { paragraphs: intro.slice(1, 2), next: 'Diapositiva 3 para explicar el objetivo.' });
  table(s, [['Integrante', 'Carné'], ...members], 72, 204, [800, 336], 366, 27);
}
{
  const s = add('Temas de la exposición', 'Enmer-0', 'Conceptos y demostraciones del Grupo 3', { paragraphs: intro.slice(2), next: 'Diapositiva 4. Empezar el tramo 03:00 a 06:00.' });
  text(s, 'Tablas de páginas y fallos de página\nReemplazo FIFO y traducción de direcciones\nTablas de segmentos, base y límite\nAsignación contigua y no contigua\nFragmentación y conclusiones', 78, 207, 1108, 330, { fontSize: 34 });
  text(s, 'La RAM y el disco representan un modelo educativo.', 78, 574, 1108, 44, { fontSize: 27, color: C.red });
}
{
  const s = add('Página y marco', 'Enmer-3', 'La página pertenece al proceso. El marco es un espacio de RAM.');
  table(s, [['Concepto', 'Qué representa', 'Tamaño del ejemplo'], ['Página', 'Parte de la memoria de Chrome', '1 KB'], ['Marco', 'Espacio de RAM para una página', '1 KB']], 72, 210, [220, 610, 306], 238, 27);
  text(s, '4 KB de RAM ÷ 1 KB por página = 4 marcos', 78, 513, 1110, 68, { fontSize: 39, bold: true, color: C.red });
  text(s, 'Los números de página y de marco empiezan en cero.', 78, 594, 1110, 38, { fontSize: 26 });
}
{
  const s = add('La aplicación y sus reglas', 'Enmer-6', 'Las tablas y el mapa consultan el estado guardado.', { sources: ['routes/web.php', 'app/Services', 'database/seeders/RolesAndPermissionsSeeder.php'] });
  table(s, [['Parte', 'Responsabilidad'], ['Blade y Bootstrap', 'Presentar formularios, tablas y resultados'], ['Livewire', 'Comunicar las acciones de la pantalla'], ['Servicios de Laravel', 'Validar permisos y reglas de memoria'], ['MySQL', 'Conservar escenarios, procesos y eventos']], 72, 202, [350, 786], 320, 27);
  text(s, 'Administrador prepara la demostración. Operador ejecuta y Observador consulta según sus permisos.', 78, 555, 1110, 82, { fontSize: 27 });
}
{
  const block = chunks.get('Enmer-9');
  const s = add('Configuración de la demostración', 'Enmer-9', 'En la aplicación: Configuración de memoria', { paragraphs: block.paragraphs.slice(0, 1), next: 'Guardar configuración, anotar el #ID y pasar a la diapositiva 7.', sources: ['resources/views/livewire/memory-configuration.blade.php'] });
  table(s, [['Campo', 'Valor'], ['Nombre del escenario', 'Exposición G3 Paginación 01'], ['RAM total', '4 KB'], ['Tamaño de página', '1 KB'], ['Número de marcos', '4, calculados automáticamente'], ['Almacenamiento secundario', '16 KB']], 72, 190, [550, 586], 360, 25);
  text(s, 'Crear un escenario nuevo y guardar la configuración.', 78, 586, 1110, 44, { fontSize: 28, color: C.red });
}
{
  const block = chunks.get('Enmer-9');
  const s = add('El proceso y sus páginas', 'Enmer-9', 'En la aplicación: Procesos', { paragraphs: block.paragraphs.slice(1), next: 'Crear Chrome, abrir Paginación y seleccionar el proceso. Lester continúa con la diapositiva 8.', sources: ['resources/views/livewire/process-manager.blade.php'] });
  table(s, [['Campo', 'Valor'], ['Nombre', 'Chrome'], ['Tamaño', '6 KB'], ['Páginas requeridas', '6, numeradas de P0 a P5']], 72, 212, [480, 656], 280, 29);
  text(s, 'RAM inicial 0 KB. Secundaria utilizada 6 KB.', 78, 542, 1108, 58, { fontSize: 35, bold: true, color: C.red });
  text(s, 'Crear el proceso deja sus páginas sin marco.', 78, 609, 1108, 34, { fontSize: 26 });
}
{
  const s = add('Tabla inicial de Chrome', 'Lester-12', 'Estado esperado antes de solicitar páginas', { sources: ['resources/views/livewire/paging-simulator.blade.php'] });
  table(s, [['Página', 'Marco', 'Presente', 'Estado'], ...Array.from({ length: 6 }, (_, i) => [`P${i}`, 'Sin marco', 'No', 'DISCO'])], 72, 190, [220, 350, 260, 306], 350, 27);
  text(s, '4 marcos libres. Ninguna página ocupa RAM.', 78, 586, 1110, 48, { fontSize: 30, color: C.red });
}
{
  const s = add('Page Fault paso a paso', 'Lester-15', 'P0 está ausente. La carga se confirma en el paso 6.', { sources: ['app/Services/PagingFlowService.php'], next: 'En la app, elegir Paso a paso, solicitar P0 y avanzar hasta el paso 8.' });
  table(s, [['Paso', 'Qué ocurre'], ['1', 'La CPU solicita P0'], ['2', 'Consulta la tabla de páginas'], ['3', 'Comprueba que P0 está ausente'], ['4', 'Identifica el Page Fault'], ['5', 'Busca un marco libre'], ['6', 'Carga P0 en el marco 0'], ['7', 'Explica la actualización de la tabla'], ['8', 'Completa el acceso']], 72, 186, [160, 976], 420, 25);
}
{
  const s = add('Carga de los cuatro marcos', 'Lester-18', 'Orden de solicitudes P0, P1, P3, P2', { sources: ['app/Services/PagingService.php'] });
  table(s, [['Solicitud', 'Marco', 'RAM usada KB', 'Faults'], ['P0', '0', '1', '1'], ['P1', '1', '2', '2'], ['P3', '2', '3', '3'], ['P2', '3', '4', '4']], 72, 210, [280, 220, 360, 276], 320, 28);
  text(s, 'P3 ocupa el marco 2. P4 y P5 siguen en disco.', 78, 578, 1110, 52, { fontSize: 31, color: C.red });
}
{
  const s = add('Page Hit con la RAM llena', 'Lester-21', 'Volvemos a solicitar P0, que ya está en el marco 0.');
  table(s, [['Indicador', 'Resultado tras el Hit'], ['RAM utilizada', '4 KB'], ['Marcos ocupados', '4'], ['Page Faults acumulados', '4']], 72, 210, [620, 516], 280, 30);
  text(s, 'El Hit conserva la antigüedad de carga de P0.', 78, 537, 1110, 53, { fontSize: 33, color: C.green });
  text(s, 'El contador acumulado se comprueba en Inicio.', 78, 602, 1110, 35, { fontSize: 26 });
}
{
  const s = add('RAM llena y reemplazo FIFO', 'Deivid-24', 'Solicitamos P4 después del Hit de P0.', { sources: ['app/Services/PagingService.php'] });
  table(s, [['Marco', 'Antes de solicitar P4', 'Después de solicitar P4'], ['0', 'P0', 'P4'], ['1', 'P1', 'P1'], ['2', 'P3', 'P3'], ['3', 'P2', 'P2']], 72, 210, [210, 463, 463], 320, 28);
  text(s, 'Sale P0 y entra P4. Faults acumulados 5.', 78, 577, 1110, 52, { fontSize: 33, color: C.red });
}
{
  const s = add('Traducción de la dirección 3500', 'Deivid-27', 'Página de 1024 bytes. P3 está en el marco 2.', { sources: ['app/Services/AddressTranslationService.php'] });
  table(s, [['Componente', 'Cálculo o valor'], ['Página', 'Parte entera de 3500 ÷ 1024 = 3'], ['Offset', '3500 - 3072 = 428 bytes'], ['Marco actual', 'P3 está en el marco 2'], ['Inicio del marco', '2 × 1024 = 2048']], 72, 202, [365, 771], 300, 27);
  text(s, 'Dirección física = 2048 + 428 = 2476 bytes', 78, 556, 1110, 67, { fontSize: 38, bold: true, color: C.red });
}
{
  const s = add('Traducción con una página ausente', 'Deivid-30', 'Consultamos la dirección lógica 100 después de expulsar P0.', { sources: ['app/Services/AddressTranslationService.php'] });
  table(s, [['Componente', 'Resultado'], ['Página y offset', 'P0 y 100 bytes'], ['Ubicación de P0', 'DISCO'], ['Marco', 'Sin asignación']], 72, 212, [500, 636], 255, 29);
  text(s, 'Dirección física no disponible', 78, 505, 1110, 70, { fontSize: 43, color: C.red, bold: true });
  text(s, 'Traducir consulta la tabla. La carga se solicita en Paginación.', 78, 589, 1110, 54, { fontSize: 26 });
}
{
  const s = add('P0 regresa en otro marco', 'Deivid-33', 'Solicitamos P0. FIFO devuelve P1 a secundaria.', { sources: ['app/Services/PagingService.php', 'app/Services/AddressTranslationService.php'] });
  table(s, [['Elemento', 'Resultado'], ['Página que entra', 'P0'], ['Página que sale', 'P1'], ['Marco de P0', '1'], ['Faults acumulados', '6']], 72, 195, [555, 581], 300, 29);
  text(s, 'Física = 1 × 1024 + 100 = 1124 bytes', 78, 536, 1110, 63, { fontSize: 39, bold: true, color: C.red });
  text(s, '7 solicitudes completadas. 6 Faults y 1 Hit.', 78, 609, 1110, 34, { fontSize: 26 });
}
{
  const s = add('Segmentación y nuevo escenario', 'Rebeca-36', 'Cada segmento tiene una base y un tamaño propios.', { sources: ['resources/views/livewire/segmentation-simulator.blade.php'] });
  table(s, [['Formulario', 'Campo', 'Valor'], ['Nuevo escenario', 'Nombre', 'Exposición G3 Segmentación 01'], ['Nuevo escenario', 'RAM total', '16 KB'], ['Crear proceso', 'Nombre', 'Editor'], ['Crear proceso', 'Tamaño', '4 KB']], 72, 212, [320, 240, 576], 320, 25);
  text(s, 'Código, Datos, Stack y Heap tienen tamaños diferentes.', 78, 580, 1110, 54, { fontSize: 29, color: C.red });
}
{
  const s = add('Tabla de segmentos de Editor', 'Rebeca-39', 'Las bases y los tamaños se ingresan en bytes.', { sources: ['app/Services/SegmentationService.php'] });
  table(s, [['Segmento', 'Base', 'Tamaño', 'Última dirección'], ['Código S0', '1000', '1200', '2199'], ['Datos S1', '4000', '800', '4799'], ['Stack S2', '7000', '600', '7599'], ['Heap S3', '9000', '1000', '9999']], 72, 210, [330, 230, 230, 346], 320, 28);
  text(s, 'RAM ocupada 3600 bytes. Disponible 12784 bytes.', 78, 578, 1110, 53, { fontSize: 31, color: C.red });
}
{
  const s = add('Accesos dentro del segmento Código', 'Rebeca-42', 'Base 1000. Tamaño y límite 1200 bytes.', { sources: ['app/Services/SegmentationService.php'] });
  table(s, [['Offset', 'Comprobación', 'Dirección física'], ['100', '100 < 1200', '1000 + 100 = 1100'], ['1199', '1199 < 1200', '1000 + 1199 = 2199']], 72, 234, [210, 390, 536], 240, 29);
  text(s, 'Último desplazamiento válido 1199', 78, 534, 1110, 64, { fontSize: 39, color: C.green, bold: true });
}
{
  const s = add('Acceso fuera del límite', 'Rebeca-45', 'Código conserva su tamaño de 1200 bytes.', { sources: ['app/Services/SegmentationService.php'] });
  text(s, '1200 < 1200 es falso', 78, 233, 1110, 85, { fontSize: 59, bold: true, color: C.red });
  text(s, 'SEGMENTATION_FAULT', 78, 346, 1110, 72, { fontSize: 48, bold: true, color: C.red });
  text(s, 'El acceso no entrega una dirección física válida.', 80, 455, 1108, 75, { fontSize: 32 });
  text(s, 'La región permanece igual y Editor conserva sus segmentos.', 80, 558, 1108, 70, { fontSize: 28 });
}
{
  const s = add('Comparación de una solicitud de 8 KB', 'Carlos-48', '9 KB libres en huecos de 3 y 6 KB. El mayor hueco mide 6 KB.', { sources: ['app/Services/MemoryComparisonService.php'] });
  table(s, [['Técnica', 'Acepta', 'Reserva bytes', 'Libre final bytes'], ['Contigua', 'No', '0', '9216'], ['Paginación', 'Sí', '8192', '1024'], ['Segmentación', 'Sí', '8192', '1024']], 72, 220, [340, 180, 300, 316], 280, 27);
  text(s, '8 KB no caben en un solo hueco de 6 KB.', 78, 548, 1110, 57, { fontSize: 36, color: C.red, bold: true });
  text(s, 'El comparador es un ejemplo calculado independiente.', 78, 611, 1110, 32, { fontSize: 25 });
}
{
  const s = add('Fragmentación interna con 7500 bytes', 'Carlos-51', 'Paginación utiliza marcos de 1024 bytes.', { sources: ['app/Services/MemoryComparisonService.php'] });
  table(s, [['Técnica', 'Reserva bytes', 'Interna bytes', 'Libre bytes'], ['Paginación', '8192', '692', '1024'], ['Segmentación', '7500', '0', '1716']], 72, 222, [350, 275, 275, 236], 222, 27);
  text(s, '8192 - 7500 = 692 bytes de sobrante', 78, 492, 1110, 72, { fontSize: 42, bold: true, color: C.red });
  text(s, 'Contigua se rechaza porque 7500 es mayor que 6144.', 78, 590, 1110, 45, { fontSize: 28 });
}
{
  const s = add('IA y verificación del proyecto', 'Carlos-54', 'Instrucciones, correcciones y resultados documentados.', { sources: ['docs/uso-ia.md', 'docs/evidencias/fase-27.md', 'database/seeders/RolesAndPermissionsSeeder.php'] });
  text(s, 'Apoyo al desarrollo', 78, 228, 655, 53, { fontSize: 35, bold: true });
  text(s, 'Análisis, código y documentación\ncon asistencia de IA', 78, 298, 655, 113, { fontSize: 30 });
  text(s, 'Comprobación de tablas, límites,\npermisos y cálculos', 78, 460, 655, 112, { fontSize: 30 });
  text(s, '655', 850, 206, 330, 128, { fontSize: 96, bold: true, color: C.navy });
  text(s, 'pruebas aprobadas', 852, 349, 340, 48, { fontSize: 28 });
  text(s, '1 omisión condicional\n4575 aserciones', 852, 414, 340, 105, { fontSize: 27 });
  text(s, 'Revisión del 8 de octubre de 2026', 852, 550, 340, 74, { fontSize: 23, color: C.red });
}
{
  const block = chunks.get('Carlos-57');
  const s = add('Conclusiones', 'Carlos-57', '', { time: '57:00 a 58:40', paragraphs: block.paragraphs.slice(0, 4), next: 'Pasar a la diapositiva 24 a las 58:40 para preguntas.' });
  text(s, 'La tabla de páginas relaciona cada página\ncon su ubicación actual en RAM.', 78, 204, 1110, 110, { fontSize: 36 });
  text(s, 'El límite protege el acceso\na cada segmento.', 78, 363, 1110, 110, { fontSize: 36 });
  text(s, 'La distribución del espacio libre\ndetermina qué solicitudes caben.', 78, 522, 1110, 110, { fontSize: 36 });
}
{
  const block = chunks.get('Carlos-57');
  const s = add('Preguntas', 'Carlos-57', '', { dark: true, time: '58:40 a 60:00', paragraphs: block.paragraphs.slice(4), next: 'Cerrar a las 60:00. Responder de acuerdo con el reparto del guion.' });
  text(s, '¿La página cero siempre ocupa\nel marco cero?', 78, 222, 1108, 130, { fontSize: 43, color: C.white });
  text(s, '¿Nueve KB libres garantizan\nocho KB contiguos?', 78, 402, 1108, 130, { fontSize: 43, color: C.white });
  text(s, 'Gracias por su atención', 80, 592, 1108, 45, { fontSize: 29, color: '#dce5ef' });
}

if (presentation.slides.items.length !== 24) throw new Error('Exactly 24 slides are required.');
if (1200 + 800 + 600 + 1000 !== 3600 || 16384 - 3600 !== 12784) throw new Error('Segment arithmetic mismatch.');
if (2 * 1024 + 428 !== 2476 || 1024 + 100 !== 1124 || 8192 - 7500 !== 692) throw new Error('Translation arithmetic mismatch.');
const candidate = path.join(work, 'candidate.pptx');
const final = path.join(checkedDir, `memorylab-exposicion-${version}.pptx`);
await (await PresentationFile.exportPptx(presentation)).save(candidate);
console.log(`Candidate exported: ${candidate}`);
const result = await finalizePresentation({
  explicitTotalSlideCount: 24,
  workspaceDir: root, candidatePath: candidate, finalPath: final,
  pythonExecutable: path.join(deps, 'python/python.exe'),
  integrityValidatorPath: path.join(skill, 'container_tools/inspect_presentation_package_integrity.py'),
  layoutValidatorPath: path.join(skill, 'container_tools/inspect_presentation_layout_geometry.py'),
  layoutArgs: ['--expected-slide-size-emu', '12192000,6858000', '--validate-bullet-geometry', '--validate-heading-fit', ...tableOwners.flatMap(n => ['--require-native-table-slide', String(n)])],
  requiredNativeTableOwnerSlides: tableOwners,
  fontPolicy: { basis: 'design', families: ['Arial'] },
  verifyArtifactToolImport: true,
  receiptPath: path.join(work, 'validation.json'),
});
console.log(`Finalized: ${result.finalSha256}`);
const checked = await PresentationFile.importPptx(await FileBlob.load(final));
for (const [i, slide] of checked.slides.items.entries()) {
  const image = await checked.export({ slide, format: 'png', scale: 1 });
  await fs.writeFile(path.join(renderDir, `slide-${String(i + 1).padStart(2, '0')}.png`), new Uint8Array(await image.arrayBuffer()));
  console.log(`Rendered slide ${i + 1}/24`);
}
const qa = {
  file: final, slides: 24, native_table_slides: tableOwners,
  native_table_count: tableOwners.length,
  logo_source: 'public/images/umg-logo.png',
  sha256: result.finalSha256,
  guide_sha256: crypto.createHash('sha256').update(guide).digest('hex'),
  validation_findings: result.packageIntegrity.findingCount + result.presentationLayout.findingCount,
  import_passed: result.firstPartyImport.passed,
  visual_review: 'pending', manifest,
  application_or_database_operations: false,
};
await fs.writeFile(path.join(work, 'build-qa.json'), JSON.stringify(qa, null, 2) + '\n');
console.log(JSON.stringify({ slides: 24, tables: tableOwners.length, findings: qa.validation_findings, final }, null, 2));
