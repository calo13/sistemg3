# Fase 24 — Terminal educativa

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización del usuario para completar todas las fases en orden sin nuevas confirmaciones. La terminal permite consultar y ejecutar las acciones existentes del simulador mediante un conjunto explícito de comandos educativos.

## Comportamiento implementado

- /terminal requiere los cuatro permisos de consulta; servicio y componente recargan al actor y comprueban autorización.
- Lista cerrada: help, memory status, process list, page table {nombre o #ID}, request {nombre o #ID} {página} y reset.
- Entrada UTF-8 de hasta 160 caracteres, sin controles o saltos de línea. Los patrones están anclados y rechazan comandos desconocidos.
- No se ejecuta shell, eval, Artisan o SQL derivado del texto del usuario.
- Los procesos pertenecen al escenario elegido. El nombre exacto permite espacios y no distingue mayúsculas; nombres repetidos exigen #ID.
- page table y request requieren paginación; la tabla muestra hasta 40 páginas. La página solicitada se valida también en PagingService.
- request reutiliza la solicitud/resolución transaccional y sus permisos; presenta flujo, Hit/Fault, marco, dirección inicial y víctima FIFO cuando corresponde.
- reset reutiliza SimulationService: Administrador finaliza procesos y libera páginas/segmentos, conservando configuración e historial. Operador no tiene permiso de reinicio; Observador consulta.
- Salida escapada en Blade, limitada a 100 líneas y protegida mediante Locked. Limpiar consola y cambiar escenario limpian la presentación local.
- Las respuestas describen el estado al ejecutar cada comando. Una mutación exitosa emite memory-updated; una consulta conserva la memoria.
- No se agrega migración ni tipo de evento.

## Revisión

La lectura de servicio, componente y vista comprobó lista cerrada, resolución de procesos por escenario, permisos renovados, delegación a los servicios transaccionales y salida escapada. No se detectó un defecto concreto en esos contratos.

## Verificación

- Terminal junto con **13 regresiones de modo paso a paso** y **32 de acceso de segmentación**: **96 pruebas aprobadas, 703 assertions**, en 17,05 segundos.
- Chrome comprobó las consultas para los tres roles, conservando la firma de las siete tablas del dominio.
- request Chrome 0 produjo Hit; request Chrome 3 produjo Fault, con **cinco eventos nuevos exactos** entre ambas acciones.
- reset administrativo finalizó **cuatro procesos** y dejó **cero páginas** en el escenario seleccionado.
- Se comprobaron 1366, 768, 390 y 320 píxeles sin desbordamiento ni errores JavaScript o de recursos.
- Los escenarios y usuarios temporales se limpiaron al terminar.

## Capturas

- [Terminal en escritorio](fase-24-terminal-escritorio.png).
- [Terminal en móvil](fase-24-terminal-movil.png).

## Uso y continuación

Selecciona escenario y escribe help. Para un proceso con espacios, usa request VS Code 0; para uno repetido, usa su #ID. Las acciones de escritura conservan los permisos de los botones del simulador. La etiqueta y respuesta de reset explican la finalización de procesos y la conservación de historia.

Fase 24 completada con pruebas y navegador verificados. Continúa la Fase 25, modo presentación, según la autorización general.

Commit sugerido: `feat: add scoped educational memory terminal`.
