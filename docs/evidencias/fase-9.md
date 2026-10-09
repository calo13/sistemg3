# Fase 9 — Configuración de memoria

## Autorización y alcance

El usuario indicó «continua» después de completar la Fase 8. Se implementó exclusivamente la configuración de memoria y su conexión con el dashboard. Se revisaron solicitud original, permisos, modelos, migración, componentes, layout y pruebas existentes antes de modificar archivos. Se delegaron servicio, estadísticas, pruebas y una revisión independiente en archivos separados.

## Implementación

- Ruta `GET /memoria/configuracion`, nombre `memory.configuration`, protegida por autenticación, sesión de Jetstream y `memory.view`. Enlace del menú Sneat para los roles con permiso de consulta.
- Componente Livewire `MemoryConfiguration` y formulario Bootstrap/Sneat con nombre del escenario, RAM total, tamaño de página y almacenamiento secundario en KB. Marcos calculados mediante RAM/página, de solo lectura.
- Valores iniciales del formulario: 16 KB de RAM, página de 1 KB y 64 KB secundarios. Son propuestas sin persistencia automática. El almacenamiento secundario puede ser cero.
- Entrada entera: RAM y página positivas; página no mayor que RAM; RAM divisible por página. Límites operativos configurables: 65.536 KB por capacidad y 1.024 marcos. La base guarda bytes; 1 KB equivale a 1024 bytes.
- `MemoryConfigurationService` recarga permisos del actor, exige `memory.configure` y, para crear, `scenarios.create`. Usa una transacción y bloqueo de fila del escenario existente.
- Nuevo escenario PAGING: configuración, marcos consecutivos desde cero y estado READY. Registra SCENARIO_CREATED y MEMORY_CONFIGURED con actor servidor, UTC y metadata anterior/posterior en bytes.
- Guardado idéntico: conserva configuración, marcos, estado e historial, sin eventos adicionales. Una reconfiguración solo procede en DRAFT/READY sin procesos, páginas o segmentos, incluidos procesos terminados. Conserva eventos anteriores y reemplaza únicamente marcos libres.
- `ActiveScenarioService` mantiene una selección explícita en `memorylab.active_scenario_id` de la sesión. No existe selección global ni elección automática del escenario más reciente. IDs inexistentes o inválidos no sustituyen el contexto válido anterior.
- Operador y Observador pueden seleccionar y consultar escenarios compartidos. Los controles de guardado se ocultan y el servidor rechaza acciones Livewire manipuladas. La lectura también comprueba permisos actuales.
- `MemoryStatisticsService` calcula los ocho indicadores de paginación con consultas por escenario en una lectura transaccional. RAM usada = marcos con página × tamaño de página; procesos activos excluyen TERMINATED; Page Faults cuenta los eventos correspondientes. Sin configuración seleccionada mantiene «Sin datos».
- `DashboardStats` actualiza cada cinco segundos mientras es visible. La barra expone el porcentaje real mediante ARIA únicamente cuando hay datos.
- Se conservaron identidad UMG, Ingeniería en Sistemas, los cinco integrantes y carnés, autenticación, perfil y administración de roles. No se instalaron paquetes ni se modificaron assets o migraciones.

## Verificación

- Suite completa en **memorylab_testing**: **135 pruebas aprobadas, 558 assertions**, y un caso condicional omitido porque el registro está habilitado. `MemoryConfigurationTest` agrega 51 casos al expandir los proveedores de datos.
- Cobertura de conversión a bytes, numeración y cantidad de marcos, secundaria cero, límites, geometría inválida, rollback al fallar un evento, idempotencia, protección de datos en uso, permisos revocados, IDs manipulados y selección por sesión.
- Dos escenarios verifican que las estadísticas no mezclen procesos, páginas ni eventos. Una segunda asignación comprueba la actualización del porcentaje de 6,3 % a 12,5 %.
- MySQL normaliza el orden de las claves de metadata JSON. Una comparación de prueba que suponía orden de objeto se corrigió para comprobar su contenido sin atribuirle un orden inexistente.
- Chrome, con Apache en `/sistemg3/public`: RAM no divisible rechazada sin insertar filas; guardado 16/1/0 crea exactamente 16 marcos y dos eventos; segundo guardado idéntico conserva esos eventos.
- Formulario sin desbordamiento horizontal a 1366, 768, 390 y 320 px. Se inspeccionaron las capturas de escritorio y móvil.
- Dashboard conserva la selección tras navegar, muestra ocho valores reales y recibe polling Livewire HTTP 200 en la URL de la subcarpeta.
- Operador y Observador consultan, seleccionan y deseleccionan el escenario, sin botón de guardado. No hubo errores JavaScript ni fallos de recursos necesarios.
- Al concluir se eliminaron exclusivamente escenario, configuración, marcos, eventos, cuenta, sesiones y perfil de Chrome generados para verificar la fase. No se modificó la cuenta real.
- Auditoría final de `memorylab`: una cuenta existente, tres roles, trece permisos; cero filas en las siete tablas del dominio. InnoDB, 12 FK, 6 CHECK efectivos y 7 restricciones únicas se conservaron.
- Pint, sintaxis PHP, compilación Blade y ruta del módulo verificados.

## Capturas

- [Configuración en escritorio](fase-9-configuracion-escritorio.png).
- [Configuración en móvil](fase-9-configuracion-movil.png).
- [Dashboard con escenario configurado](fase-9-dashboard.png).

## Uso local y límites

Abre `http://localhost/sistemg3/public/memoria/configuracion`. La base principal todavía no contiene escenarios ni una cuenta Administrador. La cuenta existente puede consultar; para guardar, asigna el primer Administrador a una cuenta registrada mediante el comando local existente:

```powershell
& 'C:\xampp\php\php.exe' artisan memorylab:user-role "tu-correo@ejemplo.com" administrador
```

El comando requiere una cuenta existente y no cambia su contraseña. Esta fase no selecciona automáticamente una cuenta para administrar.

La capacidad secundaria está configurada; su uso por páginas se implementará con los procesos y la paginación. Los cálculos del dashboard de esta fase corresponden a PAGING. No se implementaron creación de procesos, asignación de páginas, reinicio, algoritmos ni demostración.

Los futuros Services de asignación deberán bloquear la misma fila de escenario para coordinarse con la reconfiguración. Los estados e historial permanecen disponibles para esas fases.

## Cierre

Fase 9 completada. Fase 10, procesos, pendiente de autorización del usuario.

Commit sugerido: `feat: configure simulated memory by scenario`.
