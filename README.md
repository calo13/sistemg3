# MemoryLab

Simulador educativo de administración de memoria — Universidad Mariano Gálvez, Ingeniería en Sistemas, Sistemas Operativos 1, Grupo 3.

## Estado actual: Fases 0–31 y manual adicional completos

Laravel 12, Jetstream 5, Livewire 3, Bootstrap 5.3.8, Sneat Free 2.0.0 y MySQL/MariaDB. Están disponibles login, logout, registro configurable, perfil, cambio y recuperación de contraseña. La autenticación utiliza las tarjetas y estilos oficiales de Sneat, con textos en español y controles para mostrar u ocultar contraseñas. El panel mantiene menú responsive, navbar y footer. La Fase 6 incorpora Administrador, Operador y Observador mediante laravel-permission, con una pantalla protegida para administrar roles. La Fase 7 presenta el dashboard académico de la Universidad Mariano Gálvez, con los cinco integrantes, ocho indicadores y la barra de utilización de RAM. La Fase 8 incorpora las siete tablas del dominio, modelos Eloquent y restricciones MySQL. La Fase 9 permite configurar memoria por escenario y consultar sus estadísticas en el dashboard. La Fase 10 incorpora la creación y consulta de procesos, con sus páginas iniciales y reserva de capacidad secundaria. La Fase 11 reúne procesos, ubicación de páginas, RAM, almacenamiento secundario y última solicitud de CPU en el simulador de paginación. Las consultas utilizan snapshots de lectura; las acciones de CPU y resolución se incorporan en las Fases 14 y 15.

La Fase 12 completa la tabla de páginas con las columnas Página, Marco, Presente y Estado. Muestra el número del marco asignado, «Sí» y RAM para páginas presentes; «—», «No» y DISCO para las ausentes. La tabla utiliza el mismo snapshot del simulador, sin consultas o asignaciones adicionales, y conserva la paginación de 15 filas. Evidencia en [docs/evidencias/fase-12.md](docs/evidencias/fase-12.md).

La Fase 13 incorpora el mapa visual de RAM: bloques numerados con marco, proceso, página y estado. Gris identifica marcos libres, azul los ocupados y el borde morado destaca las páginas del proceso elegido. El mapa muestra todos los procesos del escenario y pagina 64 marcos por vez. Secundaria identifica las páginas ausentes del proceso seleccionado, en grupos de 15. Ambos paneles utilizan el mismo snapshot de lectura. Evidencia en [docs/evidencias/fase-13.md](docs/evidencias/fase-13.md).

La Fase 14 incorporó el registro de solicitudes CPU: Administrador y Operador seleccionan una página y el servidor crea PAGE_REQUEST con su actor y contexto. En esa entrega el acceso quedaba pendiente, sin cargar páginas ni registrar Hit/Fault. Su evidencia histórica está en [docs/evidencias/fase-14.md](docs/evidencias/fase-14.md). La Fase 15 completa la resolución: una página presente genera PAGE_HIT; una ausente genera PAGE_FAULT y PAGE_LOADED, toma el menor marco libre o reemplaza por FIFO cuando RAM está llena. La petición y resolución automáticas son transaccionales. Suite final: 285 pruebas aprobadas, una omitida condicional y 1.265 assertions. Evidencia en [docs/evidencias/fase-15.md](docs/evidencias/fase-15.md).

La Fase 16 incorpora los modos Automático y Paso a paso. Un Hit recorre cuatro pasos y un Fault ocho: en el recorrido manual, la resolución real ocurre en el paso 4 o 6 respectivamente. Los pasos posteriores explican el resultado ya guardado. La animación automática resalta la secuencia y el marco afectado después de resolver en servidor, y respeta la preferencia de movimiento reducido. Se aprobaron 13 pruebas específicas y 52 regresiones; compilación y navegador verificados. Evidencia en [docs/evidencias/fase-16.md](docs/evidencias/fase-16.md).

La Fase 17 permite traducir direcciones lógicas en bytes mediante la tabla actual: página = floor(dirección / tamaño de página), offset = dirección % tamaño de página y dirección física = marco × tamaño de página + offset. Los tres roles consultan el cálculo, sin cargar páginas ni registrar accesos. Una página ausente no tiene dirección física; el rango válido termina en el último byte real del proceso, excluyendo el relleno de la última página. Se aprobaron 29 pruebas y navegador. Evidencia en [docs/evidencias/fase-17.md](docs/evidencias/fase-17.md).

