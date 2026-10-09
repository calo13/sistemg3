# Fase 27 — Verificación integral

Fecha de cierre: **8 de octubre de 2026**.

Estado: **completada con suite, herramientas y auditoría final verificadas**.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases sin nuevas confirmaciones. Esta entrega consolida la comprobación de autenticación, permisos, dominio, operaciones y pantallas implementadas hasta Fase 26.

Las pruebas de persistencia utilizaron memorylab_testing, separada de la base local principal. Las verificaciones de navegador documentadas por fase conservaron datos anteriores y limpiaron las fixtures temporales que crearon.

## Matriz de comprobación

| Área | Resultado de consolidación |
| --- | --- |
| Suite completa de pruebas y aserciones | 655 aprobadas, una omisión condicional y 4.575 aserciones |
| Autenticación, registro configurable y sesiones | Incluidas en la suite integral; omisión explicada abajo |
| Tres roles y 13 permisos, acciones Livewire autorizadas | Suite aprobada; catálogo y cuentas conservados |
| Configuración, procesos y restricciones del dominio | Suite aprobada; siete tablas InnoDB, 12 FK, seis CHECK y siete índices únicos |
| Hit/Fault, FIFO, modo manual y traducción | Suite aprobada |
| Segmentación, límite estricto y fallos | Suite aprobada |
| Comparación, alta demanda, demostración y reset | Suite aprobada |
| Historial, terminal, presentación y seeder explícito | Suite aprobada; repetición local del seeder intacta |
| Recursos, sintaxis, estilo y rutas | Vite, Pint, 117 archivos PHP, Composer y 14 rutas propias aprobados |
| Responsive y navegador | Evidencias específicas de las fases; presentación verificada de 320 a 1920 píxeles |
| Datos locales y ausencia de fixtures residuales | Auditoría final aprobada con los conteos registrados abajo |

Los resultados específicos y capturas permanecen en las evidencias de cada fase. El total de esta entrega corresponde a una ejecución integral real, no a sumar ejecuciones anteriores.

## Punto de partida de datos locales

El cierre de Fase 26 confirmó dos usuarios, tres roles, 13 permisos, tres escenarios y configuraciones, cinco procesos, 32 marcos, 15 páginas, cuatro segmentos y 26 eventos. Incluye el escenario original y la pareja base preparada explícitamente. Las filas anteriores y cuentas/catálogo se conservaron; repetir el seeder no produjo cambios.

La auditoría posterior confirmó la conservación de estos datos. No se ejecutó una limpieza general de la base principal para validar el sistema.

## Suite y herramientas

- php artisan test --compact: **655 pruebas aprobadas, una omitida y 4.575 aserciones**, en **96,78 segundos**.
- Pint en modo de comprobación: aprobado.
- PHP lint: **117 archivos** con sintaxis correcta.
- Vite build: aprobado.
- Composer validate --strict --no-check-publish: aprobado. COMPOSER_ROOT_VERSION se estableció únicamente para ese proceso por el estado de la rama; no se cambió configuración global de Composer.
- Listado de rutas con proveedores excluidos: **14 rutas propias** verificadas.

## Omisión condicional exacta

RegistrationTest::test_registration_screen_cannot_be_rendered_if_support_is_disabled omite su ejecución cuando Fortify tiene habilitado el registro, con el mensaje «Registration support is enabled.». Esta es la condición de la instalación utilizada para la suite; no representa una prueba fallida. La condición se conserva en [RegistrationTest](../../tests/Feature/RegistrationTest.php).

El cierre del registro sí tiene una prueba independiente: RegistrationDisabledTest::test_public_registration_can_be_disabled desactiva temporalmente AUTH_REGISTRATION_ENABLED, comprueba GET y POST de /register con 404 y ausencia de enlaces de registro, y restaura el entorno. Esta prueba aprobó dentro de la suite; su código está en [RegistrationDisabledTest](../../tests/Feature/RegistrationDisabledTest.php).

## Auditoría final de persistencia

| Tabla o catálogo | Conteo |
| --- | ---: |
| Usuarios | 2 |
| Cuentas con Administrador | 1 |
| Roles | 3 |
| Permisos | 13 |
| Relaciones rol–permiso | 25 |
| Escenarios | 3 |
| Configuraciones | 3 |
| Procesos | 5 |
| Marcos | 32 |
| Páginas | 15 |
| Segmentos | 4 |
| Eventos | 26 |

Las siete tablas del dominio utilizan InnoDB y conservan **12 claves foráneas, seis CHECK y siete índices únicos**. Los hashes de cuentas, catálogo y filas anteriores permanecieron intactos al preparar la demo base; repetir MemoryDemoSeeder conservó el estado completo. La comprobación final confirmó ausencia de fixtures residuales.

## Continuación preparada

Fase 27 completada. La Fase 28 conserva el despliegue local Apache comprobado y la guía de [despliegue](../despliegue.md) con ejemplos para Linux. La máquina virtual es opcional según la solicitud original; no se afirma ejecución en Linux o una VM. Continúan los entregables académicos de las Fases 29–31.

Commit sugerido: `test: verify complete memory simulation and preserved data`.
