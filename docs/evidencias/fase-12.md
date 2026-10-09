# Fase 12 — Tabla de páginas

## Autorización y alcance

Continúa la autorización general del usuario para completar todas las fases en orden sin solicitar nuevas aprobaciones. La Fase 12 amplía la tabla del simulador de paginación con Página, Marco, Presente y Estado, utilizando los registros existentes. La asignación de marcos, solicitudes de CPU y detección de Hit/Fault corresponden a las fases posteriores.

Se revisaron el snapshot transaccional, relaciones de Page y MemoryFrame, componente Livewire, tabla previa, permisos y paginación. La revisión independiente de lectura no encontró problemas de aislamiento o presentación.

## Implementación

- Componente Blade resources/views/components/paging/page-table.blade.php. Recibe el proceso elegido y el paginator de páginas desde el snapshot del componente padre.
- El parcial page-overview reutiliza el componente sin volver a consultar modelos ni mantener un estado separado de páginas.
- Columnas Página, Marco, Presente y Estado. Página utiliza page_number; Marco muestra frame.frame_number, no la clave primaria de la fila.
- Página con marco: número correspondiente, «Sí» y badge RAM. Página sin marco: guion «—», «No» y badge DISCO, explicado como almacenamiento secundario simulado.
- El número de marco cero se conserva correctamente mediante comprobación null-safe y coalescencia; no se interpreta como dato vacío.
- La presencia se deriva del accesor Page.present, basado en frame_id. No se agrega una bandera duplicada ni una columna de estado en la base.
- Tabla responsive, encabezados con scope y caption que identifica el proceso. Colores acompañados de texto para que el estado no dependa solo de la presentación visual.
- Mantiene las 15 filas por página y la normalización de parámetros del componente padre. Cambiar de proceso o escenario vacía o actualiza la tabla correspondiente.
- Sin proceso elegido se solicita seleccionarlo. Si un proceso no tiene registros de páginas, se muestra una fila vacía con colspan cuatro.
- Mantiene autenticación y los tres permisos de lectura del módulo. Los tres roles consultan los datos sin cambiar páginas, marcos, estados o eventos.

## Verificación

- PageTableTest: **cuatro pruebas aprobadas, 51 assertions**. Comprueba las cuatro columnas, número de marco real, presencia/estado, aislamiento al cambiar selección, tabla de 15 filas con remanente ordenado y contexto sin proceso elegido.
- Regresión PagingSimulatorTest: **41 pruebas aprobadas, 151 assertions**.
- Compilación Blade mediante artisan view:cache: correcta.
- Chrome verificó las columnas y combinaciones RAM/Sí/marco y DISCO/No/guion con asignaciones persistidas dentro de fixtures temporales. La consulta fue comprobada con Administrador, Operador y Observador.
- Anchos 1366, 768, 390 y 320 px verificados. Se conservan los cinco paneles, selección dinámica y paginación sin desbordamiento de la página.
- La firma de las siete tablas del dominio se mantuvo durante la consulta. Las pruebas de tabla también comparan su contenido completo antes y después de seleccionar, paginar y refrescar.
- Sin migraciones o paquetes nuevos. La tabla consume relaciones cargadas por el snapshot existente; no ejecuta asignaciones ni genera eventos.

## Capturas

- [Tabla de páginas en escritorio](fase-12-paginacion-escritorio.png).
- [Tabla de páginas en móvil](fase-12-paginacion-movil.png).

## Uso y continuación

Abre http://localhost/sistemg3/public/paginacion, selecciona un escenario configurado y un proceso. Las páginas creadas en la Fase 10 aparecen inicialmente en DISCO. Si existe una asignación persistida, la tabla muestra su marco y presencia en RAM.

Fase 12 completada. Continúa la Fase 13, mapa visual de RAM, conforme a la autorización general del usuario.

Commit sugerido: `feat: display detailed page table`.
