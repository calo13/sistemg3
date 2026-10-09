<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DeliverablesController extends Controller
{
    private const ARTIFACTS = [
        'manual' => [
            'path' => 'output/pdf/memorylab-manual-de-uso.pdf',
            'filename' => 'memorylab-manual-de-uso.pdf',
            'mime' => 'application/pdf',
            'title' => 'Manual de uso',
            'summary' => 'Secuencia guiada · PDF',
            'description' => 'Pasos, conceptos académicos, pantallas y resultados esperados para usar y demostrar el simulador.',
        ],
        'informe' => [
            'path' => 'output/pdf/memorylab-informe-tecnico.pdf',
            'filename' => 'memorylab-informe-tecnico.pdf',
            'mime' => 'application/pdf',
            'title' => 'Informe técnico',
            'summary' => '9 páginas · PDF',
            'description' => 'Fundamentos, arquitectura, resultados y conclusiones del proyecto.',
        ],
        'presentacion' => [
            'path' => 'output/presentations/memorylab-presentacion.pptx',
            'filename' => 'memorylab-presentacion.pptx',
            'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'title' => 'Presentación académica',
            'summary' => '12 diapositivas · PowerPoint',
            'description' => 'Material para explicar paginación, segmentación y sus diferencias.',
        ],
        'video' => [
            'path' => 'output/video/memorylab-demostracion.webm',
            'filename' => 'memorylab-demostracion.webm',
            'mime' => 'video/webm',
            'title' => 'Video demostrativo',
            'summary' => '3 minutos · WebM',
            'description' => 'Recorrido con subtítulos en español por la aplicación, con accesos y resultados reales del simulador.',
        ],
    ];

    public function index(): View
    {
        $this->authorizeReader();
        $artifacts = [];
        foreach (self::ARTIFACTS as $key => $definition) {
            $artifacts[$key] = [
                'title' => $definition['title'],
                'summary' => $definition['summary'],
                'description' => $definition['description'],
                'available' => is_file($this->filePath($key)),
            ];
        }

        return view('deliverables.index', [
            'academic' => config('memorylab.academic'),
            'artifacts' => $artifacts,
        ]);
    }

    public function download(string $artifact): BinaryFileResponse
    {
        $this->authorizeReader();
        abort_unless(array_key_exists($artifact, self::ARTIFACTS), 404);
        $definition = self::ARTIFACTS[$artifact];

        $response = response()->download($this->existingFile($artifact), $definition['filename'], [
            'Content-Type' => $definition['mime'],
            'Cache-Control' => 'private, no-store',
        ]);
        $response->setPrivate();

        return $response;
    }

    public function video(): BinaryFileResponse
    {
        $this->authorizeReader();
        $response = response()->file($this->existingFile('video'), [
            'Content-Type' => self::ARTIFACTS['video']['mime'],
            'Cache-Control' => 'private, no-store',
        ]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, self::ARTIFACTS['video']['filename']);
        $response->setPrivate();

        return $response;
    }

    protected function filePath(string $artifact): string
    {
        return base_path(self::ARTIFACTS[$artifact]['path']);
    }

    private function existingFile(string $artifact): string
    {
        $path = $this->filePath($artifact);
        abort_unless(is_file($path), 404);

        return $path;
    }

    private function authorizeReader(): void
    {
        abort_unless(auth()->check(), 403);
        $actor = User::findOrFail(auth()->id());
        foreach ([PermissionName::ViewMemory, PermissionName::ViewTables, PermissionName::ViewSimulations, PermissionName::ViewResults] as $permission) {
            Gate::forUser($actor)->authorize($permission->value);
        }
    }
}
