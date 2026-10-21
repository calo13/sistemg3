# MemoryLab: aprende desde cero

Universidad Mariano Gálvez | Ingeniería en Sistemas | Grupo 3

## 1. Primero configura la memoria

**Qué hace esta pantalla:** prepara un experimento, llamado escenario. Decide cuánta RAM habrá, en qué tamaño de partes la dividirás y cuánto almacenamiento secundario tendrás. Son cantidades simuladas: no cambian la memoria física de tu computadora.

Entra a **http://localhost/sistemg3/public/login** con **admin@admin.com** y contraseña **123**. Abre **Configuración de memoria** en el menú lateral. En **Escenario**, selecciona **Crear un escenario nuevo**.

| Campo | Escribe | Qué significa |
| --- | --- | --- |
| Nombre del escenario | Mi práctica 01 | Nombre para reconocer este experimento |
| RAM total (KB) | 4 | Espacio donde estarán las páginas que se carguen |
| Tamaño de página (KB) | 1 | Tamaño de cada parte del proceso y de cada marco de RAM |
| Número de marcos | No escribas | La app calcula 4 porque 4 dividido entre 1 es 4 |
| Almacenamiento secundario (KB) | 16 | Espacio simulado para las páginas que estén fuera de RAM |

Escribe solo los números: **4**, **1** y **16**, sin agregar las letras KB. Pulsa **Guardar configuración** una vez. Anota el nombre y el **#ID** que aparezca.

**Qué debes ver:** cuatro marcos creados, todavía libres. Aún no hay procesos ni páginas. Configurar la memoria prepara el espacio; el siguiente paso será crear un proceso.

### Qué datos sirven y cuáles no

La RAM y la página deben ser enteros positivos; la página no puede superar la RAM y la división debe dar un entero. **RAM 4 / página 1** es válido; **RAM 4 / página 2** también. **RAM 4 / página 3**, **RAM 4 / página 8**, cero o decimales en esos dos campos se rechazan. La app admite hasta 1024 marcos y 65536 KB por capacidad.

El almacenamiento secundario sí admite **0**, pero las páginas nuevas empiezan allí: con cero no podrás crear Chrome en esta práctica. Usa **16**. La tarjeta lateral recomienda 16 KB de RAM; para este ejercicio escribe **4**, así podrás llenarla rápidamente.

**Orden de trabajo:** configurar memoria, crear proceso, solicitar páginas, consultar direcciones. Después practicarás segmentación y comparación. Antes de crear procesos puedes ajustar las capacidades. Cuando el escenario ya tiene procesos, crea otro escenario para probar una configuración diferente.

<!-- pagebreak -->

## 2. Después crea un proceso

**Qué es un proceso:** en este modelo representa un programa. Lo llamarás Chrome, pero escribir ese nombre no abre el navegador ni mide su consumo real.

1. Abre **Procesos**.
2. En **Escenario**, selecciona **Mi práctica 01** y comprueba su #ID.
3. En **Crear proceso**, escribe **Nombre: Chrome** y **Tamaño (KB): 6**.
4. Comprueba **Páginas requeridas: 6**. Ese resultado lo calcula la app; no eliges los números de página.
5. Pulsa **Crear proceso** una sola vez.

### De dónde salen las páginas

Chrome mide 6 KB y elegiste páginas de 1 KB. **6 dividido entre 1 es 6 páginas.** La app las crea y numera automáticamente: **P0, P1, P2, P3, P4 y P5**. Se empieza desde cero.

Piensa en seis piezas y cuatro casillas. Las piezas son las páginas de Chrome; las casillas son los marcos de RAM. Cada casilla puede guardar una página. Inicialmente las seis piezas están en almacenamiento secundario y las cuatro casillas siguen libres.

| Después de crear Chrome | Debe indicar |
| --- | --- |
| Páginas de Chrome | 6, todavía en DISCO |
| RAM utilizada | 0 KB |
| Marcos libres | 4 |
| Secundaria utilizada / disponible | 6 KB / 10 KB |

