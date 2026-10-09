# Fase 26 — Seeder explícito de demostración

Fecha de cierre: **8 de octubre de 2026**.

Estado: **completada con pruebas y ejecución local verificadas**.

## Autorización y alcance

Continúa la autorización del usuario para completar todas las fases sin nuevas confirmaciones. El seeder prepara datos educativos mediante los servicios existentes y conserva cuentas, escenarios manuales e historia. Se ejecutó en la base principal después de cerrar la verificación del modo presentación.

## Contrato implementado

- MemoryDemoSeeder es independiente de DatabaseSeeder. El seeder habitual sigue preparando únicamente roles y permisos.
- Requiere un Administrador existente del guard web; utiliza el de menor ID y bloquea su fila. Ausencia de Administrador produce RuntimeException y no crea cuentas o memoria.
- Conserva las cuentas, sus roles y contraseñas; no contiene credenciales ni crea usuarios.
- Identifica solo escenarios is_demo con los nombres de las constantes PAGING_NAME y SEGMENTATION_NAME: Demostración base: paginación y Demostración base: segmentación.
- Si no existe la pareja, usa SimulationService::startDemo y renombra exclusivamente sus nuevos IDs, dentro de la misma transacción con hasta tres intentos.
- La pareja inicial conserva el fixture de Fase 22: paginación con RAM de 16 KB, página de 1 KB, secundaria de 64 KB, 16 marcos, cinco páginas en RAM y diez ausentes. Chrome P0 inicia presente y P3 ausente.
- Segmentación contiene Editor de 4 KB y cuatro segmentos activos que suman 3600 bytes, incluido Código con base 1000 y tamaño 1200.
- Una pareja exacta y consistente se valida mediante snapshots y se conserva sin escrituras. Esto incluye accesos posteriores, procesos finalizados tras reset y escenarios COMPLETED.
- Una pareja parcial, ambigua o con modos incorrectos se rechaza mediante RuntimeException sin reemplazo o liberación. Una inconsistencia de memoria propaga ValidationException.
- Los escenarios manuales homónimos y las demos de otros nombres se conservan.
- Un fallo al preparar el segundo escenario revierte todo el par, incluidos eventos y asignaciones del primero.
- No se agrega migración, contador ni tipo de evento.

## Verificación

MemoryDemoSeederTest: **19 pruebas aprobadas y 99 assertions**, en **10,50 segundos**, sobre la base de verificación separada.

Los casos cubren datos canónicos, selección del Administrador existente de menor ID, ausencia de Administrador, ejecución habitual sin demo, repetición sin cambios, escenarios manuales homónimos, otras demostraciones, pares incompletos/duplicados/modos incorrectos, snapshots corruptos y reversión de una preparación fallida. También comprueban que repetir después de accesos, reset o COMPLETED conserva los datos y no revive procesos.

La idempotencia utiliza hashes de las siete tablas del dominio. Las verificaciones de cuentas comparan sus filas, incluidos hashes de contraseña y asignaciones de roles. No se versionan credenciales de cuentas reales.

## Ejecución explícita

Después de preparar la base, catálogo y una cuenta Administrador existente, el responsable puede ejecutar:

```sh
php artisan db:seed --class=MemoryDemoSeeder
```

En Windows se utiliza el PHP de XAMPP, como en el resto del proyecto. El comando habitual db:seed no prepara la demo base; se requiere el argumento de clase. Repetir la ejecución explícita conserva la pareja válida y su trabajo. Para obtener una generación nueva se utiliza la acción de demostración de Fase 22, que preserva la historia anterior.

## Verificación sobre la base local principal

Se ejecutó MemoryDemoSeeder y se repitió después. La primera ejecución agregó exclusivamente los dos escenarios base con sus datos; los hashes de las filas anteriores, cuentas y catálogo de permisos permanecieron intactos. La segunda ejecución conservó los hashes completos y no produjo cambios.

| Tabla o catálogo | Conteo posterior |
| --- | ---: |
| Usuarios | 2 |
| Roles | 3 |
| Permisos | 13 |
| Escenarios | 3 |
| Configuraciones | 3 |
| Procesos | 5 |
| Marcos | 32 |
| Páginas | 15 |
| Segmentos | 4 |
| Eventos | 26 |

Los tres escenarios incluyen el original conservado y la pareja base nueva; los marcos incluyen los 16 anteriores y 16 nuevos de paginación. No se borraron datos ni se modificaron cuentas existentes.

Fase 26 completada. Continúa la Fase 27, verificación integral, según la autorización general.

Commit sugerido: `feat: add explicit idempotent memory demonstration seeder`.
