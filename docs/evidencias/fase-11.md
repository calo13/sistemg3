# Fase 11 — Simulador de paginación

## Autorización y alcance

El usuario indicó «procede» después de completar la Fase 10. Durante esta fase autorizó continuar todas las fases sin esperar nuevas aprobaciones. Esta instrucción posterior sustituye la restricción inicial de detenerse al finalizar cada fase; se conserva el orden del plan y la documentación de sus entregas.

La Fase 11 integra la pantalla de paginación con cinco secciones simultáneas y selección dinámica del proceso. La tabla detallada, mapa visual de marcos, petición de CPU y Page Hit/Page Fault corresponden a las Fases 12 a 15. El módulo de esta entrega consulta las páginas existentes de la Fase 10 y las asignaciones o solicitudes ya persistidas, sin ejecutar operaciones del simulador.

Se revisaron la solicitud inicial, selección de escenario, modelos, relaciones, configuración, procesos, estadísticas y permisos. Una revisión independiente de lectura comprobó aislamiento, autorización, estados vacíos, transacciones y paginación. La implementación y sus pruebas se trabajaron en archivos separados.

## Implementación

- Ruta GET /paginacion, nombre paging.index, protegida por autenticación, sesión Jetstream y los permisos memory.view, tables.view y simulations.view. Menú Sneat y dashboard enlazan al módulo respetando esos permisos.
- Componente Livewire PagingSimulator con selección explícita de escenario y proceso. La selección de escenario utiliza el contexto de sesión existente; cambiar escenario vacía el proceso elegido. No se elige automáticamente el primer proceso.
- Cinco paneles Bootstrap/Sneat: procesos, tabla básica de ubicación de páginas, resumen de RAM simulada, capacidad secundaria simulada y última solicitud CPU del proceso.
- PagingService::snapshot recarga al actor, comprueba los tres permisos y reúne los datos en una lectura transaccional. Procesos, páginas y solicitudes se limitan al escenario y proceso elegidos.
- Geometría y marcos consistentes, modo PAGING y capacidades dentro de la configuración. Los límites de consulta son 100 procesos por escenario y 1.024 páginas del proceso elegido. Las anomalías muestran errores y no reparan o reemplazan datos.
- La tabla básica muestra página y ubicación: RAM cuando existe marco asignado, DISCO cuando frame_id es null. Presenta 15 filas por página y reinicia la paginación al cambiar selección.
- RAM y capacidad secundaria muestran totales del escenario; páginas presentes y ausentes del proceso se identifican por separado. Cada página ausente ocupa su tamaño completo en secundaria.
- CPU consulta el último evento PAGE_REQUEST del proceso, con desempate por ID después de la fecha. La página solicitada solo se presenta si metadata contiene un entero no negativo. Se conserva descripción y fecha del evento; no se infiere que el acceso fue exitoso.
- Los eventos se almacenan en UTC y se presentan en America/Guatemala mediante memorylab.display_timezone. Sin proceso o solicitud registrada se muestran mensajes de contexto vacío.
- Actualización visible cada cinco segundos mediante Livewire. Administrador, Operador y Observador consultan el mismo módulo de lectura; no hay acciones para solicitar, cargar, liberar o reiniciar páginas en esta entrega.
- Sin migraciones nuevas ni cambios en el modelo de persistencia. Las operaciones de configuración y creación de procesos mantienen sus servicios y permisos existentes.

## Corrección de paginación

La revisión encontró que getPage podía devolver directamente un valor público no numérico. Collection::forPage realiza una operación aritmética con ese valor y podía producir un TypeError. Se normaliza mediante FILTER_VALIDATE_INT, se limita entre 1 y la última página y se actualiza el estado del paginator. También se comprueba que paginators sea un array.

Esto evita respuestas 500 por parámetros manipulados y el mensaje incorrecto «sin páginas» al solicitar una página superior al total. El navegador comprobó gotoPage con una cadena no numérica y recuperó la primera página con sus filas correctas.