### Qué depende de la configuración

| Si en otro escenario cambias... | Entonces... |
| --- | --- |
| RAM a 8 KB y mantienes página de 1 KB | Hay 8 marcos; caben más páginas a la vez |
| Página a 2 KB con RAM de 4 KB | Hay 2 marcos; Chrome de 6 KB tiene 3 páginas |
| Chrome a 3 KB con página de 1 KB | Chrome tiene 3 páginas |
| Secundaria a 4 KB, antes de crear Chrome de 6 KB | La creación se rechaza: no caben sus páginas iniciales |

El número de páginas depende del tamaño del proceso y del tamaño de página. Si no divide exacto, se redondea hacia arriba: un proceso de 5 KB con páginas de 2 KB requiere 3 páginas. Cada página ocupa su tamaño completo en este modelo.

**Por ahora conserva los datos originales:** RAM 4, página 1, secundaria 16 y Chrome 6. Puede ser más grande que la RAM porque irás cargando solo algunas de sus páginas.

<!-- pagebreak -->

## 3. Ahora usa la memoria: Paginación

Abre **Paginación**. Comprueba el escenario **Mi práctica 01** y selecciona **Chrome** en **Seleccionar proceso**. Al cambiar de pantalla, comprueba siempre escenario y proceso.

En la tabla, **Página** identifica una parte de Chrome; **Marco** indica dónde está actualmente en RAM. **Presente: No / Estado: DISCO / sin marco** significa que la página todavía no ocupa RAM. No significa que esté borrada.

En **Solicitud de CPU**, selecciona **Modo de simulación: Automático**. Elige cada página de la tabla siguiente y pulsa **Acceder a página** una vez. Espera el resultado antes de continuar. Respeta el orden.

| Paso | Página que eliges | Qué debería pasar |
| --- | --- | --- |
| 1 | Página 0, DISCO | P0 entra al marco 0. RAM usada: 1 KB |
| 2 | Página 1, DISCO | P1 entra al marco 1. RAM usada: 2 KB |
| 3 | Página 3, DISCO | P3 entra al marco 2. RAM usada: 3 KB |
| 4 | Página 2, DISCO | P2 entra al marco 3. RAM usada: 4 KB |
| 5 | Página 0, RAM | Ya estaba cargada. El mapa no necesita moverla |
| 6 | Página 4, DISCO | Sale P0; P4 ocupa el marco 0. RAM sigue en 4 KB |

### Qué significa cada resultado

**PAGE_FAULT, fallo de página:** pediste una página que estaba fuera de RAM. Para completar el acceso hubo que cargarla. Eso ocurre en los pasos 1, 2, 3, 4 y 6.

**PAGE_HIT, acierto:** pediste una página que ya estaba en RAM. En el paso 5, P0 sigue en su marco y no aparece una segunda copia por pedirla otra vez.

**FIFO, primero en entrar, primero en salir:** en el paso 6 la RAM estaba llena. P0 había entrado antes que las demás, por eso deja su marco y vuelve a secundaria. Usarla otra vez en el paso 5 no cambia su orden de entrada. La página no se elimina de Chrome.

**Punto de control:** marco 0 = P4; marco 1 = P1; marco 2 = P3; marco 3 = P2. P0 y P5 están en DISCO. RAM usada: 4 KB; secundaria usada: 2 KB. En **Inicio**, con este escenario, **Page Faults** debe indicar **5**. Regresa sin hacer accesos adicionales.

Si una página sigue en RAM, pedirla de nuevo produce un Hit. Si FIFO la expulsó, volver a pedirla produce otro Fault. La tabla actual es la que te permite saberlo.

<!-- pagebreak -->

## 4. Luego busca un dato: Traducción de direcciones

**Qué significa una dirección:** una posición dentro de la memoria. Si Chrome pide la posición 100, no está pidiendo 100 KB adicionales ni el tamaño del programa; quiere localizar un dato en esa posición. Las posiciones empiezan en cero.