La Fase 18 incorpora escenarios de segmentación, procesos y segmentos con número, nombre, base, tamaño y estado. La tabla corresponde al proceso seleccionado y el mapa muestra todos los intervalos del escenario, incluidos espacios libres. Se rechazan superposiciones, intervalos fuera de RAM y segmentos que exceden el tamaño del proceso. El dashboard utiliza segmentos activos y muestra «No aplica» en marcos y fallos de página. Se aprobaron 30 pruebas específicas y siete regresiones de autenticación. Evidencia en [docs/evidencias/fase-18.md](docs/evidencias/fase-18.md).

La Fase 19 incorpora el acceso por segmento y offset. Si offset es menor que el tamaño, calcula base + offset y registra SEGMENT_ACCESS; cuando offset alcanza o supera el tamaño, muestra SEGMENTATION FAULT y registra el rechazo sin dirección física. El último offset válido es tamaño − 1. Ambos resultados conservan los intervalos de RAM; un acceso válido pasa proceso y escenario a RUNNING. Se aprobaron 32 pruebas específicas, incluida la selección con el segmento cero liberado, y 30 regresiones. Evidencia en [docs/evidencias/fase-19.md](docs/evidencias/fase-19.md).

La Fase 20 compara asignación contigua, paginación y segmentación mediante un ejemplo calculado de 16 KB con dos huecos de 3 KB y 6 KB. Permite variar la solicitud y explica aceptación, reserva, fragmentación interna e intervalos finales. El cálculo se realiza en servidor y conserva los escenarios guardados. La comparación y regresión de accesos aprobaron 69 pruebas y 1.572 assertions; navegador verificado para los tres roles. Evidencia en [docs/evidencias/fase-20.md](docs/evidencias/fase-20.md).

La Fase 21 agrega Simular alta demanda: crea un lote de procesos ficticios en el escenario elegido y solicita sus páginas en orden mediante los servicios existentes. Limita cada ejecución a ocho procesos y 64 solicitudes, guarda el lote en una transacción y muestra métricas antes/después y evolución de ocupación. La consulta incluye Page Faults acumulados y secundaria, y se actualiza cada cinco segundos mientras está visible. Se aprobaron 35 pruebas y 153 assertions; navegador verificado. Evidencia en [docs/evidencias/fase-21.md](docs/evidencias/fase-21.md).

La Fase 22 incorpora demostración y reinicio explícito. Preparar una demo crea dos escenarios nuevos con valores conocidos para Hit, Fault y Segmentation Fault; reiniciarla finaliza el par anterior y prepara otro conservando su historial. Liberar un escenario finaliza sus procesos y retira sus asignaciones, conservando configuración y eventos. Se aprobaron 75 pruebas y 364 assertions, más la verificación de navegador de los tres roles. Evidencia en [docs/evidencias/fase-22.md](docs/evidencias/fase-22.md).

La Fase 23 incorpora el historial para Administrador, con filtros por escenario, proceso, actor, tipo de evento y fechas de Guatemala. Muestra 20 eventos por página, ordenados por fecha e ID descendentes; consultar y filtrar conserva la memoria y los eventos. Se aprobaron 36 pruebas y 213 assertions, más la verificación de navegador. Evidencia en [docs/evidencias/fase-23.md](docs/evidencias/fase-23.md).

La Fase 24 incorpora una terminal educativa con help, memory status, process list, page table, request y reset. Los comandos se interpretan mediante una lista cerrada y actúan sobre el escenario seleccionado: las consultas conservan datos; request ejecuta el acceso de paginación autorizado y reset finaliza procesos y libera asignaciones con sus permisos. La terminal y sus regresiones aprobaron 96 pruebas y 703 assertions, más navegador. Evidencia en [docs/evidencias/fase-24.md](docs/evidencias/fase-24.md).

La Fase 25 incorpora /presentation para exponer paginación con cinco paneles, Page Faults y flujo de CPU, reutilizando los componentes del simulador en un layout sin administración. Los resultados persistidos permiten que Observador vea por polling el Hit/Fault ejecutado desde otra cuenta; reset limpia esa presentación sin borrar la historia. Presentación y regresiones aprobaron 79 pruebas y 515 assertions; navegador verificado de 320 a 1920 píxeles. Evidencia en [docs/evidencias/fase-25.md](docs/evidencias/fase-25.md).

