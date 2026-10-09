# Fase 8 — Modelo MySQL y migraciones

## Autorización y alcance

El usuario indicó «continua» tras completar la Fase 7. Se implementa el modelo de datos de simulación, sus relaciones Eloquent y migración MySQL. Configuración de memoria, procesos y algoritmos corresponden a fases posteriores.

Se revisaron la solicitud inicial, modelos, migraciones, seeder de roles, pruebas y diseño del dashboard. No existía un documento separado del modelo anterior; las decisiones se consolidaron en [modelo-memoria.md](../modelo-memoria.md). Una revisión independiente de lectura comprobó aislamiento por escenario, restricciones, relaciones y reversión; se delegaron modelos y pruebas en archivos separados.

## Implementación

- Migración 2026_10_07_160000_create_memory_simulation_tables crea scenarios, memory_configurations, processes, memory_frames, pages, segments y simulation_events, en orden de dependencias.
- Las siete tablas tienen InnoDB, claves primarias, timestamps e índices apropiados.
- La migración exige MySQL 8.0.16 o posterior, donde CHECK es efectivo; rechaza MariaDB y otros drivers antes de crear las tablas.
- Se agregaron siete modelos Eloquent con fillable explícito, casts y relaciones, cinco enums PHP y las relaciones de User con escenarios creados y eventos.
- Configuración única por escenario; números de marco por escenario y números de página/segmento por proceso únicos.
- FK compuestas impiden asociar procesos o marcos de otro escenario. Page.frame_id nullable y único permite páginas en almacenamiento secundario y solo una página por marco.
- frame_count y present se calculan desde sus fuentes; no son columnas duplicadas. Una configuración nueva inválida devuelve frame_count null.
- Los estados y tipos de evento se restringen con ENUM, y las reglas de geometría, tamaños positivos y nombres se validan con CHECK.
- El historial conserva referencias a procesos mediante RESTRICT. Las referencias opcionales a usuarios se anulan al borrar la cuenta.
- Eventos conservan metadata JSON y tiempos con microsegundos. La prueba detectó la pérdida de precisión del formato predeterminado de Eloquent; se corrigió mediante dateFormat y timestamps(6).
- No se agregaron seeders de demostración, escenarios ni procesos a la base principal. No se crearon pantallas u operaciones de configuración en esta fase.

## Verificación

- Pint y sintaxis PHP: correctos. Los archivos PHP y SQL se cargaron durante las pruebas y la migración.
- MemorySchemaTest contiene 43 casos, con 121 assertions finales: reconstrucción de relaciones, enums/casts, tamaños mayores de 2 GB sin asignar memoria física, derivados, unicidades, FK entre escenarios, tamaños/posiciones/nombres inválidos, estados inválidos, historial y borrado de referencias opcionales de usuario.
- Suite completa en memorylab_testing: **84 pruebas aprobadas, 285 assertions**, y un caso condicional omitido por estar habilitado el registro.
- Se probó down/up de la migración en memorylab_testing, con comprobaciones explícitas del nombre de base y de que las tablas del dominio estaban vacías. Desaparecieron y se recrearon las siete tablas; las cantidades de usuarios, roles y permisos se conservaron.
- Tras las pruebas se aplicó exclusivamente la migración nueva en memorylab. Las migraciones de caché y cola siguen pendientes.
- La base principal, MySQL 8.0.44, tiene las siete tablas vacías con InnoDB, **12 FK, 6 CHECK efectivos y 7 restricciones únicas**, además de sus claves primarias.
- La cuenta existente, tres roles y trece permisos se conservaron. La revisión de metadatos no muestra nombres, correos ni credenciales de cuentas.
- Chrome comprobó dashboard académico, los cinco integrantes, tamaños de escritorio/tableta/móvil, actualización Livewire, navegación, etiquetas de los tres roles, pantalla administrativa, logout y redirección de invitados después de aplicar el esquema principal. Se usó una cuenta temporal y se eliminó con sus sesiones y relaciones de roles; las capturas de la Fase 7 se conservaron.
- El dashboard permanece en su estado sin escenario hasta implementar los módulos siguientes. Los assets no cambiaron en esta fase y no se instalaron paquetes.

## Evidencia y fuentes

- [Diseño y diagrama de relaciones](../modelo-memoria.md).
- [Metadatos verificados de la base principal](fase-8-schema.json).
- [CHECK en MySQL 8.0](https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html): reglas locales de fila y límites de sus expresiones.
- [Claves foráneas y NULL en MySQL](https://dev.mysql.com/doc/refman/8.0/en/ansi-diff-foreign-keys.html): semántica de referencias compuestas opcionales.

## Cierre

Fase 8 completada. La siguiente fase del plan es Fase 9, configuración de memoria, pendiente de autorización del usuario.

Commit sugerido: `feat: add MySQL memory simulation schema`.
