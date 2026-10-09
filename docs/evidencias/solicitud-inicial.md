# MEMORYLAB
## Simulador Interactivo de Administración de Memoria

Actúa como desarrollador senior de Laravel, Livewire, Bootstrap, JavaScript y MySQL, con conocimientos sólidos de Sistemas Operativos.

Vamos a desarrollar juntos un proyecto universitario llamado:

**MemoryLab — Simulador Interactivo de Administración de Memoria**

IMPORTANTE:

No debes construir todo el sistema de una sola vez.

Trabajaremos por fases.

Antes de modificar archivos en cada fase:

1. Analiza el estado actual del proyecto.
2. Indica brevemente qué vas a modificar.
3. Realiza únicamente los cambios correspondientes a esa fase.
4. Verifica que el proyecto continúe funcionando.
5. No avances a la siguiente fase hasta recibir mi autorización.

---

# 1. CONTEXTO ACADÉMICO

MemoryLab corresponde al proyecto de Sistemas Operativos 1 asignado al Grupo 3.

El objetivo académico principal es demostrar mediante Inteligencia Artificial y simulaciones:

1. Tablas de páginas.
2. Simulación de fallos de página (Page Fault).
3. Tablas de segmentos.
4. Base y tamaño/límite de segmentos.
5. Comparación entre asignación contigua y no contigua.
6. Conclusiones técnicas.

Por lo tanto, estas funcionalidades constituyen el NÚCLEO OBLIGATORIO del sistema.

Las demás funcionalidades son complementarias y nunca deben hacer que el proyecto pierda su enfoque académico.

---

# 2. OBJETIVO DEL SOFTWARE

Desarrollar una aplicación web educativa que permita visualizar y simular cómo un sistema operativo administra memoria.

MemoryLab NO manipulará la memoria RAM física real.

Será un simulador educativo.

Debe permitir visualizar:

CPU
↓
Dirección lógica
↓
Tabla de páginas
↓
Página
↓
Marco
↓
Memoria física simulada

También deberá demostrar:

CPU solicita página
↓
Tabla de páginas
↓
Página no presente
↓
PAGE FAULT
↓
Carga desde almacenamiento secundario
↓
Asignación de marco
↓
Actualización de tabla
↓
Acceso completado

---

# 3. STACK TECNOLÓGICO OBLIGATORIO

Utilizar:

- Laravel
- Laravel Jetstream
- Livewire
- Bootstrap 5
- JavaScript ES6
- Blade
- Vite
- Eloquent ORM
- MySQL
- Laravel migrations
- Laravel seeders
- Laravel validation
- Middleware
- Policies cuando corresponda
- Services para lógica de negocio

NO utilizar:

- SQLite
- PostgreSQL
- SQL Server
- PHP procedural
- React
- Vue
- Angular

Evitar jQuery.

Solo utilizarlo si una dependencia específica de la plantilla lo requiere y no existe una alternativa razonable.

---

# 4. BASE DE DATOS

La única base de datos oficial del proyecto será:

**MySQL**

El proyecto será desarrollado inicialmente en Windows utilizando:

XAMPP
Apache
MySQL

La configuración local deberá ser compatible con un entorno típico:

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=memorylab
DB_USERNAME=root

No escribir una contraseña fija en archivos versionados.

Utilizar variables de entorno.

La aplicación también deberá poder desplegarse posteriormente en un servidor Linux con MySQL simplemente modificando las variables del archivo `.env`.

Toda la estructura de base de datos deberá crearse mediante migrations.

NO depender de phpMyAdmin para crear manualmente las tablas.

phpMyAdmin podrá utilizarse únicamente para visualizar y administrar MySQL.

---

# 5. INTERFAZ

Utilizar como base visual:

**Sneat Bootstrap 5 HTML Laravel Admin Template Free**

Repositorio oficial:

https://github.com/themeselection/sneat-bootstrap-html-laravel-admin-template-free

Autor:

ThemeSelection

No crear desde cero un dashboard con apariencia genérica de IA.

Integrar y adaptar Sneat.

Utilizar sus:

- layouts;
- sidebar;
- navbar;
- cards;
- tablas;
- formularios;
- dropdowns;
- alerts;
- badges;
- modals;
- componentes Bootstrap;
- iconografía.

Mantener los créditos/licencia que correspondan.

Personalizar Sneat con la identidad:

**MemoryLab**

---

# 6. DISEÑO

El sistema debe parecer una aplicación universitaria desarrollada profesionalmente.

