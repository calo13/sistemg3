<?php

namespace Tests\Feature;

use Tests\TestCase;

class LivewireRoutingTest extends TestCase
{
    public function test_livewire_uses_the_application_prefix_when_served_from_a_subdirectory(): void
    {
        $response = $this->withServerVariables([
            'SCRIPT_NAME' => '/sistemg3/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF' => '/sistemg3/public/index.php',
        ])->get('/sistemg3/public/login');

        $response->assertOk();

        $this->assertStringContainsString(
            'http://localhost/sistemg3/public/livewire/update',
            str_replace('\\/', '/', $response->getContent()),
        );

        $response->assertDontSee('src="/livewire/livewire.js"', false);
    }
}
