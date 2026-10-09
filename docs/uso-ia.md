# Evidencia real del uso de IA en MemoryLab

Fecha de registro: **8 de octubre de 2026**.

Última actualización: **9 de octubre de 2026**.

Este documento reúne instrucciones reales, decisiones y correcciones conservadas durante el desarrollo asistido por IA. Los extractos literales se identifican como citas; los resúmenes se indican como tales. La solicitud extensa permanece en [solicitud-inicial.md](evidencias/solicitud-inicial.md), y cada fase conserva implementación, comprobaciones y capturas. No se reconstruye una conversación que no esté disponible ni se añaden prompts ficticios.

## Instrucciones y prompts conservados

**Solicitud inicial, extracto literal:**

> Actúa como desarrollador senior de Laravel, Livewire, Bootstrap, JavaScript y MySQL, con conocimientos sólidos de Sistemas Operativos.

**Forma de trabajo de esa solicitud, extractos literales:**

> No debes construir todo el sistema de una sola vez.
>
> Trabajaremos por fases.

La misma solicitud exige analizar el estado, indicar cambios, limitarse a la fase y verificar funcionamiento antes de continuar. Su núcleo académico comprende tablas de páginas, Page Fault, tablas de segmentos, base/límite y comparación de asignación contigua/no contigua. También solicita conservar prompts, decisiones, diseños, código, capturas y resultados para el informe. [Fuente completa](evidencias/solicitud-inicial.md).

**Inicio de instalación, mensaje literal del usuario:**

> si inicismo con fase 1 donde estas trabajando ya isntalste laravel en la carpeta

Se inició la instalación en C:\xampp\htdocs\sistemg3, confirmando stack y MySQL antes de añadir otros módulos. [Evidencia de Fase 1](evidencias/fase-1.md).

**Continuación, mensajes literales:**

> procede

> continua

Estos mensajes autorizaron fases consecutivas. Las evidencias de [Fase 3](evidencias/fase-3.md), [Fase 4](evidencias/fase-4.md) y [Fase 6](evidencias/fase-6.md) registran su aplicación al alcance concreto, no a una sustitución del stack o una limpieza de datos.

**Corrección visual, mensaje literal acompañado de captura de registro:**

> veriica porque se ve asiaun

La captura mostraba el logo sobredimensionado y controles sin formato en /register. Se revisó el CSS servido y la compatibilidad de las clases de los componentes, y se corrigieron dimensiones y estilos. La revisión visual y las capturas posteriores se conservan en [Fase 3](evidencias/fase-3.md).

**Dashboard académico, resumen de la instrucción del usuario:** identificar Universidad Mariano Gálvez, Ingeniería en Sistemas y los cinco integrantes con sus carnés. El usuario proporcionó los nombres y números; se centralizaron en config/memorylab.php, conservando ceros iniciales. La tabla y comparación con la pantalla real se registran en [Fase 7](evidencias/fase-7.md).

**Autorización posterior, mensaje literal registrado el 7 de octubre:**

> continua con todas las fases yo no podre estar termina el proyecto fase por fase

Esta instrucción sustituyó la pausa de aprobación entre fases y mantuvo su desarrollo y verificación en orden. Se conserva en [avance.md](avance.md). El resultado sigue documentando por separado lo ejecutado y lo preparado, como la VM opcional.

## Decisiones asistidas y evidencia

| Decisión | Motivo y comprobación real | Evidencia |
| --- | --- | --- |
| Laravel, Livewire, Bootstrap y MySQL | Se mantuvo el stack solicitado. PHP de XAMPP y MySQL oficial se comprobaron; la base de pruebas quedó separada. | [Fase 1](evidencias/fase-1.md) |
| Sneat oficial y recursos locales | Se adaptó la plantilla solicitada, conservando procedencia/licencias y compilando recursos con Vite. | [Fase 4](evidencias/fase-4.md) |
| Tres roles con autorización en servidor | Registro asigna Observador; acciones recargan permisos y protegen al último Administrador. | [Fase 6](evidencias/fase-6.md) |
| Modelo en bytes enteros y escenarios | Una unidad mostrada como KB equivale a 1024 bytes; restricciones compuestas conservan el contexto de páginas y marcos. | [Modelo de memoria](modelo-memoria.md), [Fase 8](evidencias/fase-8.md) |
| Resolución transaccional e idempotente | Una solicitud resuelta conserva su resultado; FIFO utiliza fecha de carga y un Hit no la renueva. | [Fase 15](evidencias/fase-15.md) |
| Paso manual con mutación explícita | La explicación anterior a la carga conserva RAM; el paso de resolución guarda la acción real y los posteriores presentan su resultado. | [Fase 16](evidencias/fase-16.md) |
| Reset con historia conservada | Se finalizan procesos y liberan asignaciones; las solicitudes pendientes anteriores se rechazan y los eventos permanecen. | [Fase 22](evidencias/fase-22.md) |
| Terminal con lista cerrada | Se resuelven comandos educativos por escenario mediante servicios existentes, sin interpretar texto como shell. | [Fase 24](evidencias/fase-24.md) |
| Resultados compartidos de lectura | Observador recibe el acceso persistido mediante polling; leer no resuelve la petición de otra cuenta. | [Fase 25](evidencias/fase-25.md) |
| Seeder explícito sin cuentas automáticas | Usa un Administrador existente y conserva la pareja válida; segunda ejecución local sin cambios. | [Fase 26](evidencias/fase-26.md) |

