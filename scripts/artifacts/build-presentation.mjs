import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

// Editable academic deliverable. Run with the bundled Node runtime.
const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const dependencies = process.env.MEMORYLAB_RUNTIME ?? 'C:/Users/Urizar/.cache/codex-runtimes/codex-primary-runtime/dependencies';
const skill = process.env.MEMORYLAB_PRESENTATIONS_SKILL ?? 'C:/Users/Urizar/.codex/plugins/cache/openai-primary-runtime/presentations/26.915.20218/skills/presentations';
process.env.RUNTIME_NODE_MODULES ??= path.join(dependencies, 'node/node_modules');
const { Presentation, PresentationFile } = await import(pathToFileURL(path.join(dependencies, 'node/node_modules/@oai/artifact-tool/dist/artifact_tool.mjs')).href);
const { finalizePresentation } = await import(pathToFileURL(path.join(skill, 'container_tools/artifact_tool_utils.mjs')).href);
const staging = path.join(workspace, 'tmp/slides');
const receipts = path.join(workspace, 'tmp/presentation-qa');
const destination = process.argv[2] ? path.resolve(workspace, process.argv[2]) : path.join(workspace, 'output/presentations/memorylab-presentacion.pptx');
await fs.mkdir(staging, { recursive: true });
await fs.mkdir(receipts, { recursive: true });
await fs.mkdir(path.dirname(destination), { recursive: true });

const academicSource = await fs.readFile(path.join(workspace, 'config/memorylab.php'), 'utf8');
const academic = Object.fromEntries(['university', 'degree', 'course', 'group'].map(key => {
  const value = academicSource.match(new RegExp(`'${key}'\\s*=>\\s*'([^']+)'`))?.[1];
  if (!value) throw new Error(`Academic configuration missing: ${key}`);
  return [key, value];
}));
const members = [...academicSource.matchAll(/\['name'\s*=>\s*'([^']+)',\s*'carnet'\s*=>\s*'([^']+)'\]/g)].map(match => [match[1], match[2]]);
if (members.length !== 5) throw new Error('The academic team must contain exactly five configured members.');

const C = { purple: '#655CF3', dark: '#242338', text: '#45445A', muted: '#67667B', line: '#DFDFEA', pale: '#F1F0FF', green: '#23794C', red: '#B63D42', white: '#FFFFFF' };
const p = Presentation.create({ slideSize: { width: 1280, height: 720 } });
const tableOwners = [];
const OSTEP = {
  paging: 'https://pages.cs.wisc.edu/~remzi/OSTEP/vm-paging.pdf',
  segmentation: 'https://pages.cs.wisc.edu/~remzi/OSTEP/vm-segmentation.pdf',
  freespace: 'https://pages.cs.wisc.edu/~remzi/OSTEP/vm-freespace.pdf',
};

