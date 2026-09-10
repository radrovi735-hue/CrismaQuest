<?php

namespace App\Service;

use PDO;
use Throwable;

final class CrismaQuestAlbumService
{
    public function getAlbum(?int $userId = null): array
    {
        $userId ??= (int)(Session::get('user')['id'] ?? 0);
        if ($userId <= 0) {
            return ['cards'=>[],'collected'=>0,'total'=>0,'progressPercent'=>0];
        }

        try {
            $this->grantStarterCardIfEmpty($userId);
            $stmt = Database::getConnection()->prepare(
                'SELECT c.id,c.card_number,c.slug,c.name,c.category,c.short_bio,c.feast_date,c.short_teaching,c.image_path,c.image_license,
                        e.id AS edition_id,e.edition_type,
                        COALESCE(uc.quantity,0) AS quantity,
                        uc.first_obtained_at
                 FROM cq_saint_cards c
                 LEFT JOIN cq_card_editions e ON e.card_id=c.id AND e.edition_type="normal" AND e.active=1
                 LEFT JOIN cq_user_cards uc ON uc.card_edition_id=e.id AND uc.user_id=:user_id
                 WHERE c.active=1
                 ORDER BY c.card_number'
            );
            $stmt->execute(['user_id'=>$userId]);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $collected = count(array_filter($cards, static fn(array $card): bool => (int)($card['quantity'] ?? 0) > 0));
            $total = count($cards);

            return [
                'cards'=>$cards,
                'collected'=>$collected,
                'total'=>$total,
                'progressPercent'=>$total > 0 ? (int)floor(($collected/$total)*100) : 0,
            ];
        } catch (Throwable) {
            return ['cards'=>[],'collected'=>0,'total'=>0,'progressPercent'=>0];
        }
    }

    private function grantStarterCardIfEmpty(int $userId): void
    {
        $pdo = Database::getConnection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM cq_user_cards WHERE user_id=:user_id');
        $count->execute(['user_id'=>$userId]);
        if ((int)$count->fetchColumn() > 0) {
            return;
        }

        $edition = $pdo->query(
            'SELECT e.id FROM cq_card_editions e INNER JOIN cq_saint_cards c ON c.id=e.card_id WHERE c.card_number=1 AND e.edition_type="normal" LIMIT 1'
        )->fetchColumn();
        if (!$edition) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO cq_user_cards (user_id,card_edition_id,quantity,first_obtained_at) VALUES (:user_id,:edition_id,1,NOW())'
        );
        $insert->execute(['user_id'=>$userId,'edition_id'=>(int)$edition]);
    }
}
