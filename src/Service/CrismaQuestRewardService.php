<?php

namespace App\Service;

use PDO;
use RuntimeException;

final class CrismaQuestRewardService
{
    /**
     * Concede XP/Lúmens uma única vez por reward_key.
     * Deve ser chamado dentro de uma transação do chamador.
     */
    public function grant(PDO $pdo, int $userId, int $studentId, string $rewardKey, int $xp, int $lumens, string $description): array
    {
        $marker = $pdo->prepare(
            'INSERT IGNORE INTO cq_reward_events
                (user_id,reward_key,xp_delta,lumen_delta,description)
             VALUES (:u,:k,:xp,:l,:d)'
        );
        $marker->execute([
            'u'=>$userId,
            'k'=>mb_substr($rewardKey, 0, 190),
            'xp'=>$xp,
            'l'=>$lumens,
            'd'=>mb_substr($description, 0, 255),
        ]);

        if ($marker->rowCount() === 0) {
            return ['applied'=>false,'xp'=>0,'lumens'=>0];
        }

        $student = $pdo->prepare('SELECT xp, monete FROM ct_studenti WHERE id_studente=:s FOR UPDATE');
        $student->execute(['s'=>$studentId]);
        $row = $student->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Perfil do crismando não encontrado.');
        }

        $newXp = max(0, (int)$row['xp'] + $xp);
        $newLumens = max(0, (int)$row['monete'] + $lumens);

        $levelStmt = $pdo->prepare(
            'SELECT level_no, name FROM cq_game_levels
             WHERE xp_min <= :xp
             ORDER BY xp_min DESC LIMIT 1'
        );
        $levelStmt->execute(['xp'=>$newXp]);
        $level = $levelStmt->fetch(PDO::FETCH_ASSOC) ?: ['level_no'=>1,'name'=>'Peregrino'];

        $pdo->prepare(
            'UPDATE ct_studenti SET xp=:xp, monete=:l, livello=:level WHERE id_studente=:s'
        )->execute([
            'xp'=>$newXp,
            'l'=>$newLumens,
            'level'=>(int)$level['level_no'],
            's'=>$studentId,
        ]);

        if ($lumens !== 0) {
            $pdo->prepare(
                'INSERT INTO cq_lumen_ledger
                    (user_id,delta,balance_after,reason_type,reason_ref,description)
                 VALUES (:u,:d,:b,:t,:r,:txt)'
            )->execute([
                'u'=>$userId,
                'd'=>$lumens,
                'b'=>$newLumens,
                't'=>'game_reward',
                'r'=>mb_substr($rewardKey, 0, 120),
                'txt'=>mb_substr($description, 0, 255),
            ]);
        }

