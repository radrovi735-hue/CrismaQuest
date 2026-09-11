<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Service/CrismaQuestSaintCatalog.php';

use App\Service\CrismaQuestSaintCatalog;

function assertCardExample(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "OK: {$message}\n";
}

$expected = [
    'santa-teresinha-menino-jesus' => 'Fotografia',
    'sao-francisco-assis' => 'Pintura',
    'sao-pedro' => 'Ícone religioso',
];

$examples = array_filter(
    CrismaQuestSaintCatalog::all(),
    static fn (array $card): bool => array_key_exists($card['slug'], $expected)
);

assertCardExample(count($examples) === 3, 'três cartas reais selecionadas');
assertCardExample(
    array_values(array_unique(array_column($examples, 'image_kind'))) === array_values($expected),
    'fotografia, pintura e ícone religioso presentes'
);

foreach ($examples as $card) {
    foreach (['name', 'image_kind', 'source_url', 'credit', 'license', 'license_url', 'image_path', 'sha256'] as $field) {
        assertCardExample(!empty($card[$field]), $card['name'] . ': ' . $field . ' registrado');
    }

    $image = dirname(__DIR__) . '/public' . $card['image_path'];
    assertCardExample(is_file($image), $card['name'] . ': imagem local disponível');
    assertCardExample(hash_file('sha256', $image) === $card['sha256'], $card['name'] . ': obra original íntegra');
}

echo "PASS: três cartas reais prontas para a prévia do catequista\n";