Evitar:

- exceso de degradados;
- exceso de efectos neon;
- interfaces exageradamente futuristas;
- componentes gigantes;
- emojis innecesarios;
- textos que parezcan generados por IA.

Preferir:

- Bootstrap;
- Sneat;
- cards;
- tablas;
- badges;
- progress bars;
- modals;
- tooltips;
- iconos;
- animaciones discretas.

Estados visuales sugeridos:

Verde = acceso correcto.

Azul = página en RAM.

Rojo = Page Fault/error.

Gris = marco libre.

Amarillo = advertencia.

Morado = almacenamiento secundario.

---

# 7. AUTENTICACIÓN

Implementar autenticación utilizando:

Laravel Jetstream + Livewire.

Debe incluir:

- Login.
- Logout.
- Perfil.
- Cambio de contraseña.
- Recuperación de contraseña.

El registro público deberá poder deshabilitarse.

Adaptar posteriormente las vistas de autenticación al estilo visual de Sneat/Bootstrap.

---

# 8. ROLES Y PERMISOS

Implementar:

ADMINISTRADOR

OPERADOR

OBSERVADOR

Puede utilizarse:

spatie/laravel-permission

si es compatible con las versiones seleccionadas.

ADMINISTRADOR:

- administrar usuarios;
- administrar configuración;
- crear escenarios;
- ejecutar simulaciones;
- reiniciar memoria;
- consultar historial.

OPERADOR:

- crear procesos;
- ejecutar simulaciones;
- solicitar páginas;
- ejecutar segmentación;
- consultar resultados.

OBSERVADOR:

- visualizar;
- consultar tablas;
- observar simulaciones;
- no modificar configuraciones.

---

# 9. DASHBOARD

El dashboard debe mostrar:

RAM TOTAL

RAM UTILIZADA

RAM DISPONIBLE

MARCOS TOTALES

MARCOS OCUPADOS

MARCOS LIBRES

PROCESOS ACTIVOS

PAGE FAULTS

Mostrar además:

barra de utilización de memoria.

Ejemplo:

RAM

██████████████░░░░░░

70%

Las estadísticas deberán actualizarse dinámicamente mediante Livewire cuando corresponda.

---

# 10. CONFIGURACIÓN DE MEMORIA

Crear módulo:

Configuración de memoria.

Campos principales:

- RAM total.
- Tamaño de página.
- Número de marcos.
- Capacidad simulada de almacenamiento secundario.

Escenario inicial recomendado:

RAM = 16 KB

Página = 1 KB

Marcos = 16

El número de marcos deberá calcularse automáticamente:

marcos = RAM / tamaño_página

Validar configuraciones incorrectas.

---

# 11. PROCESOS

Crear módulo de procesos.

Cada proceso tendrá como mínimo:

id

name

size

status

created_at

Estados:

READY
RUNNING
WAITING
TERMINATED

Al crear un proceso, calcular automáticamente el número de páginas requeridas.

Ejemplo:

Chrome
4 KB

Tamaño página:
1 KB

Resultado:
4 páginas.

---

# 12. PAGINACIÓN

Crear módulo:

Simulador de Paginación.

Debe mostrar simultáneamente:

1. Procesos.
2. Tabla de páginas.
3. Memoria RAM simulada.
4. Almacenamiento secundario.
5. Solicitud realizada por CPU.

Seleccionar un proceso debe actualizar dinámicamente la información mediante Livewire.

---

# 13. TABLA DE PÁGINAS

Mostrar como mínimo:

Página
Marco
Presente
Estado

Ejemplo:

0 | 4 | Sí | RAM
1 | 9 | Sí | RAM
2 | 1 | Sí | RAM
3 | - | No | DISCO

Debe quedar visualmente claro qué páginas están en RAM y cuáles no.

---

# 14. MEMORIA RAM VISUAL

Representar los marcos como bloques visuales.

Ejemplo:

Marco 0
LIBRE

Marco 1
Chrome P2

Marco 2
Spotify P0

Marco 3
LIBRE

Marco 4
Chrome P0

Cada marco debe indicar:

- número;
- proceso;
- página;
- estado.

Debe ser responsive.

---

# 15. CPU — SOLICITUD DE PÁGINA

Crear un componente donde el usuario seleccione:

Proceso

Página

y presione:

ACCEDER A PÁGINA

El sistema debe comprobar si la página está presente.

Si está presente:

PAGE HIT

Mostrar:

Página localizada.

