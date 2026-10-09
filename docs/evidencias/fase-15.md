# Fase 15 — Page Hit y Page Fault

Fecha de cierre: **8 de octubre de 2026**. Las evidencias de fases anteriores conservan sus fechas históricas del 7 de octubre.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases en orden sin nuevas confirmaciones. La Fase 15 resuelve peticiones CPU, detecta páginas presentes o ausentes, carga desde almacenamiento secundario simulado y aplica FIFO cuando RAM está llena.

Se revisaron registro de solicitudes, aislamiento del snapshot, permisos, transacciones, capacidades, marcos y eventos. La revisión independiente de lectura comprobó autorización, referencias canónicas, idempotencia, FIFO y reserva secundaria. El modo paso a paso y las animaciones educativas continúan en la Fase 16.

## Esquema

- Migración 2026_10_07_220000_add_loaded_at_to_pages_table agrega loaded_at DATETIME(6) nullable a pages y el índice pages_fifo_index sobre scenario_id, loaded_at e id.
- La fecha representa la entrada de una página en RAM. Cast inmutable y formato de escritura con microsegundos conservan UTC y precisión.
- Las asignaciones anteriores pueden conservar null; FIFO las considera primero como antigüedad desconocida, con ID como desempate. No se inventa una fecha histórica.
- La migración aditiva se aplicó en la base principal sin eliminar o recrear tablas ni borrar registros. Las restricciones de integridad anteriores permanecen.

## Resolución

- beginRequest conserva el contrato de la Fase 14. resolveRequest completa un PAGE_REQUEST persistido. requestPage integra ambos en una transacción para el acceso automático de CPU.
- Bloqueo de escenario compartido con configuración y procesos. Permisos recargados: pages.request, simulations.execute, memory.view, tables.view y simulations.view.
- El evento debe ser una solicitud del actor original y contener referencias válidas de proceso, página y tamaño. La resolución comprueba la página y configuración actuales frente a esos datos.
- Escenario PAGING en READY/RUNNING, proceso no finalizado, geometría, marcos y páginas consistentes. Errores de contexto o autorización no producen asignaciones parciales.
- PAGE HIT: la página ya tiene marco. Se registra PAGE_HIT, se devuelve el marco y se conserva loaded_at; no se vuelve a cargar ni se modifica su prioridad FIFO.
- PAGE FAULT: la página no está presente. Se registra PAGE_FAULT, se toma el menor marco libre y se actualiza la página. PAGE_LOADED conserva la carga completada y su resultado.
- RAM llena: víctima residente global del escenario ordenada por loaded_at e id. Vuelve a secundaria; el objetivo ocupa su marco. El resultado registra proceso, página y marco de la víctima para explicar el reemplazo.
- La página objetivo libera una plaza secundaria. Si existe víctima, esta ocupa esa plaza; se valida la capacidad final y se libera el marco antes de reasignarlo para respetar su unicidad.
- El escenario y proceso accedido pasan a RUNNING. Los cambios de páginas, estados y eventos pertenecen a la transacción; un error revierte la operación automática completa.
- Idempotencia mediante el resultado terminal PAGE_HIT/PAGE_LOADED vinculado a request_event_id. Resolver otra vez ese mismo evento devuelve el resultado almacenado sin duplicar fallos, cargas o estados. Un acceso nuevo registra un nuevo PAGE_REQUEST.
- CPU muestra resultado, marco, dirección física inicial y víctima FIFO. memory-updated refresca las lecturas; el dashboard responde a ese evento y cuenta PAGE_FAULT persistidos.

## Verificación

- PagingAccessTest: **21 pruebas aprobadas, 88 assertions**. Incluye Hit, Fault, marco libre, FIFO, capacidad de intercambio, metadatos, idempotencia, autorización y rollback.
- Regresión PageRequestTest: **31 pruebas aprobadas, 110 assertions**.
- Suite final en memorylab_testing: **285 pruebas aprobadas, 1.265 assertions**, más un caso condicional omitido; duración de 38,71 segundos.
- Chrome verificó consulta de los tres roles y preservación de la firma de datos durante lectura. Operador completó un Fault con exactamente PAGE_REQUEST, PAGE_FAULT y PAGE_LOADED; un acceso posterior presente generó PAGE_REQUEST y PAGE_HIT.
- El fixture de navegador reflejó RAM utilizada de 2 KB y secundaria utilizada de 22 KB después de la carga. Tabla, mapa, páginas ausentes y métricas respondieron al cambio real de asignación.
- No se detectaron errores JavaScript en la revisión. Se conservaron capturas del resultado CPU en escritorio y móvil.
- Auditoría posterior: dos cuentas, un escenario, una configuración, 16 marcos, cero procesos, páginas y segmentos, y dos eventos en la base principal. Los fixtures temporales no quedaron como datos reales.
- Se conservaron 12 claves foráneas, seis CHECK y siete restricciones únicas del dominio. La ampliación de fecha e índice FIFO no eliminó datos anteriores.

## Capturas

- [Resultado CPU en escritorio](fase-15-cpu-escritorio.png).
- [Resultado CPU en móvil](fase-15-cpu-movil.png).

## Uso y continuación

En Paginación selecciona escenario, proceso y página. Administrador u Operador pulsa Acceder a página. Una página ausente se carga con Fault; repetir un acceso a una página que sigue en RAM produce Hit. Al llenarse RAM, FIFO permite observar qué página vuelve a secundaria.

El modelo representa ubicación exclusiva: una página ocupa RAM o secundaria. Estas operaciones modifican únicamente registros del simulador; no ejecutan procesos reales ni manipulan las tablas de páginas del sistema operativo.

Fase 15 completada con suite final y navegador verificados. Continúa la Fase 16, modo automático/paso a paso y animaciones educativas, según la autorización general.

Commit sugerido: `feat: resolve page accesses with FIFO replacement`.
