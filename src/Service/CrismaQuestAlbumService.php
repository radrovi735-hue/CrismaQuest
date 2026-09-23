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
            $albumProgress = (new CrismaQuestAlbumProgressService())->getProgress($userId);
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
                $card = CrismaQuestSaintCatalog::enrich($card);
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
                'albumProgress'=>$albumProgress,
            ];
        } catch (Throwable) {
            return $this->emptyAlbum();
        }
    }

    private function grantStarterCardIfEmpty(int $userId): void
    {
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            (new CrismaQuestRewardService())->ensureStarterCard($pdo,$userId);
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }

    private function emptyAlbum(): array
    {
        return [
            'cards'=>[],
            'collected'=>0,
            'total'=>0,
            'progressPercent'=>0,
            'stateCounts'=>['locked'=>0,'collected'=>0,'repeated'=>0,'illuminated'=>0],
            'albumProgress'=>[
                'activeDays'=>0,'daysIntoPack'=>0,'daysUntilPack'=>3,'packsEarned'=>0,
                'collected'=>0,'duplicates'=>0,'finalProtection'=>false,
                'exchangeUnlocked'=>false,'exchangeAvailable'=>false,'exchangeUsedThisWeek'=>false,
            ],
        ];
    }
}