        return [
            'applied'=>true,
            'xp'=>$xp,
            'lumens'=>$lumens,
            'totalXp'=>$newXp,
            'balance'=>$newLumens,
            'level'=>(int)$level['level_no'],
            'levelName'=>(string)$level['name'],
        ];
    }

    public function markOnce(PDO $pdo, int $userId, string $rewardKey, string $description): bool
    {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO cq_reward_events
                (user_id,reward_key,xp_delta,lumen_delta,description)
             VALUES (:u,:k,0,0,:d)'
        );
        $stmt->execute([
            'u'=>$userId,
            'k'=>mb_substr($rewardKey,0,190),
            'd'=>mb_substr($description,0,255),
        ]);
        return $stmt->rowCount() > 0;
    }

    public function grantCard(PDO $pdo, int $userId, string $rewardKey, ?string $saintSlug = null, bool $preferNew = true, string $editionType = 'normal'): ?array
    {
        // O reward_key independente impede duplicação mesmo se o endpoint for reenviado.
        if (!$this->markOnce($pdo, $userId, 'card:' . $rewardKey, 'Carta do Álbum')) {
            return null;
        }

        if ($saintSlug !== null) {
            $stmt = $pdo->prepare(
                'SELECT ce.id, sc.name, sc.slug, ce.edition_type
                 FROM cq_card_editions ce
                 JOIN cq_saint_cards sc ON sc.id=ce.card_id
                 WHERE sc.slug=:slug AND ce.edition_type=:edition AND ce.active=1 AND sc.active=1
                 LIMIT 1'
            );
            $stmt->execute(['slug'=>$saintSlug,'edition'=>$editionType]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $sql =
                'SELECT ce.id, sc.name, sc.slug, ce.edition_type
                 FROM cq_card_editions ce
                 JOIN cq_saint_cards sc ON sc.id=ce.card_id
                 LEFT JOIN cq_user_cards uc ON uc.user_id=:u AND uc.card_edition_id=ce.id
                 WHERE ce.edition_type=:edition AND ce.active=1 AND sc.active=1 ';
            if ($preferNew) {
                $sql .= 'AND COALESCE(uc.quantity,0)=0 ';
            }
            $sql .= 'ORDER BY CRC32(CONCAT(sc.card_number, :seed)) ASC LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'u'=>$userId,
                'edition'=>$editionType,
                'seed'=>$rewardKey . ':' . $userId,
            ]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card && $preferNew) {
                // Coleção completa: duplicata passa a ser permitida.
                return $this->grantCardFallback($pdo, $userId, $rewardKey, $editionType);
            }
        }

        if (!$card) {
            return null;
        }

        $pdo->prepare(
            'INSERT INTO cq_user_cards (user_id,card_edition_id,quantity)
             VALUES (:u,:e,1)
             ON DUPLICATE KEY UPDATE quantity=quantity+1'
        )->execute(['u'=>$userId,'e'=>(int)$card['id']]);

        return $card;
    }

    private function grantCardFallback(PDO $pdo, int $userId, string $rewardKey, string $editionType): ?array
    {
        // O marcador da tentativa principal já foi criado. Aqui apenas escolhemos e entregamos.
        $stmt = $pdo->prepare(
            'SELECT ce.id, sc.name, sc.slug, ce.edition_type
             FROM cq_card_editions ce
             JOIN cq_saint_cards sc ON sc.id=ce.card_id
             WHERE ce.edition_type=:edition AND ce.active=1 AND sc.active=1
             ORDER BY CRC32(CONCAT(sc.card_number, :seed)) ASC LIMIT 1'
        );
        $stmt->execute(['edition'=>$editionType,'seed'=>$rewardKey . ':' . $userId]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$card) return null;

        $pdo->prepare(
            'INSERT INTO cq_user_cards (user_id,card_edition_id,quantity)
             VALUES (:u,:e,1)
             ON DUPLICATE KEY UPDATE quantity=quantity+1'
        )->execute(['u'=>$userId,'e'=>(int)$card['id']]);
        return $card;
    }

    public function grantIlluminatedCard(PDO $pdo, int $userId, string $rewardKey): ?array
    {
        if (!$this->markOnce($pdo, $userId, 'illuminated:' . $rewardKey, 'Carta em edição iluminada')) {
            return null;
        }

        $card = $pdo->query(
            'SELECT sc.id, sc.name, sc.slug
             FROM cq_saint_cards sc
             WHERE sc.active=1
             ORDER BY sc.card_number ASC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        if (!$card) return null;

        $pdo->prepare(
            'INSERT INTO cq_card_editions (card_id,edition_type,visual_asset,chance_weight,active)
             VALUES (:card,"illuminated",NULL,20,1)
             ON DUPLICATE KEY UPDATE active=1'
        )->execute(['card'=>(int)$card['id']]);

        $edition = $pdo->prepare(
            'SELECT id FROM cq_card_editions WHERE card_id=:card AND edition_type="illuminated" LIMIT 1'
        );
        $edition->execute(['card'=>(int)$card['id']]);
        $editionId = (int)$edition->fetchColumn();
        if ($editionId <= 0) return null;

        $pdo->prepare(
            'INSERT INTO cq_user_cards (user_id,card_edition_id,quantity)
             VALUES (:u,:e,1)
             ON DUPLICATE KEY UPDATE quantity=quantity+1'
        )->execute(['u'=>$userId,'e'=>$editionId]);

        return ['id'=>$editionId,'name'=>$card['name'],'slug'=>$card['slug'],'edition_type'=>'illuminated'];
    }

    public function grantCosmetic(PDO $pdo, int $userId, string $slug, string $rewardKey): bool
    {
        if (!$this->markOnce($pdo, $userId, 'cosmetic:' . $rewardKey, 'Cosmético CrismaQuest')) {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT id, cosmetic_slot FROM cq_gift_catalog
             WHERE slug=:slug AND category="cosmetic" AND active=1 LIMIT 1'
        );
        $stmt->execute(['slug'=>$slug]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item || empty($item['cosmetic_slot'])) return false;

        $pdo->prepare(
            'INSERT INTO cq_user_cosmetics (user_id,gift_catalog_id,cosmetic_slot,equipped)
             VALUES (:u,:g,:slot,0)
             ON DUPLICATE KEY UPDATE obtained_at=obtained_at'
        )->execute([
            'u'=>$userId,
            'g'=>(int)$item['id'],
            'slot'=>$item['cosmetic_slot'],
        ]);
        return true;
    }

    public function grantBadge(PDO $pdo, int $userId, string $slug): bool
    {
        $stmt = $pdo->prepare('SELECT id FROM cq_badge_catalog WHERE slug=:slug AND active=1 LIMIT 1');
        $stmt->execute(['slug'=>$slug]);
        $id = (int)$stmt->fetchColumn();
        if ($id <= 0) return false;

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO cq_user_badges (user_id,badge_id) VALUES (:u,:b)'
        );
        $insert->execute(['u'=>$userId,'b'=>$id]);
        return $insert->rowCount() > 0;
    }
}
