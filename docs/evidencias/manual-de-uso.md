# Entrega adicional - Manual de uso

Fecha de cierre: **8 de octubre de 2026**.

## Alcance y resultado

El usuario solicitó una guía con pasos, secuencia y conceptos académicos para usar el sistema y demostrar los resultados de la tarea. Se completó el [manual de uso PDF](../../output/pdf/memorylab-manual-de-uso.pdf) de **ocho páginas**, con fuente editable en [manual-de-uso.md](../manual-de-uso.md). La página `/entregables` lo presenta como primer recurso: «Cómo usar y demostrar MemoryLab».

El manual distingue la guía operativa del informe técnico, que documenta arquitectura, fundamentos y validación. Incluye:

- Entrada al sistema y acciones disponibles para Administrador, Operador y Observador.
- Preparación de un par nuevo de demostración y comprobación de su estado inicial.
- Chrome P0 como Hit y Chrome P3 como Fault en modo Paso a paso, con métricas antes/después.
- Traducción de 3500 bytes a la dirección física 5548 y consulta de una página ausente.
- Accesos válidos al segmento Código y rechazo del offset igual a su límite.
- Comparación de 7168 y 7500 bytes, y prueba FIFO en un escenario separado de 2 KB con seis solicitudes y cuatro reemplazos.
- Historial, terminal, presentación, conservación de evidencia y finalización o reinicio al terminar.
- Relación entre conceptos académicos, pantallas y evidencia esperada, más un **guion de exposición de 14 minutos**.

Las **cuatro capturas reales** proceden de las evidencias de Demostración, Presentación, Segmentation Fault y Terminal. El documento distingue esas capturas anteriores del nuevo par que preparará quien siga el recorrido; sus identificadores pueden variar. Las unidades, entradas, secuencia y cálculos esperados se contrastaron con vistas y servicios del proyecto.

## Verificación

La comprobación de construcción confirmó **ocho páginas**. Poppler las renderizó a 140 dpi y se inspeccionaron individualmente: sin texto recortado, tablas fuera de página ni elementos superpuestos. Las cuatro capturas tienen encuadres legibles. Se revisaron nuevamente la aclaración de unidades de la página 2 y los roles en español de la página 8.

El [registro persistente de revisión](manual-de-uso-verificacion.json) tiene resultado **PASS** y enumera las ocho páginas inspeccionadas. La construcción y revisión del manual no realizaron operaciones sobre la aplicación ni la base de datos.

El PDF final tiene **809.961 bytes**. Su SHA-256 es:

`934ef193b405a2b71d379cff1614ab89f1b6d470ad16ea922cf76808ba3c5397`

El generador reproducible se conserva en [build-manual.py](../../scripts/artifacts/build-manual.py). El documento no incluye contraseñas y utiliza cuentas existentes según su rol. Distingue el Apache local comprobado de la guía Linux preparada y la VM opcional no provisionada.

La [verificación final del portal](entregables.md) confirmó la descarga del manual y los otros tres archivos para los tres roles. Se mantienen intactos los resultados históricos de Fase 27; el portal aporta 27 pruebas y 243 aserciones adicionales. Las Fases 0–31 y esta entrega adicional están completas.
