# MemoryLab: nuestras prácticas explicadas

Universidad Mariano Gálvez | Ingeniería en Sistemas | Grupo 3

## 1. Configuración: prepara la zapatera

Esta guía reúne las pruebas que hicimos con Vice City, Netflix y HBO. Cada página explica qué escribir, qué debería pasar y cómo contárselo a tus compañeros. Las capacidades son educativas: no son requisitos reales de esos programas ni cambian la RAM física de tu computadora.

**Orden:** configuración, procesos, paginación y FIFO, traducción, segmentación, límites y comparación. Entra a **http://localhost/sistemg3/public/login** con **admin@admin.com**, contraseña inicial **123**. Para reproducir todo, crea escenarios nuevos y anota sus #ID; así los accesos anteriores no cambian los resultados de este recorrido.

En **Configuración de memoria**, selecciona **Crear un escenario nuevo** y guarda estas dos configuraciones, una por una:

| Campo | Escenario para provocar un rechazo | Escenario principal |
| --- | --- | --- |
| Nombre del escenario | Práctica secundaria 6 | Práctica secundaria 8 |
| RAM total (KB) | 4 | 4 |
| Tamaño de página (KB) | 1 | 1 |
| Número de marcos | Se calcula: 4 | Se calcula: 4 |
| Almacenamiento secundario (KB) | 6 | 8 |

### Qué representa cada dato

**La RAM es la zapatera:** cuatro marcos son cuatro espacios. **Las páginas son los pares de zapatos:** cada una ocupa 1 KB. **La secundaria es el depósito:** guarda las páginas fuera de RAM. Aumentar secundaria agranda el depósito, pero sigues con cuatro espacios en la zapatera.

La RAM dividida entre el tamaño de página determina los marcos: **4 / 1 = 4**. Si otro escenario tuviera RAM de 8 KB y página de 1 KB, tendría ocho marcos. Con RAM de 4 KB y página de 2 KB, tendría dos. Usa enteros; la página debe caber en RAM y dividirla exactamente. RAM 4 y página 3 se rechaza. La secundaria admite cero, pero un proceso nuevo necesita espacio allí para sus páginas iniciales.

En la app **1 KB = 1024 bytes**. Un byte tiene ocho bits; los campos de estas prácticas utilizan KB o bytes, no bits. Las posiciones empiezan en cero.

**Para decir:** "Primero definimos el espacio del experimento. RAM determina cuántas páginas pueden estar cargadas; secundaria determina cuánto podemos guardar fuera de RAM".

<!-- pagebreak -->

## 2. Procesos: que falle y que sí permita crearlos

**Vice City es un proceso. P0, P1 y las demás son sus páginas.** Escribir su nombre no abre el juego; le das un nombre a un proceso simulado.

### Prueba A: el mismo proceso no cabe en secundaria de 6

Abre **Procesos**, selecciona **Práctica secundaria 6** y comprueba **Secundaria disponible: 6 KB**. En **Crear proceso**, escribe **Nombre: Vice City**, **Tamaño (KB): 7**, y pulsa **Crear proceso**.

Debe rechazarlo: sus siete páginas iniciales necesitan 7 KB, pero solo hay 6 KB disponibles. El rechazo es una validación; no se rompe la app ni se crea medio proceso. En una secundaria vacía de 4 KB, un proceso de 4 KB cabría y uno de 6 KB sería rechazado por la misma razón.

### Prueba B: secundaria de 8 y un proceso de 7

Selecciona **Práctica secundaria 8**. Comprueba que esté vacío. Crea **Vice City de 7 KB**. Ahora sí cabe: la app crea **P0 a P6**, todas en DISCO. La RAM sigue usando 0 KB; secundaria usa 7 KB y queda 1 KB libre.

Intenta crear **Spotify de 2 KB** antes de cargar páginas. Debe rechazarlo: solo queda 1 KB en secundaria. El tamaño total del depósito es 8, pero importa cuánto está disponible ahora.

### Prueba C: cargar una página libera secundaria

