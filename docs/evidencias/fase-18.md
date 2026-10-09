# Fase 18 — Segmentación

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases en orden sin nuevas confirmaciones. La Fase 18 incorpora configuración de escenarios de segmentación, creación de procesos, asignación de segmentos, tabla por proceso y mapa global de RAM.

La revisión independiente comprobó permisos, límites, aislamiento, transacciones y estados de selección. La adaptación del dashboard usa las mismas reglas de intervalos activos y conserva los cálculos de paginación.

## Comportamiento implementado

- Administrador crea escenarios SEGMENTATION con RAM en KB enteros. Configuración y eventos se guardan juntos; no se crean páginas o marcos.
- Administrador y Operador crean procesos READY con tamaño en KB. Crear un proceso no reserva RAM.
- Un segmento conserva número, nombre, base, size_bytes y estado. La numeración se asigna automáticamente por proceso desde cero.
- Base y tamaño se capturan en bytes enteros. El intervalo [base, base + tamaño) debe quedar dentro de RAM. Se rechazan superposiciones con cualquier segmento ACTIVE del escenario y se permiten intervalos adyacentes.
- La suma de segmentos activos no excede el tamaño del proceso. Un proceso finalizado no admite nuevas asignaciones.
- Límites: 100 procesos por escenario, 16 segmentos por proceso y 1.024 segmentos por escenario. Los conteos de segmentos incluyen liberados.
- Configuración, creación y asignación verifican permisos actuales. Las operaciones sobre un escenario existente utilizan su bloqueo de fila y transacciones.
- Los tres roles consultan un snapshot consistente. La tabla corresponde al proceso elegido y muestra también registros liberados; el mapa reúne los segmentos activos de todos los procesos y sus huecos libres.
- RAM utilizada es la suma de tamaños activos. Dashboard valida rangos y superposiciones con un cursor ordenado por base/ID y limita la colección antes de obtenerla.
- Marcos totales, ocupados, libres y Page Faults devuelven null para segmentación. La vista muestra cuatro «No aplica» con data-not-applicable y explica que pertenecen a paginación.
- No se modifica el esquema. Los accesos con segmento y offset continúan en la Fase 19.

## Verificación

- SegmentationSimulatorTest: **30 pruebas aprobadas, 154 assertions**.
- Regresión AuthenticationTest: **siete pruebas aprobadas, 28 assertions**.
- Total de esta verificación: **37 pruebas aprobadas, 182 assertions**.
- Pint y caché de vistas Blade: **aprobados**. Se verificaron las vistas compiladas y la respuesta del dashboard y autenticación.
- Chrome comprobó consulta de Administrador, Operador y Observador, con firma estable de las siete tablas durante lectura.
- El fixture mostró un mapa global con **2.600 bytes** utilizados: la tabla del proceso A mostró bases **1000** y **4000**; al seleccionar B mostró base **7000**, conservando los ocupantes globales en el mapa.
- Dashboard reflejó **2600 / 1024 = 2,5390625 KB** utilizados y cuatro indicadores «No aplica».
- Nombres de 100 caracteres se presentaron sin romper el diseño. Se comprobaron anchos de 1366, 768, 390 y 320 píxeles.
- No se detectaron errores JavaScript ni de recursos en la verificación final. Los fixtures temporales se limpiaron.

## Capturas

- [Segmentación en escritorio](fase-18-segmentacion-escritorio.png).
- [Segmentación en móvil](fase-18-segmentacion-movil.png).

## Uso y continuación

Abre Segmentación y selecciona un escenario o crea uno con la cuenta Administrador. Crea un proceso y agrega segmentos con base y tamaño en bytes. La tabla permite identificar los intervalos del proceso, mientras el mapa explica dónde se encuentran sus segmentos y los de otros procesos dentro de la RAM.

Fase 18 completada con pruebas y navegador verificados. Continúa la Fase 19, accesos y fallos de segmentación, según la autorización general.

Commit sugerido: `feat: configure and visualize segmented memory`.
