<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_home_page_returns_a_successful_response(): void
    {
        $response = $this->get('/en');

        $response->assertOk();
    }
}
