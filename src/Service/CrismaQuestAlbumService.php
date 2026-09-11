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
            return $this->emptyAlbum();
        }

        try {
            $this->grantStarterCardIfEmpty($userId);
            $stmt = Database::getConnection()->prepare(
                'SELECT c.id,c.card_number,c.slug,c.name,c.category,c.short_bio,c.feast_date,c.short_teaching,c.image_path,c.image_license,
                        normal.id AS edition_id,
                        COALESCE(un.quantity,0) AS quantity,
                        un.first_obtained_at AS normal_obtained_at,
                        illuminated.id AS illuminated_edition_id,
                        COALESCE(ui.quantity,0) AS illuminated_quantity,
                        ui.first_obtained_at AS illuminated_obtained_at
                 FROM cq_saint_cards c
                 LEFT JOIN cq_card_editions normal
                        ON normal.card_id=c.id AND normal.edition_type="normal" AND normal.active=1
                 LEFT JOIN cq_user_cards un
                        ON un.card_edition_id=normal.id AND un.user_id=:normal_user
                 LEFT JOIN cq_card_editions illuminated
                        ON illuminated.card_id=c.id AND illuminated.edition_type="illuminated" AND illuminated.active=1
                 LEFT JOIN cq_user_cards ui
                        ON ui.card_edition_id=illuminated.id AND ui.user_id=:illuminated_user
                 WHERE c.active=1
                 ORDER BY c.card_number'
            );
            $stmt->execute(['normal_user'=>$userId,'illuminated_user'=>$userId]);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stateCounts = ['locked'=>0,'collected'=>0,'repeated'=>0,'illuminated'=>0];
            foreach ($cards as &$card) {
                $normal = (int)($card['quantity'] ?? 0);
                $illuminated = (int)($card['illuminated_quantity'] ?? 0);
                $totalQuantity = $normal + $illuminated;

                if ($illuminated > 0) {
                    $state = 'illuminated';
                    $displayEdition = 'illuminated';
                } elseif ($normal > 1) {
                    $state = 'repeated';
                    $displayEdition = 'normal';
                } elseif ($normal > 0) {
                    $state = 'collected';
                    $displayEdition = 'normal';
                } else {
                    $state = 'locked';
                    $displayEdition = 'normal';
                }

                $card['collection_state'] = $state;
                $card['display_edition'] = $displayEdition;
                $card['total_quantity'] = $totalQuantity;
                $card['first_obtained_at'] = $card['illuminated_obtained_at'] ?: $card['normal_obtained_at'];
                $stateCounts[$state]++;
            }
            unset($card);

            $collected = $stateCounts['collected'] + $stateCounts['repeated'] + $stateCounts['illuminated'];
            $total = count($cards);

            return [
                'cards'=>$cards,
                'collected'=>$collected,
                'total'=>$total,
                'progressPercent'=>$total > 0 ? (int)floor(($collected/$total)*100) : 0,
                'stateCounts'=>$stateCounts,
            ];
        } catch (Throwable) {
            return $this->emptyAlbum();
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
            'SELECT e.id
             FROM cq_card_editions e
             INNER JOIN cq_saint_cards c ON c.id=e.card_id
             WHERE c.card_number=1 AND e.edition_type="normal" AND e.active=1
             LIMIT 1'
        )->fetchColumn();
        if (!$edition) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO cq_user_cards (user_id,card_edition_id,quantity,first_obtained_at)
             VALUES (:user_id,:edition_id,1,NOW())'
        );
        $insert->execute(['user_id'=>$userId,'edition_id'=>(int)$edition]);
    }

    private function emptyAlbum(): array
    {
        return [
            'cards'=>[],
            'collected'=>0,
            'total'=>0,
            'progressPercent'=>0,
            'stateCounts'=>['locked'=>0,'collected'=>0,'repeated'=>0,'illuminated'=>0],
        ];
    }
}
