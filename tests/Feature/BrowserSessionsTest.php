<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Http\Livewire\LogoutOtherBrowserSessionsForm;
use Tests\TestCase;

class BrowserSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_browser_sessions_can_be_logged_out(): void
    {
        config(['session.driver' => 'database']);
        $this->actingAs($user = User::factory()->create());

        DB::table('sessions')->insert([
            'id' => 'other-browser-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'MemoryLabTestBrowser/1.0',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        request()->setLaravelSession(app('session')->driver());

        $component = app(LogoutOtherBrowserSessionsForm::class);
        $component->password = 'password';
        $component->logoutOtherBrowserSessions(app(StatefulGuard::class));

        $this->assertDatabaseMissing('sessions', ['id' => 'other-browser-session']);
    }
}