1. Abre **Paginación**. Selecciona **Práctica secundaria 8** y **Vice City**.
2. En **Solicitud de CPU**, elige **Automático**, solicita **Página 0 · DISCO** y pulsa **Acceder a página** una vez.
3. P0 entra al **marco 0**. RAM usada: 1 KB; secundaria usada: 6 KB; secundaria disponible: 2 KB.
4. Vuelve a **Procesos** con ese escenario. Crea **Netflix de 2 KB**. Ahora sí caben sus dos páginas iniciales en secundaria.

| Punto de control antes de continuar | Estado esperado |
| --- | --- |
| Vice City | 7 páginas; solo P0 está en RAM, marco 0 |
| Netflix | 2 páginas, P0 y P1; ambas en DISCO |
| RAM | 1 KB usado, 3 marcos libres |
| Secundaria | 8 KB usados, 0 KB disponibles |

**Para decir:** "En esta app, todas las páginas de un proceso nuevo empiezan en secundaria. Pedir una página desde Paginación la lleva a RAM y libera su espacio secundario. El número de páginas depende del tamaño del proceso y del tamaño de página".

<!-- pagebreak -->

## 3. Paginación: cargar, acertar y reemplazar

Continúa en **Práctica secundaria 8**. Mantén **Automático** y espera cada respuesta antes de hacer la siguiente. La primera carga de Vice City P0 ya se hizo en la página anterior: no vuelvas a empezar otro escenario.

| Orden | Proceso y página que solicitas | Resultado esperado |
| --- | --- | --- |
| 1, ya realizado | Vice City P0 | Entra al marco 0: Fault |
| 2 | Vice City P1 | Entra al marco 1: Fault |
| 3 | Vice City P2 | Entra al marco 2: Fault |
| 4 | Vice City P3 | Entra al marco 3: Fault; RAM llena |
| 5 | Vice City P0 otra vez | Hit: permanece en marco 0 |
| 6 | Vice City P5 | Sale Vice City P0; P5 usa marco 0 |
| 7 | Vice City P0 | Sale Vice City P1; P0 usa marco 1 |
| 8 | Netflix P0 | Sale Vice City P2; Netflix P0 usa marco 2 |
| 9 | Vice City P6 | Sale Vice City P3; P6 usa marco 3 |

**PAGE_HIT:** la página ya estaba dentro de la zapatera. Consultarla no ocupa otro espacio ni crea una copia. **PAGE_FAULT:** estaba en el depósito y hubo que cargarla. Si había un marco libre, lo usó. Si no, fue necesario reemplazar una página.

**FIFO significa "primero en entrar, primero en salir".** Es como una fila: cuando necesitas espacio, sale la página que lleva más tiempo cargada. Usar otra vez P0 en el paso 5 no cambia cuándo entró; por eso igual sale en el paso 6. La página expulsada vuelve a DISCO, no se borra del proceso.

| Marco al terminar | Qué debe contener |
| --- | --- |
| 0 | Vice City P5 |
| 1 | Vice City P0 |
| 2 | Netflix P0 |
| 3 | Vice City P6 |

Vice City tiene **P1, P2, P3 y P4 en DISCO**: 4 KB. Secundaria muestra **5 KB usados** porque también incluye Netflix P1. RAM está llena, con 4 KB. En **Inicio**, para este escenario, **Page Faults: 8** si seguiste exactamente el orden. Son nueve accesos, ocho Faults, un Hit y cuatro reemplazos.

**Para decir:** "Paginación divide cada proceso en páginas iguales. La tabla guarda qué marco tiene cada página. FIFO entra en juego cuando no queda un marco libre".

<!-- pagebreak -->

## 4. Traducción: ¿dónde está mi dato?

Piensa en dos libros. "Busca la página uno y una línea" no basta: debes indicar de qué libro. **Netflix y Vice City tienen sus propias posiciones**, aunque ambos utilicen números como 0 o 500.

Abre **Traducción de direcciones**, selecciona **Práctica secundaria 8** y sigue estas dos consultas. No hagas nuevos accesos en Paginación entre ellas.

| Proceso | Escribe en Dirección en bytes, desde cero | Pulsa |
| --- | --- | --- |
| Netflix | 500 | Traducir dirección |
| Vice City | 500 | Traducir dirección |

