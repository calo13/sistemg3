<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Livewire\SimulationHistory;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\SimulationHistoryService;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimulationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_paginated_twenty_at_a_time_with_stable_timestamp_and_id_ordering(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->scenario($admin);
        $ids = [];
        for ($number = 0; $number < 41; $number++) {
            $ids[] = $this->event($scenario, $admin)->id;
        }
        $before = $this->domainState();
        $service = app(SimulationHistoryService::class);
        $descending = array_reverse($ids);

        foreach ([-10, 0, 1, 2, 3, 999] as $page) {
            $expectedPage = max(1, min(3, $page));
            $events = $service->search($admin, [], $page);
            $this->assertInstanceOf(LengthAwarePaginator::class, $events);
            $this->assertSame(41, $events->total());
            $this->assertSame(20, $events->perPage());
            $this->assertSame($expectedPage, $events->currentPage());
            $this->assertSame(3, $events->lastPage());
            $this->assertSame('historyPage', $events->getPageName());
            $this->assertSame(array_slice($descending, ($expectedPage - 1) * 20, 20), $events->getCollection()->pluck('id')->all());
        }
        $empty = $service->search($admin, ['type' => SimulationEventType::SegmentAccess->value], 999);
        $this->assertSame(0, $empty->total());
        $this->assertSame(1, $empty->currentPage());
        $this->assertTrue($empty->isEmpty());
        $this->assertSame($before, $this->domainState());
    }

    public function test_occurrence_time_is_sorted_before_the_id_tiebreaker(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->scenario($admin);
        $newer = $this->event($scenario, $admin, at: '2026-10-08 12:00:00.000001');
        $older = $this->event($scenario, $admin, at: '2026-10-07 12:00:00.999999');
        $sameTimeLaterId = $this->event($scenario, $admin, at: '2026-10-08 12:00:00.000001');

        $events = app(SimulationHistoryService::class)->search($admin, []);

        $this->assertSame([$sameTimeLaterId->id, $newer->id, $older->id], $events->getCollection()->pluck('id')->all());
    }

    public function test_guatemala_day_filters_include_both_boundary_microseconds_and_exclude_the_next_midnight(): void
    {
        config(['memorylab.display_timezone' => 'America/Guatemala']);
        $admin = $this->user('administrador');
        $scenario = $this->scenario($admin);
        $previous = $this->event($scenario, $admin, at: '2026-10-07 05:59:59.999999');
        $first = $this->event($scenario, $admin, at: '2026-10-07 06:00:00.000000');
        $middle = $this->event($scenario, $admin, at: '2026-10-07 16:30:00.123456');
        $last = $this->event($scenario, $admin, at: '2026-10-08 05:59:59.999999');
        $next = $this->event($scenario, $admin, at: '2026-10-08 06:00:00.000000');
        $before = $this->domainState();
        $service = app(SimulationHistoryService::class);

        $events = $service->search($admin, ['from_date' => '2026-10-07', 'to_date' => '2026-10-07']);

        $this->assertSame([$last->id, $middle->id, $first->id], $events->getCollection()->pluck('id')->all());
        $this->assertSame('999999', $events->getCollection()->first()->occurred_at->format('u'));
        $this->assertSame([$next->id, $last->id, $middle->id, $first->id], $service->search($admin, ['from_date' => '2026-10-07'])->getCollection()->pluck('id')->all());
        $this->assertSame([$last->id, $middle->id, $first->id, $previous->id], $service->search($admin, ['to_date' => '2026-10-07'])->getCollection()->pluck('id')->all());
        $this->assertSame($before, $this->domainState());
    }

    public function test_scenario_process_actor_and_type_filters_intersect_without_leaking_another_context(): void
    {
        $admin = $this->user('administrador');
        $otherActor = User::factory()->create();
        $first = $this->scenario($admin, 'Primera memoria');
        $second = $this->scenario($admin, 'Segunda memoria');
        $selected = $this->process($first, 'Seleccionado');
        $other = $this->process($first, 'Otro proceso');
        $foreign = $this->process($second, 'Proceso ajeno');
        $match = $this->event($first, $admin, $selected);
        $hit = $this->event($first, $otherActor, $selected, SimulationEventType::PageHit);
        $otherProcess = $this->event($first, $admin, $other);
        $foreignEvent = $this->event($second, $admin, $foreign);
        $withoutProcess = $this->event($first, $admin, type: SimulationEventType::MemoryConfigured);
        $before = $this->domainState();
        $service = app(SimulationHistoryService::class);

        $this->assertSame([$withoutProcess->id, $otherProcess->id, $hit->id, $match->id], $service->search($admin, ['scenario_id' => $first->id])->getCollection()->pluck('id')->all());
        $this->assertSame([$hit->id, $match->id], $service->search($admin, ['scenario_id' => $first->id, 'process_id' => $selected->id])->getCollection()->pluck('id')->all());
        $this->assertSame([$match->id], $service->search($admin, [
            'scenario_id' => $first->id, 'process_id' => $selected->id, 'user_id' => $admin->id, 'type' => 'PAGE_FAULT',
        ])->getCollection()->pluck('id')->all());
        $this->assertSame([$hit->id], $service->search($admin, ['user_id' => $otherActor->id])->getCollection()->pluck('id')->all());
        $this->assertSame([$foreignEvent->id], $service->search($admin, ['scenario_id' => $second->id])->getCollection()->pluck('id')->all());
        $this->assertValidationFailure(fn () => $service->search($admin, ['process_id' => $selected->id]), 'process_id');
        $this->assertValidationFailure(fn () => $service->search($admin, ['scenario_id' => $first->id, 'process_id' => $foreign->id]), 'process_id');
        $this->assertSame($before, $this->domainState());
    }

    public function test_all_canonical_event_types_can_be_filtered_and_blank_filters_mean_all_events(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->scenario($admin);
        $service = app(SimulationHistoryService::class);
        $ids = [];
        foreach (SimulationEventType::cases() as $type) {
            $ids[$type->value] = $this->event($scenario, $admin, type: $type)->id;
        }
        $before = $this->domainState();

        foreach ($ids as $type => $id) {
            $this->assertSame([$id], $service->search($admin, ['type' => $type])->getCollection()->pluck('id')->all());
        }
        $events = $service->search($admin, [
            'scenario_id' => '', 'process_id' => '', 'user_id' => null, 'type' => '', 'from_date' => '', 'to_date' => '',
            'order' => 'user.email', 'unexpected_filter' => 'ignored',
        ]);
        $this->assertSame(count(SimulationEventType::cases()), $events->total());
        $this->assertSame($before, $this->domainState());
    }

    public function test_related_models_are_eager_loaded_with_only_id_and_name(): void
    {
        $admin = $this->user('administrador');
        $actor = User::factory()->create(['email' => 'actor-private@example.test']);
        $scenario = $this->scenario($admin);
        $process = $this->process($scenario);
        $event = $this->event($scenario, $actor, $process);

        $row = app(SimulationHistoryService::class)->search($admin, [])->getCollection()->sole();

        $this->assertSame($event->id, $row->id);
        foreach (['scenario', 'process', 'user'] as $relation) {
            $this->assertTrue($row->relationLoaded($relation));
            $this->assertSame(['id', 'name'], array_keys($row->{$relation}->getAttributes()));
        }
        $this->assertArrayNotHasKey('email', $row->user->getAttributes());
        $this->assertArrayNotHasKey('password', $row->user->getAttributes());
    }

    public function test_deleted_actor_events_remain_readable_and_an_explicit_deleted_actor_filter_is_invalid(): void
    {
        $admin = $this->user('administrador');
        $actor = User::factory()->create();
        $scenario = $this->scenario($admin);
        $event = $this->event($scenario, $actor);
        $deletedActorId = $actor->id;
        $actor->delete();
        $before = $this->domainState();

        $row = app(SimulationHistoryService::class)->search($admin, [])->getCollection()->sole();

        $this->assertSame($event->id, $row->id);
        $this->assertNull($row->user_id);
        $this->assertNull($row->user);
        $this->assertValidationFailure(fn () => app(SimulationHistoryService::class)->search($admin, ['user_id' => $deletedActorId]), 'user_id');
        Livewire::actingAs($admin)->test(SimulationHistory::class)->assertSee('Cuenta eliminada')->assertSee('Sin proceso');
        $this->assertSame($before, $this->domainState());
    }

    public static function invalidFilters(): array
    {
        return [
            'missing scenario' => [['scenario_id' => 999999], 'scenario_id'],
            'negative scenario' => [['scenario_id' => -1], 'scenario_id'],
            'array scenario' => [['scenario_id' => [1]], 'scenario_id'],
            'missing process' => [['process_id' => 999999], 'process_id'],
            'missing actor' => [['user_id' => 999999], 'user_id'],
            'unknown type' => [['type' => 'FAKE_EVENT'], 'type'],
            'array type' => [['type' => ['PAGE_FAULT']], 'type'],
            'localized date' => [['from_date' => '07/10/2026'], 'from_date'],
            'invalid calendar day' => [['from_date' => '2026-02-30'], 'from_date'],
            'invalid final date' => [['to_date' => 'yesterday'], 'to_date'],
            'inverted range' => [['from_date' => '2026-10-08', 'to_date' => '2026-10-07'], 'to_date'],
            'space is not blank' => [['scenario_id' => ' '], 'scenario_id'],
            'date before database range' => [['from_date' => '0999-12-31'], 'from_date'],
            'exclusive upper bound would overflow' => [['to_date' => '9999-12-31'], 'to_date'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_produce_validation_errors_and_never_change_records(array $filters, string $error): void
    {
        $admin = $this->user('administrador');
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => app(SimulationHistoryService::class)->search($admin, $filters), $error);

        $this->assertSame($before, $this->domainState());
    }

    public function test_last_safe_day_of_the_database_range_is_valid_without_overflowing_the_exclusive_bound(): void
    {
        $admin = $this->user('administrador');
        $before = $this->domainState();

        $events = app(SimulationHistoryService::class)->search($admin, ['from_date' => '9999-12-30', 'to_date' => '9999-12-30']);

        $this->assertSame(0, $events->total());
        $this->assertSame(1, $events->currentPage());
        $this->assertSame($before, $this->domainState());
    }

    public function test_history_permission_alone_authorizes_the_service_and_route(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('history.view');
        $before = $this->domainState();

        $this->assertSame(0, app(SimulationHistoryService::class)->search($actor, [])->total());
        $this->actingAs($actor)->get(route('history.index'))->assertOk();
        Livewire::actingAs($actor)->test(SimulationHistory::class)->assertViewHas('events', fn ($events) => $events->isEmpty());

        $this->assertSame($before, $this->domainState());
    }

    public static function deniedRoles(): array
    {
        return ['operator' => ['operador'], 'observer' => ['observador']];
    }

    #[DataProvider('deniedRoles')]
    public function test_operator_and_observer_cannot_read_history_through_service_route_or_component(string $role): void
    {
        $actor = $this->user($role);
        $before = $this->domainState();

        try {
            app(SimulationHistoryService::class)->search($actor, []);
            $this->fail('Consultar memoria no concede acceso al historial.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
        $this->actingAs($actor)->get(route('history.index'))->assertForbidden();
        Livewire::actingAs($actor)->test(SimulationHistory::class)->assertForbidden();
        $this->assertSame($before, $this->domainState());
    }

    public function test_permissions_are_reloaded_after_revocation_for_search_and_a_mounted_component(): void
    {
        $admin = $this->user('administrador');
        $this->assertTrue($admin->can('history.view'));
        $component = Livewire::actingAs($admin)->test(SimulationHistory::class);
        $admin->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        try {
            app(SimulationHistoryService::class)->search($admin, []);
            $this->fail('La instancia anterior no conserva el permiso de historial.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
        $component->call('$refresh')->assertForbidden();
        $this->assertSame($before, $this->domainState());
    }

    public function test_history_route_requires_authentication(): void
    {
        $this->get(route('history.index'))->assertRedirect(route('login'));
    }

    public function test_component_starts_with_the_active_scenario_and_filter_changes_do_not_change_that_selection(): void
    {
        $admin = $this->user('administrador');
        $first = $this->scenario($admin, 'Primera memoria');
        $second = $this->scenario($admin, 'Segunda memoria');
        $process = $this->process($first);
        $firstEvent = $this->event($first, $admin, $process);
        $secondEvent = $this->event($second, $admin);
        session(['memorylab.active_scenario_id' => $first->id]);
        $before = $this->domainState();
        $component = Livewire::actingAs($admin)->test(SimulationHistory::class)->assertSet('scenarioId', $first->id)
            ->assertViewHas('events', fn ($events) => $events->getCollection()->pluck('id')->all() === [$firstEvent->id])
            ->set('processId', $process->id)->assertHasNoErrors();

        $component->set('scenarioId', $second->id)->assertSet('processId', null)->assertSet('paginators.historyPage', 1)
            ->assertViewHas('events', fn ($events) => $events->getCollection()->pluck('id')->all() === [$secondEvent->id]);
        $this->assertSame($first->id, session('memorylab.active_scenario_id'));
        $component->call('clearFilters')->assertSet('scenarioId', null)->assertSet('processId', null)->assertSet('userId', null)
            ->assertSet('eventType', null)->assertSet('fromDate', null)->assertSet('toDate', null)
            ->assertSet('paginators.historyPage', 1)->assertViewHas('events', fn ($events) => $events->total() === 2);
        $this->assertSame($first->id, session('memorylab.active_scenario_id'));
        $this->assertSame($before, $this->domainState());
    }

    public static function malformedPages(): array
    {
        return ['text' => ['abc', 1], 'array' => [[1], 1], 'zero' => [0, 1], 'negative' => [-3, 1], 'beyond range' => [999, 3]];
    }

    #[DataProvider('malformedPages')]
    public function test_component_normalizes_invalid_pages_and_clamps_the_last_page_without_writes($value, int $expectedPage): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->scenario($admin);
        for ($number = 0; $number < 41; $number++) {
            $this->event($scenario, $admin);
        }
        $before = $this->domainState();

        Livewire::actingAs($admin)->test(SimulationHistory::class)->set('paginators.historyPage', $value)
            ->assertSet('paginators.historyPage', $expectedPage)
            ->assertViewHas('events', fn ($events) => $events->currentPage() === $expectedPage && $events->count() === ($expectedPage === 3 ? 1 : 20));

        $this->assertSame($before, $this->domainState());
    }

    public function test_component_normalizes_a_malformed_paginators_container_and_resets_the_page_when_filters_change(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->scenario($admin);
        for ($number = 0; $number < 21; $number++) {
            $this->event($scenario, $admin);
        }
        $before = $this->domainState();
        $component = Livewire::actingAs($admin)->test(SimulationHistory::class)->set('paginators', 'bad')
            ->assertSet('paginators.historyPage', 1)->call('setPage', 2, 'historyPage')->assertSet('paginators.historyPage', 2);

        $component->set('eventType', 'PAGE_FAULT')->assertSet('paginators.historyPage', 1)->assertHasNoErrors();
        $this->assertSame($before, $this->domainState());
    }

    public function test_component_maps_every_filter_error_and_never_shows_a_foreign_process_row(): void
    {
        $admin = $this->user('administrador');
        $selected = $this->scenario($admin, 'Memoria seleccionada');
        $other = $this->scenario($admin, 'Memoria ajena');
        $own = $this->process($selected, 'Proceso seleccionado');
        $foreign = $this->process($other, 'Proceso privado');
        $this->event($selected, $admin, $own);
        $foreignEvent = $this->event($other, $admin, $foreign);
        $before = $this->domainState();
        $component = Livewire::actingAs($admin)->test(SimulationHistory::class)->set('scenarioId', $selected->id)
            ->assertViewHas('processes', fn ($processes) => $processes->pluck('id')->all() === [$own->id]);

        $component->set('processId', $foreign->id)->assertHasErrors(['processId'])
            ->assertViewHas('events', fn ($events) => $events->isEmpty());
        $this->assertCount(0, $this->xpath($component->html())->query('//*[@data-history-event="'.$foreignEvent->id.'"]'));
        foreach (['scenarioId' => 999999, 'userId' => 999999, 'eventType' => 'FAKE', 'fromDate' => 'bad', 'toDate' => 'bad'] as $property => $value) {
            $component->call('clearFilters')->set($property, $value)->assertHasErrors([$property])
                ->assertViewHas('events', fn ($events) => $events->isEmpty());
        }
        $component->call('clearFilters')->set('fromDate', '2026-10-08')->set('toDate', '2026-10-07')->assertHasErrors(['toDate']);
        $this->assertSame($before, $this->domainState());
    }

    public function test_view_escapes_event_and_related_names_and_shows_guatemala_time_without_polling_or_email(): void
    {
        config(['memorylab.display_timezone' => 'America/Guatemala']);
        $admin = $this->user('administrador');
        $unsafe = '<img src=x onerror="alert(1)">';
        $actor = User::factory()->create(['name' => $unsafe, 'email' => 'private-actor@example.test']);
        $scenario = $this->scenario($admin, '<script>alert(2)</script>');
        $process = $this->process($scenario, $unsafe);
        $event = $this->event($scenario, $actor, $process, at: '2026-10-07 06:00:00.000000', description: $unsafe);
        $before = $this->domainState();
        $component = Livewire::actingAs($admin)->test(SimulationHistory::class)->assertSee($unsafe);
        $xpath = $this->xpath($component->html());

        $this->assertCount(1, $xpath->query('//*[@data-history-event="'.$event->id.'"]'));
        $this->assertCount(0, $xpath->query('//*[@data-history-event]//img | //*[@data-history-event]//script'));
        $this->assertCount(0, $xpath->query('//select//img | //select//script'));
        $this->assertSame('07/10/2026 00:00:00', trim($xpath->query('//*[@data-history-event="'.$event->id.'"]/td[1]')->item(0)->textContent));
        $this->assertStringNotContainsString('private-actor@example.test', $component->html());
        $this->assertStringNotContainsString('wire:poll', $component->html());
        $component->call('$refresh');
        $this->assertSame($before, $this->domainState());
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function scenario(User $actor, string $name = 'Historial de memoria'): Scenario
    {
        return Scenario::create([
            'name' => $name, 'mode' => SimulationMode::Paging, 'status' => ScenarioStatus::Ready,
            'created_by' => $actor->id, 'is_demo' => false,
        ]);
    }

    private function process(Scenario $scenario, string $name = 'Proceso historico'): Process
    {
        return $scenario->processes()->create(['name' => $name, 'size_bytes' => 1024, 'status' => ProcessStatus::Ready]);
    }

    private function event(Scenario $scenario, ?User $actor, ?Process $process = null, SimulationEventType $type = SimulationEventType::PageFault, string $at = '2026-10-07 12:00:00.123456', string $description = 'Evento de prueba'): SimulationEvent
    {
        return $scenario->events()->create([
            'user_id' => $actor?->id, 'process_id' => $process?->id, 'type' => $type,
            'description' => $description, 'metadata' => ['source' => 'history-test'],
            'occurred_at' => CarbonImmutable::parse($at, 'UTC'),
        ]);
    }

    private function domainState(): array
    {
        $state = [];
        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    private function assertValidationFailure(callable $operation, string $error): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($error, $exception->errors());

            return;
        }

        $this->fail('El filtro debe rechazarse mediante validacion.');
    }
}
