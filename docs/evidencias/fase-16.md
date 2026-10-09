# Fase 16 — Animaciones y modo paso a paso

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases en orden sin nuevas confirmaciones. La Fase 16 incorpora presentación animada de los accesos automáticos y un recorrido manual que explica la consulta, Hit/Fault, carga y finalización. Reutiliza el servicio transaccional y FIFO de la Fase 15, sin ampliar el esquema.

La revisión independiente de lectura comprobó ciclo de vida del componente, permisos, estado protegido, resolución única, cambios concurrentes de presencia, polling y separación entre animación y decisiones de memoria. No encontró errores bloqueantes.

## Comportamiento implementado

- CPU permite elegir Automático o Paso a paso. Administrador y Operador actúan con los permisos actuales; Observador conserva su consulta.
- Automático registra y resuelve el acceso en servidor, y después anima el recorrido y el marco afectado.
- Un Hit tiene cuatro pasos; un Fault, ocho. PagingFlowService proporciona textos educativos sin consultas o escrituras.
- Paso a paso registra PAGE_REQUEST al comenzar. Un Hit se resuelve en el paso 4; un Fault se resuelve en el paso 6. Los pasos 1 a 5 del Fault conservan las asignaciones anteriores.
- La carga, reemplazo FIFO, estados y eventos se guardan juntos en el paso de resolución. Los pasos restantes explican el resultado ya persistido.
- pending, step, stateChanged y result están protegidos mediante Locked. Cada avance recarga la autorización; no se aceptan marcos, resultados o saltos de paso enviados por el cliente.
- La presencia se comprueba de nuevo al resolver. Si otro escritor cargó o expulsó la página entre petición y resolución, se adapta el recorrido al resultado real y se muestra el cambio de estado.
- Cambiar página o modo, o cerrar el recorrido, limpia su presentación local. Una petición registrada permanece en el historial; cerrar antes de resolver no asigna memoria ni elimina ese evento.
- Polling mantiene actualizado el contexto del hijo sin borrar su avance local. Cambiar escenario o proceso recrea el componente. Otra sesión consulta tabla, RAM y última petición, pero no recibe el progreso local de otro navegador.
- JavaScript resalta elementos existentes del DOM después del resultado; no participa en las decisiones de memoria. Respeta prefers-reduced-motion y limpia temporizadores al navegar.

## Verificación

- PagingStepModeTest: **13 pruebas aprobadas, 106 assertions**. Incluye puntos de resolución de Hit/Fault, acceso automático, permisos revocados, estado manipulado, modo inválido, limpieza local y cambio concurrente de presencia.
- Regresiones: **52 pruebas aprobadas, 202 assertions**.
- Compilación Vite y caché de vistas Blade: **aprobadas**.
- Chrome verificó los tres roles, preservación de datos durante consulta y anchos de 1366, 768, 390 y 320 píxeles.
- Los accesos automáticos completaron Fault y Hit con actualización real de memoria.
- En el fixture manual, el paso 5 conservó **2 KB** de RAM utilizada; el paso 6 cargó la página y elevó el uso a **3 KB**; el paso 8 mostró el recorrido finalizado.
- Con movimiento reducido no aparecieron resaltados animados. Sin esa preferencia se observaron los resaltados de pasos y marco.
- No se detectaron errores JavaScript ni de carga de recursos. Los fixtures temporales se limpiaron al completar la verificación.

Los resultados de esta entrega corresponden a sus pruebas específicas y regresiones. La suite integral de 285 pruebas y una omitida pertenece al cierre previo de la Fase 15.

## Capturas

- [Paginación en escritorio](fase-16-paginacion-escritorio.png).
- [Paginación en móvil](fase-16-paginacion-movil.png).
- [CPU en escritorio](fase-16-cpu-escritorio.png).
- [CPU en móvil](fase-16-cpu-movil.png).
- [Paso 5: inspección sin carga](fase-16-paso-5.png).
- [Paso 8: acceso finalizado](fase-16-paso-8.png).

## Uso y continuación

En Paginación selecciona escenario, proceso y página. Elige Automático para resolver y observar la secuencia, o Paso a paso para avanzar con Siguiente paso. El mensaje del panel identifica cuándo se guardan los cambios reales. La animación puede omitirse por la preferencia del sistema sin alterar el acceso.

Fase 16 completada con pruebas, compilación y navegador verificados. Continúa la Fase 17, traducción de direcciones, según la autorización general.

Commit sugerido: `feat: explain paging accesses with step mode and animations`.
