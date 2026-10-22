# Resumen de las prácticas para explicar al grupo

El usuario pidió reunir las prácticas de aprendizaje en una guía con ejemplos y comparaciones para sus compañeros. Se prepararon la [fuente editable](../guia-practicas-companeros.md) y el [PDF de diez páginas](../../output/pdf/memorylab-guia-practicas-companeros.pdf), con la misma tipografía Arial y esquema azul marino de las guías anteriores.

El recorrido conserva los ejemplos aprendidos: RAM 4 KB, páginas de 1 KB, secundaria de 6 y 8 KB; Vice City de 7 KB y Netflix de 2 KB; FIFO y tabla de páginas; traducciones 500 a 1524 y 2548 según proceso; dirección 4500 de P4 ausente y después en marco 0; HBO de 1 KB con Película, Serie y Caricatura; rechazo por superposición en base 301 con tamaño 340; corrección a 49 bytes; límites de Serie y comparación independiente de 5000, 7168, 7500 y 15000 bytes.

Incluye analogías de zapatera, depósito, cajas, casas, libros, terreno cercado y cine. Cada práctica tiene valores concretos, resultado esperado y una frase para explicar lo que demuestra. La última página permite repasar la sesión sin releer todo el documento. Se distingue este recorrido del guion formal y su PowerPoint, que mantienen los ejemplos de Chrome y Editor.

## Comprobaciones

- Los campos y reglas se contrastaron con las vistas y los servicios `MemoryConfigurationService`, `ProcessManagerService`, `PagingService`, `SegmentationService` y `MemoryComparisonService`.
- El constructor `scripts/artifacts/build-practice-recap.py` comprobó el contenido, las sumas, rangos y traducciones. Una simulación independiente del orden de nueve solicitudes produjo ocho Faults, un Hit y cuatro reemplazos, con Vice City P5/P0, Netflix P0 y Vice City P6 en marcos 0/1/2/3. P4 reemplaza después a P5 y permite traducir 4500 a 404.
- Se invocó el servicio real de comparación, sin iniciar Laravel ni conectarse a la base, para confirmar las cuatro combinaciones de aceptación y los 692 bytes de fragmentación interna con 7500.
- Poppler renderizó las diez páginas a 110 dpi. Se inspeccionaron todas y se volvió a revisar la última tras precisar la relación con el guion formal: tablas completas, texto legible, encabezados y pies correctos, sin recortes, desbordes o glifos defectuosos.

No se crearon ni eliminaron escenarios, no se ejecutaron seeders y no se modificó código de la aplicación. La revisión es documental y de cálculos; no se ejecutó la suite de la aplicación ni se atribuye esta revisión a una nueva demostración en vivo.
