# Fase 20 — Comparación de administración de memoria

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases en orden sin nuevas confirmaciones. La Fase 20 compara asignación contigua, paginación y segmentación mediante un ejemplo matemático calculado en servidor, con mapas de intervalos y una tabla técnica.

El cálculo conserva los escenarios guardados. Sus reglas de carga completa y partición ilustrativa se muestran para interpretar el resultado.

## Comportamiento implementado

- Ejemplo fijo de RAM de 16384 bytes, con A y B ocupando 7168 bytes. Quedan dos huecos de 3072 y 6144 bytes: 9216 libres en total.
- La solicitud puede variar entre 1 y 16384 bytes. Las tres técnicas parten del mismo mapa inicial.
- Contigua variable utiliza first fit para reservar el proceso completo en un único hueco.
- Paginación usa marcos de 1024 bytes y exige que toda la solicitud pueda cargarse en los nueve marcos libres. Reserva páginas completas y muestra el espacio interno sin utilizar.
- Segmentación propone Código y Datos, asignados con first fit y tamaño exacto. Datos es min(3072, floor(solicitud × 3 / 7)); Código utiliza el resto. Si un segmento no cabe, no se reserva ninguno.
- Los mapas muestran bloques existentes, asignaciones nuevas y huecos. Cada técnica indica aceptación, reserva, fragmentación interna y memoria libre final.
- Una tabla compara unidad, ubicación, fragmentación, estructura, ventaja y límite. La partición de segmentos se identifica como ilustrativa.
- Los tres roles consultan con los cuatro permisos de lectura. El resultado utiliza Locked, la entrada se valida y no se modifica la selección de escenario.
- El servicio no consulta ni escribe la base; no carga páginas, registra accesos o crea un escenario CONTIGUOUS persistente.

## Referencias

Las páginas y marcos de tamaño fijo y la tabla de traducción se fundamentan en [OSTEP: Paging](https://pages.cs.wisc.edu/~remzi/OSTEP/vm-paging.pdf). La ubicación independiente de segmentos y el riesgo de fragmentación externa se fundamentan en [OSTEP: Segmentation](https://pages.cs.wisc.edu/~remzi/OSTEP/vm-segmentation.pdf). La selección del primer hueco suficiente se fundamenta en [OSTEP: Free-Space Management](https://pages.cs.wisc.edu/~remzi/OSTEP/vm-freespace.pdf). Se utilizan paráfrasis; el mapa y los valores del ejemplo son propios del proyecto.

## Verificación

- MemoryComparisonTest: **37 pruebas aprobadas**.
- Regresión SegmentationAccessTest: **32 pruebas aprobadas**, incluida selección de un segmento activo cuando S0 está liberado.
- Resultado conjunto: **69 pruebas aprobadas, 1572 assertions**, en 9,90 segundos.
- Pint y caché de vistas Blade: **aprobados**.
- Chrome verificó los tres roles y solicitudes de **7168**, **7500** y **9217 bytes**.
- Con 7168 bytes, contigua se rechazó porque supera el mayor hueco, mientras paginación y la partición propuesta de segmentos encajaron.
- Con 7500 bytes, paginación reservó 8192 bytes y mostró **692 bytes** de fragmentación interna.
- Con 9217 bytes, la solicitud superó los 9216 bytes libres; las técnicas rechazaron la asignación sin reservas parciales.
- La firma de las siete tablas del dominio permaneció igual; el usuario temporal de la comprobación se eliminó después.
- Se comprobaron anchos de 1366, 768, 390 y 320 píxeles, sin desbordamiento ni errores JavaScript o de recursos.

## Capturas

- [Comparación en escritorio](fase-20-comparacion-escritorio.png).
- [Comparación en móvil](fase-20-comparacion-movil.png).

## Uso y continuación

Abre Comparación e introduce un tamaño en bytes. Observa si cada técnica acepta la solicitud y compara sus intervalos, reserva y espacio libre. El ejemplo muestra que tener bytes libres suficientes no garantiza un hueco continuo suficiente, y que reservar páginas completas puede dejar espacio interno sin utilizar.

Fase 20 completada con pruebas y navegador verificados. Continúa la Fase 21, alta demanda simulada, según la autorización general.

Commit sugerido: `feat: compare contiguous paging and segmented allocation`.
