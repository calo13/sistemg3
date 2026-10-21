# PowerPoint para la exposición de una hora

Abrir [memorylab-presentacion-exposicion-60-minutos.pptx](../output/presentations/memorylab-presentacion-exposicion-60-minutos.pptx). Tiene 24 diapositivas editables, formato panorámico 16:9, logo de la Universidad Mariano Gálvez y colores azul marino, rojo y blanco.

Usarlo junto con la [guía detallada](guion-exposicion-60-minutos.md) o su [PDF para imprimir](../output/pdf/memorylab-guion-exposicion-60-minutos.pdf). Los números de diapositiva y los valores de los ejemplos coinciden entre los tres archivos.

## Reparto

| Integrante | Tiempo | Diapositivas | Contenido |
| --- | --- | --- | --- |
| Enmer Antonio Buch Xinic | 00:00 a 12:00 | 1 a 7 | Presentación del grupo, conceptos, arquitectura y preparación del escenario |
| Lester Emilio Pedro Juan | 12:00 a 24:00 | 8 a 11 | Tabla inicial, Page Fault paso a paso, carga de RAM y Page Hit |
| Deivid Alberto Guerra Carpio | 24:00 a 36:00 | 12 a 15 | Reemplazo FIFO, traducción de 3500, página ausente y retorno de P0 |
| Rebeca Alizon Najarro Duarte | 36:00 a 48:00 | 16 a 19 | Segmentación, tabla, accesos válidos y protección del límite |
| Carlos Lopez Urizar | 48:00 a 60:00 | 20 a 24 | Comparación, fragmentación, uso de IA, conclusiones y preguntas |

## Cómo exponer

1. Abrir el PowerPoint en la aplicación de escritorio. En vista **Normal**, pulsar **Notas** para leer el texto de cada diapositiva en el panel inferior.
2. En **Presentación con diapositivas**, activar **Usar vista Moderador**. Con un proyector como segunda pantalla, el público ve la diapositiva y el expositor puede consultar sus notas. Comprobar antes de empezar qué pantalla recibe el proyector. Si solo hay una pantalla, usar el guion impreso para mantener las instrucciones fuera de la proyección.
3. Mantener abierta la aplicación en `http://localhost/sistemg3/public/`, con la cuenta Administrador del grupo. Revisar la preparación en las páginas 2 y 3 del PDF. Los escenarios de la exposición se crean siguiendo el recorrido manual de la guía.
4. Pulsar **F5** para comenzar. Usar las flechas para avanzar y **Alt + Tab** para cambiar entre la presentación y el navegador; regresar después a la misma presentación.
5. Consultar en las notas **TIEMPO**, **HACER Y COMPROBAR**, **DECIR EN VOZ ALTA** y **CAMBIO DE PANTALLA**. Leer al público el texto de DECIR; ejecutar las acciones cuando corresponda. Las tablas de las diapositivas muestran los estados esperados, que se comprueban en vivo en la aplicación.
6. Ensayar los cinco turnos con cronómetro. Cada turno de 12 minutos combina explicación, acciones y pausas; el archivo por sí solo no determina la duración. Reservar las diapositivas 23 y 24 para el cierre y las preguntas dentro de la hora.

Para cambiar de escenario, verificar siempre su nombre y número, y seleccionar de nuevo el proceso cuando lo pida el formulario. Respetar el orden de cargas **P0, P1, P3, P2** para obtener P3 en el marco 2 y traducir **3500 a 2476**. Después del segundo reemplazo, P0 ocupa el marco 1 y **100 se traduce a 1124**.

La diapositiva 22 identifica expresamente las cifras de pruebas como evidencia histórica del **8 de octubre de 2026**. Las notas incluyen las referencias del proyecto utilizadas para cada tema.

El menú **Entregables académicos** conserva la presentación anterior de doce diapositivas. Para esta exposición de una hora, abrir el nuevo archivo de 24 diapositivas enlazado al inicio de este documento.

## Fuente editable

El texto y las tablas se pueden editar directamente en PowerPoint. El constructor reproducible está en `scripts/artifacts/build-exposition-presentation.mjs` y toma los nombres de `config/memorylab.php` y el texto hablado de `docs/guion-exposicion-60-minutos.md`. La generación utiliza el runtime de artefactos instalado y no realiza operaciones sobre la aplicación ni la base de datos.