La Fase 26 incorpora MemoryDemoSeeder, separado de DatabaseSeeder. Su ejecución explícita requiere un Administrador existente y prepara una pareja base de demostración; repetirlo conserva la memoria y el historial, incluso si la pareja fue liberada o finalizada. No crea cuentas ni modifica contraseñas. Sus 19 pruebas aprobaron 99 assertions; la ejecución local y su repetición conservaron las filas anteriores, cuentas y catálogo, y la segunda ejecución no produjo cambios. Evidencia en [docs/evidencias/fase-26.md](docs/evidencias/fase-26.md).

La Fase 27 cierra la suite integral con **655 pruebas aprobadas, una omisión condicional y 4.575 aserciones**, en 96,78 segundos. Pint, sintaxis de 117 archivos PHP, compilación Vite, validación de Composer y 14 rutas propias se verificaron. La auditoría conserva las cuentas y datos locales, sin fixtures residuales. Evidencia en [docs/evidencias/fase-27.md](docs/evidencias/fase-27.md).

La Fase 28 documenta el despliegue local comprobado en Apache y prepara una guía y ejemplos para Linux con Apache/Nginx, PHP y MySQL. VirtualBox/VMware es opcional según la solicitud; no se ha provisionado una VM ni publicado un servidor externo. Guía en [docs/despliegue.md](docs/despliegue.md) y evidencia en [docs/evidencias/fase-28.md](docs/evidencias/fase-28.md).

La Fase 29 completa el informe técnico de nueve páginas, con fundamentos, arquitectura, resultados y conclusiones; su [evidencia](docs/evidencias/fase-29.md) registra la revisión del PDF. La Fase 30 completa la presentación académica de doce diapositivas, con comprobación nativa y revisión de sus doce renders finales; [evidencia de presentación](docs/evidencias/fase-30.md). La Fase 31 completa el video de **180 segundos**, WebM VP9 a 1280 × 720, con subtítulos en español y demostraciones capturadas de la aplicación real; [evidencia de video](docs/evidencias/fase-31.md). Las fases originales están completas. La entrega adicional incorpora el [manual de uso](docs/manual-de-uso.md) de **ocho páginas**, con cuatro capturas, ejercicios, resultados esperados y un guion de exposición de 14 minutos; [evidencia del manual](docs/evidencias/manual-de-uso.md).

El usuario autorizó continuar todas las fases sin pedir aprobación entre ellas. Esta instrucción posterior sustituyó la pausa por autorización de cada fase establecida en la solicitud inicial. Se conservó el orden del plan y la evidencia de cada entrega, incluidos los entregables académicos de las Fases 29–31 y el manual solicitado después. El avance se registra en [docs/avance.md](docs/avance.md), y las instrucciones reales, decisiones y correcciones asistidas por IA en [docs/uso-ia.md](docs/uso-ia.md).

El modelo de memoria organiza cada simulación por escenario: configuración, procesos, páginas, marcos, segmentos y eventos. Guarda tamaños y direcciones en bytes enteros; una unidad mostrada como KB corresponde a 1024 bytes. Los marcos calculados y la presencia de páginas se derivan de la configuración y la asignación física, sin contadores o banderas duplicados. Las claves foráneas compuestas impiden relacionar procesos y marcos de escenarios distintos. La migración admite MySQL 8.0.16 o superior y MariaDB 10.4.3 o superior, con drivers `mysql` o `mariadb`, InnoDB y restricciones CHECK activas. El mínimo de MariaDB preserva también la validación automática del campo JSON. Son mínimos de compatibilidad; para producción debe utilizarse una versión mantenida por el proveedor. El diseño y sus límites están documentados en [docs/modelo-memoria.md](docs/modelo-memoria.md), y la verificación histórica con MySQL en [docs/evidencias/fase-8.md](docs/evidencias/fase-8.md).

Los datos de universidad, carrera, curso, grupo, nombres y carnés están centralizados en `config/memorylab.php`; no representan cuentas de usuario ni se insertan mediante seeders. Los carnés son cadenas de texto para conservar ceros iniciales. Si cambias esa configuración con caché activa, ejecuta `php artisan config:clear` con el PHP del entorno.

El dashboard conserva las tarjetas y componentes de Sneat. `DashboardStats` consulta el escenario seleccionado en la sesión mediante `ActiveScenarioService` y obtiene sus valores de `MemoryStatisticsService`. Sin un escenario compatible configurado, los ocho indicadores muestran «—» y la barra indica «Sin datos». En paginación muestra RAM, marcos, procesos activos y fallos registrados; los marcos ocupados se calculan mediante su página asignada. En segmentación suma los tamaños de segmentos ACTIVE y presenta «No aplica» para los tres indicadores de marcos y Page Faults. Valida límites y superposiciones antes de calcular utilización. Actualiza los datos cada cinco segundos mientras está visible. Las consultas se limitan al escenario elegido; no se selecciona automáticamente el último escenario.

