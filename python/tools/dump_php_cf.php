<?php
/**
 * Dump hasil CF PHP (in-memory) ke JSON untuk parity_check.py.
 * Tidak menulis ke product_similarity.
 */
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RecommenderService;

$svc = app(RecommenderService::class);
$matrix = $svc->buildUserItemMatrix();
$result = $svc->calculateCosineSimilarity($matrix);

$pairs = [];
foreach ($result['similarities'] as $sim) {
    $a = (int) $sim['product_a'];
    $b = (int) $sim['product_b'];
    if ($a > $b) {
        [$a, $b] = [$b, $a];
    }
    $key = $a . '-' . $b;
    $pairs[$key] = [
        'product_a' => $a,
        'product_b' => $b,
        'score' => (float) $sim['score'],
        'co_occurrence' => (int) $sim['co_occurrence'],
    ];
}

$sampleUsers = [];
foreach ($matrix as $userId => $products) {
    $bought = array_keys(array_filter($products, fn ($v) => (float) $v > 0));
    sort($bought, SORT_NUMERIC);
    if (count($bought) < 2) {
        continue;
    }
    $sampleUsers[] = [
        'id_user' => (int) $userId,
        'bought' => array_map('intval', $bought),
    ];
    if (count($sampleUsers) >= 5) {
        break;
    }
}

echo json_encode([
    'engine' => 'php',
    'min_co_occurrence' => $svc->minCoOccurrence(),
    'stats' => $result['stats'] ?? [],
    'pair_count' => count($pairs),
    'pairs' => $pairs,
    'sample_users' => $sampleUsers,
], JSON_UNESCAPED_UNICODE);