### Primero, un dato en una página que sí está cargada

Abre **Traducción de direcciones**. Selecciona el mismo escenario y **Chrome**. En **Dirección en bytes, desde cero**, escribe **3500** y pulsa **Traducir dirección**.

P0 contiene las posiciones 0 a 1023; P1, de 1024 a 2047; P2, de 2048 a 3071; P3, de 3072 a 4095. **3500 cae dentro de P3.** Desde el comienzo de P3 avanzas **3500 - 3072 = 428 bytes**.

| Dato | Cómo entenderlo |
| --- | --- |
| Tamaño de página: 1024 bytes | Lo fijaste al elegir 1 KB |
| Página: 3 | La posición 3500 pertenece a P3 |
| Offset: 428 | El dato está 428 bytes después del comienzo de P3 |
| P3 está en marco 2 | Lo decidieron las cargas de la página anterior |
| Dirección física: 2476 | El marco 2 empieza en 2048; 2048 + 428 = 2476 |

**Dirección lógica 3500:** posición desde la que Chrome identifica el dato. **Dirección física simulada 2476:** posición donde lo localizas en la RAM del escenario. Ambas se refieren al mismo dato. El offset es cuánto avanzas dentro de la página.

### Ahora, un dato cuya página salió de RAM

1. En la misma pantalla, cambia la dirección a **100** y vuelve a traducir.
2. Debe mostrar **P0**, **offset 100** y **sin marco**. P0 salió por FIFO; por eso no hay dirección física disponible.
3. Ve a **Paginación**, comprueba escenario y Chrome. En Automático solicita **Página 0, DISCO** una vez.
4. Sale **P1** y entra **P0 al marco 1**: era la siguiente víctima de FIFO.
5. Vuelve a **Traducción de direcciones**, selecciona Chrome y traduce **100** otra vez. Ahora obtienes **1124**, porque el marco 1 empieza en 1024 y sumas 100.

La misma dirección lógica puede terminar en otro lugar de RAM si cambia la ubicación de su página. Por eso consultas una tabla y no memorizas un marco.

**Qué no hace esta calculadora:** no carga la página ni registra otro acceso de CPU. Solo consulta la tabla. La carga del paso 3 sí cuenta: llevas siete solicitudes, seis Faults y un Hit. Comprueba **Page Faults: 6** en Inicio.

<!-- pagebreak -->

## 5. Después practica Segmentación

**Nueva idea:** ahora dividirás un programa en partes de distinto tamaño, como Código y Datos. Cada parte tiene un comienzo, llamado **base**, y un tamaño que también funciona como **límite**.

Este ejercicio utiliza otro escenario. **No cambies la configuración del anterior.** Abre **Segmentación**; en **Nuevo escenario** escribe **Nombre: Mi práctica segmentos 01**, **RAM total (KB): 16** y pulsa **Crear escenario**. Anota su #ID.

En **Crear proceso**, escribe **Nombre: Editor**, **Tamaño del proceso (KB): 4** y pulsa **Crear proceso**. Aquí el proceso se crea en esta misma pantalla. No tienes que poner tamaño de página ni secundaria en este formulario.

En **Agregar segmento**, crea las siguientes filas, una por una. Las bases y tamaños se escriben en **bytes**, sin KB.

| Nombre | Base (bytes) | Tamaño / límite (bytes) | Posiciones que ocupa |
| --- | --- | --- | --- |
| Código | 1000 | 1200 | 1000 a 2199 |
| Datos | 4000 | 800 | 4000 a 4799 |
| Stack | 7000 | 600 | 7000 a 7599 |
| Heap | 9000 | 1000 | 9000 a 9999 |

Pulsa **Agregar segmento** después de cada fila. El número de segmento se asigna solo. Las regiones deben caber en la RAM y no pisarse entre sí. Código empieza en 1000 y tiene 1200 posiciones: termina en 2199 porque se cuenta también la primera.