**Configuración de memoria:** abre `http://localhost/sistemg3/public/memoria/configuracion` o el enlace del menú. Un Administrador puede crear un escenario de paginación con RAM total, tamaño de página y almacenamiento secundario en KB enteros; el número de marcos se calcula automáticamente. El formulario propone 16 KB de RAM, páginas de 1 KB y 64 KB de almacenamiento secundario sin guardar datos hasta enviar el formulario. Cada KB equivale a 1024 bytes. La capacidad secundaria puede ser cero. Los límites operativos están en `config/memorylab.php`: 65.536 KB por capacidad y 1.024 marcos; la RAM debe ser divisible entre el tamaño de página.

El guardado autoriza con los permisos actuales, bloquea el escenario y persiste configuración, marcos numerados desde cero e historial en una transacción. Guardar los mismos valores conserva los marcos y evita duplicar eventos. Las capacidades solo cambian en escenarios borrador/listos sin procesos, páginas ni segmentos; el historial se conserva. Operador y Observador pueden seleccionar y consultar escenarios compartidos. La selección corresponde a la sesión y no cambia el dashboard de otros usuarios. Las capturas y verificaciones están en [docs/evidencias/fase-9.md](docs/evidencias/fase-9.md).

**Procesos:** abre `http://localhost/sistemg3/public/procesos`, selecciona un escenario y captura nombre y tamaño en KB. Administrador y Operador pueden crear; Observador consulta el listado y sus estados. El proceso inicia en READY y requiere `ceil(tamaño / página)` páginas. Por ejemplo, 3 KB con páginas de 2 KB genera dos páginas, numeradas 0 y 1. Todas comienzan sin marco en almacenamiento secundario simulado, por lo que reservan 4 KB en ese ejemplo y todavía no consumen RAM. El dashboard aumenta su contador de procesos activos.

`ProcessManagerService` comprueba permisos actuales, memoria y marcos consistentes, estado READY/RUNNING y espacio secundario suficiente. Crea proceso, páginas y evento PROCESS_CREATED en una transacción con el mismo bloqueo de escenario que la configuración. La reserva considera páginas completas sin marco de ese escenario, incluidas las de procesos terminados que aún persistan; con secundaria cero no se puede preparar un proceso en esta fase. Un proceso puede superar el tamaño de la RAM si cabe en secundaria. Los límites operativos son 65.536 KB por proceso, 1.024 páginas por proceso y 100 procesos totales por escenario, incluidos los finalizados. El listado pagina de 15 en 15 y se actualiza cada cinco segundos mientras es visible. La evidencia está en [docs/evidencias/fase-10.md](docs/evidencias/fase-10.md).

**Paginación:** abre `http://localhost/sistemg3/public/paginacion` o Paginación en el menú. Los tres roles consultan cinco paneles simultáneos: proceso, tabla de páginas con marco/presencia/estado, memoria RAM del escenario, secundaria simulada y última solicitud de CPU del proceso. Selecciona el escenario y después un proceso; Livewire actualiza los datos sin recargar la página. No se selecciona un proceso automáticamente. Las capacidades globales y los datos del proceso aparecen identificados por separado.

`PagingService` recarga al actor y exige `memory.view`, `tables.view` y `simulations.view` para consultar. Obtiene un snapshot transaccional, comprueba configuración y marcos consistentes, y rechaza procesos de otro escenario. La tabla muestra 15 páginas por vez; los parámetros inválidos se normalizan y cambiar de proceso o escenario reinicia la paginación. La consulta se actualiza cada cinco segundos mientras está visible y conserva páginas, marcos, estados e historial. CPU permite a Administrador y Operador acceder con `pages.request` y `simulations.execute`; Observador conserva su consulta. El resultado indica Hit/Fault, marco, dirección inicial y víctima FIFO cuando corresponde. La última solicitud se obtiene de PAGE_REQUEST, ordenado por fecha e ID, y su fecha se presenta en Guatemala desde UTC. La evidencia de la pantalla inicial se conserva en [docs/evidencias/fase-11.md](docs/evidencias/fase-11.md).

