# Fase 10 — Procesos

## Autorización y alcance

El usuario indicó «continua» después de la Fase 9 y de solicitar una cuenta Administrador local. Se revisaron la solicitud, modelos, configuración de memoria, selección de escenario, permisos, migración, dashboard y pruebas. Durante la implementación volvió a indicar «continua», manteniendo el trabajo de esta fase.

Se implementó creación y consulta de procesos por escenario, cálculo de páginas y reserva secundaria inicial. Servicio, pruebas y revisión independiente se delegaron en archivos separados. No se adelantaron los algoritmos ni las pantallas de paginación.

## Implementación

- Ruta `GET /procesos`, nombre `processes.index`, protegida por autenticación, sesión Jetstream y `memory.view`. Enlace Procesos en el menú Sneat.
- Componente Livewire `ProcessManager` y formulario Bootstrap/Sneat: nombre, tamaño en KB y número de páginas calculado, de solo lectura. Selector explícito que utiliza el contexto de sesión existente.
- Administrador y Operador crean mediante `processes.create`. Observador consulta, selecciona escenario y utiliza la paginación; no dispone de formulario y las acciones manipuladas se rechazan en servidor.
- `ProcessManagerService` recarga al actor, valida entrada, bloquea el escenario y exige PAGING, READY/RUNNING, configuración válida y marcos consistentes. Comparte el bloqueo con `MemoryConfigurationService`.
- Tamaño positivo en KB enteros; nombre recortado, no vacío y máximo 100 caracteres. Límites en configuración: 65.536 KB, 1.024 páginas por proceso y 100 procesos totales por escenario, incluidos TERMINATED.
- Las páginas se calculan con división entera redondeada hacia arriba. Se numeran desde cero y nacen con frame_id null. La capacidad secundaria se reserva por páginas completas, no por el tamaño nominal del proceso.
- Reserva actual: todas las páginas sin marco del escenario × tamaño de página. Incluye páginas de procesos terminados que aún existan; excluye las páginas con marco. La capacidad cero o insuficiente rechaza la creación.
- Proceso READY, páginas y evento PROCESS_CREATED se persisten en la misma transacción. Actor, estado y relaciones se obtienen en servidor; se ignoran campos adicionales manipulados. El evento registra tamaño, página, cantidad de páginas y reserva en bytes.
- Listado con ID, nombre, tamaño, páginas, estado y fecha UTC. Paginación Bootstrap de 15 filas, reiniciada al cambiar escenario, y actualización visible cada cinco segundos.
- ProcessStatus incorpora etiquetas y colores para READY, RUNNING, WAITING y TERMINATED. La UI explica estos estados; las transiciones corresponden a las operaciones posteriores del simulador.
- El dashboard refleja el nuevo proceso activo, manteniendo RAM utilizada y marcos ocupados en cero mientras no se asignen páginas. No genera PAGE_FAULT ni PAGE_LOADED durante la creación.
- Tabla responsive con desplazamiento interno y nombres sin transformación a mayúsculas. Se conservan layout, identidad académica, integrantes, autenticación y permisos existentes.

## Correcciones detectadas en navegador

La comprobación de un envío inmediato encontró un proceso guardado con el tamaño anterior. La lectura del bundle local de Livewire confirmó que `.live.debounce.300ms` retrasaba la actualización local de Alpine. Se sustituyó por `.live`, que actualiza el valor inmediatamente y conserva el control predeterminado de peticiones. Se aplicó la misma corrección a RAM y tamaño de página en el formulario de memoria, donde existía el mismo patrón.

La comprobación final cambió el tamaño y envió el formulario en la misma evaluación de navegador, sin permitir que transcurriera un temporizador. Se guardaron los 3 KB actuales. La regresión de memoria verificó igualmente cambios inmediatos de RAM y página, conservando las capturas de la Fase 9.

La inspección de capturas detectó `Creando…` visible después de finalizar: `d-block` de Bootstrap anulaba el ocultamiento de Livewire. Se cambió a `wire:loading.block` y se verificó que el indicador desaparezca. La alerta de éxito utiliza `text-break` para nombres largos.

## Verificación

- Suite completa en **memorylab_testing**: **182 pruebas aprobadas, 800 assertions**, más un caso condicional omitido porque el registro está habilitado. ProcessManagerTest agrega 47 casos al expandir proveedores de datos.
- Pruebas de conversión, redondeo, numeración, estado inicial, evento y campos manipulados; permisos y revocación; límites y entradas inválidas; configuración, modo, estado y marcos inconsistentes; capacidad cero/llena/redondeada y procesos mayores que RAM; reserva por escenario y páginas TERMINATED; rollback de proceso y páginas al fallar el evento.
- Livewire: selección obligatoria, IDs inválidos, errores por campo, consulta de Observador, actualización de contador, dos escenarios y paginación/reset sin mezclar procesos.
- Chrome con Apache en `/sistemg3/public`: Administrador creó un proceso de 3 KB con páginas de 2 KB, dos páginas y reserva de 4 KB. Un proceso de 5 KB no cabía en los 4 KB restantes y se rechazó sin filas parciales. Operador creó otro de 4 KB y ocupó exactamente la capacidad restante.
- Persistencia comprobada: READY, bytes correctos, páginas 0 y 1 sin marco, un evento por proceso y RAM libre. El envío inmediato se comprobó después de corregir la sincronización.
- Escritorio, tableta, 390 y 320 px sin desbordamiento de la página, con scroll dentro de la tabla, iconos locales y títulos correctos. Capturas inspeccionadas.
- Otro escenario con 17 procesos verificó paginación 15/2 y reinicio al cambiar selección. Observador consultó sin formulario; polling devolvió HTTP 200 en la ruta de actualización de la subcarpeta.
- Dashboard mostró dos procesos activos con RAM y marcos ocupados en cero. No hubo errores JavaScript ni recursos necesarios fallidos.
- Verificación adicional de memoria: geometría inválida rechazada; guardado idempotente; RAM y página enviadas inmediatamente guardadas con valores actuales; dashboard, consulta y selección para los roles anteriores funcionan.
- Pint, sintaxis PHP, compilación Blade y ruta correctos. `npm run build` compiló los estilos de tabla con Vite. No se instalaron paquetes ni se modificaron migraciones.
- Los datos temporales se eliminaron por cuenta y nombres exactos de escenario, respetando FK. Las cuentas y el escenario existente se conservaron. Los perfiles de Chrome se eliminaron tras comprobar sus rutas temporales.

## Capturas

- [Procesos en escritorio](fase-10-procesos-escritorio.png).
- [Procesos en móvil](fase-10-procesos-movil.png).
- [Dashboard con procesos activos](fase-10-dashboard.png).

## Uso local

Abre `http://localhost/sistemg3/public/procesos` con la cuenta Administrador creada a petición del usuario. Selecciona el escenario configurado, escribe un nombre y tamaño y pulsa Crear proceso. El ejemplo recomendado con página de 1 KB permite que un proceso de 4 KB tenga cuatro páginas.

Las páginas se preparan en almacenamiento secundario simulado; la carga en RAM corresponde a las fases de paginación. Crear procesos no ejecuta programas reales. La reserva de capacidad es matemática y no consume los KB indicados de RAM o disco físicos.

No hay controles para cambiar estados arbitrariamente, terminar o borrar procesos en esta fase. Tampoco se cargan procesos de demostración en la base principal.

## Cierre

Fase 10 completada. Fase 11, paginación, pendiente de autorización del usuario.

Commit sugerido: `feat: create processes with simulated pages`.
