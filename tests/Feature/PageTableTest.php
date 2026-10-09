<?php

namespace Tests\Feature;

use App\Livewire\PagingSimulator;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\User;
use App\Services\MemoryConfigurationService;
use App\Services\ProcessManagerService;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PageTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_displays_page_frame_presence_and_state_from_existing_assignments(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 'Editor', 3);
        $frame = $scenario->frames()->where('frame_number', 4)->firstOrFail();
        $process->pages()->where('page_number', 0)->firstOrFail()->update(['frame_id' => $frame->id]);
        $before = $this->domainState();

        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id);
        $xpath = $this->xpath($component->html());
        $headers = $xpath->query('//*[@data-paging-panel="pages"]//thead//th[@scope="col"]');
        $this->assertSame(['Página', 'Marco', 'Presente', 'Estado'], $this->texts($headers));
        $rows = $xpath->query('//*[@data-paging-panel="pages"]//tr[@data-paging-page]');
        $this->assertCount(3, $rows);
        $this->assertSame(['0', '1', '2'], $this->attributes($rows, 'data-paging-page'));

        $first = $rows->item(0);
        $this->assertSame('4', $this->cell($xpath, $first, 'data-page-frame')->getAttribute('data-page-frame'));
        $this->assertSame('4', trim($this->cell($xpath, $first, 'data-page-frame')->textContent));
        $this->assertSame('true', $this->cell($xpath, $first, 'data-page-present')->getAttribute('data-page-present'));
        $this->assertSame('Sí', trim($this->cell($xpath, $first, 'data-page-present')->textContent));
        $this->assertSame('RAM', trim($this->cell($xpath, $first, 'data-page-location')->textContent));

        foreach ([$rows->item(1), $rows->item(2)] as $diskRow) {
            $this->assertSame('', $this->cell($xpath, $diskRow, 'data-page-frame')->getAttribute('data-page-frame'));
            $this->assertSame('—', trim($this->cell($xpath, $diskRow, 'data-page-frame')->textContent));
            $this->assertSame('false', $this->cell($xpath, $diskRow, 'data-page-present')->getAttribute('data-page-present'));
            $this->assertSame('No', trim($this->cell($xpath, $diskRow, 'data-page-present')->textContent));
            $this->assertSame('DISCO', trim($this->cell($xpath, $diskRow, 'data-page-location')->textContent));
        }
        $this->assertSame($before, $this->domainState());
    }

    public function test_table_stays_scoped_when_switching_processes_and_scenarios(): void
    {
        $admin = $this->user('administrador');
        $first = $this->memory($admin, 'Primera memoria');
        $firstProcess = $this->process($admin, $first, 'Proceso A', 2);
        $local = $this->process($admin, $first, 'Proceso local', 4);
        $second = $this->memory($admin, 'Segunda memoria');
        $secondProcess = $this->process($admin, $second, 'Proceso B', 8);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $first->id)->set('processId', (string) $firstProcess->id);

        $this->assertSame(['0', '1'], $this->pageNumbers($component->html()));
        $this->assertStringContainsString('Proceso A', $this->caption($component->html()));
        $component->set('processId', (string) $local->id);
        $this->assertSame(['0', '1', '2', '3'], $this->pageNumbers($component->html()));
        $this->assertStringContainsString('Proceso local', $this->caption($component->html()));
        $component->set('scenarioId', (string) $second->id);
        $this->assertSame([], $this->pageNumbers($component->html()));
        $component->set('processId', (string) $secondProcess->id);
        $this->assertSame(array_map('strval', range(0, 7)), $this->pageNumbers($component->html()));
        $this->assertStringContainsString('Proceso B', $this->caption($component->html()));
        $this->assertSame($before, $this->domainState());
    }

    public function test_table_paginates_fifteen_rows_and_keeps_remaining_pages_in_order(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 'Proceso largo', 17);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id);

        $this->assertSame(array_map('strval', range(0, 14)), $this->pageNumbers($component->html()));
        $component->call('gotoPage', 2, 'pagesPage');
        $this->assertSame(['15', '16'], $this->pageNumbers($component->html()));
        $xpath = $this->xpath($component->html());
        $this->assertCount(2, $xpath->query('//*[@data-paging-panel="pages"]//*[@data-page-present="false"]'));
        $this->assertCount(2, $xpath->query('//*[@data-paging-panel="pages"]//*[@data-page-location="DISCO"]'));
        $component->call('$refresh');
        $this->assertSame(['15', '16'], $this->pageNumbers($component->html()));
        $this->assertSame($before, $this->domainState());
    }

    public function test_table_does_not_show_pages_until_a_process_is_selected(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario, 'Proceso disponible', 3);
        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id);

        $this->assertSame([], $this->pageNumbers($component->html()));
        $this->assertCount(0, $this->xpath($component->html())->query('//*[@data-paging-panel="pages"]//table'));
        $component->assertSee('Selecciona un proceso');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function memory(User $admin, string $name = 'Memoria de prueba'): Scenario
    {
        return app(MemoryConfigurationService::class)->configure($admin, null, [
            'name' => $name, 'ram_kb' => 16, 'page_kb' => 1, 'secondary_kb' => 64,
        ]);
    }

    private function process(User $actor, Scenario $scenario, string $name, int $size): Process
    {
        return app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => $name, 'size_kb' => $size]);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    private function cell(DOMXPath $xpath, DOMNode $row, string $attribute): DOMElement
    {
        $cell = $xpath->query('.//*[@'.$attribute.']', $row)->item(0);
        $this->assertInstanceOf(DOMElement::class, $cell);

        return $cell;
    }

    private function pageNumbers(string $html): array
    {
        return $this->attributes($this->xpath($html)->query('//*[@data-paging-panel="pages"]//tr[@data-paging-page]'), 'data-paging-page');
    }

    private function caption(string $html): string
    {
        return trim($this->xpath($html)->query('//*[@data-paging-panel="pages"]//caption')->item(0)->textContent);
    }

    private function texts(iterable $nodes): array
    {
        $values = [];
        foreach ($nodes as $node) {
            $values[] = trim($node->textContent);
        }

        return $values;
    }

    private function attributes(iterable $nodes, string $attribute): array
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
