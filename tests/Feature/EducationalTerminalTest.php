<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SegmentStatus;
use App\Enums\SimulationEventType;
use App\Livewire\EducationalTerminal;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\User;
use App\Services\EducationalTerminalService;
use App\Services\MemoryConfigurationService;
use App\Services\PagingService;
use App\Services\ProcessManagerService;
use App\Services\SegmentationService;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EducationalTerminalTest extends TestCase
{
    use RefreshDatabase;

    public static function readerRoles(): array
    {
        return ['administrator' => ['administrador'], 'operator' => ['operador'], 'observer' => ['observador']];
    }

    #[DataProvider('readerRoles')]
    public function test_read_commands_return_the_current_simulation_and_never_change_memory(string $role): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 0);
        $actor = $this->user($role);
        $before = $this->domainState();

        foreach (['help', '  MeMoRy   StAtUs  ', 'process list', 'PaGe TABLE cHrOmE'] as $command) {
            $result = $this->execute($actor, $scenario, $command);
            $this->assertSame(trim($command), $result['command']);
            $this->assertSame($scenario->id, $result['scenario_id']);
            $this->assertFalse($result['mutated']);
            $this->assertGreaterThan(0, count($result['lines']));
            $this->assertLessThanOrEqual(100, count($result['lines']));
            foreach ($result['lines'] as $line) {
                $this->assertIsString($line);
                $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $line);
            }
        }
        $table = implode("\n", $this->execute($actor, $scenario, 'page table Chrome')['lines']);
        $this->assertStringContainsString('Página 0 | Marco 0 | Presente: Sí | RAM.', $table);
        $this->assertStringContainsString('Página 1 | Marco — | Presente: No | Almacenamiento secundario simulado.', $table);
        $memory = implode("\n", $this->execute($actor, $scenario, 'memory status')['lines']);
        $this->assertStringContainsString('1024 de 2048 bytes ocupados', $memory);
        $this->assertStringContainsString('3072 de 65536 bytes ocupados', $memory);
        $this->assertSame($before, $this->domainState());
    }

    public static function invalidCommands(): array
    {
        return [
            'empty' => [''], 'blank' => ['   '], 'over length' => ['help'.str_repeat(' ', 157)],
            'unknown OS command' => ['dir'], 'script invocation' => ['php -v'],
            'chained reset' => ['help; reset'], 'shell conditional' => ['memory status && reset'],
            'pipeline' => ['process list | more'], 'selector includes command text' => ['page table Chrome; reset'],
            'missing selector' => ['page table'], 'missing page' => ['request Chrome'],
            'negative page' => ['request Chrome -1'], 'fractional page' => ['request Chrome 1.5'],
            'unsigned page overflow' => ['request Chrome 4294967296'],
            'newline' => ["help\nreset"], 'tab' => ["help\t"], 'NUL' => ["help\0"],
            'invalid UTF8' => ["\xC3\x28"], 'extra reset argument' => ['reset now'],
        ];
    }

    #[DataProvider('invalidCommands')]
    public function test_unknown_or_malformed_commands_are_validation_errors_and_do_not_execute_any_memory_action(string $command): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->execute($admin, $scenario, $command));

        $this->assertSame($before, $this->domainState());
    }

    public function test_raw_command_length_accepts_exactly_one_hundred_sixty_characters_before_trimming(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $before = $this->domainState();

        $result = $this->execute($admin, $scenario, 'help'.str_repeat(' ', 156));

        $this->assertSame('help', $result['command']);
        $this->assertFalse($result['mutated']);
        $this->assertSame($before, $this->domainState());
    }

    public function test_exact_case_insensitive_names_support_spaces_and_unicode_and_id_selectors_resolve_duplicates(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $first = $this->process($admin, $scenario);
        $duplicate = $this->process($admin, $scenario, 'CHROME');
        $editor = $this->process($admin, $scenario, 'VS Code', 1);
        $unicode = $this->process($admin, $scenario, 'Navegación', 1);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->execute($admin, $scenario, 'page table chrome'));
        $this->assertValidationFailure(fn () => $this->execute($admin, $scenario, 'page table Chr'));
        $this->assertStringContainsString('#'.$first->id.' Chrome:', $this->execute($admin, $scenario, 'page table #00'.$first->id)['lines'][0]);
        $this->assertStringContainsString('#'.$duplicate->id.' CHROME:', $this->execute($admin, $scenario, 'page table #'.$duplicate->id)['lines'][0]);
        $this->assertStringContainsString('#'.$editor->id.' VS Code:', $this->execute($admin, $scenario, 'page table vs code')['lines'][0]);
        $this->assertStringContainsString('#'.$unicode->id.' Navegación:', $this->execute($admin, $scenario, 'page table NAVEGACIÓN')['lines'][0]);
        $this->assertSame($before, $this->domainState());
    }

    public function test_process_ids_are_scoped_and_invalid_ids_never_fall_back_to_another_process(): void
    {
        $admin = $this->user('administrador');
        $selected = $this->memory($admin);
        $this->process($admin, $selected);
        $other = $this->memory($admin, ['name' => 'Memoria ajena']);
        $foreign = $this->process($admin, $other, 'Proceso ajeno');
        $before = $this->domainState();

        foreach (['#0', '#18446744073709551616', '#bad', '#'.$foreign->id] as $selector) {
            $this->assertValidationFailure(fn () => $this->execute($admin, $selected, 'page table '.$selector));
            $this->assertValidationFailure(fn () => $this->execute($admin, $selected, 'request '.$selector.' 0'));
        }
        $this->assertSame($before, $this->domainState());
    }

    public function test_page_table_output_stops_at_forty_rows_and_reports_the_remaining_pages(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 'Grande', 42);
        $before = $this->domainState();

        $result = $this->execute($this->user('observador'), $scenario, 'page table #'.$process->id);

        $this->assertCount(42, $result['lines']);
        $text = implode("\n", $result['lines']);
        $this->assertStringContainsString('Página 39 |', $text);
        $this->assertStringNotContainsString('Página 40 |', $text);
        $this->assertStringContainsString('quedan 2 filas', $text);
        $this->assertFalse($result['mutated']);
        $this->assertSame($before, $this->domainState());
    }

    public function test_operator_requests_perform_fault_hit_and_fifo_with_real_events_and_frame_zero_addresses(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $operator = $this->user('operador');
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00.000001', 'UTC'));
        $fault = $this->execute($operator, $scenario, 'request chrome 000');
        $firstPage = $process->pages()->where('page_number', 0)->firstOrFail();
        $firstLoadedAt = $firstPage->loaded_at->format('Y-m-d H:i:s.u');
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00.000002', 'UTC'));
        $this->execute($operator, $scenario, 'request Chrome 1');
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00.000003', 'UTC'));
        $hit = $this->execute($operator, $scenario, 'request #'.$process->id.' 0');
        $this->assertSame($firstLoadedAt, $firstPage->fresh()->loaded_at->format('Y-m-d H:i:s.u'));
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00.000004', 'UTC'));
        $replacement = $this->execute($operator, $scenario, 'request Chrome 2');
        $this->travelBack();

        $this->assertTrue($fault['mutated']);
        $this->assertCount(10, $fault['lines']);
        $this->assertStringContainsString('PAGE_FAULT. Marco 0;', implode("\n", $fault['lines']));
        $this->assertStringContainsString('dirección física inicial 0 bytes', implode("\n", $fault['lines']));
        $this->assertCount(6, $hit['lines']);
        $this->assertStringContainsString('PAGE_HIT. Marco 0;', implode("\n", $hit['lines']));
        $this->assertStringContainsString('FIFO: página 0 de #'.$process->id, implode("\n", $replacement['lines']));
        $this->assertNull($firstPage->fresh()->frame_id);
        $this->assertNull($firstPage->fresh()->loaded_at);
        $this->assertSame('2026-10-08 12:00:00.000001', $firstLoadedAt);
        $this->assertSame(4, $scenario->events()->where('type', SimulationEventType::PageRequest->value)->count());
        $this->assertSame(3, $scenario->events()->where('type', SimulationEventType::PageFault->value)->count());
        $this->assertSame(3, $scenario->events()->where('type', SimulationEventType::PageLoaded->value)->count());
        $this->assertSame(1, $scenario->events()->where('type', SimulationEventType::PageHit->value)->count());
        $this->assertSame(ProcessStatus::Running, $process->fresh()->status);
        $this->assertSame(ScenarioStatus::Running, $scenario->fresh()->status);
    }

    public function test_terminal_reset_uses_the_canonical_service_and_invalidates_pending_requests(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 0);
        $pending = app(PagingService::class)->beginRequest($admin, $scenario->id, $process->id, 3);
        $eventCount = $scenario->events()->count();
        $frameIds = $scenario->frames()->pluck('id')->all();

        $result = $this->execute($admin, $scenario, 'ReSeT');

        $this->assertTrue($result['mutated']);
        $this->assertStringContainsString('Páginas liberadas: 4.', implode("\n", $result['lines']));
        $this->assertSame(0, $scenario->pages()->count());
        $this->assertSame($frameIds, $scenario->frames()->pluck('id')->all());
        $this->assertSame(ProcessStatus::Terminated, $process->fresh()->status);
        $this->assertSame($eventCount + 1, $scenario->events()->count());
        $this->assertSame(SimulationEventType::MemoryReset, $scenario->events()->latest('id')->firstOrFail()->type);
        $before = $this->domainState();
        $this->assertValidationFailure(fn () => app(PagingService::class)->resolveRequest($admin, $pending['request_event_id']), 'request_event_id');
        $this->assertValidationFailure(fn () => $this->execute($admin, $scenario, 'request Chrome 0'));
        $this->assertSame($before, $this->domainState());
    }

    public function test_segmentation_supports_reads_and_reset_but_not_paging_commands(): void
    {
        $admin = $this->user('administrador');
        $scenario = app(SegmentationService::class)->configure($admin, ['name' => 'Memoria segmentada', 'ram_kb' => 16]);
        $process = app(SegmentationService::class)->createProcess($admin, $scenario->id, ['name' => 'Editor', 'size_kb' => 4]);
        $segment = app(SegmentationService::class)->createSegment($admin, $scenario->id, $process->id, ['name' => 'Codigo', 'base' => 1000, 'size_bytes' => 1200]);
        $observer = $this->user('observador');
        $before = $this->domainState();

        $this->assertFalse($this->execute($observer, $scenario, 'help')['mutated']);
        $this->assertStringContainsString('1200 de 16384 bytes ocupados', implode("\n", $this->execute($observer, $scenario, 'memory status')['lines']));
        $this->assertStringContainsString('Editor', implode("\n", $this->execute($observer, $scenario, 'process list')['lines']));
        $this->assertValidationFailure(fn () => $this->execute($admin, $scenario, 'page table Editor'));
        $this->assertValidationFailure(fn () => $this->execute($admin, $scenario, 'request Editor 0'));
        $this->assertSame($before, $this->domainState());

        $this->assertTrue($this->execute($admin, $scenario, 'reset')['mutated']);
        $this->assertSame(SegmentStatus::Released, $segment->fresh()->status);
        $this->assertSame(ProcessStatus::Terminated, $process->fresh()->status);
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('memory_frames', 0);
    }

    public static function missingReadPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], ['memory.view', 'tables.view', 'simulations.view', 'results.view']);
    }

    #[DataProvider('missingReadPermissions')]
    public function test_recognized_commands_and_the_route_require_each_read_permission(string $missing): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff(['memory.view', 'tables.view', 'simulations.view', 'results.view'], [$missing])));
        $before = $this->domainState();

        try {
            $this->execute($actor, $scenario, 'help');
            $this->fail('Todos los permisos de consulta son necesarios para usar la terminal.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
        $this->actingAs($actor)->get(route('terminal.index'))->assertForbidden();
        Livewire::actingAs($actor)->test(EducationalTerminal::class)->assertForbidden();
    }

    public static function missingWritePermissions(): array
    {
        return [
            'request pages' => ['request Chrome 0', 'pages.request'],
            'execute request' => ['request Chrome 0', 'simulations.execute'],
            'reset memory' => ['reset', 'memory.reset'],
            'execute reset' => ['reset', 'simulations.execute'],
        ];
    }

    #[DataProvider('missingWritePermissions')]
    public function test_write_commands_require_their_own_permissions_in_addition_to_reading(string $command, string $missing): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario);
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff([
            'memory.view', 'tables.view', 'simulations.view', 'results.view', 'pages.request', 'simulations.execute', 'memory.reset',
        ], [$missing])));
        $before = $this->domainState();

        try {
            $this->execute($actor, $scenario, $command);
            $this->fail('Consultar una terminal no concede sus permisos de mutacion.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_invalid_memory_and_missing_scenarios_do_not_produce_a_fake_success(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $scenario->configuration->delete();
        $before = $this->domainState();
        $this->assertValidationFailure(fn () => $this->execute($admin, $scenario, 'memory status'));

        try {
            app(EducationalTerminalService::class)->execute($admin, 999999, 'help');
            $this->fail('Los comandos requieren un escenario existente.');
        } catch (ModelNotFoundException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    #[DataProvider('readerRoles')]
    public function test_all_roles_can_use_the_terminal_form_for_reading(string $role): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $actor = $this->user($role);
        $before = $this->domainState();

        $this->actingAs($actor)->get(route('terminal.index'))->assertOk();
        Livewire::actingAs($actor)->test(EducationalTerminal::class)->set('scenarioId', $scenario->id)
            ->assertViewHas('canRequest', $role !== 'observador')->assertViewHas('canReset', $role === 'administrador')
            ->set('command', 'help')->call('execute')->assertHasNoErrors()->assertSet('command', '')
            ->assertSet('result.mutated', false)->assertNotDispatched('memory-updated');

        $this->assertSame($before, $this->domainState());
    }

    public function test_observer_forged_request_and_operator_reset_are_forbidden(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario);
        $before = $this->domainState();

        Livewire::actingAs($this->user('observador'))->test(EducationalTerminal::class)->set('scenarioId', $scenario->id)
            ->set('command', 'request Chrome 0')->call('execute')->assertForbidden();
        Livewire::actingAs($this->user('operador'))->test(EducationalTerminal::class)->set('scenarioId', $scenario->id)
            ->set('command', 'reset')->call('execute')->assertForbidden();

        $this->assertSame($before, $this->domainState());
    }

    public function test_operator_form_executes_real_requests_and_dispatches_memory_updates(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);

        Livewire::actingAs($this->user('operador'))->test(EducationalTerminal::class)->set('scenarioId', $scenario->id)
            ->set('command', 'request Chrome 0')->call('execute')->assertHasNoErrors()->assertSet('command', '')
            ->assertSet('result.mutated', true)->assertSee('PAGE_FAULT')->assertDispatched('memory-updated');

        $this->assertSame(1, $process->pages()->whereNotNull('frame_id')->count());
        $this->assertSame(1, $scenario->events()->where('type', SimulationEventType::PageFault->value)->count());
    }

    public function test_ui_keeps_only_the_last_hundred_lines_and_clear_changes_no_simulation_rows(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(EducationalTerminal::class)->set('scenarioId', $scenario->id)
            ->set('command', 'memory status')->call('execute')->assertHasNoErrors();
        for ($number = 0; $number < 12; $number++) {
            $component->set('command', 'help')->call('execute')->assertHasNoErrors();
        }
        $lines = $component->instance()->lines;

        $this->assertCount(100, $lines);
        $this->assertStringNotContainsString('> memory status', implode("\n", $lines));
        $this->assertContains('> help', $lines);
        $component->call('clear')->assertSet('lines', [])->assertSet('result', null)->assertSet('command', '');
        $this->assertSame($before, $this->domainState());
    }

    public function test_console_output_is_escaped_and_control_characters_in_model_names_are_normalized(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $unsafe = '<img src=x onerror="alert(1)">';
        $this->process($admin, $scenario, $unsafe, 1);
        $this->process($admin, $scenario, "Nombre\ncon\tcontroles", 1);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(EducationalTerminal::class)->set('scenarioId', $scenario->id)
            ->set('command', 'process list')->call('execute')->assertHasNoErrors()->assertSee($unsafe);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8" ?>'.$component->html(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        $this->assertCount(0, $xpath->query('//*[@data-terminal-output]//img | //*[@data-terminal-output]//script'));
        $this->assertStringContainsString($unsafe, $xpath->query('//*[@data-terminal-output]')->item(0)->textContent);
        foreach ($component->instance()->result['lines'] as $line) {
            $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $line);
        }
        $this->assertSame($before, $this->domainState());
    }

    public function test_selection_change_clears_console_and_switches_session_without_running_a_command(): void
    {
        $admin = $this->user('administrador');
        $first = $this->memory($admin);
        $second = $this->memory($admin, ['name' => 'Segunda memoria']);
        session(['memorylab.active_scenario_id' => $first->id]);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(EducationalTerminal::class)->assertSet('scenarioId', $first->id)
            ->set('command', 'help')->call('execute')->assertHasNoErrors();

        $component->set('scenarioId', $second->id)->assertSet('lines', [])->assertSet('result', null)->assertSet('command', '');

        $this->assertSame($second->id, session('memorylab.active_scenario_id'));
        $this->assertSame($before, $this->domainState());
    }

    public static function lockedOutput(): array
    {
        return ['lines' => ['lines'], 'result' => ['result']];
    }

    #[DataProvider('lockedOutput')]
    public function test_client_cannot_forge_console_lines_or_command_results(string $field): void
    {
        $component = Livewire::actingAs($this->user('administrador'))->test(EducationalTerminal::class);
        $before = $this->domainState();

        try {
            $component->set($field, ['Fabricado por el cliente']);
            $this->fail('La salida de la terminal se genera solamente en el servidor.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_revoked_operator_and_reader_permissions_are_checked_again_before_actions(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario);
        $operator = $this->user('operador');
        $component = Livewire::actingAs($operator)->test(EducationalTerminal::class)->set('scenarioId', $scenario->id);
        $this->assertTrue($operator->can('pages.request'));
        $operator->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        try {
            $this->execute($operator, $scenario, 'request Chrome 0');
            $this->fail('El permiso previo no debe permitir una nueva solicitud.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
        $component->set('command', 'request Chrome 0')->call('execute')->assertForbidden();
        $component = Livewire::test(EducationalTerminal::class)->set('scenarioId', $scenario->id);
        $operator->fresh()->syncRoles([]);
        $component->call('clear')->assertForbidden();
        try {
            $this->execute($operator, $scenario, 'help');
            $this->fail('La consulta tambien requiere permisos actuales.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_form_validates_context_and_command_errors_without_a_fallback_scenario(): void
    {
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(EducationalTerminal::class)
            ->set('command', 'help')->call('execute')->assertHasErrors(['scenarioId']);
        $this->assertSame($before, $this->domainState());
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $before = $this->domainState();

        $component->set('scenarioId', $scenario->id)->set('command', 'help; reset')->call('execute')
            ->assertHasErrors(['command'])->assertSet('lines', [])->assertSet('result', null);
        $component->set('command', str_repeat('x', 161))->call('execute')->assertHasErrors(['command']);
        $this->assertSame($before, $this->domainState());
    }

    public function test_terminal_route_requires_authentication(): void
    {
        $this->get(route('terminal.index'))->assertRedirect(route('login'));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function memory(User $actor, array $input = []): Scenario
    {
        return app(MemoryConfigurationService::class)->configure($actor, null, array_replace([
            'name' => 'Memoria de terminal', 'ram_kb' => 2, 'page_kb' => 1, 'secondary_kb' => 64,
        ], $input));
    }

    private function process(User $actor, Scenario $scenario, string $name = 'Chrome', int $sizeKb = 4): Process
    {
        return app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => $name, 'size_kb' => $sizeKb]);
    }

    private function execute(User $actor, Scenario $scenario, string $command): array
    {
        return app(EducationalTerminalService::class)->execute($actor, $scenario->id, $command);
    }

    private function domainState(): array
    {
        $state = [];
        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }

    private function assertValidationFailure(callable $operation, string $field = 'command'): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());

            return;
        }

        $this->fail('El comando debe rechazarse mediante validacion.');
    }
}