function text(slide, value, x, y, w, h, options = {}) {
  const shape = slide.shapes.add({ geometry: 'textbox', position: { left: x, top: y, width: w, height: h }, fill: 'none', line: { fill: 'none', width: 0 } });
  shape.text = value;
  shape.text.style = { typeface: 'Arial', fontSize: 28, color: C.text, autoFit: 'none', wrap: true, insets: { left: 0, right: 0, top: 0, bottom: 0 }, ...options };
  return shape;
}
function rect(slide, x, y, w, h, fill = C.pale, border = 'none') {
  return slide.shapes.add({ geometry: 'rect', position: { left: x, top: y, width: w, height: h }, fill, line: { fill: border, width: border === 'none' ? 0 : 1.5 } });
}
function slide(title, subtitle = '') {
  const s = p.slides.add();
  s.background.fill = C.white;
  text(s, title, 56, 42, 1168, 68, { fontSize: 46, bold: true, color: C.dark });
  if (subtitle) text(s, subtitle, 58, 116, 1164, 48, { fontSize: 25, color: C.muted });
  rect(s, 56, 665, 1168, 1, C.line);
  text(s, `MemoryLab · ${academic.group} · ${academic.course}`, 56, 681, 1040, 23, { fontSize: 15, color: C.muted });
  text(s, String(p.slides.items.length).padStart(2, '0'), 1178, 679, 46, 25, { fontSize: 18, bold: true, color: C.purple, alignment: 'right' });
  return s;
}
function notes(s, description, sources = []) {
  s.speakerNotes.textFrame.setText(`${description}\n\nFuentes y evidencia:\n${sources.map(source => `• ${source.startsWith('http') ? source : path.join(workspace, source)}`).join('\n')}`);
}
function table(s, values, x, y, widths, h, size = 25) {
  tableOwners.push(p.slides.items.length);
  const t = s.tables.add({ rows: values.length, columns: values[0].length, left: x, top: y, width: widths.reduce((a, b) => a + b, 0), height: h, columnWidths: widths, values });
  t.styleOptions = { headerRow: true, bandedRows: false };
  t.borders.assign({ style: 'solid', fill: C.line, width: 1 });
  t.cells.block({ row: 0, column: 0, rowCount: values.length, columnCount: values[0].length }).assign({ margins: { left: 16, right: 14, top: 12, bottom: 10 } });
  for (let r = 0; r < values.length; r++) {
    t.rows[r].height = h / values.length;
    for (let c = 0; c < values[0].length; c++) {
      const cell = t.getCell(r, c);
      cell.fill = r === 0 ? C.purple : r % 2 ? C.white : '#F8F8FC';
      cell.text.style = { typeface: 'Arial', fontSize: size, color: r === 0 ? C.white : C.dark, bold: r === 0, autoFit: 'none', wrap: true };
    }
  }
  return t;
}
async function image(s, filename, x, y, w, h, crop) {
  const bytes = await fs.readFile(path.join(workspace, 'docs/evidencias', filename));
  let frame = { left: x, top: y, width: w, height: h };
  if (crop) {
    const sourceWidth = bytes.readUInt32BE(16) * (1 - crop.left - crop.right);
    const sourceHeight = bytes.readUInt32BE(20) * (1 - crop.top - crop.bottom);
    const scale = Math.min(w / sourceWidth, h / sourceHeight);
    const width = sourceWidth * scale;
    const height = sourceHeight * scale;
    frame = { left: x + (w - width) / 2, top: y + (h - height) / 2, width, height };
  }
  const img = s.images.add({ blob: new Uint8Array(bytes), contentType: 'image/png', alt: `Captura real de MemoryLab: ${filename}`, fit: crop ? 'cover' : 'contain', position: frame });
  if (crop) {
    // Disable automatic cover fitting so it cannot replace the explicit source rectangle.
    img.fit = undefined;
    img.crop = crop;
  }
}
function diagramNode(s, label, x, y, w, h, color = C.pale) {
  const node = rect(s, x, y, w, h, color, C.line);
  node.text = label;
  node.text.style = { typeface: 'Arial', fontSize: 27, color: C.dark, bold: true, alignment: 'center', verticalAlignment: 'middle', autoFit: 'none', wrap: true, insets: { left: 12, right: 12, top: 14, bottom: 10 } };
  return node;
}
function connect(s, from, to, fromSide = 'right', toSide = 'left') {
  s.shapes.connect(from, to, { kind: 'straight', fromSide, toSide, line: { style: 'solid', fill: C.purple, width: 2.5 }, tail: { type: 'arrow', width: 'med', length: 'med' } });
}

// 1. Cover.
{
  const s = p.slides.add();
  s.background.fill = C.purple;
  text(s, 'MemoryLab', 72, 130, 1136, 116, { fontSize: 86, bold: true, color: C.white });
  text(s, 'Simulador interactivo de\nadministración de memoria', 76, 270, 1100, 130, { fontSize: 42, color: C.white });
  text(s, academic.university, 76, 476, 1100, 54, { fontSize: 31, bold: true, color: C.white });
  text(s, `${academic.degree}\n${academic.course} · ${academic.group}`, 76, 544, 1100, 90, { fontSize: 27, color: C.white });
  notes(s, 'Presentación académica final de doce diapositivas. MemoryLab es una simulación educativa desarrollada en Laravel. Los datos institucionales proceden de la configuración del proyecto. No se utiliza un logotipo institucional inventado.', ['config/memorylab.php', 'docs/evidencias/solicitud-inicial.md']);
}

// 2. Exact team from configuration.
{
  const s = slide('Equipo de trabajo', `${academic.degree} · ${academic.group}`);
  table(s, [['Integrante', 'Carné'], ...members], 72, 192, [820, 316], 396, 27);
  notes(s, 'Los nombres y carnés se leen directamente del archivo de configuración, conservando su escritura exacta.', ['config/memorylab.php']);
}

