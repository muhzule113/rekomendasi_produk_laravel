<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RecommenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecommendationTestHelper;
use Tests\TestCase;

class RecommenderServiceTest extends TestCase
{
    use RefreshDatabase;
    use RecommendationTestHelper;

    private RecommenderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['recommendation.min_co_occurrence' => 2]);
        $this->service = app(RecommenderService::class);
    }

    public function test_matrix_cosine_fixture_and_symmetry_without_max_normalization(): void
    {
        config(['recommendation.min_co_occurrence' => 1]);
        $products = $this->seedCatalog();

        // A buyers = u1..u4, B = u1, C = u2,u3
        $u1 = $this->makeUser('u1@test.com');
        $u2 = $this->makeUser('u2@test.com');
        $u3 = $this->makeUser('u3@test.com');
        $u4 = $this->makeUser('u4@test.com');

        $this->addTransaction($u1, [$products['A'] => 1, $products['B'] => 1]);
        $this->addTransaction($u2, [$products['A'] => 1, $products['C'] => 1]);
        $this->addTransaction($u3, [$products['A'] => 1, $products['C'] => 1]);
        $this->addTransaction($u4, [$products['A'] => 1]);

        $matrix = $this->service->buildUserItemMatrix();
        $result = $this->service->calculateCosineSimilarity($matrix);
        $sims = collect($result['similarities']);

        $ab = $sims->first(fn ($s) => (
            ($s['product_a'] == $products['A'] && $s['product_b'] == $products['B'])
            || ($s['product_a'] == $products['B'] && $s['product_b'] == $products['A'])
        ));
        $ac = $sims->first(fn ($s) => (
            ($s['product_a'] == $products['A'] && $s['product_b'] == $products['C'])
            || ($s['product_a'] == $products['C'] && $s['product_b'] == $products['A'])
        ));
        $bc = $sims->first(fn ($s) => (
            ($s['product_a'] == $products['B'] && $s['product_b'] == $products['C'])
            || ($s['product_a'] == $products['C'] && $s['product_b'] == $products['B'])
        ));

        $this->assertNotNull($ab);
        $this->assertEqualsWithDelta(0.5, $ab['score'], 1e-6);
        $this->assertNotNull($ac);
        $this->assertEqualsWithDelta(0.707107, $ac['score'], 1e-6);
        $this->assertNull($bc); // skor 0 tidak disimpan

        // Tidak ada diagonal
        foreach ($result['similarities'] as $s) {
            $this->assertNotEquals($s['product_a'], $s['product_b']);
            $this->assertGreaterThan(0, $s['score']);
            $this->assertLessThanOrEqual(1, $s['score']);
        }

        // Tanpa normalisasi max: max_score bukan otomatis 1 kecuali ada pasangan identik
        $this->assertLessThan(1.0, $result['stats']['max_score'] + 1e-9);
        $this->assertEqualsWithDelta(0.707107, $result['stats']['max_score'], 1e-5);
    }

    public function test_min_co_occurrence_filters_pairs(): void
    {
        config(['recommendation.min_co_occurrence' => 2]);
        $products = $this->seedCatalog();
        $u1 = $this->makeUser('a@test.com');
        $u2 = $this->makeUser('b@test.com');
        $u3 = $this->makeUser('c@test.com');

        // A-B co-occurrence = 1 (hanya u1) → difilter
        $this->addTransaction($u1, [$products['A'] => 1, $products['B'] => 1]);
        // A-C co-occurrence = 2 (u2,u3) → lolos
        $this->addTransaction($u2, [$products['A'] => 1, $products['C'] => 1]);
        $this->addTransaction($u3, [$products['A'] => 1, $products['C'] => 1]);

        $result = $this->service->calculateCosineSimilarity($this->service->buildUserItemMatrix());
        $pairs = collect($result['similarities']);

        $this->assertTrue($pairs->contains(fn ($s) => (
            ($s['product_a'] == $products['A'] && $s['product_b'] == $products['C'])
            || ($s['product_a'] == $products['C'] && $s['product_b'] == $products['A'])
        )));
        $this->assertFalse($pairs->contains(fn ($s) => (
            ($s['product_a'] == $products['A'] && $s['product_b'] == $products['B'])
            || ($s['product_a'] == $products['B'] && $s['product_b'] == $products['A'])
        )));
    }

    public function test_invalid_transactions_excluded_from_matrix_and_bought(): void
    {
        $products = $this->seedCatalog();
        $user = $this->makeUser('buyer@test.com');

        $this->addTransaction($user, [$products['A'] => 1], 'Dibatalkan', 'Dibayar');
        $this->addTransaction($user, [$products['B'] => 1], 'Selesai', 'Belum Dibayar');
        $this->addTransaction($user, [$products['C'] => 1], 'Selesai', 'Dibayar');

        $matrix = $this->service->buildUserItemMatrix();
        $this->assertArrayHasKey($user, $matrix);
        $this->assertSame(0, $matrix[$user][$products['A']]);
        $this->assertSame(0, $matrix[$user][$products['B']]);
        $this->assertSame(1, $matrix[$user][$products['C']]);

        $bought = $this->service->getBoughtProducts($user);
        $this->assertSame([$products['C']], array_values($bought));
    }

    public function test_recommendation_aggregates_before_limit_and_excludes_purchased(): void
    {
        config(['recommendation.min_co_occurrence' => 1]);
        $products = $this->seedCatalog();

        // Shared buyers to create similarities
        $u1 = $this->makeUser('s1@test.com');
        $u2 = $this->makeUser('s2@test.com');
        $target = $this->makeUser('target@test.com');

        $this->addTransaction($u1, [$products['A'] => 1, $products['B'] => 1, $products['C'] => 1]);
        $this->addTransaction($u2, [$products['A'] => 1, $products['B'] => 1, $products['D'] => 1]);
        $this->addTransaction($target, [$products['A'] => 1, $products['B'] => 1]);

        $calc = $this->service->calculateCosineSimilarity($this->service->buildUserItemMatrix());
        $this->assertTrue($this->service->saveSimilarity($calc['similarities']));

        // Low rating on C should NOT filter IBCF
        DB::table('product_reviews')->insert([
            ['id_product' => $products['C'], 'id_user' => $u1, 'rating' => 1, 'created_at' => now()],
            ['id_product' => $products['C'], 'id_user' => $u2, 'rating' => 1, 'created_at' => now()],
        ]);

        $recs = $this->service->recommendForCustomer($target, 2);
        $ids = array_column($recs, 'id_product');

        $this->assertNotContains($products['A'], $ids);
        $this->assertNotContains($products['B'], $ids);
        $this->assertCount(2, $recs);
        $this->assertArrayHasKey('prediction_score', $recs[0]);
        $this->assertStringContainsString('kemiripan pola pembelian', $recs[0]['alasan']);
        // No hybrid_score key
        $this->assertArrayNotHasKey('hybrid_score', $recs[0]);
    }

    public function test_fallback_method_labels_are_not_cf(): void
    {
        $this->seedCatalog();
        $user = $this->makeUser('cold@test.com');
        $full = $this->service->getFullRecommendation($user, 4);

        $this->assertSame(RecommenderService::METHOD_COLD_START, $full['method']);
        $this->assertSame(RecommenderService::LOG_COLD_START, $full['log_source']);
        $this->assertStringContainsString('bukan', strtolower($full['method']));
    }

    public function test_admin_similarity_endpoint_requires_admin(): void
    {
        $pelanggan = User::factory()->create(['email' => 'p@test.com']);
        $this->actingAs($pelanggan)
            ->post(route('admin.similarity.recalculate'))
            ->assertRedirect(route('home'));

        $admin = User::factory()->admin()->create(['email' => 'admin@test.com']);
        $this->actingAs($admin)
            ->postJson(route('admin.similarity.recalculate'))
            ->assertOk()
            ->assertJson(['status' => false]); // matrix kosong
    }

    public function test_dirty_flag_cleared_only_on_successful_save(): void
    {
        config(['recommendation.min_co_occurrence' => 1]);
        $products = $this->seedCatalog();
        $u1 = $this->makeUser('d1@test.com');
        $u2 = $this->makeUser('d2@test.com');
        $this->addTransaction($u1, [$products['A'] => 1, $products['B'] => 1]);
        $this->addTransaction($u2, [$products['A'] => 1, $products['B'] => 1]);

        $this->service->setRecommendationDirty();
        $this->assertTrue($this->service->isRecommendationDirty());

        $this->assertFalse($this->service->saveSimilarity([]));
        $this->assertTrue($this->service->isRecommendationDirty());

        $calc = $this->service->calculateCosineSimilarity($this->service->buildUserItemMatrix());
        $this->assertTrue($this->service->saveSimilarity($calc['similarities']));
        $this->assertFalse($this->service->isRecommendationDirty());

        // Simetri di DB
        $forward = DB::table('product_similarity')
            ->where('product_a', $products['A'])
            ->where('product_b', $products['B'])
            ->value('score');
        $backward = DB::table('product_similarity')
            ->where('product_a', $products['B'])
            ->where('product_b', $products['A'])
            ->value('score');
        $this->assertEqualsWithDelta((float) $forward, (float) $backward, 1e-6);
        $this->assertEquals(0, DB::table('product_similarity')->whereColumn('product_a', 'product_b')->count());
    }
}
