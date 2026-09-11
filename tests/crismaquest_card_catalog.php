<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Service/CrismaQuestSaintCatalog.php';

use App\Service\CrismaQuestSaintCatalog;

function assertCardCatalog(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "OK: {$message}\n";
}

$cards = CrismaQuestSaintCatalog::all();
assertCardCatalog(count($cards) === 40, 'catálogo contém 40 santos');
assertCardCatalog(count(array_unique(array_column($cards, 'card_number'))) === 40, 'numeração sem repetição');
assertCardCatalog(count(array_unique(array_column($cards, 'slug'))) === 40, 'identificadores sem repetição');
assertCardCatalog(count(array_unique(array_column($cards, 'image_path'))) === 40, 'uma imagem local por carta');

foreach ($cards as $card) {
    foreach (['name', 'category', 'short_bio', 'short_teaching', 'image_kind', 'source_url', 'credit', 'license', 'license_url', 'image_path', 'sha256'] as $field) {
        assertCardCatalog(!empty($card[$field]), $card['name'] . ': ' . $field . ' registrado');
    }

    $image = dirname(__DIR__) . '/public' . $card['image_path'];
    assertCardCatalog(is_file($image), $card['name'] . ': imagem local disponível');
    assertCardCatalog(hash_file('sha256', $image) === $card['sha256'], $card['name'] . ': arquivo íntegro');
}

echo "PASS: 40 cartas reais prontas para a prévia responsiva\n";