// 3. Objectives.
{
  const s = slide('Objetivos del simulador', 'Observar los mecanismos y explicar cada resultado.');
  const objectives = [
    ['01', 'Relacionar dirección lógica y física', 'Identificar página, desplazamiento, marco y límites del proceso.'],
    ['02', 'Seguir el acceso a una página', 'Distinguir PAGE_HIT, PAGE_FAULT, carga y reemplazo FIFO.'],
    ['03', 'Comparar formas de asignación', 'Contrastar bloques contiguos, páginas y segmentos con la misma RAM.'],
  ];
  objectives.forEach(([n, label, detail], i) => {
    const y = 206 + 141 * i;
    text(s, n, 72, y, 75, 55, { fontSize: 39, bold: true, color: C.purple });
    text(s, label, 168, y, 1035, 52, { fontSize: 31, bold: true, color: C.dark });
    text(s, detail, 168, y + 58, 1000, 60, { fontSize: 27 });
  });
  notes(s, 'Los objetivos sintetizan los requisitos de las fases de paginación, segmentación, traducción, comparación y explicación paso a paso. La memoria del sistema operativo del equipo no se consume para simular RAM.', ['docs/evidencias/solicitud-inicial.md', 'app/Services/PagingFlowService.php', 'app/Services/MemoryComparisonService.php']);
}

// 4. Editable architecture diagram.
{
  const s = slide('Arquitectura de la aplicación', 'Interfaz reactiva, reglas centralizadas y datos persistentes.');
  const ui = diagramNode(s, 'Interfaz web\nBlade + Bootstrap\nLivewire', 72, 222, 324, 155);
  const services = diagramNode(s, 'Laravel Services\nPaginación\nSegmentación', 478, 222, 324, 155);
  const db = diagramNode(s, 'MySQL\nEscenarios\nMemoria e historial', 884, 222, 324, 155);
  connect(s, ui, services); connect(s, services, db);
  text(s, 'Autorización por permisos', 76, 430, 480, 50, { fontSize: 31, bold: true, color: C.dark });
  text(s, 'Las operaciones de memoria comprueban los permisos vigentes.', 76, 490, 505, 106, { fontSize: 27 });
  text(s, 'Cambios atómicos', 666, 430, 535, 50, { fontSize: 31, bold: true, color: C.dark });
  text(s, 'Una transacción y el bloqueo del escenario conservan la coherencia.', 666, 490, 535, 106, { fontSize: 27 });
  notes(s, 'Diagrama nativo editable. Los controladores y componentes Livewire consumen servicios de dominio; las operaciones de memoria se ejecutan dentro de transacciones con bloqueo del escenario. Gates y permisos Spatie se comprueban con el usuario actualizado. La terminal educativa interpreta únicamente comandos permitidos y no ejecuta instrucciones del sistema operativo.', ['composer.json', 'app/Services/PagingService.php', 'app/Services/SegmentationService.php', 'app/Services/EducationalTerminalService.php', 'docs/informe-tecnico.md']);
}

// 5. Domain schema.
{
  const s = slide('Modelo persistente de memoria', '7 tablas de dominio · 12 claves foráneas · 6 restricciones CHECK');
  table(s, [
    ['Tabla', 'Responsabilidad'],
    ['scenarios', 'Modo y estado de la simulación'],
    ['memory_configurations', 'Capacidades de RAM, página y secundaria'],
    ['processes', 'Nombre, tamaño y estado del proceso'],
    ['memory_frames', 'Marcos físicos del escenario'],
    ['pages', 'Páginas y referencia opcional al marco'],
    ['segments', 'Número, base, tamaño y estado'],
    ['simulation_events', 'Actor, operación, fecha y metadatos'],
  ], 72, 184, [440, 696], 432, 24);
  notes(s, 'El conteo corresponde exclusivamente a las siete tablas del dominio de memoria; las tablas de usuarios y autorización no forman parte de ese total. El reporte de esquema de MySQL confirma doce FK y seis CHECK. pages.frame_id es nullable: una página sin marco se encuentra en memoria secundaria simulada. El historial se conserva después del reinicio.', ['docs/modelo-memoria.md', 'docs/evidencias/fase-8-schema.json', 'database/migrations']);
}