`resolveRequest` exige el autor original, valida las referencias del evento y conserva un resultado terminal vinculado a `request_event_id`: resolver otra vez la misma petición devuelve ese resultado sin nuevos cambios o eventos. FIFO utiliza `pages.loaded_at` en UTC con microsegundos; un Hit conserva la fecha de carga y una expulsión la limpia. Cargar una página libera su espacio secundario; si se expulsa una víctima, esta ocupa ese mismo espacio. El escenario y proceso accedido pasan a RUNNING, y las estadísticas cuentan los fallos persistidos.

En CPU, selecciona Automático para completar el acceso y observar la animación, o Paso a paso para avanzar con Siguiente paso. Hasta el paso 5 de un Fault la asignación permanece intacta; el paso 6 carga la página de forma atómica. En un Hit, el acceso se resuelve en el paso 4. El servidor vuelve a consultar la presencia al resolver y adapta el recorrido si otro acceso cambió la memoria. Cambiar página o modo, o cerrar el recorrido, limpia su presentación local y conserva los eventos registrados. Observador consulta la memoria y última solicitud mediante polling; el progreso de los pasos corresponde al componente de quien realiza el acceso.

**Traducción de direcciones:** abre Traducción en el menú, selecciona escenario y proceso, introduce una dirección lógica entera en bytes y pulsa Traducir dirección. La pantalla desglosa página, offset, marco y dirección física. Si la página sigue en secundaria, solicita su acceso desde Paginación y vuelve a calcular. El resultado representa la asignación consultada en ese momento; traducir conserva memoria e historial.

**Segmentación:** abre `http://localhost/sistemg3/public/segmentacion`. Administrador crea un escenario con RAM en KB; Administrador y Operador crean procesos y agregan segmentos mediante base y tamaño en bytes. Crear un proceso no reserva RAM. Cada segmento ocupa [base, base + tamaño), puede ser adyacente a otro y debe quedar dentro de RAM. La suma de segmentos activos no supera el tamaño del proceso. Hay un límite de 16 segmentos por proceso y 1.024 por escenario, incluidos liberados; el escenario admite 100 procesos. Los tres roles consultan tabla y mapa global. La asignación se guarda con bloqueo del escenario y no crea páginas ni marcos.

En Direccionamiento por segmentación, Administrador y Operador seleccionan un segmento activo, introducen el offset en bytes y pulsan Acceder. La pantalla compara el offset con el límite antes de sumar la base. Un segmento de 1.200 bytes acepta 0 a 1.199; el offset 1.200 produce SEGMENTATION FAULT. Un número inexistente o liberado y una entrada negativa se rechazan como errores de validación, sin registrar un acceso. Observador conserva la consulta de tabla y mapa.

**Comparación:** abre Comparación en el menú e introduce entre 1 y 16.384 bytes. La pantalla muestra tres mapas y una tabla técnica desde el mismo ejemplo de memoria. Con 7.168 bytes, contigua se rechaza porque el mayor hueco mide 6.144; paginación y la partición ilustrativa de segmentos encajan. Con 7.500 bytes, paginación reserva 8.192 y deja 692 sin utilizar en su última página. Este ejemplo exige cargar la solicitud completa en RAM; la simulación de paginación por demanda conserva su propio contrato.

**Alta demanda simulada:** selecciona un escenario de paginación listo o en ejecución e indica cantidad de procesos y tamaño en KB. Administrador y Operador ejecutan el lote; Observador consulta seis indicadores actuales con actualización cada cinco segundos. Cada proceso admite hasta 64 KB, pero el conjunto no puede requerir más de 64 páginas. Primero se reserva el lote completo en secundaria: si esa reserva o un acceso falla, se revierte toda la ejecución. Al llenarse los marcos, los accesos aplican FIFO. La operación agrega datos del simulador y no inicia procesos del sistema operativo ni reserva grandes bloques de RAM física.

**Demostración:** abre `http://localhost/sistemg3/public/demostracion`. Administrador prepara o reinicia el par; los tres roles pueden abrir demostraciones guardadas y Operador ejecuta sus accesos. En Chrome, P0 inicia presente y P3 ausente; en Editor, Código tiene base 1000 y límite 1200. Offset 100 calcula 1100 y offset 1200 produce fallo. Liberar memoria exige seleccionar el escenario y pulsar Finalizar procesos y liberar memoria; mantiene configuración, marcos, procesos e historial, y registra MEMORY_RESET. Los fallos anteriores siguen contando en el historial acumulado.

