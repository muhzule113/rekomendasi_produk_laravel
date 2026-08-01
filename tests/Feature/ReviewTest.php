<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecommendationTestHelper;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;
    use RecommendationTestHelper;

    public function test_pelanggan_can_submit_one_review_and_duplicate_rejected(): void
    {
        $products = $this->seedCatalog();
        $user = User::factory()->create(['email' => 'rev@test.com']);

        $this->actingAs($user)
            ->postJson(route('api.review.store'), [
                'id_product' => $products['A'],
                'rating' => 5,
                'komentar' => 'Bagus',
            ])
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('product_reviews', [
            'id_user' => $user->id_user,
            'id_product' => $products['A'],
            'rating' => 5,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.review.store'), [
                'id_product' => $products['A'],
                'rating' => 4,
                'komentar' => 'Lagi',
            ])
            ->assertOk()
            ->assertJson(['status' => false]);

        $this->assertSame(1, DB::table('product_reviews')->where('id_user', $user->id_user)->count());
    }
}
