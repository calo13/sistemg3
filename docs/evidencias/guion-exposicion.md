# Guion de exposición de una hora

Fecha de elaboración: 2026-10-21.

El usuario solicitó una guía literal para que los cinco integrantes expongan durante una hora, con presentación del grupo, temas de la tarea y demostraciones detalladas de la aplicación.

Se entregan la [fuente editable](../guion-exposicion-60-minutos.md) y el [PDF de 28 páginas](../../output/pdf/memorylab-guion-exposicion-60-minutos.pdf). El constructor reproducible es `scripts/artifacts/build-exposition-guide.py`, utilizando ReportLab y fuentes Arial incrustadas. No se agregaron dependencias a Laravel.

La entrega se complementa con el [PowerPoint de 24 diapositivas](../../output/presentations/memorylab-presentacion-exposicion-60-minutos.pptx), cuyas notas contienen el texto y las acciones del guion. La [guía de presentación](../presentacion-exposicion-60-minutos.md) indica el reparto y el uso de las notas.

## Contenido y revisión

- Cinco turnos de 12 minutos, divididos en veinte tramos de tres minutos. El cierre y las preguntas están dentro de la hora. La duración debe ajustarse mediante ensayo cronometrado.
- Unas 5000 palabras de texto hablado, incluida la alternativa de Carlos si no hay preguntas, con instrucciones separadas en DECIR, HACER, COMPROBAR y PAUSA.
- Correspondencia actualizada con el PowerPoint de 24 diapositivas para esta exposición, hojas de resultados, respuestas al docente y alternativas si se retrasan o una pantalla muestra otro estado.
- Escenario manual de paginación: RAM 4 KB, página 1 KB, secundaria 16 KB y Chrome 6 KB. La secuencia P0/P1/P3/P2, Hit P0, P4 y retorno de P0 produce siete solicitudes, seis Faults, un Hit y dos reemplazos FIFO.
- Traducciones verificadas: P3 en marco 2 produce 3500 → 2476; P0 ausente no tiene dirección física y, al regresar al marco 1, 100 → 1124. El acumulado de Page Faults se consulta en Inicio.
- Segmentación: Editor de 4 KB con cuatro segmentos que ocupan 3600 bytes en RAM de 16384, dejando 12784. Código con base 1000 y tamaño 1200 permite offsets 100 y 1199, y rechaza 1200.
- Comparación independiente: solicitudes 8192, 7500 y 6144. Para 7500, paginación reserva 8192 y presenta 692 bytes de fragmentación interna; segmentación reserva 7500 y deja 1716 libres.
- Las cifras del PowerPoint coinciden con el escenario manual del guion. Los datos de pruebas de la diapositiva 22 se presentan como evidencia histórica del 8 de octubre de 2026.

Los nombres de campos, botones, unidades y resultados se contrastaron con las vistas y servicios instalados. Esta revisión no ejecutó accesos, seeders, reinicios ni cambios en los escenarios existentes.

## Verificación del documento

El constructor verificó los cinco integrantes, el contenido esencial y los cálculos. Tras actualizar las referencias al PowerPoint, Poppler renderizó las 28 páginas a 100 dpi y todas fueron inspeccionadas visualmente: texto legible, tablas completas, encabezados y pies consistentes, sin recortes, solapamientos ni glifos defectuosos. Los bloques de tres minutos quedan en páginas independientes.

La comprobación corresponde al contenido y al documento. No se modificó código de la aplicación ni se repitió su suite de pruebas. Las cuatro descargas existentes permanecen sin cambios.