Marco correspondiente.

Dirección/ubicación.

Registrar el evento.

---

# 16. PAGE FAULT

Si la página no está presente:

Mostrar claramente:

PAGE FAULT

Representar el flujo:

CPU
↓
Solicita página
↓
Tabla de páginas
↓
Página no presente
↓
PAGE FAULT
↓
Buscar marco disponible
↓
Cargar página
↓
Actualizar tabla
↓
Reintentar acceso
↓
Acceso correcto

Utilizar:

Livewire para estado y lógica.

JavaScript para animaciones visuales.

NO recargar completamente la página.

---

# 17. MODO PASO A PASO

Implementar:

MODO AUTOMÁTICO

MODO PASO A PASO

En modo paso a paso:

Paso 1:
CPU solicita Página X.

Paso 2:
Se consulta la tabla.

Paso 3:
Página no presente.

Paso 4:
PAGE FAULT.

Paso 5:
Se busca marco disponible.

Paso 6:
Página cargada.

Paso 7:
Tabla actualizada.

Debe existir botón:

SIGUIENTE

Esto será especialmente importante durante la exposición.

---

# 18. TRADUCCIÓN DE DIRECCIONES

Crear módulo educativo:

Dirección lógica → Dirección física.

Entrada:

Dirección lógica.

Calcular:

Página.

Offset.

Marco.

Dirección física.

Ejemplo:

Página = floor(dirección / tamaño_página)

Offset = dirección % tamaño_página

Obtener marco desde la tabla.

Dirección física:

marco × tamaño_página + offset

Mostrar todos los pasos.

---

# 19. ALMACENAMIENTO SECUNDARIO

Representar visualmente páginas que no están actualmente en RAM.

Ejemplo:

Chrome P3

Spotify P2

VSCode P4

Debe quedar claramente indicado que se trata de:

ALMACENAMIENTO SECUNDARIO SIMULADO.

No afirmar que se está manipulando directamente el disco real.

---

# 20. SEGMENTACIÓN

Crear módulo:

Simulador de Segmentación.

Cada segmento tendrá:

id

process_id

segment_number

name

base

size

status

Ejemplo:

0 | Código | 1000 | 1200
1 | Datos | 4000 | 800
2 | Stack | 7000 | 600
3 | Heap | 9000 | 1000

Mostrar una tabla de segmentos.

---

# 21. DIRECCIONAMIENTO POR SEGMENTACIÓN

Permitir introducir:

Segmento.

Offset.

Comprobar:

offset < tamaño/límite.

Si es válido:

dirección física = base + offset.

Mostrar el cálculo.

Si no es válido:

SEGMENTATION FAULT.

Explicar visualmente:

Offset solicitado.

Límite permitido.

Razón del error.

---

# 22. MAPA DE SEGMENTOS

Mostrar representación visual:

RAM

LIBRE

CÓDIGO

LIBRE

DATOS

STACK

LIBRE

HEAP

Esto debe ayudar a demostrar que los segmentos no necesariamente se encuentran consecutivos.

---

# 23. COMPARADOR ACADÉMICO

Crear módulo:

Comparación de Administración de Memoria.

Comparar:

ASIGNACIÓN CONTIGUA

PAGINACIÓN

SEGMENTACIÓN

Mostrar:

- forma de asignación;
- tamaño de bloques;
- fragmentación;
- flexibilidad;
- estructuras utilizadas;
- ventajas;
- desventajas.

Debe combinar tablas con representaciones visuales.

---

# 24. STRESS TEST SIMULADO

Crear:

SIMULAR ALTA DEMANDA

Generar procesos ficticios y aumentar progresivamente la ocupación simulada de memoria.

Mostrar:

RAM utilizada.

RAM disponible.

Marcos ocupados.

Procesos.

Page Faults.

IMPORTANTE:

NO consumir deliberadamente grandes cantidades de RAM real.

Todo el Stress Test será matemático/simulado.

---

# 25. MODO DEMOSTRACIÓN

Crear:

INICIAR DEMOSTRACIÓN

Debe preparar automáticamente:

RAM = 16 KB

Página = 1 KB

16 marcos.

Procesos de demostración.

Algunas páginas presentes.

Algunas páginas ausentes.

Debe ser posible provocar fácilmente:

PAGE HIT

PAGE FAULT

SEGMENTATION FAULT

Agregar:

REINICIAR DEMOSTRACIÓN.

---

# 26. HISTORIAL

Registrar eventos como:

PROCESS_CREATED

