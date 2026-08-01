<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RecommendationTestHelper;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    use RecommendationTestHelper;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seedCatalog();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
