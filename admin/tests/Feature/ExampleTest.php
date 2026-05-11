<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_admin_login(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_healthz_returns_ok(): void
    {
        $this->get('/healthz')->assertStatus(200);
    }
}