## Correcciones documentadas

1. **Registro con SVG gigante.** El CSS respondía correctamente, pero los componentes aún dependían de clases Tailwind sin estilos. Se añadieron dimensiones explícitas y componentes Bootstrap; se comprobó registro/login/recuperación en escritorio y móvil. [Fase 3](evidencias/fase-3.md).
2. **Recursos y menú móvil.** Las fuentes apuntaban inicialmente a /build bajo la subcarpeta de Apache; se configuró base relativa. También se incorporó Perfect Scrollbar requerido por el menú oficial y se limitó la navbar en 320 píxeles. [Fase 4](evidencias/fase-4.md).
3. **Selección de segmento liberado.** Inicializar siempre en S0 podía dejar seleccionado un segmento ausente de las opciones activas. Se utiliza el primer ACTIVE o una selección vacía; se añadió regresión con S0 liberado y S1 activo. [Fase 19](evidencias/fase-19.md).
4. **Borde de fechas y filtros vacíos.** El último día de 9999 produciría un día siguiente fuera de MySQL; ambos extremos se limitan a 9999-12-30. Los filtros compuestos solo por espacios se rechazan antes de nullable para evitar convertirlos en ID cero. [Fase 23](evidencias/fase-23.md).
5. **Demo liberada presentada como activa.** Liberar un escenario del par limpia su referencia local; el montaje valida procesos activos para no mantener instrucciones de una demo finalizada. La historia permanece. [Fase 22](evidencias/fase-22.md).
6. **Textos residuales de autenticación en inglés.** Se revisaron las funciones activas y se completó la traducción del correo de recuperación, errores de contraseña, sesión vencida y exceso de solicitudes. El correo se renderizó con datos sintéticos sin enviarlo; 23 pruebas de autenticación aprobaron. El envío real de correos sigue dependiendo de configurar el servicio de correo de cada instalación.
7. **MariaDB del hosting rechazada por la migración.** El usuario informó el fallo y confirmó `10.6.28-MariaDB`. Se sustituyó el rechazo general por mínimos compatibles, sin omitir restricciones; la instalación interrumpida se reprodujo en una base temporal y pudo continuar. Se verificaron versiones, integridad y ambos conectores, y se añadió MariaDB a la matriz de GitHub. [Compatibilidad y pruebas](evidencias/compatibilidad-mariadb.md).
8. **Portada sencilla y paleta morada.** El usuario pidió el logo de la UMG, los integrantes y explicaciones breves, y eligió azul marino, rojo y blanco. Se rediseñó la portada y se aplicó la paleta al resto de la interfaz. El crédito visual se sustituyó por la identidad académica, conservando los avisos y la licencia MIT. La compilación, 89 pruebas y la revisión real en escritorio/móvil aprobaron. [Resultado, capturas y actualización del hosting](evidencias/rediseno-portada.md).
9. **Ajuste visual solicitado sobre la primera portada.** El usuario rechazó el bloque ilustrativo de RAM y pidió reemplazarlo por el escudo, respetar Sneat, animar la portada y añadir un icono de pestaña. Se mantuvo Public Sans con pesos locales y componentes de la plantilla, y se añadieron animaciones progresivas con adaptación a movimiento reducido. La compilación, 18 pruebas y la comprobación de navegador en cuatro tamaños aprobaron. [Cambios y capturas](evidencias/ajuste-portada-umg.md).

Estas correcciones muestran la necesidad de revisar resultados, permisos y pantallas reales: una respuesta HTTP correcta o una propuesta de IA no acredita por sí sola el comportamiento visual y persistente.

## Verificación y resultados comprobados

La suite integral de Fase 27 aprobó **655 pruebas**, con **una omisión condicional** y **4.575 aserciones**, en **96,78 segundos**. La omisión corresponde al caso de registro deshabilitado cuando el registro está activo; una prueba independiente verifica el cierre del registro. Pint, sintaxis de 117 archivos PHP, Vite y validación estricta de Composer aprobaron. [Evidencia integral](evidencias/fase-27.md).

Las pruebas utilizan memorylab_testing. La auditoría principal conserva dos cuentas y la historia del proyecto, con un Administrador, tres roles, 13 permisos y los datos de la pareja base. Los fixtures de navegador se limpiaron; no se publican credenciales, .env ni enlaces de recuperación. Las capturas se encuentran junto a la evidencia de cada fase.

## Alcance y atribución

El usuario proporcionó objetivos, stack, integrantes, correcciones y autorización. La IA asistió en análisis, implementación, revisión, pruebas y documentación; sus propuestas se contrastaron con código instalado, documentación oficial, pruebas automatizadas y navegador. Las decisiones concretas y límites quedan visibles en los archivos citados.

MemoryLab simula memoria con modelos y eventos; no administra la RAM física del equipo. La comparación educativa cita OSTEP en [el modelo](modelo-memoria.md). Sneat conserva la procedencia y los avisos de autoría/licencia de ThemeSelection en sus archivos. La guía Linux está preparada y el Apache local comprobado; no se afirma un despliegue en una VM o servidor externo. [Fase 28](evidencias/fase-28.md).

Las Fases 29–31 cuentan con un informe de nueve páginas, una presentación editable de doce diapositivas y un video de 180 segundos con subtítulos. Sus resultados y verificaciones se conservan en sus propias evidencias. El usuario solicitó después un manual de uso para seguir la secuencia y explicar qué demuestra cada actividad académica; se registra como entrega adicional.
