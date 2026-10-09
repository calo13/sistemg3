# Fase 29 - Informe técnico

Fecha de cierre: **8 de octubre de 2026**.

## Alcance y resultado

La autorización general del usuario permite completar los entregables sin esperar nuevas confirmaciones. Se creó el informe académico de **nueve páginas**, superando el mínimo de cinco solicitado.

Archivo final: [memorylab-informe-tecnico.pdf](../../output/pdf/memorylab-informe-tecnico.pdf).

Fuente editable: [informe-tecnico.md](../informe-tecnico.md). Generador reproducible: [build-report.py](../../scripts/artifacts/build-report.py).

## Contenido

- Portada con Universidad Mariano Gálvez, Ingeniería en Sistemas, Sistemas Operativos 1, Grupo 3 y los cinco nombres/carnés proporcionados.
- Objetivos, alcance educativo y arquitectura Laravel/Livewire/Services/MySQL.
- Diagrama vectorial del dominio y tabla de las siete entidades, restricciones y transacciones.
- Paginación, Hit/Fault, selección de marco, FIFO y ubicación exclusiva RAM/secundaria.
- Traducción lógica/física y validación de base/límite en segmentación.
- Comparación de técnicas y el caso de demanda comprobado.
- Roles y resultados de la verificación integral de Fase 27.
- Evidencia real del uso de IA, correcciones, conclusiones, recorrido de exposición y referencias OSTEP.

Las capturas corresponden al modo presentación y a alta demanda de la aplicación real. El encuadre de indicadores conserva la imagen original. Los ejemplos didácticos se identifican y no se presentan como mediciones de la RAM física del equipo.

## Verificación

- ReportLab generó el documento; pypdf confirmó exactamente **9 páginas**.
- Se comprobaron nombres, carné, cifras de pruebas y eventos mediante extracción de texto.
- Poppler renderizó todas las páginas; se inspeccionaron títulos, párrafos, tablas, imágenes, diagramas y numeración.
- Se corrigió el conector de persistencia de Services y se amplió el encuadre de indicadores de demanda antes de regenerar y revisar.
- Fuentes Arial incrustadas, referencias externas clicables y márgenes conservados.
- El PDF no contiene credenciales. La guía distingue instalación local ejecutada de VM opcional preparada.

El [registro persistente de verificación](informe-tecnico-verificacion.json) conserva las comprobaciones del informe final.

La cifra de **655 pruebas y 4575 aserciones** corresponde al cierre integral de Fase 27, identificado expresamente en el documento. Las verificaciones posteriores de entrega se registran en sus fases.

Commit sugerido: `docs: add academic technical report and reproducible PDF source`.