500 pertenece a P0, porque una página de 1024 bytes contiene las posiciones 0 a 1023. El **offset 500** es cuánto avanzas dentro de esa página.

| Proceso | Relación guardada por Paginación | Resultado |
| --- | --- | --- |
| Netflix | P0 está en marco 2; comienza en 2048 | 2048 + 500 = **2548** |
| Vice City | P0 está en marco 1; comienza en 1024 | 1024 + 500 = **1524** |

**Dirección lógica:** el número que el programa utiliza para pedir su dato. **Dirección física simulada:** la posición de RAM donde está ese dato ahora. El marco 0 empieza en 0; el 1, en 1024; el 2, en 2048; el 3, en 3072.

La traducción sabe el marco porque **consulta la tabla que actualizó Paginación**. No elige un marco nuevo ni mueve la página. Si FIFO cambia su ubicación, la tabla cambia y después obtendrás otra dirección física para la misma dirección lógica.

### ¿Para qué sirve?

El programa puede seguir pidiendo "mi posición 500" aunque sus páginas estén repartidas por RAM. La traducción encuentra la ubicación correspondiente. La posición 500 de Netflix no es el mismo dato que la posición 500 de Vice City.

**Para decir:** "El programa identifica el dato dentro de su propia memoria. La traducción consulta su tabla de páginas y encuentra dónde está ese dato en RAM. No estamos agregando 500 bytes: estamos buscando una posición".

Si tus resultados son otros, revisa primero escenario, proceso y marco actual de P0. Los valores 2548 y 1524 dependen de que el mapa siga como en la página anterior.

<!-- pagebreak -->

## 5. Rango válido y una página que está en DISCO

Vice City mide **7 KB = 7168 bytes**. Sus posiciones se numeran **0 a 7167**. Ese es el rango que puedes consultar: 7167 es la última posición; 7168 queda fuera del proceso. No depende de cuántas páginas estén cargadas en RAM.

| Página | Posiciones dentro de Vice City | Estado después de la página 3 |
| --- | --- | --- |
| P0 | 0 a 1023 | RAM, marco 1 |
| P1 | 1024 a 2047 | DISCO |
| P2 | 2048 a 3071 | DISCO |
| P3 | 3072 a 4095 | DISCO |
| P4 | 4096 a 5119 | DISCO |
| P5 | 5120 a 6143 | RAM, marco 0 |
| P6 | 6144 a 7167 | RAM, marco 3 |

En **Traducción de direcciones**, elige Vice City y traduce **1500**. Pertenece a P1 y tiene offset 476, pero P1 está en DISCO: muestra **sin marco**, sin dirección física. La dirección pertenece al proceso; falta que su página esté en RAM. Conserva P1 en DISCO para seguir el ejemplo.

### Prueba con P4 antes y después de cargarla

1. Traduce **4500**. Está en P4: 4500 - 4096 = **404** de offset. Todavía no hay marco.
2. Abre **Paginación**, elige el mismo escenario y **Vice City**, y solicita **P4** una vez en Automático.
3. FIFO expulsa **Vice City P5**, la residente más antigua. P4 ocupa su **marco 0**.
4. Vuelve a Traducción, selecciona Vice City y traduce **4500**: dirección física **0 x 1024 + 404 = 404**.

Pedir **4500** no significa guardar 4500 bytes nuevos. Es consultar una posición de Vice City. **Escribir 4 tampoco elige P4:** la posición 4 pertenece a P0. Para consultar el comienzo de P4 escribirías 4096.

Solo el acceso desde Paginación carga la página y cuenta un Fault. La calculadora no carga ni registra accesos. Después de esta carga, Inicio debe indicar **Page Faults: 9** si se siguió el recorrido exacto.

**Para decir:** "Una dirección válida puede tener su página fuera de RAM. Primero consultamos y vimos que faltaba un marco; después cargamos la página y repetimos la consulta para localizar el dato".

<!-- pagebreak -->

## 6. Segmentación: HBO y sus cajas

