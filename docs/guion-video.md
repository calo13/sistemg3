# MemoryLab: guion del video de demostración

**Duración:** 3 minutos. **Formato:** WebM, 1280 × 720, subtítulos en español.

El video muestra capturas nuevas de la aplicación Laravel/Livewire en funcionamiento. Cada imagen proviene de una acción real sobre un par de escenarios de demostración creado mediante los servicios del sistema. Las credenciales se utilizan antes de comenzar las capturas y no aparecen en el video. Los escenarios, eventos, cuenta y sesiones temporales se eliminan al terminar la captura.

| Tiempo | Pantalla y acción | Explicación |
|---|---|---|
| 00:00–00:08 | Panel académico | Universidad Mariano Gálvez, Ingeniería en Sistemas, Sistemas Operativos 1, Grupo 3. |
| 00:08–00:14 | Integrantes | Presentación de los cinco integrantes y sus carnés. |
| 00:14–00:24 | Tabla de Chrome y RAM | RAM de 16 KB, páginas de 1 KB, cuatro páginas del proceso. P0/P1 presentes; P2/P3 en DISCO. |
| 00:24–00:34 | Solicitar Chrome P0 | PAGE_HIT: marco 0; no carga adicional ni aumento de fallos. |
| 00:34–00:42 | Paso a paso: solicitar P3 | Se registra PAGE_REQUEST; la RAM conserva cinco marcos ocupados. |
| 00:42–00:50 | Paso 3 | La tabla confirma P3 ausente. |
| 00:50–00:58 | Paso 5 | Buscar un marco libre; explicar FIFO cuando la RAM se llena. |
| 00:58–01:08 | Paso 6 | Cargar P3 en el marco 5; registrar PAGE_FAULT y PAGE_LOADED. |
| 01:08–01:16 | Paso 8 | Acceso completado y tabla actualizada. |
| 01:16–01:26 | RAM y secundaria | Distinguir marcos ocupados y páginas no residentes. |
| 01:26–01:38 | Traducir 3500 bytes | Página 3, offset 428, marco 5: dirección física 5548. |
| 01:38–01:48 | Tabla y mapa de segmentos | Código, Datos, Stack y Heap del proceso Editor. |
| 01:48–01:58 | Código, offset 100 | Base 1000 + offset 100 = dirección física 1100. |
| 01:58–02:10 | Código, offset 1200 | SEGMENTATION_FAULT: offset fuera del límite; RAM sin cambio. |
| 02:10–02:22 | Comparar solicitud de 7168 bytes | Contigua falla; paginación y segmentación aprovechan huecos separados. |
| 02:22–02:32 | Comparar solicitud de 7500 bytes | Ocho páginas reservan 8192 bytes: 692 bytes de fragmentación interna. |
| 02:32–02:42 | Terminal educativa | `memory status`, `page table chrome`, `request chrome 3`; el último acceso es HIT. |
| 02:42–02:50 | Historial filtrado por Chrome | Evidencia de solicitudes, HIT y Page Fault con actor y fecha. |
| 02:50–03:00 | Presentación y conclusión | Relacionar tabla/marcos, base/límite y aprovechamiento de la RAM. |

La demostración describe memoria simulada. La traducción y comparación consultan o calculan sin cargar páginas. El modo paso a paso cambia la RAM al confirmar la carga en el paso 6. La explicación de FIFO acompaña la regla del simulador; esta secuencia usa un marco libre y no presenta un reemplazo inexistente.

## Reproducir la construcción

Con la aplicación local en `http://localhost/sistemg3/public`, PHP de XAMPP (`C:\xampp\php\php.exe`) y Chrome (`C:\Program Files\Google\Chrome\Application\chrome.exe`) disponibles. El helper CDP entregado está en `scripts/artifacts/browser-client.mjs`:

```powershell
node scripts/artifacts/build-video.mjs --probe
node scripts/artifacts/build-video.mjs
```

El archivo final es `output/video/memorylab-demostracion.webm`. `output/video/verificar-video.html` permite reproducirlo. El constructor conserva las capturas y `storyboard.json`; `verificacion.json` registra duración, resolución, códec, tamaño y comprobaciones de reproducción. Para volver a codificar las mismas capturas, sin acceder a la base de datos, se puede ejecutar:

```powershell
node scripts/artifacts/build-video.mjs --encode-only
```

Para repetir solamente la verificación del archivo exportado:

```powershell
node scripts/artifacts/build-video.mjs --verify-only
```

La codificación utiliza [MediaRecorder](https://developer.mozilla.org/en-US/docs/Web/API/MediaRecorder) con un canvas de 1280 × 720. La duración finita se guarda en el elemento Duration de Info, según la [especificación de Matroska/WebM](https://www.matroska.org/technical/elements.html). Se verifican metadatos, reproducción y fotogramas del video exportado, no sólo las imágenes fuente.