// 6. Real screenshot cropped to page table.
{
  const s = slide('Paginación: páginas y marcos', 'RAM de 16 KB · páginas de 1 KB · 16 marcos físicos');
  text(s, 'Tamaño fijo', 72, 210, 398, 52, { fontSize: 33, bold: true, color: C.dark });
  text(s, 'Cada página ocupa un marco completo cuando está en RAM.', 72, 274, 406, 123, { fontSize: 29 });
  text(s, 'Tabla de páginas', 72, 438, 409, 50, { fontSize: 33, bold: true, color: C.dark });
  text(s, 'El marco indica presencia. Sin marco, la página permanece en secundaria.', 72, 502, 406, 127, { fontSize: 29 });
  await image(s, 'fase-13-paginacion-escritorio.png', 518, 208, 690, 390, { left: 0.474, top: 0.20, right: 0.016, bottom: 0.565 });
  text(s, 'Tabla real: Chrome prueba, 4 páginas', 528, 612, 670, 32, { fontSize: 21, color: C.muted });
  notes(s, 'La captura real corresponde a una evidencia de la fase 13: el proceso Chrome prueba tiene cuatro páginas y la página 0 ocupa el marco 2. Este ejemplo documenta la vista de tabla; no se presenta como el estado de la demostración base. La configuración utiliza unidades de 1024 bytes por KB.', [OSTEP.paging, 'app/Services/PagingService.php', 'docs/evidencias/fase-13-paginacion-escritorio.png']);
}

// 7. Real HIT evidence plus native educational flow.
{
  const s = slide('PAGE_HIT, PAGE_FAULT y FIFO', 'Una solicitud genera una secuencia real de eventos.');
  const request = diagramNode(s, 'CPU solicita\nuna página', 74, 214, 273, 99);
  const lookup = diagramNode(s, 'Consultar tabla\nde páginas', 421, 214, 322, 99);
  connect(s, request, lookup);
  text(s, 'HIT: la página está en RAM', 76, 368, 580, 51, { fontSize: 30, bold: true, color: C.green });
  text(s, 'Acceso al marco actual.\nNo cambia la fecha de carga.', 76, 432, 588, 100, { fontSize: 27 });
  text(s, 'FAULT: la página está en secundaria', 76, 548, 1040, 42, { fontSize: 30, bold: true, color: C.red });
  text(s, 'Cargar en el primer marco libre. Si la RAM está llena, reemplazar la carga más antigua con FIFO.', 76, 597, 1130, 60, { fontSize: 25 });
  await image(s, 'fase-15-cpu-escritorio.png', 774, 325, 433, 189, { left: 0.62, top: 0.825, right: 0.015, bottom: 0.113 });
  notes(s, 'PAGE_REQUEST seguido de PAGE_HIT cuando la página ya está presente. Si está ausente, la secuencia es PAGE_REQUEST, PAGE_FAULT y PAGE_LOADED. El servicio elige el marco libre de menor número. Si no hay libres, FIFO utiliza loaded_at y luego id; la víctima pasa a secundaria. Un HIT no renueva loaded_at. La resolución es idempotente y no duplica eventos al repetirse.', [OSTEP.paging, 'app/Services/PagingService.php', 'app/Services/PagingFlowService.php', 'docs/evidencias/fase-15-cpu-escritorio.png']);
}

// 8. Fully editable translation example.
{
  const s = slide('Traducción de dirección lógica', 'Ejemplo: página de 1024 bytes y dirección lógica 1124');
  table(s, [
    ['Componente', 'Valor'],
    ['Página = 1124 ÷ 1024, parte entera', '1'],
    ['Desplazamiento = 1124 mod 1024', '100 bytes'],
    ['Marco de la página 1', '5'],
  ], 72, 194, [838, 298], 288, 27);
  text(s, 'Dirección física = 5 × 1024 + 100 = 5220', 80, 526, 1120, 67, { fontSize: 38, bold: true, color: C.purple });
  text(s, 'Se verifica el tamaño real del proceso antes de traducir.', 82, 611, 1100, 35, { fontSize: 25 });
  notes(s, 'Ejemplo matemático ilustrativo editable, independiente de las capturas. La dirección lógica 1124 produce página 1 y desplazamiento 100. Si la página 1 está en el marco 5, la dirección física es 5220. Cuando la página está en secundaria, el traductor informa ausencia y dirección física null; traducir no provoca carga, fallo ni eventos. El límite es el tamaño real del proceso, no el relleno de su última página.', [OSTEP.paging, 'app/Services/AddressTranslationService.php', 'docs/evidencias/fase-17.md']);
}

