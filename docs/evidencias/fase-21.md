# Fase 21 — Alta demanda simulada

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases en orden sin nuevas confirmaciones. La Fase 21 agrega un lote de procesos ficticios y accesos de página para observar ocupación de RAM, marcos, secundaria y fallos mediante los mismos servicios del simulador.

La ejecución y consulta se verificaron con pruebas del servicio/componente y navegador. El resultado corresponde a datos persistidos del simulador.

## Comportamiento implementado

- Administrador y Operador ejecutan Simular alta demanda en un escenario de paginación seleccionado. Observador consulta la memoria.
- El escenario debe estar READY/RUNNING. El servicio toma su bloqueo, recarga al actor y aplica validación de entrada en servidor.
- Límites por ejecución: ocho procesos, 64 KB por proceso y 64 solicitudes de página en total. La cantidad de páginas utiliza el tamaño configurado y redondea hacia arriba.
- Los nombres ficticios y UUID de ejecución se producen en servidor. La operación guarda únicamente procesos y asignaciones del simulador.
- Primero se crean todos los procesos mediante ProcessManagerService y se reservan sus páginas en secundaria. El lote completo debe caber antes de realizar accesos a RAM.
- Después se solicitan las páginas en orden mediante PagingService::requestPage. Las cargas y reemplazos reutilizan FIFO, permisos, estados y eventos existentes.
- La ejecución completa es transaccional: un error de reserva o acceso revierte todo el lote, incluyendo cambios en páginas anteriores que una carga hubiera expulsado.
- El resultado conserva métricas antes/después, procesos creados, número de solicitudes, reemplazos y una traza de ocupación por acceso. Page Faults se obtiene de eventos persistidos.
- MemoryStress protege el resultado mediante Locked y lo limpia al cambiar escenario. La traza se presenta al completar la ejecución; no muestra escrituras parciales.
- La consulta actual presenta seis indicadores, incluidos Page Faults acumulados y secundaria utilizada. Observador los recibe sin formulario de ejecución, con polling cada cinco segundos mientras la pantalla está visible.
- No se agregan tablas ni tipos de evento y no se inician procesos reales o asignan deliberadamente grandes bloques de RAM física.

## Revisión de lectura

La revisión comprobó reserva completa antes de acceso, límite de trabajo, bloqueo compartido y transacción global. El contador de fallos y polling de la consulta se incorporaron antes del cierre. La interfaz exige results.view para presentar resultados; el servicio conserva sus permisos operativos de creación, solicitud, ejecución y tres consultas de memoria.

## Verificación

- MemoryStressTest: **35 pruebas aprobadas, 153 assertions**, en 11,63 segundos. Incluye carga/FIFO, capacidad, validación, rollback, roles y resultado protegido.
- Chrome verificó los tres roles. Observador consultó la memoria y no recibió formulario de ejecución.
- El fixture de tres procesos de **2 KB**, con RAM de **2 KB** y dos marcos, terminó con **2048 bytes** utilizados, **seis Page Faults** y **cuatro reemplazos FIFO**.
- Se conservaron seis muestras de evolución correspondientes a accesos reales. El escenario del fixture tuvo **23 eventos en total**: dos de preparación, tres PROCESS_CREATED y seis grupos PAGE_REQUEST/PAGE_FAULT/PAGE_LOADED.
- Se comprobaron anchos de 1366, 768, 390 y 320 píxeles sin desbordamiento, errores JavaScript o fallos de recursos.
- Los usuarios y fixtures temporales se limpiaron después de la comprobación.

## Capturas

- [Alta demanda en escritorio](fase-21-demanda-escritorio.png).
- [Alta demanda en móvil](fase-21-demanda-movil.png).

## Uso y continuación

Selecciona un escenario de paginación e indica cantidad y tamaño de procesos. La operación agrega datos al escenario elegido. Un lote con más páginas que marcos permite observar cómo la ocupación alcanza la capacidad de RAM y continúa mediante reemplazos FIFO, siempre que primero quepa su reserva en secundaria.

Fase 21 completada con pruebas y navegador verificados. Continúa la Fase 22, demostración y reinicio explícito, según la autorización general.

Commit sugerido: `feat: simulate bounded paging workloads`.
