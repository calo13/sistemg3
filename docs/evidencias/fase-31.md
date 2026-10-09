# Fase 31 - Video demostrativo

Fecha de cierre: **8 de octubre de 2026**.

## Alcance y resultado

Se completó el video de **180,000 segundos**, en formato **WebM VP9, 1280 × 720**, con subtítulos en español. El archivo final es [memorylab-demostracion.webm](../../output/video/memorylab-demostracion.webm); también se reproduce y descarga mediante `/entregables`, con autenticación y permisos de consulta.

La secuencia utiliza **19 escenas y 33 capturas nuevas de la aplicación real**. El [guion](../guion-video.md) identifica los tiempos y resultados esperados: equipo académico, tabla y RAM de Chrome, Page Hit, Page Fault paso a paso, traducción de direcciones, segmentos, acceso válido, Segmentation Fault, comparación, terminal, historial y modo presentación.

El acceso a Chrome P3 conserva RAM hasta el paso de carga y ocupa el marco libre 5 al resolver el Fault. La traducción posterior de 3500 bytes obtiene página 3, offset 428 y dirección física **5548**. El acceso al segmento Código con offset 100 obtiene **1100**; offset 1200 alcanza su límite y genera **Segmentation Fault**. La explicación de FIFO describe la regla implementada; esta captura utiliza un marco libre y no presenta un reemplazo inexistente.

## Verificación del archivo exportado

[verificacion.json](../../output/video/verificacion.json) registra duración finita, resolución, códec, tamaño, reproducción verificada y limpieza de datos temporales. Se comprobaron búsqueda temporal y reproducción del WebM exportado, y se extrajeron **nueve fotogramas** en los segundos 2, 46, 62, 93, 122, 145, 157, 168 y 176. La revisión final inspeccionó además los fotogramas del segundo 62 —carga en el paso 6—, 93 —traducción física 5548— y 122 —Segmentation Fault—.

El archivo final tiene **5.804.448 bytes**. Su SHA-256 es:

`df54389410846b2e0ae708567eaedb35cf16a54fbde1cb135d35060d71cd63ea`

Las firmas de las siete tablas del dominio y de los usuarios se conservaron tras la captura y limpieza. Los escenarios, cuenta y sesiones temporales utilizados para grabar se retiraron; las credenciales no aparecen en las imágenes.

## Reproducibilidad

La construcción se conserva en [build-video.mjs](../../scripts/artifacts/build-video.mjs), con el helper [browser-client.mjs](../../scripts/artifacts/browser-client.mjs), el guion, storyboard y capturas. El guion describe las opciones para construir, volver a codificar las capturas y verificar el archivo exportado.

Esta verificación del video es posterior e independiente de la suite integral histórica de Fase 27. El portal tiene comprobaciones adicionales de descarga y rangos HTTP en [entregables.md](entregables.md). Tras completar el manual adicional, la revisión conjunta del portal confirmó descargas para los tres roles y reproducción HTTP del video con metadatos, búsqueda temporal y duración correctos.
