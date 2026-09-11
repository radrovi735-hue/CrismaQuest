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
    'santo-inacio-antioquia' => 'Ícone religioso',
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
    foreach (['name','image_kind','source_url','credit','license','license_url','image_path','fallback_image_path','sha256'] as $field) {
        assertCardExample(!empty($card[$field]), $card['name'] . ': ' . $field . ' registrado');
    }

    assertCardExample(
        str_starts_with((string)$card['image_path'], 'https://commons.wikimedia.org/wiki/Special:Redirect/file/')
        || str_starts_with((string)$card['image_path'], '/assets/crismaquest/saints/'),
        $card['name'] . ': obra aprovada usada na carta'
    );
    $fallback = dirname(__DIR__) . '/public' . $card['fallback_image_path'];
    assertCardExample(is_file($fallback), $card['name'] . ': fallback local disponível');
    assertCardExample(hash_file('sha256', $fallback) === $card['sha256'], $card['name'] . ': fallback local íntegro');
}

echo "PASS: fotografia, pintura e ícone canônicos prontos para a prévia\n";
