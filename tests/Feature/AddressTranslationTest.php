<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Livewire\AddressTranslator;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\AddressTranslationService;
use App\Services\MemoryConfigurationService;
use App\Services\ProcessManagerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddressTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_logical_address_is_split_and_mapped_to_the_current_frame_without_writes(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 2);
        $this->assign($scenario, $process, 1, 5);
        $before = $this->domainState();

        $result = $this->translate($this->user('observador'), $scenario, $process, 1500);

        $this->assertSame([
            'scenario_id' => $scenario->id, 'process_id' => $process->id,
            'logical_address' => 1500, 'page_number' => 1, 'offset' => 476,
            'page_size_bytes' => 1024, 'frame_number' => 5, 'present' => true,
            'physical_address' => 5596, 'process_size_bytes' => 2048,
        ], $result);
        $this->assertSame($before, $this->domainState());
    }

    public function test_first_and_last_process_bytes_remain_readable_for_completed_and_terminated_records(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['page_kb' => 2]);
        $process = $this->process($admin, $scenario, 3);
        $this->assign($scenario, $process, 0, 1);
        $this->assign($scenario, $process, 1, 3);
        $scenario->update(['status' => ScenarioStatus::Completed]);
        $process->update(['status' => ProcessStatus::Terminated]);
        $observer = $this->user('observador');
        $before = $this->domainState();

        $first = $this->translate($observer, $scenario, $process, 0);
        $last = $this->translate($observer, $scenario, $process, 3071);

        $this->assertSame(0, $first['page_number']);
        $this->assertSame(0, $first['offset']);
        $this->assertSame(2048, $first['physical_address']);
        $this->assertSame(1, $last['page_number']);
        $this->assertSame(1023, $last['offset']);
        $this->assertSame(7167, $last['physical_address']);
        $this->assertSame($before, $this->domainState());
    }

    public static function invalidAddresses(): array
    {
        return ['negative byte' => [-1], 'first padding byte' => [3072], 'last padding byte' => [4095]];
    }

    #[DataProvider('invalidAddresses')]
    public function test_negative_addresses_and_last_page_padding_are_rejected(int $address): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['page_kb' => 2]);
        $process = $this->process($admin, $scenario, 3);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->translate($this->user('observador'), $scenario, $process, $address), 'logical_address');

        $this->assertSame($before, $this->domainState());
    }

    public function test_an_absent_page_returns_no_physical_address_and_does_not_execute_a_cpu_request(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 2);
        $before = $this->domainState();

        $result = $this->translate($this->user('observador'), $scenario, $process, 1500);

        $this->assertSame(1, $result['page_number']);
        $this->assertSame(476, $result['offset']);
        $this->assertFalse($result['present']);
        $this->assertNull($result['frame_number']);
        $this->assertNull($result['physical_address']);
        $this->assertSame($before, $this->domainState());
        $this->assertDatabaseCount('simulation_events', 3);
    }

    public static function readerRoles(): array
    {
        return ['administrator' => ['administrador'], 'operator' => ['operador'], 'observer' => ['observador']];
    }

    #[DataProvider('readerRoles')]
    public function test_all_reader_roles_can_calculate_addresses(string $role): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 1);

        $result = $this->translate($this->user($role), $scenario, $process, 1023);

        $this->assertSame(0, $result['page_number']);
        $this->assertSame(1023, $result['offset']);
    }

    public static function missingPermissions(): array
    {
        return [
            'memory' => ['memory.view'], 'tables' => ['tables.view'],
            'simulations' => ['simulations.view'], 'results' => ['results.view'],
        ];
    }

    #[DataProvider('missingPermissions')]
    public function test_each_of_the_four_read_permissions_is_required(string $missing): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 1);
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff(['memory.view', 'tables.view', 'simulations.view', 'results.view'], [$missing])));
        $before = $this->domainState();

        try {
            $this->translate($actor, $scenario, $process, 0);
            $this->fail('La traducción requiere los cuatro permisos de consulta.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_service_rechecks_a_readers_revoked_permissions(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 1);
        $observer = $this->user('observador');
        $this->assertTrue($observer->can('results.view'));
        $observer->fresh()->syncRoles([]);
        $before = $this->domainState();

        try {
            $this->translate($observer, $scenario, $process, 0);
            $this->fail('El usuario no conserva permisos de una instancia anterior.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_foreign_and_missing_processes_cannot_produce_a_translation(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario, 1);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $other, 1);
        $observer = $this->user('observador');
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->translate($observer, $scenario, $foreign, 0), 'process_id');
        $this->assertValidationFailure(fn () => app(AddressTranslationService::class)->translate($observer, $scenario->id, 999999, 0), 'process_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_missing_scenario_does_not_reuse_an_existing_memory_configuration(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 1);
        $before = $this->domainState();

        try {
            app(AddressTranslationService::class)->translate($this->user('observador'), 999999, $process->id, 0);
            $this->fail('Un escenario inexistente debe rechazarse.');
        } catch (ModelNotFoundException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function inconsistentPages(): array
    {
        return ['missing page' => ['missing'], 'noncontiguous page' => ['gap']];
    }

    #[DataProvider('inconsistentPages')]
    public function test_inconsistent_page_geometry_does_not_produce_a_translation(string $inconsistency): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 2);
        $last = $process->pages()->where('page_number', 1)->firstOrFail();
        if ($inconsistency === 'missing') {
            $last->delete();
        } else {
            $last->update(['page_number' => 99]);
        }
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->translate($this->user('observador'), $scenario, $process, 0), 'process_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_configuration_and_frame_geometry_must_match_before_calculation(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 2);
        $scenario->configuration->update(['page_size_bytes' => 2048]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->translate($this->user('observador'), $scenario, $process, 0), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_route_requires_authentication_and_all_read_permissions(): void
    {
        $this->get('/traduccion')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/traduccion')->assertForbidden();
        $partial = User::factory()->create();
        $partial->givePermissionTo(['memory.view', 'tables.view', 'simulations.view']);
        $this->actingAs($partial)->get('/traduccion')->assertForbidden();

        foreach (['administrador', 'operador', 'observador'] as $role) {
            $this->actingAs($this->user($role))->get('/traduccion')->assertOk();
        }
    }

    public function test_observer_can_use_the_form_and_changing_the_address_clears_its_read_only_result(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 2);
        $this->assign($scenario, $process, 1, 5);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(AddressTranslator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id)
            ->set('logicalAddress', 1500)->call('translate')->assertHasNoErrors()
            ->assertSet('result', fn ($result) => $result['physical_address'] === 5596 && $result['offset'] === 476);

        $this->assertStringContainsString('wire:submit="translate"', $component->html());
        $component->set('logicalAddress', 1501)->assertSet('result', null);
        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
        $this->assertSame($before, $this->domainState());
    }

    public function test_translator_uses_the_session_scenario_but_requires_an_explicit_process(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario, 1);
        $observer = $this->user('observador');
        $this->actingAs($observer);
        app(ActiveScenarioService::class)->select($observer, $scenario->id);
        $before = $this->domainState();

        Livewire::test(AddressTranslator::class)->assertSet('scenarioId', $scenario->id)
            ->assertSet('processId', null)->assertSet('result', null)
            ->call('translate')->assertHasErrors(['processId']);

        $this->assertSame($before, $this->domainState());
    }

    public function test_forged_foreign_process_id_cannot_show_a_translation_or_foreign_snapshot(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario, 1);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $other, 1);
        $foreign->update(['name' => 'Proceso externo oculto']);
        $before = $this->domainState();

        Livewire::actingAs($this->user('observador'))->test(AddressTranslator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $foreign->id)
            ->call('translate')->assertHasErrors(['processId'])->assertSet('result', null)
            ->assertViewHas('snapshot', null)->assertDontSee('Proceso externo oculto');

        $this->assertSame($before, $this->domainState());
    }

    public function test_changing_process_or_scenario_clears_the_previous_translation(): void
    {
        $admin = $this->user('administrador');
        $first = $this->memory($admin, ['name' => 'Primera memoria']);
        $firstProcess = $this->process($admin, $first, 2);
        $local = $this->process($admin, $first, 1);
        $second = $this->memory($admin, ['name' => 'Segunda memoria']);
        $secondProcess = $this->process($admin, $second, 1);

        Livewire::actingAs($this->user('observador'))->test(AddressTranslator::class)
            ->set('scenarioId', (string) $first->id)->set('processId', (string) $firstProcess->id)
            ->call('translate')->assertSet('result', fn ($result) => $result['process_id'] === $firstProcess->id)
            ->set('processId', (string) $local->id)->assertSet('result', null)
            ->call('translate')->assertSet('result', fn ($result) => $result['process_id'] === $local->id)
            ->set('scenarioId', (string) $second->id)->assertSet('processId', null)->assertSet('result', null)
            ->set('processId', (string) $secondProcess->id)->call('translate')
            ->assertSet('result', fn ($result) => $result['scenario_id'] === $second->id && $result['process_id'] === $secondProcess->id);

        $this->assertSame($second->id, session('memorylab.active_scenario_id'));
    }

    public static function invalidFormAddresses(): array
    {
        return ['negative' => [-1], 'fractional' => [1.5], 'non numeric' => ['invalid'], 'outside process' => [2048]];
    }

    #[DataProvider('invalidFormAddresses')]
    public function test_invalid_form_addresses_use_the_logical_address_error(mixed $address): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 2);
        $before = $this->domainState();

        Livewire::actingAs($this->user('observador'))->test(AddressTranslator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id)
            ->set('logicalAddress', $address)->call('translate')->assertHasErrors(['logicalAddress']);

        $this->assertSame($before, $this->domainState());
    }

    public function test_livewire_result_is_locked_and_permissions_are_rechecked_on_calculation(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 1);
        $observer = $this->user('observador');
        $component = Livewire::actingAs($observer)->test(AddressTranslator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id);
        $before = $this->domainState();

        try {
            $component->set('result', ['physical_address' => 999999]);
            $this->fail('El cliente no puede forjar el resultado calculado.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertSame($before, $this->domainState());
        }
        $component = Livewire::test(AddressTranslator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id);
        $observer->fresh()->syncRoles([]);
        $component->call('translate')->assertForbidden();
        $this->assertSame($before, $this->domainState());
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function memory(User $admin, array $input = []): Scenario
    {
        return app(MemoryConfigurationService::class)->configure($admin, null, array_replace([
            'name' => 'Memoria de prueba', 'ram_kb' => 16, 'page_kb' => 1, 'secondary_kb' => 64,
        ], $input));
    }

    private function process(User $actor, Scenario $scenario, int $size): Process
    {
        return app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => 'Proceso de prueba', 'size_kb' => $size]);
    }

    private function assign(Scenario $scenario, Process $process, int $pageNumber, int $frameNumber): void
    {
        $frame = $scenario->frames()->where('frame_number', $frameNumber)->firstOrFail();
        $process->pages()->where('page_number', $pageNumber)->firstOrFail()->update(['frame_id' => $frame->id]);
    }

    private function translate(User $actor, Scenario $scenario, Process $process, int $logicalAddress): array
    {
        return app(AddressTranslationService::class)->translate($actor, $scenario->id, $process->id, $logicalAddress);
    }

    private function domainState(): array
    {
        $state = [];
        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }

    private function assertValidationFailure(callable $operation, string $error): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($error, $exception->errors());

            return;
        }

        $this->fail('La operación debe rechazarse mediante validación.');
    }
}