Ahora cambiaremos a otro experimento. En **Segmentación**, crea **Nombre: Práctica HBO**, **RAM total (KB): 4**. Pulsa **Crear escenario** y anota su #ID. Aquí el escenario se crea en esta misma pantalla.

En **Crear proceso**, escribe **Nombre: HBO** y **Tamaño del proceso (KB): 1**. Pulsa **Crear proceso** y comprueba que HBO esté seleccionado arriba. El proceso permite segmentos que sumen hasta **1024 bytes**; crearlo todavía no reserva RAM.

Piensa en **cajas sobre una mesa**: la mesa es la RAM, HBO es el programa y cada caja es un segmento suyo. Pueden tener tamaños diferentes. Película y Serie son nombres elegidos para aprender; no representan un video real de ese tamaño. En un programa las partes pueden llamarse Código, Datos, Stack y Heap.

En **Agregar segmento**, crea estas filas en orden, pulsando el botón una vez después de cada una:

| Nombre | Base (bytes) | Tamaño / límite (bytes) | Ocupará |
| --- | --- | --- | --- |
| Película | 0 | 100 | 0 a 99 |
| Serie | 100 | 200 | 100 a 299 |
| Caricatura | 350 | 25 | 350 a 374 |

### Base y tamaño son cosas distintas

**Base es el lugar donde empieza la caja. Tamaño es cuánto espacio ocupa.** Una casa en el número 2024 no tiene por eso 2024 habitaciones. De la misma forma, empezar en 350 no hace que Caricatura ocupe 350 bytes: solo ocupa 25.

Cada posición representa un byte y se avanza de uno en uno: **0, 1, 2... 99, 100...**. El último lugar ocupado es **base + tamaño - 1**. Película acaba en 99 porque contamos también la posición 0.

| Mapa esperado | Qué significa |
| --- | --- |
| Película 0-99 / Serie 100-299 | Dos partes, una al lado de la otra |
| Libre 300-349 | Un hueco de 50 bytes que dejaste entre segmentos |
| Caricatura 350-374 / Libre 375-4095 | Otra parte y el espacio que queda después |

RAM total: **4096 bytes**; utilizada: **325**; disponible: **3771**. HBO aún puede agregar hasta **699 bytes** de segmentos activos. Su tamaño declarado y la RAM libre son límites distintos.

**Para decir:** "Dividimos HBO en partes de distintos tamaños. Cada parte ocupa un intervalo continuo; las partes del programa pueden estar separadas por huecos".

<!-- pagebreak -->

## 7. ¿Por qué no me dejaba poner base 301?

Conserva **Práctica HBO** y el proceso **HBO**. Intenta agregar un segmento llamado **Extra** con **Base: 301** y **Tamaño / límite: 340**.

Debe rechazarse por superposición. **340 es la cantidad de bytes, no la dirección final.** Empezando en 301 ocuparía **301 a 640**, y en medio ya está Caricatura, en 350 a 374.

| Región | Posiciones |
| --- | --- |
| Hueco que tienes antes de Caricatura | 300 a 349: 50 bytes |
| Segmento Extra que intentaste | 301 a 640: 340 bytes |
| Parte que invade | Caricatura, 350 a 374 |

Que la posición inicial esté libre no basta: **todo el espacio de la caja debe estar libre**. Cambiar la base mueve la caja; cambiar el tamaño la agranda o la reduce. Aumentar la base no reduce automáticamente lo que ocupa.

### Corrige manteniendo la base 301

Cambia el tamaño a **49** y pulsa **Agregar segmento**. Ahora Extra ocupa **301 a 349** y debe aceptarse. El byte **300** sigue libre; no tienes obligación de llenar cada hueco.

La suma de segmentos pasa de 325 a **374 bytes**. El proceso HBO de 1 KB permite hasta 1024, por lo que quedan **650 bytes** de capacidad para otros segmentos. El mapa global de RAM muestra **3722 bytes disponibles**.

Otra posibilidad, desde el estado anterior a agregar Extra, era **base 375 y tamaño 340**: ocuparía 375 a 714, después de Caricatura. Esta guía continúa con Extra de 49; no agregues ambas opciones con el mismo nombre para seguir el recorrido.

