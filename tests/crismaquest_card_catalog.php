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
assertCardCatalog(count(array_unique(array_column($cards, 'image_path'))) === 40, 'uma imagem canônica por carta');

foreach ($cards as $card) {
    foreach (['name','category','short_bio','short_teaching','image_kind','source_url','credit','license','license_url','image_path','fallback_image_path','sha256','selection_policy'] as $field) {
        assertCardCatalog(!empty($card[$field]), $card['name'] . ': ' . $field . ' registrado');
    }

    assertCardCatalog(
        str_starts_with((string)$card['image_path'], 'https://commons.wikimedia.org/wiki/Special:Redirect/file/'),
        $card['name'] . ': imagem principal usa arquivo canônico do Commons'
    );
    assertCardCatalog(
        str_starts_with((string)$card['source_url'], 'https://commons.wikimedia.org/wiki/File:'),
        $card['name'] . ': página de origem registrada'
    );
    assertCardCatalog(
        mb_stripos((string)$card['image_kind'], 'escultura') === false,
        $card['name'] . ': escultura não é imagem principal'
    );

    $fallback = dirname(__DIR__) . '/public' . $card['fallback_image_path'];
    assertCardCatalog(is_file($fallback), $card['name'] . ': fallback local disponível');
    assertCardCatalog(hash_file('sha256', $fallback) === $card['sha256'], $card['name'] . ': fallback local íntegro');
}

echo "PASS: 40 cartas com imagem canônica reconhecível e fallback local\n";
