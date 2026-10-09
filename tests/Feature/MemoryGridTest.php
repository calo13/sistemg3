<?php

namespace Tests\Feature;

use App\Livewire\PagingSimulator;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\User;
use App\Services\MemoryConfigurationService;
use App\Services\PagingService;
use App\Services\ProcessManagerService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MemoryGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_orders_all_scenario_frames_and_preloads_their_page_and_process(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $selected = $this->process($admin, $scenario, 'Seleccionado', 3);
        $local = $this->process($admin, $scenario, 'Otro proceso', 2);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $other, 'Proceso externo', 2);
        $this->assign($scenario, $selected, 0, 4);
        $this->assign($scenario, $local, 1, 7);
        $this->assign($other, $foreign, 0, 4);
        $before = $this->domainState();

        $snapshot = app(PagingService::class)->snapshot($this->user('observador'), $scenario->id, $selected->id);

        $this->assertSame(range(0, 15), $snapshot['frames']->pluck('frame_number')->all());
        $this->assertSame([$scenario->id], $snapshot['frames']->pluck('scenario_id')->unique()->values()->all());
        $this->assertNull($snapshot['frames']->first()->page);
        $this->assertTrue($snapshot['frames']->firstWhere('frame_number', 4)->page->process->is($selected));
        $this->assertTrue($snapshot['frames']->firstWhere('frame_number', 7)->page->process->is($local));
        foreach ($snapshot['frames'] as $frame) {
            $this->assertTrue($frame->relationLoaded('page'));
            if ($frame->page !== null) {
                $this->assertTrue($frame->page->relationLoaded('process'));
                $this->assertNotSame($foreign->id, $frame->page->process_id);
            }
        }
        $this->assertSame(2, $snapshot['ram']['frames_used']);
        $this->assertSame(1, $snapshot['ram']['selected_frames_used']);
        $this->assertSame($before, $this->domainState());
    }

    public function test_partial_last_page_occupies_a_whole_frame_and_only_disk_pages_appear_in_secondary(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['page_kb' => 2]);
        $process = $this->process($admin, $scenario, 'Proceso de tres KB', 3);
        $this->assign($scenario, $process, 1, 4);
        $before = $this->domainState();

        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id)
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['ram']['used_bytes'] === 2048
                && $snapshot['ram']['available_bytes'] === 14336
                && $snapshot['ram']['frames_used'] === 1
                && $snapshot['ram']['frames_free'] === 7
                && $snapshot['secondary']['selected_bytes'] === 2048);
        $xpath = $this->xpath($component->html());
        $occupied = $xpath->query('//*[@data-paging-panel="ram"]//*[@data-memory-frame="4"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $occupied);
        $this->assertSame('OCCUPIED', $occupied->getAttribute('data-frame-state'));
        $this->assertSame((string) $process->id, $occupied->getAttribute('data-frame-process'));
        $this->assertSame('1', $occupied->getAttribute('data-frame-page'));
        $free = $xpath->query('//*[@data-paging-panel="ram"]//*[@data-memory-frame="0"]')->item(0);
        $this->assertSame('FREE', $free->getAttribute('data-frame-state'));
        $this->assertSame('', $free->getAttribute('data-frame-process'));
        $this->assertSame('', $free->getAttribute('data-frame-page'));
        $this->assertSame(['0'], $this->secondaryPages($component->html()));
        $this->assertSame($before, $this->domainState());
    }

    public function test_completely_occupied_ram_has_zero_free_frames_and_no_pending_pages_for_that_process(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 4]);
        $process = $this->process($admin, $scenario, 'Proceso completo', 4);
        for ($number = 0; $number < 4; $number++) {
            $this->assign($scenario, $process, $number, $number);
        }
        $before = $this->domainState();

        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id)
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['ram']['frames_used'] === 4
                && $snapshot['ram']['frames_free'] === 0
                && $snapshot['ram']['available_bytes'] === 0
                && $snapshot['secondary']['used_bytes'] === 0);
        $xpath = $this->xpath($component->html());
        $this->assertCount(4, $xpath->query('//*[@data-paging-panel="ram"]//*[@data-frame-state="OCCUPIED"]'));
        $this->assertCount(0, $xpath->query('//*[@data-paging-panel="ram"]//*[@data-frame-state="FREE"]'));
        $this->assertSame(['0', '1', '2', '3'], $this->frameNumbers($component->html()));
        $this->assertSame([], $this->secondaryPages($component->html()));
        $component->call('$refresh');
        $this->assertSame($before, $this->domainState());
    }

    public function test_ram_map_paginates_sixty_four_then_six_frames_and_resets_for_another_scenario(): void
    {
        $admin = $this->user('administrador');
        $first = $this->memory($admin, ['name' => 'Setenta marcos', 'ram_kb' => 70]);
        $second = $this->memory($admin, ['name' => 'Cuatro marcos', 'ram_kb' => 4]);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $first->id);

        $this->assertSame(array_map('strval', range(0, 63)), $this->frameNumbers($component->html()));
        $component->call('gotoPage', 2, 'framesPage')
            ->assertViewHas('frameRows', fn ($rows) => $rows->total() === 70 && $rows->currentPage() === 2 && $rows->count() === 6);
        $this->assertSame(array_map('strval', range(64, 69)), $this->frameNumbers($component->html()));
        $component->set('scenarioId', (string) $second->id)
            ->assertViewHas('frameRows', fn ($rows) => $rows->currentPage() === 1 && $rows->total() === 4);
        $this->assertSame(['0', '1', '2', '3'], $this->frameNumbers($component->html()));
        $this->assertSame($before, $this->domainState());
    }

    public function test_secondary_list_filters_ram_pages_scopes_the_process_and_resets_its_pagination(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $first = $this->process($admin, $scenario, 'Proceso largo', 18);
        $second = $this->process($admin, $scenario, 'Proceso corto', 2);
        $this->assign($scenario, $first, 0, 4);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $first->id)
            ->assertViewHas('secondaryRows', fn ($rows) => $rows->total() === 17 && $rows->count() === 15);

        $this->assertSame(array_map('strval', range(1, 15)), $this->secondaryPages($component->html()));
        $component->call('gotoPage', 2, 'secondaryPage');
        $this->assertSame(['16', '17'], $this->secondaryPages($component->html()));
        $component->set('processId', (string) $second->id)
            ->assertViewHas('secondaryRows', fn ($rows) => $rows->total() === 2 && $rows->currentPage() === 1);
        $this->assertSame(['0', '1'], $this->secondaryPages($component->html()));
        $component->call('$refresh');
        $this->assertSame($before, $this->domainState());
    }

    public function test_ram_map_is_global_to_the_scenario_while_secondary_waits_for_process_selection(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 'Proceso residente', 2);
        $this->assign($scenario, $process, 0, 4);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $other, 'Proceso externo', 2);
        $this->assign($other, $foreign, 0, 4);

        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->assertSet('processId', null);
        $xpath = $this->xpath($component->html());
        $occupied = $xpath->query('//*[@data-paging-panel="ram"]//*[@data-memory-frame="4"]')->item(0);
        $this->assertSame((string) $process->id, $occupied->getAttribute('data-frame-process'));
        $this->assertSame('0', $occupied->getAttribute('data-frame-page'));
        $this->assertCount(0, $xpath->query('//*[@data-paging-panel="ram"]//*[@data-frame-process="'.$foreign->id.'"]'));
        $this->assertSame([], $this->secondaryPages($component->html()));
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

    private function process(User $actor, Scenario $scenario, string $name, int $size): Process
    {
        return app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => $name, 'size_kb' => $size]);
    }

    private function assign(Scenario $scenario, Process $process, int $pageNumber, int $frameNumber): void
    {
        $frame = $scenario->frames()->where('frame_number', $frameNumber)->firstOrFail();
        $process->pages()->where('page_number', $pageNumber)->firstOrFail()->update(['frame_id' => $frame->id]);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    private function frameNumbers(string $html): array
    {
        return $this->attributeValues($this->xpath($html)->query('//*[@data-paging-panel="ram"]//*[@data-memory-frame]'), 'data-memory-frame');
    }

    private function secondaryPages(string $html): array
    {
        return $this->attributeValues($this->xpath($html)->query('//*[@data-paging-panel="secondary"]//*[@data-secondary-page]'), 'data-secondary-page');
    }

    private function attributeValues(iterable $nodes, string $attribute): array
    {
        $values = [];
        foreach ($nodes as $node) {
            $values[] = $node->getAttribute($attribute);
        }

        return $values;
    }

    private function domainState(): array
    {
        $state = [];
        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }
}
