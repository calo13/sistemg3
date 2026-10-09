<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Http\Controllers\DeliverablesController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DeliverablesTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    /** @var array<string,string> */
    private array $fixturePaths;

    private const VIDEO_BYTES = '0123456789abcdefghijklmnopqrst';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'memorylab-deliverables-'.Str::uuid();
        mkdir($this->fixtureRoot);
        $this->fixturePaths = [
            'manual' => $this->fixtureRoot.DIRECTORY_SEPARATOR.'manual.pdf',
            'informe' => $this->fixtureRoot.DIRECTORY_SEPARATOR.'informe.pdf',
            'presentacion' => $this->fixtureRoot.DIRECTORY_SEPARATOR.'presentacion.pptx',
            'video' => $this->fixtureRoot.DIRECTORY_SEPARATOR.'video.webm',
        ];
        file_put_contents($this->fixturePaths['manual'], "%PDF-1.4\nMANUAL DE PRUEBA\n%%EOF\n");
        file_put_contents($this->fixturePaths['informe'], "%PDF-1.4\nINFORME DE PRUEBA\n%%EOF\n");
        file_put_contents($this->fixturePaths['presentacion'], 'PRESENTACION DE PRUEBA');
        file_put_contents($this->fixturePaths['video'], self::VIDEO_BYTES);

        $this->app->instance(DeliverablesController::class, new class($this->fixturePaths) extends DeliverablesController
        {
            public function __construct(private array $paths) {}

            protected function filePath(string $artifact): string
            {
                return $this->paths[$artifact];
            }
        });
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->fixturePaths ?? [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (isset($this->fixtureRoot) && is_dir($this->fixtureRoot)) {
                rmdir($this->fixtureRoot);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_guests_are_redirected_to_login_for_the_page_downloads_and_inline_video(): void
    {
        foreach ($this->endpoints() as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public static function roles(): array
    {
        return [
            'administrator' => [RoleName::Administrator],
            'operator' => [RoleName::Operator],
            'observer' => [RoleName::Observer],
        ];
    }

    #[DataProvider('roles')]
    public function test_each_role_can_read_the_academic_page_and_all_fixed_files_without_domain_changes(RoleName $role): void
    {
        $this->actingAs($this->user($role));
        $before = $this->domainSignature();

        $page = $this->get(route('deliverables.index'))->assertOk()
            ->assertSee('Manual de uso')->assertSee('Cómo usar y demostrar MemoryLab')->assertSee('Secuencia guiada')
            ->assertSee('Entregables académicos')->assertSee('Informe técnico')->assertSee('12 diapositivas')
            ->assertSee('Video demostrativo')->assertSeeHtml('data-deliverable-video')
            ->assertSeeHtml('aria-current="page"')->assertDontSee('Preparación temporal')
            ->assertDontSee('output/pdf/')->assertDontSee('output/video/');
        $page->assertSeeInOrder(['Manual de uso', 'Informe técnico', 'Presentación académica', 'Video demostrativo']);
        foreach (config('memorylab.academic.members') as $member) {
            $page->assertSee($member['name'])->assertSee($member['carnet']);
        }
        $page->assertSee(config('memorylab.academic.university'))->assertSee(config('memorylab.academic.degree'));
        foreach (array_slice($this->endpoints(), 1) as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        }

        $this->assertSame($before, $this->domainSignature());
    }

    public static function readPermissions(): array
    {
        return [
            'memory' => ['memory.view'], 'tables' => ['tables.view'],
            'simulations' => ['simulations.view'], 'results' => ['results.view'],
        ];
    }

    #[DataProvider('readPermissions')]
    public function test_every_endpoint_requires_each_read_permission_after_revocation(string $revoked): void
    {
        $actor = $this->user(RoleName::Observer);
        $actor->load('roles.permissions', 'permissions');
        $this->actingAs($actor);
        $currentActor = $actor->fresh();
        $currentActor->syncRoles([]);
        $currentActor->syncPermissions(array_values(array_diff([
            'memory.view', 'tables.view', 'simulations.view', 'results.view',
        ], [$revoked])));
        $before = $this->domainSignature();

        foreach ($this->endpoints() as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->assertSame($before, $this->domainSignature());
    }

    public static function artifacts(): array
    {
        return [
            'manual' => ['manual', 'application/pdf', 'memorylab-manual-de-uso.pdf'],
            'report' => ['informe', 'application/pdf', 'memorylab-informe-tecnico.pdf'],
            'slides' => ['presentacion', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'memorylab-presentacion.pptx'],
            'video' => ['video', 'video/webm', 'memorylab-demostracion.webm'],
        ];
    }

    #[DataProvider('artifacts')]
    public function test_downloads_use_the_fixed_file_mime_and_attachment_filename(string $key, string $mime, string $filename): void
    {
        $this->actingAs($this->user(RoleName::Observer));

        $response = $this->get(route('deliverables.download', ['artifact' => $key]))
            ->assertOk()->assertHeader('Content-Type', $mime);

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(realpath($this->fixturePaths[$key]), realpath($response->baseResponse->getFile()->getPathname()));
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringContainsString($filename, $disposition);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    #[DataProvider('artifacts')]
    public function test_missing_artifact_is_marked_as_preparing_and_download_returns_404(string $key, string $mime, string $filename): void
    {
        unlink($this->fixturePaths[$key]);
        clearstatcache(true, $this->fixturePaths[$key]);
        $this->actingAs($this->user(RoleName::Observer));

        $this->get(route('deliverables.index'))->assertOk()
            ->assertSeeHtml('data-deliverable-pending="'.$key.'"')->assertSee('Preparación temporal')
            ->assertDontSee(route('deliverables.download', ['artifact' => $key]));
        $this->get(route('deliverables.download', ['artifact' => $key]))->assertNotFound();
        if ($key === 'video') {
            $this->get(route('deliverables.video'))->assertNotFound();
        }
    }

    public function test_page_can_render_before_any_of_the_artifacts_exist(): void
    {
        foreach ($this->fixturePaths as $path) {
            unlink($path);
            clearstatcache(true, $path);
        }
        $this->actingAs($this->user(RoleName::Observer));

        $this->get(route('deliverables.index'))->assertOk()
            ->assertViewHas('artifacts', fn ($artifacts) => count($artifacts) === 4
                && array_filter($artifacts, fn ($artifact) => $artifact['available']) === [])
            ->assertSeeHtml('data-deliverable-pending="manual"')
            ->assertSeeHtml('data-deliverable-pending="informe"')
            ->assertSeeHtml('data-deliverable-pending="presentacion"')
            ->assertSeeHtml('data-deliverable-pending="video"')
            ->assertDontSeeHtml('data-deliverable-video');
    }

    public static function invalidArtifacts(): array
    {
        return [
            'unknown key' => ['unknown'], 'environment filename' => ['.env'],
            'parent directory' => ['%2e%2e'], 'encoded traversal' => ['%2e%2e%2f%2eenv'],
            'absolute path' => ['C%3A%5CUsers%5C.env'], 'uppercase key' => ['INFORME'],
            'manual traversal' => ['manual%2f..%2f.env'],
        ];
    }

    #[DataProvider('invalidArtifacts')]
    public function test_unknown_keys_and_traversal_are_not_download_routes(string $value): void
    {
        $this->actingAs($this->user(RoleName::Observer));

        $this->get('/entregables/descargar/'.$value)->assertNotFound();
    }

    public function test_path_query_parameters_cannot_replace_the_whitelisted_download(): void
    {
        $this->actingAs($this->user(RoleName::Observer));
        $url = route('deliverables.download', ['artifact' => 'informe']).'?path=.env&file=../../.env';

        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame(realpath($this->fixturePaths['informe']), realpath($response->baseResponse->getFile()->getPathname()));
        $this->assertSame(file_get_contents($this->fixturePaths['informe']), file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    public function test_inline_video_uses_webm_disposition_and_supports_byte_ranges(): void
    {
        $this->actingAs($this->user(RoleName::Observer));

        $response = $this->get(route('deliverables.video'), ['Range' => 'bytes=2-6'])
            ->assertStatus(206)->assertHeader('Content-Type', 'video/webm')
            ->assertHeader('Accept-Ranges', 'bytes')->assertHeader('Content-Length', '5')
            ->assertHeader('Content-Range', 'bytes 2-6/'.strlen(self::VIDEO_BYTES));

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('memorylab-demostracion.webm', $response->headers->get('Content-Disposition'));
        ob_start();
        try {
            $response->baseResponse->sendContent();
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertSame('23456', $bytes);
    }

    public function test_unsatisfiable_video_range_returns_416_with_its_actual_size(): void
    {
        $this->actingAs($this->user(RoleName::Observer));

        $this->get(route('deliverables.video'), ['Range' => 'bytes=999-1000'])
            ->assertStatus(416)->assertHeader('Content-Range', 'bytes */'.strlen(self::VIDEO_BYTES));
    }

    /** @return list<string> */
    private function endpoints(): array
    {
        return [
            route('deliverables.index'),
            route('deliverables.download', ['artifact' => 'manual']),
            route('deliverables.download', ['artifact' => 'informe']),
            route('deliverables.download', ['artifact' => 'presentacion']),
            route('deliverables.download', ['artifact' => 'video']),
            route('deliverables.video'),
        ];
    }

    private function user(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    /** @return array<string,string> */
    private function domainSignature(): array
    {
        $signatures = [];
        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $signatures[$table] = hash('sha256', json_encode(DB::table($table)->orderBy('id')->get()->all(), JSON_THROW_ON_ERROR));
        }

        return $signatures;
    }
}
