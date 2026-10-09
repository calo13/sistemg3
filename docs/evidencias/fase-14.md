# Fase 14 — Solicitud de CPU

## Autorización y alcance histórico

Continúa la autorización general para completar las fases en orden. Esta evidencia conserva la versión de la Fase 14 anterior a integrar la resolución de Page Hit/Page Fault de la Fase 15. La interfaz actual puede haber incorporado ya esos cambios en desarrollo; los resultados siguientes corresponden exclusivamente al registro de peticiones verificado en la Fase 14.

La entrega añade selección de página y registro de CPU dentro del simulador. El servicio inspecciona la tabla, registra PAGE_REQUEST y deja el acceso pendiente. No carga páginas, reemplaza marcos ni modifica estados en esta versión.

## Implementación de la entrega

- Componente Livewire PageRequest dentro del panel CPU, con escenario y proceso reactivos provenientes del contexto validado del padre. La clave por escenario/proceso reinicia el componente al cambiar selección.
- Administrador y Operador disponían de selector de página y botón Acceder a página. Observador conservaba la lectura de la última solicitud; una acción manipulada se rechazaba en servidor.
- PagingService::beginRequest bloquea la fila del escenario en una transacción, recarga al actor y exige pages.request y simulations.execute. El contrato snapshot aplica también memory.view, tables.view y simulations.view.
- Requiere un escenario PAGING en READY/RUNNING, configuración y marcos consistentes y proceso del escenario no TERMINATED. Comprueba cantidad y numeración consecutiva de páginas frente al tamaño y configuración.
- El número de página debe pertenecer al proceso y estar dentro del límite. Un ID de proceso ajeno, página inválida, estado incompatible o permisos revocados no genera un evento.
- Cada llamada válida registra un PAGE_REQUEST con actor del servidor y metadata de página, ID de página, tamaño, proceso y marco observado. La respuesta conserva el ID de petición para su resolución posterior.
- Resultado protegido con Locked. Las acciones y el render vuelven a comprobar permisos; el resultado de registro no puede ser reemplazado por datos del navegador.
- El evento memory-updated refresca el snapshot del padre. El polling del padre incluye al hijo por sus propiedades reactivas.
- La interfaz mostraba la presencia y marco observados, o la ubicación en secundaria, junto a «acceso pendiente de resolución». Esa inspección no confirmaba Page Hit ni éxito de acceso.
- Se mantuvo el último PAGE_REQUEST por proceso, con fecha presentada en Guatemala desde UTC y descripción persistida. No se crearon eventos PAGE_HIT, PAGE_FAULT o PAGE_LOADED como parte de esta entrega.

## Verificación histórica

- PageRequestTest: **31 pruebas aprobadas, 110 assertions**. Cubre registro exacto y actor, presencia en RAM sin resolver, roles, permisos y revocación, estados, páginas inválidas o inconsistentes, contextos ajenos, rollback y controles Livewire.
- Regresiones de paginación, tabla de páginas y mapa de RAM: **51 pruebas aprobadas, 263 assertions**.
- Compilación Blade mediante artisan view:cache: correcta.
- Chrome comprobó consulta con los tres roles y conservación de la firma de datos durante las operaciones de lectura.
- Operador seleccionó una página y creó exactamente un PAGE_REQUEST. Se comprobó que no hubo asignación de marco, cambios de estados ni consumo adicional de RAM; no aparecieron eventos de resolución.
- El contexto CPU y la última solicitud se actualizaron sin recargar la página. Los componentes previos de tabla, mapa y secundaria conservaron su comportamiento.
- Los nombres largos del panel CPU requirieron ajuste de salto de línea. La comprobación posterior a 320 px pasó sin desbordamiento de la página.
- Capturas de escritorio y móvil conservadas. No se agregaron paquetes o migraciones en esta fase.

## Capturas de la versión verificada

- [Solicitud CPU en escritorio](fase-14-cpu-escritorio.png).
- [Solicitud CPU en móvil](fase-14-cpu-movil.png).

## Continuación

Fase 14 completada. La Fase 15 está en desarrollo y resuelve los eventos registrados, detecta Page Hit/Page Fault y carga páginas según la memoria disponible. Esta evidencia no certifica todavía el resultado final de esa resolución.

Commit sugerido: `feat: record CPU page requests`.