// 9. Segmentation boundary plus real fault evidence.
{
  const s = slide('Segmentación: base y límite', 'Segmento Código: base 1000 · tamaño 1200 bytes');
  table(s, [
    ['Desplazamiento', 'Comprobación', 'Resultado'],
    ['100', '100 < 1200', 'Física = 1000 + 100 = 1100'],
    ['1200', '1200 ≥ 1200', 'SEGMENTATION_FAULT'],
  ], 72, 190, [270, 312, 554], 195, 25);
  await image(s, 'fase-19-segmentation-fault-escritorio.png', 73, 416, 1135, 220, { left: 0.225, top: 0.85, right: 0.025, bottom: 0.046 });
  notes(s, 'La condición estricta es 0 <= offset < size_bytes. Un acceso válido produce base + offset y el evento SEGMENT_ACCESS. Si el desplazamiento iguala o supera el tamaño, el resultado es SEGMENTATION_FAULT sin dirección física y sin modificar RAM ni el estado de ejecución. Los segmentos activos no se solapan y el total del proceso no supera su tamaño. La captura real muestra el límite exacto de 1200 bytes.', [OSTEP.segmentation, 'app/Services/SegmentationService.php', 'docs/evidencias/fase-19-segmentation-fault-escritorio.png']);
}

// 10. Fair mathematical comparison, native editable table.
{
  const s = slide('Comparación con la misma RAM', 'Solicitud de 7168 bytes · RAM de 16 KB · 9 KB libres');
  text(s, 'Huecos disponibles: 3 KB y 6 KB. El mayor bloque libre mide 6 KB.', 73, 184, 1135, 50, { fontSize: 27 });
  table(s, [
    ['Técnica', '¿Acepta?', 'Reserva nueva', 'RAM libre final'],
    ['Contigua', 'No', '0 bytes', '9216 bytes'],
    ['Paginación', 'Sí: 7 páginas', '7168 bytes', '2048 bytes'],
    ['Segmentación', 'Sí: 2 segmentos', '7168 bytes', '2048 bytes'],
  ], 72, 255, [268, 319, 271, 278], 268, 25);
  text(s, 'Contigua exige un bloque de 7 KB.', 78, 548, 1098, 42, { fontSize: 29, bold: true, color: C.dark });
  text(s, 'Páginas de 1 KB y segmentos Código de 4 KB + Datos de 3 KB aprovechan los huecos separados.', 78, 596, 1117, 57, { fontSize: 25 });
  notes(s, 'Comparación matemática sin modificar la base de datos. Ocupaciones iniciales A=[0,4096) y B=[7168,10240). Huecos: [4096,7168), 3072 bytes; [10240,16384), 6144 bytes. Contigua first-fit rechaza una solicitud de 7168 porque el hueco mayor es 6144. Paginación reserva siete marcos de 1024 y desperdicio interno cero. Segmentación divide la solicitud en Código=4096 y Datos=3072: Código cabe en el hueco de 6144 y Datos en el de 3072. Ambos aceptados dejan 2048 bytes libres. Los modos rechazados conservan el estado inicial.', [OSTEP.freespace, OSTEP.paging, OSTEP.segmentation, 'app/Services/MemoryComparisonService.php', 'tests/Feature/MemoryComparisonTest.php']);
}