## Verificación

- Suite final en memorylab_testing: **223 pruebas aprobadas, 951 assertions**, más un caso condicional omitido porque el registro está habilitado; duración de 22,08 segundos. Incluye las regresiones de parámetros inválidos y normalización de la paginación.
- PagingSimulatorTest verifica snapshots de lectura, separación entre escenarios y procesos, los tres permisos y su revocación, selección explícita, geometría y marcos inconsistentes, límites, secundaria excedida y preservación de las siete tablas del dominio.
- Solicitudes CPU verificadas mediante eventos persistidos de prueba, orden por fecha e ID, aislamiento por proceso y rechazo de metadata inválida para el número de página.
- Chrome con Apache en /sistemg3/public: cinco paneles, selección dinámica, ubicaciones RAM/DISCO existentes, RAM y secundaria globales y solicitud PAGE_REQUEST real dentro de un fixture temporal.
- Cambio entre procesos y escenarios sin mezclar datos; un escenario sin configuración muestra advertencia y mantiene el contexto vacío. Se comprobó que el primer proceso no se seleccione automáticamente.
- Un proceso con 18 páginas verificó tabla 15/3, retorno seguro por parámetros inválidos y reinicio de paginación al cambiar selección.
- Anchos 1366, 768, 390 y 320 px sin desbordamiento de la página. Nombres largos, iconos locales y títulos correctos; capturas de escritorio y móvil conservadas.
- Los tres roles consultaron el módulo. Polling Livewire devolvió HTTP 200 en la subcarpeta de Apache. No se detectaron errores JavaScript ni recursos necesarios fallidos.
- La firma completa de las siete tablas del dominio permaneció idéntica durante selecciones, cambios de proceso, paginación y polling. La consulta no modificó páginas, marcos, procesos, estados o eventos.
- Los fixtures contenían asignaciones RAM y PAGE_REQUEST persistidos exclusivamente para comprobar su lectura. Tras la revisión se eliminaron escenarios, procesos, páginas, eventos, cuenta y sesiones temporales respetando las claves foráneas. El perfil temporal de Chrome también se eliminó.
- La auditoría final de la base principal confirma dos cuentas, tres roles, trece permisos, un Administrador, un escenario, una configuración, 16 marcos, cero procesos, páginas y segmentos, y dos eventos. Las siete tablas del dominio mantienen InnoDB, 12 claves foráneas, seis CHECK y siete restricciones únicas. No se cargaron datos de demostración ni nuevas cuentas reales como parte de esta fase.

## Capturas

- [Paginación en escritorio](fase-11-paginacion-escritorio.png).
- [Paginación en móvil](fase-11-paginacion-movil.png).

## Uso local

Abre http://localhost/sistemg3/public/paginacion con una cuenta que tenga los tres permisos de lectura. Selecciona un escenario configurado y después un proceso creado desde Procesos. Sus páginas iniciales aparecen en DISCO y no ocupan RAM mientras no exista una asignación persistida. Si todavía no hay procesos, el módulo explica cómo agregarlos desde la pantalla correspondiente.

Consultar y cambiar de selección no registra solicitudes de CPU. El panel CPU conserva su estado vacío hasta que exista un PAGE_REQUEST para ese proceso. La pantalla representa capacidades simuladas y no consulta las tablas internas de páginas del sistema operativo ni altera RAM o disco físicos.

## Continuación

Fase 11 completada, con suite final y revisión de navegador aprobadas. La consulta preserva los registros de simulación y el estado de la base principal.

Las siguientes entregas siguen el plan autorizado: Fase 12, tabla de páginas detallada; Fase 13, mapa visual de RAM; Fase 14, solicitud de CPU; y Fase 15, Page Hit/Page Fault. Continúan sin solicitar nuevas aprobaciones, conforme a la instrucción expresa del usuario.

Commit sugerido: `feat: add read-only paging simulator`.