### Un rechazo diferente: superar el tamaño del proceso

Después de Extra, intenta agregar **Nombre: Demasiado**, **Base: 1000**, **Tamaño: 700**. No se cruza con las cajas anteriores y cabría en RAM, pero debe rechazarse porque **374 + 700 = 1074**, más que los **1024 bytes** del proceso HBO.

**Para decir:** "Primero comprobamos que la región quepa en RAM y que la suma respete el tamaño del proceso. También revisamos que no invada ningún segmento activo. Tener RAM libre no elimina estas reglas".

<!-- pagebreak -->

## 8. Acceder a un segmento: la cerca del terreno

Una vez creadas las cajas, puedes consultar posiciones dentro de ellas. Baja a **Direccionamiento por segmentación**, selecciona **Serie**, de **base 100 y tamaño 200**.

**Offset significa cuánto avanzas desde el comienzo de esa caja.** No es memoria que agregas ni una dirección absoluta de RAM. Primero se comprueba el límite y luego, si está permitido, se suma la base.

Escribe cada valor en **Offset en bytes** y pulsa **Acceder**:

| Offset | Comprobación | Resultado esperado |
| --- | --- | --- |
| 0 | Primer espacio dentro de Serie | SEGMENT_ACCESS; 100 + 0 = **100** |
| 100 | 100 es menor que 200 | SEGMENT_ACCESS; 100 + 100 = **200** |
| 199 | Último desplazamiento permitido | SEGMENT_ACCESS; 100 + 199 = **299** |
| 200 | Ya no es menor que el tamaño | SEGMENTATION_FAULT; sin dirección válida |

Serie tiene 200 posiciones internas numeradas **0 a 199**. Su ubicación en RAM es **100 a 299**. No confundas las posiciones dentro del segmento con las posiciones de RAM.

### ¿Qué protege esta comprobación?

El offset 200 intentaría llegar a la posición 300 de RAM. En este ejemplo está libre, pero **no pertenece a Serie**. Es como un terreno cercado: que haya un espacio vacío al otro lado no te da permiso para usarlo.

Comprobar límites ayuda a impedir que un acceso fuera de una región alcance información de otra. La app simula el rechazo: no calcula una dirección física válida, registra el fallo y conserva los segmentos. No cierra HBO automáticamente ni está reproduciendo realmente videos.

Crear una caja encima de otra y salirte al consultar son errores distintos: el primero se detecta en **Agregar segmento**; el segundo, en **Acceder**.

**Para decir:** "Base indica dónde comienza una región; tamaño, cuántos bytes le pertenecen; offset, cuánto avanzamos dentro. El acceso se permite solo si el offset es cero o positivo y menor que el tamaño. Así demostramos protección de memoria".

Las partes del proceso pueden estar separadas, pero **cada segmento individual ocupa un intervalo seguido**. Ese es el sentido de que un segmento sea continuo.

<!-- pagebreak -->

## 9. Comparación: el cine y Vice City

Abre **Comparación de asignación**. Esta pantalla trae **un ejercicio fijo del comparador**: RAM de **16 KB**, procesos A y B ocupando **7 KB**, y **9 KB libres en huecos de 3 y 6 KB**. No usa tu escenario de 4 KB ni lo modifica. Tú cambias la solicitud.

Imagina un cine: un grupo libre de tres asientos y otro de seis, separados. Llegan siete personas. Hay nueve lugares en total, pero la forma de acomodarse importa.

| Técnica | En el cine | En la memoria |
| --- | --- | --- |
| Contigua | Los siete juntos: no hay una fila libre de siete | El proceso completo debe caber en un solo hueco |
| Paginación | Se reparten individualmente entre los dos grupos | Piezas iguales de 1 KB en marcos separados |
| Segmentación | Una familia de cuatro y otra de tres; cada familia junta | Partes por función; cada segmento necesita su propio hueco continuo |

En **Tamaño solicitado en bytes**, escribe cada valor y pulsa **Comparar asignación**:

