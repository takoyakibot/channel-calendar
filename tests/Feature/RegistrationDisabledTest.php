<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_register_post_is_not_available(): void
    {
        $this->post('/register', [])->assertNotFound();
    }
}
