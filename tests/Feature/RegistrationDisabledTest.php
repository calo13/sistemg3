<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    public function test_public_registration_can_be_disabled(): void
    {
        $key = 'AUTH_REGISTRATION_ENABLED';
        $previous = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];

        try {
            putenv("{$key}=false");
            $_ENV[$key] = $_SERVER[$key] = 'false';
            Env::enablePutenv();
            $this->refreshApplication();

            $this->get('/register')->assertNotFound();
            $this->post('/register', [])->assertNotFound();
            $this->get('/')->assertDontSee('Crear cuenta');
            $this->get('/login')->assertOk()->assertDontSee('Crear cuenta');
        } finally {
            $previous[0] === false ? putenv($key) : putenv("{$key}={$previous[0]}");

            if ($previous[1] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $previous[1];
            }

            if ($previous[2] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $previous[2];
            }

            Env::enablePutenv();
        }
    }
}