| Solicitud | Contigua | Paginación | Segmentación |
| --- | --- | --- | --- |
| 5000 | Aceptada | Aceptada | Aceptada |
| 7168, que son 7 KB | Rechazada | Aceptada | Aceptada |
| 7500 | Rechazada | Aceptada | Aceptada |
| 15000 | Rechazada | Rechazada | Rechazada |

Con 7168, el ejemplo de segmentación divide Código en **4 KB** y Datos en **3 KB**. Caben en los huecos. Son tamaños educativos, no partes medidas del juego real. Con 15000 no alcanza ni la suma de los espacios libres: solo hay 9216 bytes.

### Dos clases de fragmentación

**Externa:** hay espacio libre suficiente al sumarlo, pero está separado y no ofrece un hueco continuo adecuado. Eso explica el rechazo contiguo de 7168.

**Interna:** con solicitud 7500, paginación reserva ocho páginas, **8192 bytes**. Sobran **692** dentro de la última página. Segmentación reserva 7500 en este caso, sin ese relleno; otras particiones podrían no caber en los huecos.

**Para decir:** "Comparamos las técnicas con la misma memoria inicial. Espacio libre total no significa espacio libre junto. Este comparador exige que la solicitud completa quepa en RAM; en Paginación practicamos otro caso donde algunas páginas quedan en secundaria".

<!-- pagebreak -->

## 10. Hoja para explicarlo a tus compañeros

Sigue el orden de las páginas anteriores. Puedes usar estas frases como recordatorio mientras muestras cada pantalla:

| Pantalla | Qué puedes decir |
| --- | --- |
| Configuración | "La RAM es nuestra zapatera y los marcos son sus espacios. La secundaria es el depósito. Agrandar el depósito no agrega espacios a la zapatera". |
| Procesos | "Creamos un programa y la app genera sus páginas. Vice City de 7 KB tiene siete páginas de 1 KB. Empiezan en secundaria, por eso primero deben caber allí". |
| Paginación | "Hit significa que la página ya estaba en RAM. Fault significa que necesitamos cargarla. Cuando no queda lugar, FIFO saca la que entró primero". |
| Traducción | "El programa pide una posición propia. Consultamos su tabla para encontrar en qué marco está la página y dónde queda ese dato en RAM". |
| Segmentación | "Organizamos HBO en cajas de distintos tamaños. Elegimos dónde empieza cada una y cuánto ocupa, sin poner una encima de otra". |
| Acceso a segmentos | "Podemos consultar dentro de una caja, pero no salir de sus límites aunque exista memoria libre al lado". |
| Comparación | "Como en el cine, no es igual necesitar todos los asientos juntos que repartir personas o grupos. Comparamos las tres formas con los mismos huecos". |

### Preguntas que ya puedes responder

**¿2024 significa que el segmento ocupa más?** No. La base es el lugar; el tamaño determina lo que ocupa. **¿El offset agranda el segmento?** No: indica una posición interna que quieres consultar.

**¿P0 de Netflix y P0 de Vice City son lo mismo?** Son páginas de procesos distintos. Cada proceso tiene su propia tabla. **¿Escribir 4 en Traducción elige P4?** No. Escribes una dirección en bytes; con páginas de 1024, P4 comienza en 4096.

**¿Una dirección válida siempre está en RAM?** No. Puede pertenecer al proceso y tener su página en DISCO. **¿La calculadora la carga?** No: la solicitas desde Paginación y después vuelves a traducir.

Para repetir, crea escenarios con otro nombre, por ejemplo "Práctica secundaria 8 - ensayo 2". Comprueba siempre nombre, #ID y proceso. Un acceso adicional puede cambiar FIFO y los marcos; explica la tabla actual en lugar de forzar un número aprendido de memoria. El botón **Demostración** prepara otros datos y no forma parte de este recorrido manual.

Guía basada en las prácticas conversadas, las capturas compartidas y las reglas instaladas de MemoryLab. La secuencia reproduce los resultados como ejercicio nuevo; este documento no configura ni borra tus escenarios. El guion de una hora y el PowerPoint de 24 diapositivas usan otros ejemplos, Chrome y Editor, para el reparto formal de integrantes. Conserva los datos y el orden de cada recorrido al usarlo.