PAGE_REQUEST

PAGE_HIT

PAGE_FAULT

PAGE_LOADED

SEGMENT_ACCESS

SEGMENTATION_FAULT

MEMORY_RESET

Guardar:

usuario

proceso

evento

descripción

fecha/hora

Crear filtros y tabla de historial.

---

# 27. TERMINAL EDUCATIVA

Crear opcionalmente una terminal simulada.

NO ejecutar comandos del sistema operativo.

Comandos educativos:

memory status

process list

page table chrome

request chrome 3

reset

Ejemplo:

> request chrome 3

CPU requesting Chrome Page 3...

Checking page table...

Page not present.

PAGE FAULT

Searching free frame...

Loading Page 3...

Page table updated.

Access completed.

La terminal debe utilizar exactamente los mismos Services del simulador.

No duplicar la lógica.

---

# 28. PRESENTATION MODE

Crear ruta/pantalla especial:

/presentation

Debe ser apropiada para proyectarse durante la exposición.

Mostrar principalmente:

MemoryLab

CPU

Proceso seleccionado

Tabla de páginas

RAM

Almacenamiento secundario

Page Faults

Flujo actual

Ocultar elementos administrativos innecesarios.

---

# 29. ARQUITECTURA LARAVEL

Mantener separación de responsabilidades.

Modelos para persistencia.

Livewire para interacción.

Services para lógica del simulador.

JavaScript para animaciones.

Blade para presentación.

Policies/Middleware para autorización.

No colocar lógica compleja directamente en Blade.

No realizar consultas SQL desde Blade.

No crear archivos PHP sueltos fuera de la arquitectura Laravel.

---

# 30. SERVICES PROPUESTOS

Analizar la conveniencia de crear:

MemoryManagerService

PagingService

SegmentationService

AddressTranslationService

SimulationService

PagingService debe encargarse de:

- generar páginas;
- consultar páginas;
- asignar marcos;
- detectar Page Fault;
- cargar páginas;
- actualizar estado.

SegmentationService:

- administrar segmentos;
- validar offset;
- calcular dirección física;
- detectar Segmentation Fault.

---

# 31. COMPONENTES LIVEWIRE PROPUESTOS

Evaluar:

DashboardStats

MemoryConfiguration

ProcessManager

PagingSimulator

PageTable

MemoryGrid

PageRequest

AddressTranslator

SegmentationSimulator

SegmentTable

StressTest

SimulationHistory

No construir todo en un único componente Livewire.

---

# 32. JAVASCRIPT

JavaScript deberá utilizarse para mejorar la experiencia visual.

Ejemplos:

- animar CPU → tabla;
- tabla → RAM;
- DISCO → RAM;
- Page Fault;
- cambio de marco;
- barras;
- Presentation Mode.

La lógica académica principal no debe existir exclusivamente en JavaScript.

Debe estar centralizada en Services de Laravel para mantener consistencia.

---

# 33. MYSQL — MODELO INICIAL

Analizar como mínimo estas tablas:

users

memory_configurations

processes

pages

memory_frames

segments

simulation_events

scenarios

y las tablas necesarias para roles/permisos.

Utilizar:

InnoDB.

Foreign keys.

Índices.

Restricciones apropiadas.

timestamps.

Diseñar las relaciones correctamente antes de crear las migrations.

---

# 34. DATOS DE DEMOSTRACIÓN

Crear seeders posteriormente.

Escenario:

RAM:
16 KB.

Página:
1 KB.

Marcos:
16.

Procesos:

Chrome
Spotify
VS Code
MemoryLab

Debe existir un estado inicial que permita demostrar rápidamente:

Page Hit.

Page Fault.

Segmentación.

Segmentation Fault.

---

# 35. MÁQUINA VIRTUAL

En una fase posterior se podrá ejecutar MemoryLab dentro de:

Ubuntu + Apache/Nginx + PHP + MySQL.

Opcionalmente usar VirtualBox o VMware.

La VM servirá para relacionar el proyecto con un sistema operativo real.

Podrán utilizarse posteriormente comandos seguros:

free -h

vmstat

ps aux --sort=-%mem

Pero MemoryLab NO debe afirmar que sus tablas corresponden directamente a las tablas de páginas internas reales de Linux.

Son una simulación educativa.

---

# 36. EVIDENCIA DE INTELIGENCIA ARTIFICIAL

Durante el desarrollo debemos conservar evidencia de:

