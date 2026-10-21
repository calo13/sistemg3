# Guía sencilla de aprendizaje desde cero

Fecha: 21 de octubre de 2026.

El usuario pidió entender qué hace la configuración de memoria, qué valores ingresar, qué campos se calculan y qué configurar después en un orden lógico. Se entregan la [guía editable](../guia-desde-cero.md) y el [PDF de seis páginas](../../output/pdf/memorylab-guia-desde-cero.pdf).

El recorrido empieza con la aplicación vacía: configuración 4 KB de RAM, página de 1 KB y secundaria de 16 KB; creación manual de Chrome de 6 KB; cargas P0, P1, P3 y P2; Hit de P0; FIFO con P4; traducción 3500 a 2476; P0 ausente y recarga al marco 1 para obtener 1124. Continúa con segmentación en un escenario separado y con la comparación independiente de 8192 y 7500 bytes.

Se explican páginas y marcos con piezas y casillas, la relación entre tamaño de proceso y cantidad de páginas, la ubicación inicial en secundaria, los valores inválidos de configuración, la condición para cambiar capacidades y las unidades KB/bytes. Los ejemplos mantienen los valores y el orden del guion de exposición de una hora. Ese guion sigue destinado al reparto y texto hablado de los cinco integrantes; esta entrega se concentra en comprender y practicar.

Los campos y reglas se contrastaron con `resources/views/livewire/`, `app/Services/MemoryConfigurationService.php`, `ProcessManagerService.php`, `PagingService.php`, `SegmentationService.php` y `config/memorylab.php`, además del guion previamente revisado. No se configuraron escenarios ni se ejecutaron seeders para generar el documento.

El constructor `scripts/artifacts/build-beginner-guide.py` utiliza ReportLab, Arial incrustada y comprobaciones del texto y los cálculos. Poppler renderizó las seis páginas a 110 dpi; se revisaron visualmente las seis páginas y las dos páginas actualizadas al precisar el rango de P3 y la validación del offset. Texto y tablas legibles, sin desbordes ni glifos defectuosos. Las comprobaciones no acceden a la aplicación ni a la base de datos.
