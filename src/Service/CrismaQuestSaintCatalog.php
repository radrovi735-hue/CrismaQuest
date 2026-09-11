<?php

namespace App\Service;

final class CrismaQuestSaintCatalog
{
    public static function all(): array
    {
        static $cards;
        if ($cards === null) {
            $cards = json_decode(file_get_contents(__DIR__ . '/../../config/crismaquest/saints.json'), true, 512, JSON_THROW_ON_ERROR);
        }
        return $cards;
    }

    public static function enrich(array $card): array
    {
        foreach (self::all() as $art) {
            if ($art['slug'] === ($card['slug'] ?? '')) {
                return $card + $art;
            }
        }
        return $card;
    }
}