- prompts;
- decisiones;
- diseños;
- simulaciones;
- diagramas;
- correcciones;
- resultados.

Esto será utilizado posteriormente en el informe académico como evidencia del uso de IA.

---

# 37. ENTREGABLES ACADÉMICOS

El software es solamente una parte del proyecto.

Posteriormente debemos preparar:

- Informe técnico mínimo de 5 páginas.
- Presentación de 10 a 15 diapositivas.
- Video demostrativo de 2 a 4 minutos.
- Evidencias del uso de IA.
- Diagramas.
- Conclusiones técnicas.

Por lo tanto, mantener el proyecto fácil de explicar y demostrar.

---

# 38. FASES DE DESARROLLO

FASE 0
Análisis y arquitectura.

FASE 1
Crear Laravel + MySQL.

FASE 2
Jetstream + Livewire.

FASE 3
Bootstrap 5.

FASE 4
Integración Sneat.

FASE 5
Autenticación visual Sneat.

FASE 6
Roles y permisos.

FASE 7
Layout/dashboard.

FASE 8
Modelo MySQL y migrations.

FASE 9
Configuración de memoria.

FASE 10
Procesos.

FASE 11
Paginación.

FASE 12
Tabla de páginas.

FASE 13
Mapa visual de RAM.

FASE 14
CPU Page Request.

FASE 15
Page Hit/Page Fault.

FASE 16
Animaciones JavaScript.

FASE 17
Traducción lógica/física.

FASE 18
Segmentación.

FASE 19
Segmentation Fault.

FASE 20
Contigua vs. no contigua.

FASE 21
Stress Test.

FASE 22
Modo demostración.

FASE 23
Historial.

FASE 24
Terminal educativa.

FASE 25
Presentation Mode.

FASE 26
Seeders.

FASE 27
Pruebas.

FASE 28
Despliegue/VM.

FASE 29
Informe.

FASE 30
Presentación.

FASE 31
Video.

---

# 39. REGLAS PARA CODEX

Antes de modificar código:

Lee primero los archivos existentes relacionados.

No reemplaces archivos completos innecesariamente.

No elimines código funcional sin justificarlo.

No cambies tecnologías del stack sin autorización.

No instales paquetes innecesarios.

No cambies MySQL por SQLite.

No cambies Bootstrap por Tailwind.

No cambies Livewire por Vue/React.

No cambies Sneat por otra plantilla.

Respeta las convenciones Laravel.

Después de cada modificación:

- verifica sintaxis;
- revisa imports;
- revisa rutas;
- revisa migrations;
- revisa relaciones;
- revisa compatibilidad;
- ejecuta las pruebas/comandos pertinentes cuando el entorno lo permita.

Si encuentras un error:

Primero identifica la causa.

Después corrígelo.

No realices cambios masivos intentando resolverlo al azar.

---

# 40. CONTROL DE VERSIONES

Trabajar con Git.

Realizar cambios pequeños y coherentes.

No incluir:

.env

vendor/

node_modules/

archivos temporales

credenciales

contraseñas.

Sugerir commits por fase.

Ejemplo:

feat: configure jetstream authentication

feat: integrate sneat bootstrap layout

feat: add memory simulation schema

feat: implement paging simulator

feat: implement page fault simulation

---

# 41. PRIMERA TAREA PARA CODEX

NO escribas todavía el sistema.

Comienza exclusivamente con la FASE 0.

Necesito que:

1. Analices los requerimientos anteriores.
2. Revises el proyecto actual si ya existe.
3. Determines versiones compatibles de:
   - Laravel
   - PHP
   - Jetstream
   - Livewire
   - Bootstrap
   - Node
   - MySQL
   - Sneat.
4. Identifiques posibles conflictos entre Jetstream, Livewire, Bootstrap y Sneat.
5. Propongas la arquitectura final.
6. Diseñes el modelo MySQL.
7. Muestres las tablas y relaciones.
8. Definas los componentes Livewire.
9. Definas los Services.
10. Diseñes el sidebar de Sneat.
11. Expliques el flujo de Page Hit.
12. Expliques el flujo de Page Fault.
13. Expliques el flujo de segmentación.
14. Expliques el flujo de Segmentation Fault.
15. Indiques qué funcionalidades corresponden directamente al requerimiento académico y cuáles son mejoras de MemoryLab.

NO ejecutes todavía migraciones.

NO instales paquetes.

NO modifiques archivos.

Al finalizar la FASE 0, detente y espera mi autorización para iniciar la FASE 1.