**Historial:** Administrador abre `http://localhost/sistemg3/public/historial`. Puede combinar escenario, proceso del escenario, usuario, tipo de evento y fechas inicial/final. Las fechas incluyen todo el día local y los eventos guardan sus instantes en UTC. Limpiar filtros recupera la consulta completa; cambiar filtros vuelve a la primera página. Operador y Observador no tienen acceso a esta pantalla.

**Terminal educativa:** abre `http://localhost/sistemg3/public/terminal`, selecciona un escenario y escribe help. memory status y process list consultan su estado; page table Chrome muestra la tabla y request Chrome 3 realiza el acceso a P3 en paginación. Se aceptan nombres con espacios, por ejemplo request VS Code 0, o #ID para distinguir nombres repetidos. Administrador puede ejecutar reset para finalizar procesos y liberar asignaciones conservando configuración e historial; Observador utiliza las consultas. Limpiar consola elimina solo la salida de la pantalla.

**Modo presentación:** abre `http://localhost/sistemg3/public/presentation`, selecciona escenario de paginación y proceso, y utiliza Pantalla completa para la exposición. Mantiene CPU, tabla, RAM y secundaria con la ocupación global y Page Faults. Administrador y Operador conservan sus accesos; Observador consulta el resultado guardado y su flujo. Preparar o liberar escenarios corresponde a los módulos habituales.

**Entregables académicos:** abre `http://localhost/sistemg3/public/entregables` o utiliza el enlace del menú. Empieza por el **Manual de uso** de ocho páginas, que guía la secuencia de la práctica con conceptos, pantallas y resultados esperados; el **Informe técnico** de nueve páginas documenta fundamentos, arquitectura y resultados del proyecto. También puedes descargar la presentación PowerPoint de doce diapositivas y el video con subtítulos de tres minutos, o reproducir este último en la página. Los cuatro archivos están completos y disponibles. Los tres roles acceden con sus cuatro permisos de consulta. Las descargas y la reproducción utilizan rutas autenticadas con archivos permitidos explícitamente; Apache impide el acceso directo a `output`. La integración aprobó **27 pruebas y 243 aserciones**, y la revisión final de navegador confirmó descargas, reproducción y pantallas de 320 a 1366 píxeles. Evidencia en [docs/evidencias/entregables.md](docs/evidencias/entregables.md).

**Seeder de demostración:** MemoryDemoSeeder se invoca de forma explícita con `php artisan db:seed --class=MemoryDemoSeeder`. Utiliza el Administrador existente de menor ID; si no existe uno o la pareja base es parcial, ambigua o de modos incorrectos, rechaza la operación conservando datos. Los escenarios manuales con esos nombres se conservan y no se interpretan como demo. DatabaseSeeder mantiene únicamente el catálogo de roles/permisos; no prepara esta pareja automáticamente. La pareja base ya se preparó en la instalación local y repetir el seeder se verificó sin cambios.

El menú incluye Inicio, Integrantes, Configuración de memoria, Procesos, Paginación, Traducción, Segmentación, Comparación y Mi perfil; los módulos requieren sus permisos y Usuarios y roles aparece con el permiso correspondiente. Integrantes lleva al equipo desde cualquier pantalla del layout. La navbar identifica UMG y permite consultar el rol en el menú de cuenta. Se mantienen el cierre de sesión por POST con CSRF y los créditos de ThemeSelection. La evidencia y capturas académicas están en [docs/evidencias/fase-7.md](docs/evidencias/fase-7.md).

En Windows se utiliza el PHP de XAMPP (`C:\xampp\php\php.exe`) tanto para Composer como para Artisan. Esto evita utilizar por accidente el PHP diferente de `C:\PHP`.

```powershell
& 'C:\xampp\php\php.exe' -d extension=zip 'C:\composer\composer.phar' install
Copy-Item .env.example .env # Solo en una instalación nueva, si .env no existe.
& 'C:\xampp\php\php.exe' artisan key:generate # Solo en una instalación nueva.
```