// 11. Roles and authoritative full-suite verification.
{
  const s = slide('Roles y verificación del proyecto', 'Permisos vigentes y pruebas sobre los límites del modelo.');
  table(s, [
    ['Rol', 'Alcance'],
    ['Administrador', 'Configuración, usuarios, historial y reinicio'],
    ['Operador', 'Creación de procesos y ejecución'],
    ['Observador', 'Consulta de memoria y resultados'],
  ], 72, 195, [246, 467], 328, 25);
  text(s, '655', 843, 180, 340, 111, { fontSize: 80, bold: true, color: C.purple });
  text(s, 'pruebas pasadas', 848, 299, 340, 45, { fontSize: 28, bold: true, color: C.dark });
  text(s, '1 omitida\n4575 aserciones\n96.78 segundos', 848, 376, 340, 145, { fontSize: 28 });
  text(s, 'Cobertura de límites, permisos, aislamiento, FIFO, reinicio y reversión atómica.', 78, 562, 1124, 83, { fontSize: 27 });
  notes(s, 'Resultado de la verificación integral de fase 27: 655 pruebas pasadas, 1 omitida, 4575 aserciones y duración 96.78 segundos. Las cifras provienen de la ejecución realizada para el cierre del proyecto; este proceso de autoría no ejecuta nuevamente pruebas ni consulta la base de datos. Los permisos se asignan por rol, con comprobación de autorización actualizada en los servicios. Los casos cubren lectura sin mutaciones, aislamiento entre escenarios, capacidad, FIFO, reintentos, límites de segmentos y reversión de transacciones.', ['database/seeders/RolesAndPermissionsSeeder.php', 'app/Enums/PermissionName.php', 'docs/evidencias/fase-27.md', 'tests/Feature']);
}

// 12. Conclusions, traceable AI use and live demo.
{
  const s = slide('Conclusiones y demostración', 'Explicar el resultado mediante estado, límites y eventos.');
  text(s, 'Aprendizajes', 72, 206, 497, 49, { fontSize: 31, bold: true, color: C.dark });
  text(s, 'La tabla conecta páginas y marcos.\nEl límite protege cada segmento.\nLa fragmentación determina qué cabe.', 72, 267, 500, 151, { fontSize: 27 });
  text(s, 'IA y evidencia', 72, 447, 501, 50, { fontSize: 31, bold: true, color: C.dark });
  text(s, 'Codex/ChatGPT apoyó análisis, desarrollo y correcciones. Los prompts y resultados quedan documentados.', 72, 507, 500, 121, { fontSize: 26 });
  await image(s, 'fase-25-presentacion-escritorio.png', 615, 190, 593, 455);
  notes(s, 'Ruta sugerida de demostración: iniciar un par nuevo de demostración, seleccionar Chrome y solicitar página 0 para HIT; solicitar página 3, inicialmente en secundaria, para PAGE_FAULT. Para observar FIFO, completar la RAM usando carga controlada y solicitar una página ausente. En segmentación, seleccionar Código y offset 1200 para mostrar el límite inválido. La captura real de modo presentación muestra una carga de Chrome página 3 en marco 5. El uso de IA se documenta mediante la solicitud inicial, decisiones, correcciones y evidencias por fase; no sustituye los resultados comprobables del software.', ['docs/evidencias/solicitud-inicial.md', 'docs/evidencias/fase-25-presentacion-escritorio.png', 'app/Services/SimulationService.php', 'app/Services/MemoryStressService.php', 'docs/evidencias']);
}

if (p.slides.items.length !== 12) throw new Error('Expected exactly twelve slides.');
const candidate = path.join(staging, `candidate-${path.basename(destination)}`);
await (await PresentationFile.exportPptx(p)).save(candidate);
const result = await finalizePresentation({
  explicitTotalSlideCount: 12,
  workspaceDir: workspace,
  candidatePath: candidate,
  finalPath: destination,
  pythonExecutable: path.join(dependencies, 'python/python.exe'),
  integrityValidatorPath: path.join(skill, 'container_tools/inspect_presentation_package_integrity.py'),
  layoutValidatorPath: path.join(skill, 'container_tools/inspect_presentation_layout_geometry.py'),
  layoutArgs: ['--expected-slide-size-emu', '12192000,6858000', '--validate-bullet-geometry', '--validate-heading-fit', ...tableOwners.flatMap(number => ['--require-native-table-slide', String(number)])],
  requiredNativeTableOwnerSlides: tableOwners,
  fontPolicy: { basis: 'design', families: ['Arial'] },
  verifyArtifactToolImport: true,
  receiptPath: path.join(receipts, `${path.basename(destination)}.validation.json`),
});
console.log(JSON.stringify({ finalPath: destination, slides: p.slides.items.length, nativeTableSlides: tableOwners, sha256: result.finalSha256, receiptPath: result.receiptPath, validationFindings: result.packageIntegrity.findingCount + result.presentationLayout.findingCount, importPassed: result.firstPartyImport.passed }, null, 2));
