# Fase 25 — Modo presentación

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización general del usuario. El modo presentación reúne la paginación en una pantalla académica para exposición, reutilizando componentes y permisos existentes y resultados reales persistidos.

## Comportamiento implementado

- /presentation requiere memory.view, tables.view, simulations.view y results.view.
- Layout con MemoryLab, Universidad Mariano Gálvez, Ingeniería en Sistemas, grupo y curso, control de pantalla completa y créditos de Sneat.
- PagingSimulator utiliza presentation protegido por Locked. Selección explícita de escenario de paginación y proceso, sin preparación o acceso al abrir.
- Cinco paneles: selección/procesos, CPU con flujo, tabla de páginas, mapa global de RAM y secundaria.
- Tabla, RAM, secundaria y Page Faults utilizan el snapshot del padre; se conservan sus límites de paginación.
- Se reutilizan PageRequest, page-table y memory-grid. La pantalla de exposición no presenta formularios de administración, configuración o creación de procesos.
- Administrador y Operador conservan sus permisos para acceso automático/paso a paso. Observador consulta memoria y el resultado guardado desde otra cuenta.
- last_result valida el vínculo request_event_id entre PAGE_REQUEST y PAGE_HIT/PAGE_LOADED, contexto, página, marco, dirección y víctima, y devuelve una estructura canónica con fecha.
- Un pendiente conserva su flujo local; el resultado guardado se presenta cuando no existe un recorrido pendiente. Su fecha identifica el acceso persistido, y consultar no ejecuta la resolución.
- MEMORY_RESET posterior limpia la proyección y un proceso terminado limpia el recorrido local; el historial se conserva.
- Polling cada cinco segundos mientras está visible actualiza el resultado compartido y Page Faults.
- SegmentationService también expone last_access validado para que el módulo habitual consulte el último acceso/fallo del proceso sin ejecutarlo de nuevo.
- No hay migración ni algoritmo nuevo.

## Verificación

- **34 pruebas de presentación**, **13 de paso a paso** y **32 de acceso de segmentación**: **79 pruebas aprobadas y 515 assertions**, en **15,50 segundos**.
- La compilación Vite se verificó correctamente.
- Chrome comprobó los cinco paneles y los tres roles, sin administración en la pantalla.
- Observador recibió por polling el Fault y el Hit ejecutados desde otra cuenta, con resultado y flujo persistidos.
- Reset limpió el resultado y dejó **16 marcos libres** en el fixture.
- Las consultas conservaron el hash de las siete tablas del dominio.
- Se comprobó presentación de **320 a 1920 píxeles** sin desbordamiento; las fixtures temporales se limpiaron al terminar.

## Capturas

- [Presentación en escritorio](fase-25-presentacion-escritorio.png).
- [Presentación en móvil](fase-25-presentacion-movil.png).

## Continuación

Fase 25 completada con pruebas y navegador verificados. El seeder explícito de Fase 26 permite preparar la pareja base; continúa la verificación integral en Fase 27.

Commit sugerido: `feat: add presentation mode and shared persisted memory results`.
