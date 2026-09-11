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

    $imagePath = (string)$card['image_path'];
    $sourceUrl = (string)$card['source_url'];
    assertCardCatalog(
        str_starts_with($imagePath, 'https://commons.wikimedia.org/wiki/Special:Redirect/file/')
        || str_starts_with($imagePath, 'https://www.vaticannews.va/')
        || str_starts_with($imagePath, '/assets/crismaquest/saints/')
        || str_starts_with($imagePath, 'https://www.ctsbooks.org/'),
        $card['name'] . ': imagem principal usa fonte canônica, oficial ou arquivo aprovado'
    );
    assertCardCatalog(
        str_starts_with($sourceUrl, 'https://commons.wikimedia.org/wiki/File:')
        || str_starts_with($sourceUrl, 'https://www.vaticannews.va/')
        || str_starts_with($sourceUrl, '/assets/crismaquest/saints/')
        || str_starts_with($sourceUrl, 'https://www.ctsbooks.org/'),
        $card['name'] . ': origem registrada'
    );
    assertCardCatalog(
        mb_stripos((string)$card['image_kind'], 'escultura') === false,
        $card['name'] . ': escultura não é imagem principal'
    );

    $fallbackPath = (string)$card['fallback_image_path'];
    if (str_starts_with($fallbackPath, 'https://')) {
        assertCardCatalog(
            str_starts_with($fallbackPath, 'https://commons.wikimedia.org/wiki/Special:Redirect/file/')
            || (($card['slug'] ?? '') === 'sao-carlo-acutis' && str_starts_with($fallbackPath, 'https://www.vaticannews.va/')),
            $card['name'] . ': fallback remoto reconhecido'
        );
    } else {
        $fallback = dirname(__DIR__) . '/public' . $fallbackPath;
        assertCardCatalog(is_file($fallback), $card['name'] . ': fallback local disponível');
        assertCardCatalog(hash_file('sha256', $fallback) === $card['sha256'], $card['name'] . ': fallback local íntegro');
    }
}

$staleCarlo = CrismaQuestSaintCatalog::enrich([
    'slug'=>'sao-carlo-acutis',
    'image_path'=>'/assets/crismaquest/saints/sao-carlo-acutis.jpg',
    'quantity'=>1,
]);
assertCardCatalog(
    (string)$staleCarlo['image_path'] === 'https://www.ctsbooks.org/wp-content/uploads/2025/10/St-Carlo-Acutis-Prayer-Card-1.png.webp',
    'aluno com registro antigo recebe a imagem aprovada do Carlo'
);
assertCardCatalog((int)$staleCarlo['quantity'] === 1, 'enriquecimento preserva dados do aluno');

$approved = [
    'sao-francisco-assis'=>'/assets/crismaquest/saints/sao-francisco-assis-user.jpg?v=20260911f',
    'sao-carlo-acutis'=>'https://www.ctsbooks.org/wp-content/uploads/2025/10/St-Carlo-Acutis-Prayer-Card-1.png.webp',
    'sao-jeronimo'=>'/assets/crismaquest/saints/sao-jeronimo-user.jpg?v=20260911f',
    'sao-jose'=>'/assets/crismaquest/saints/sao-jose-user.jpg?v=20260911f',
];
foreach ($approved as $slug=>$path) {
    $card = array_values(array_filter($cards, static fn(array $item): bool => $item['slug'] === $slug))[0] ?? null;
    assertCardCatalog($card !== null && $card['image_path'] === $path, $slug . ': usa exatamente o arquivo aprovado');
}

echo "PASS: 40 cartas válidas e quatro imagens aprovadas fixadas para todos os alunos\n";