**Qué debes ver:** cuatro segmentos activos. Ocupan **3600 bytes** de **16384**; quedan **12784 libres**. Declarar Editor de 4 KB no reserva automáticamente esos 4096 bytes en RAM: aquí ocupan espacio los segmentos que agregaste. Los espacios libres entre ellos son huecos.

### Comprueba qué accesos están permitidos

En **Direccionamiento por segmentación**, selecciona **0 · Código · límite 1200**. Prueba cada offset y pulsa **Acceder**.

| Offset en bytes | Resultado esperado | Por qué |
| --- | --- | --- |
| 100 | SEGMENT_ACCESS; dirección 1100 | 100 es menor que 1200; sumas 1000 + 100 |
| 1199 | SEGMENT_ACCESS; dirección 2199 | Es el último desplazamiento permitido |
| 1200 | SEGMENTATION_FAULT | Queda una posición fuera del segmento |

**Regla:** el offset debe ser cero o positivo y menor que el tamaño; después suma base y offset. El rechazo no borra los segmentos ni cierra automáticamente Editor en la app. Aunque haya RAM libre al lado, ese espacio no pertenece a Código.

<!-- pagebreak -->

## 6. Al final compara y repasa

Abre **Comparación de asignación**. Esta pantalla trae un ejemplo fijo: hay **9 KB libres**, separados en huecos de **3 KB y 6 KB**. Cada cálculo es independiente y no cambia tus dos escenarios.

En **Tamaño solicitado en bytes**, escribe **8192** y pulsa **Comparar asignación**.

| Técnica | Qué debería pasar | Idea que demuestra |
| --- | --- | --- |
| Contigua | Rechazada | Pide 8 KB juntos; el mayor hueco solo mide 6 KB |
| Paginación | Aceptada | Reparte páginas entre los marcos disponibles |
| Segmentación | Aceptada en este ejemplo | Código de 5 KB y Datos de 3 KB caben en los dos huecos |

Tener suficiente espacio al sumar los huecos no garantiza tener una región continua del tamaño solicitado. Esa separación del espacio libre ilustra la **fragmentación externa**. Que segmentación acepte aquí depende del tamaño de sus partes y de estos huecos.

Después escribe **7500** y compara otra vez. Paginación usa ocho páginas de 1024: reserva **8192 bytes**, y quedan **692** sin usar dentro de la última página. Ese sobrante es **fragmentación interna**. En este caso, segmentación reserva **7500**, sin ese relleno. Contigua sigue rechazada porque 7500 supera el hueco de 6144 bytes.

### Qué menú usar para cada pregunta

| Quiero... | Abro... |
| --- | --- |
| Definir el tamaño de mi experimento de paginación | Configuración de memoria |
| Crear Chrome y sus páginas | Procesos |
| Cargar páginas, obtener Hit/Fault y ver FIFO | Paginación |
| Localizar un dato usando la tabla actual | Traducción de direcciones |
| Crear regiones y probar base, límite y offset | Segmentación |
| Entender huecos y sobrantes | Comparación de asignación |
| Revisar lo que hice y los resultados | Historial, seleccionando el escenario |

Para repetir desde el principio, crea **Mi práctica 02** y otro escenario de segmentos. Así conservas lo anterior y empiezas con contadores nuevos. No hace falta volver a ejecutar seeders. Deja **Demostración**, **Demanda de memoria** y **Terminal educativa** para después de dominar este recorrido.

### Qué guía usar ahora y cuál para exponer

Esta guía es para practicar desde la app vacía y entender lo que haces. El archivo **docs/guion-exposicion-60-minutos.md** y su PDF son el guion de una hora con turnos y texto para los cinco integrantes. Usa ese guion y el PowerPoint de 24 diapositivas después de practicar aquí. La secuencia de cargas y los cálculos son los mismos.

Preparado el 21 de octubre de 2026 a partir de las pantallas y reglas instaladas de MemoryLab. Abrir este documento no crea datos: tú completas los formularios.