Configura `DB_PASSWORD` únicamente en `.env`. El entorno local verificado durante las fases utiliza MySQL 8.0.44 en `127.0.0.1:3306`; el MariaDB incluido con XAMPP no se utilizó en esas verificaciones. En otra instalación se admite MySQL >= 8.0.16 o MariaDB >= 10.4.3, incluida la versión `10.6.28-MariaDB` informada por el hosting. Puede conservarse `DB_CONNECTION=mysql` al conectar a MariaDB, o utilizar `mariadb`; ambos requieren `pdo_mysql`. `memorylab:database` comprueba la conexión, el motor y su versión mínima. [Laravel 12](https://laravel.com/framework/docs/12.x/database) admite MariaDB desde 10.3; MemoryLab exige 10.4.3 para conservar la validación automática de JSON descrita en las [notas de esa versión](https://mariadb.com/docs/release-notes/community-server/old-releases/10.4/10.4.3), además de las [restricciones CHECK](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint).

```powershell
& 'C:\xampp\php\php.exe' artisan memorylab:database --create
& 'C:\xampp\php\php.exe' artisan memorylab:database
& 'C:\xampp\php\php.exe' artisan migrate --path=database/migrations/0001_01_01_000000_create_users_table.php
& 'C:\xampp\php\php.exe' artisan migrate --path=database/migrations/2026_10_07_131055_create_permission_tables.php
& 'C:\xampp\php\php.exe' artisan db:seed --class=RolesAndPermissionsSeeder
& 'C:\xampp\php\php.exe' artisan migrate --path=database/migrations/2026_10_07_160000_create_memory_simulation_tables.php
& 'C:\xampp\php\php.exe' artisan migrate --path=database/migrations/2026_10_07_220000_add_loaded_at_to_pages_table.php
npm.cmd ci
npm.cmd run build
& 'C:\xampp\php\php.exe' artisan serve --host=127.0.0.1 --port=8000
```

El primer comando crea únicamente la base configurada si no existe. Ninguno ejecuta migraciones. No utilizar `migrate:fresh` sobre bases con información.

Abre `http://127.0.0.1:8000`. Con Apache de XAMPP, la ruta local es `http://localhost/sistemg3/public/`. En un despliegue, el DocumentRoot debe apuntar a la carpeta `public`.

Las sesiones utilizan la base de datos configurada; caché utiliza archivos y la cola es síncrona. En el entorno MySQL local se ejecutaron las migraciones de autenticación, permisos y dominio: `users`, `password_reset_tokens`, `sessions`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `scenarios`, `memory_configurations`, `processes`, `memory_frames`, `pages`, `segments` y `simulation_events`, además de la tabla de seguimiento `migrations`. Las siete tablas del dominio estaban vacías al completar la Fase 8: no se cargó un escenario de demostración. En esa instalación, las migraciones de caché y cola permanecieron pendientes porque esos drivers no se utilizan.

### Continuar la instalación en un hosting MariaDB

Si la versión anterior rechazó `10.6.28-MariaDB` antes de crear las tablas del dominio y todavía no se han creado escenarios, actualiza el código desde la carpeta del proyecto y continúa las migraciones pendientes:

```sh
git pull --ff-only origin main
php artisan config:clear
php artisan migrate --seed --force
```

Conserva `.env`, `APP_KEY` y los datos existentes. No uses `migrate:fresh` ni rollback para resolver ese rechazo. `--force` permite ejecutar las migraciones en producción; `--seed` invoca `DatabaseSeeder`, que prepara únicamente roles y permisos, sin crear cuentas ni demostraciones. La comprobación `php artisan memorylab:database` acepta ambos motores compatibles y valida sus mínimos. Los pasos completos del servidor están en [docs/despliegue.md](docs/despliegue.md).

Puedes crear tu cuenta en `http://localhost/sistemg3/public/register` e iniciar sesión en `http://localhost/sistemg3/public/login`. No se generaron cuentas ni contraseñas predeterminadas.

Los registros nuevos reciben Observador en la misma transacción que crea la cuenta. El seeder crea los roles y permisos con el guard `web`, conserva los roles ya asignados y agrega Observador a las cuentas sin rol. Puede repetirse sin crear cuentas ni duplicar el catálogo.

| Rol | Permisos del simulador |
| --- | --- |
| Administrador | Todos: usuarios, configuración, escenarios, simulaciones, reinicio, historial, procesos, páginas, segmentación y consultas. |
| Operador | Crear procesos, ejecutar simulaciones, solicitar páginas, ejecutar segmentación y consultar memoria, tablas, simulaciones y resultados. |
| Observador | Consultar memoria, tablas, simulaciones y resultados. |

El catálogo y los módulos del simulador aplican la autorización en servidor. El perfil personal sigue disponible para cualquier cuenta autenticada.

Para asignar el primer Administrador, registra tu cuenta y ejecuta este comando local con su correo real:

```powershell
& 'C:\xampp\php\php.exe' artisan memorylab:user-role "tu-correo@ejemplo.com" administrador
```

El comando acepta `administrador`, `operador` u `observador` y requiere una cuenta existente. No crea usuarios ni cambia contraseñas. No se elige automáticamente un administrador. Después de asignarlo, aparecerá **Usuarios y roles** en su menú, en `http://localhost/sistemg3/public/administracion/usuarios`. Esta ruta y sus acciones Livewire comprueban permisos en servidor. Un administrador puede cambiar el rol de otras cuentas; su propio rol no se modifica desde esta pantalla. Los cambios se serializan con un bloqueo de fila y se impide degradar al último administrador, incluso desde el comando local.

La evidencia de esta fase está en [docs/evidencias/fase-6.md](docs/evidencias/fase-6.md).

Para desactivar el registro público, establece `AUTH_REGISTRATION_ENABLED=false` en `.env` y ejecuta `php artisan optimize:clear` usando el PHP de tu entorno. Tanto GET como POST de registro quedan deshabilitados. Login y recuperación siguen disponibles.

El correo local utiliza `MAIL_MAILER=log`: las notificaciones de recuperación se guardan en `storage/logs/laravel.log`. Para enviarlas a un buzón real, configura SMTP mediante las variables `MAIL_*` en `.env`. No publicar esos logs ni sus enlaces de recuperación.

Las vistas oficiales de Jetstream se publicaron de forma controlada, conservando las acciones y componentes de autenticación. No se instaló Tailwind, Sanctum, Teams ni Inertia. Se utiliza el guard de sesión `web`; API, eliminación de cuenta, 2FA y passkeys están desactivados. Login, registro, recuperación, restablecimiento y confirmación de contraseña adaptan las plantillas básicas de Sneat. El registro conserva su confirmación de contraseña y respeta `AUTH_REGISTRATION_ENABLED`; su enlace en login desaparece cuando está desactivado. Perfil, cambio de contraseña y sesiones utilizan tarjetas Bootstrap/Sneat y mantienen sus acciones Livewire.

Bootstrap se instala desde npm y su JavaScript se compila con Vite. Su CSS se compila una sola vez mediante el core SCSS oficial de Sneat, desde `resources/scss/app.scss`. Popper permite los componentes que requieren posicionamiento. Los plugins están disponibles mediante los atributos `data-bs-*` y `window.bootstrap`; tooltips y popovers requieren inicialización explícita cuando se utilicen. No se utiliza jQuery ni un CDN para estos assets.

Los estilos y módulos oficiales de Sneat se conservan en `resources/vendor/sneat`, con su commit de origen y licencias en `PROVENANCE.md`. El panel reutiliza su estructura vertical con enlaces a Inicio, Integrantes y Mi perfil, administración según permisos y cierre de sesión mediante POST con CSRF. Perfect Scrollbar acompaña al menú oficial. Public Sans y los iconos utilizados se sirven localmente; Vite genera URLs relativas para las fuentes, compatibles con la subcarpeta de Apache. El footer acredita a ThemeSelection. Los estilos de autenticación incluyen `pages/page-auth.scss`; su tarjeta usa el logo de 32 píxeles y la identidad MemoryLab. Los módulos del simulador y sus menús están incorporados y respetan sus permisos.

Las traducciones de autenticación, recuperación, validación y perfil se encuentran en `lang/es` y `lang/es.json`. El diálogo de otras sesiones utiliza los estilos del modal de Sneat y mantiene el estado, focus trap y cierre por Escape de Livewire/Alpine, sin agregar otra instancia JavaScript de Bootstrap sobre ese diálogo.

Livewire y su Alpine se compilan juntos con Vite. La URL de actualización se genera desde la ruta de Laravel para funcionar tanto en `/sistemg3/public` como desde la raíz de un servidor Linux. Después de actualizar Livewire con Composer, ejecutar `npm run build` para recompilar sus assets.

```powershell
& 'C:\xampp\php\php.exe' artisan route:list
& 'C:\xampp\php\php.exe' artisan test
```

PHPUnit utiliza `memorylab_testing`, separada de `memorylab`, y aplica allí las migraciones necesarias para sus pruebas. Nunca ejecutar las pruebas de persistencia sobre `memorylab`. En una instalación nueva, preparar la base de pruebas cambiando temporalmente `DB_DATABASE=memorylab_testing` para `memorylab:database --create` y restaurar `DB_DATABASE=memorylab` antes de iniciar la aplicación.

No versionar `.env`, `vendor`, `node_modules`, credenciales ni archivos temporales. `composer.lock` y `package-lock.json` sí deben versionarse. En Linux, configurar las variables de entorno y utilizar `php` y `composer` del servidor, sin rutas de Windows.

## Laravel

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
