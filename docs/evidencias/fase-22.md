# Fase 22 — Demostración y reinicio explícito

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases en orden sin nuevas confirmaciones. La Fase 22 prepara datos conocidos para explicar Hit, Fault y Segmentation Fault, y agrega acciones explícitas para liberar memoria conservando configuración e historial.

La implementación se revisó y verificó con pruebas del servicio/UI, regresiones de paginación y navegador.

## Comportamiento implementado

- SimulationService prepara un par nuevo marcado is_demo mediante los servicios existentes, en una transacción.
- Paginación: RAM 16 KB, página 1 KB, 16 marcos y secundaria 64 KB. Procesos Chrome 4 KB, Spotify 3 KB, VS Code 6 KB y MemoryLab 2 KB.
- Cinco páginas se cargan con accesos reales: Chrome P0/P1, Spotify P0 y VS Code P0/P1. Chrome P0 permite un Hit; P3 comienza ausente y permite un Fault; repetir P3 después de cargarla permite un Hit.
- Segmentación: RAM 16 KB, Editor de 4 KB y segmentos Código con base 1000 y tamaño 1200, Datos con base 4000 y tamaño 800, Stack con base 7000 y tamaño 600, y Heap con base 9000 y tamaño 1000. Suman 3600 bytes. Código con offset 100 calcula 1100; offset 1200 produce fallo.
- Iniciar crea escenarios nuevos y conserva los manuales anteriores. Los IDs y UUID se producen en servidor y la sesión guarda explícitamente el par después del éxito.
- Preparación y reinicio exigen los permisos del catálogo; Administrador los tiene. Operador ejecuta accesos sobre escenarios preparados y Observador consulta.
- Liberar memoria requiere un ID explícito, memory.reset, simulations.execute y las tres consultas. Solo acepta escenario PAGING/SEGMENTATION configurado en READY/RUNNING.
- La operación elimina pages, marca segmentos activos como RELEASED y procesos como TERMINATED. Conserva escenario, configuración, marcos, procesos y eventos anteriores, deja READY y registra un MEMORY_RESET con actor y conteos.
- Un reinicio repetido conserva los datos y registra una acción explícita de conteos cero. Page Faults permanece acumulado; los procesos y segmentos retenidos siguen contando contra sus límites.
- Los pendientes PAGE_REQUEST anteriores a MEMORY_RESET se rechazan. Una petición ya resuelta puede devolver su resultado histórico sin asignar memoria otra vez.
- Reiniciar demostración bloquea ambos escenarios antiguos en orden ascendente, los libera y marca COMPLETED, y prepara un par nuevo. La transacción revierte todas las partes si una falla.
- demo y resetResult están protegidos por Locked. Si se libera un escenario del par local, se limpian tarjeta y sesión; un escenario sin procesos activos no se muestra como demo preparada.
- Se reutilizan tablas y tipos de evento existentes. No hay migración ni reinicio automático por nombre o al consultar la pantalla.

## Revisión de lectura

Se comprobaron preservación de referencias históricas, capacidad secundaria, bloqueo ordenado, atomicidad y rechazo de solicitudes pendientes tras reset. También se corrigió la presentación de una demo que había sido liberada manualmente: sus instrucciones iniciales ya no se mantienen como si sus procesos siguieran activos.

## Verificación

- Demostración/reinicio: **54 pruebas aprobadas**. Regresión de paginación: **21 pruebas aprobadas**.
- Resultado conjunto: **75 pruebas aprobadas, 364 assertions**, en 19,94 segundos.
- Chrome comprobó que Administrador preparó el par real: paginación con **16 marcos**, **cinco páginas en RAM** y **diez en secundaria**.
- Chrome P0 produjo Hit y P3 produjo Fault con carga real. Reiniciar generó IDs nuevos y dejó ambos escenarios anteriores en COMPLETED, preservando sus datos históricos.
- Liberar el nuevo escenario de paginación dejó **cero páginas**, conservando configuración, marcos, procesos y eventos según el contrato.
- Operador y Observador no recibieron botones administrativos de preparación o reinicio; consultaron las demostraciones guardadas.
- Se comprobaron anchos de 1366, 768, 390 y 320 píxeles sin errores JavaScript o de recursos.
- Los cuatro escenarios temporales del par inicial y nuevo, y el usuario de comprobación, se limpiaron al finalizar.

## Capturas

- [Demostración en escritorio](fase-22-demostracion-escritorio.png).
- [Demostración en móvil](fase-22-demostracion-movil.png).

## Uso y continuación

Administrador abre Demostración y pulsa Iniciar demostración. Los enlaces abren paginación y segmentación con el escenario elegido; el proceso se selecciona en cada módulo. Reiniciar demostración prepara una generación nueva y conserva la anterior como historial. Para un escenario manual, Finalizar procesos y liberar memoria ejecuta la liberación indicada por su etiqueta.

Fase 22 completada con pruebas y navegador verificados. Continúa la Fase 23, historial, según la autorización general.

Commit sugerido: `feat: prepare memory demonstrations and preserve history on reset`.
