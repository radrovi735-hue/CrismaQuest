<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class CrismaQuestAlbumProgressService
{
    private const TZ = 'America/Fortaleza';
    private const DAYS_PER_PACK = 3;
    private const DUPLICATES_PER_EXCHANGE = 5;

    private CrismaQuestRewardService $rewards;

    public function __construct()
    {
        $this->rewards = new CrismaQuestRewardService();
    }

    public function recordActiveDay(int $userId, string $activityDate): array
    {
        if ($userId <= 0) return ['activeDays'=>0,'packsAwarded'=>0,'cardsAwarded'=>0];

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();
            $this->rewards->ensureStarterCard($pdo,$userId);
            $this->backfillUserActivity($pdo,$userId);

            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO cq_album_activity (user_id,activity_date)
                 VALUES (:u,:d)'
            );
            $stmt->execute(['u'=>$userId,'d'=>$activityDate]);

            $activeDays = $this->activeDays($pdo,$userId);
            $result = $this->grantDuePacks($pdo,$userId,$activeDays);

            $pdo->commit();
            return ['activeDays'=>$activeDays] + $result;
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['activeDays'=>0,'packsAwarded'=>0,'cardsAwarded'=>0];
        }
    }

    public function getProgress(int $userId): array
    {
        $default = [
            'activeDays'=>0,
            'daysIntoPack'=>0,
            'daysUntilPack'=>self::DAYS_PER_PACK,
            'packsEarned'=>0,
            'collected'=>0,
            'duplicates'=>0,
            'finalProtection'=>false,
            'exchangeUnlocked'=>false,
            'exchangeAvailable'=>false,
            'exchangeUsedThisWeek'=>false,
        ];
        if ($userId <= 0) return $default;

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $this->rewards->ensureStarterCard($pdo,$userId);
            $this->backfillUserActivity($pdo,$userId);
            $days = $this->activeDays($pdo,$userId);
            $this->grantDuePacks($pdo,$userId,$days);
            $pdo->commit();

            $collected = $this->collectedSaints($pdo,$userId);
            $duplicates = $this->duplicateCopies($pdo,$userId);
            $mod = $days % self::DAYS_PER_PACK;
            $used = $this->exchangeUsedThisWeek($pdo,$userId);

            return [
                'activeDays'=>$days,
                'daysIntoPack'=>$mod,
                'daysUntilPack'=>$mod === 0 ? self::DAYS_PER_PACK : self::DAYS_PER_PACK-$mod,
                'packsEarned'=>intdiv($days,self::DAYS_PER_PACK),
                'collected'=>$collected,
                'duplicates'=>$duplicates,
                'finalProtection'=>$collected >= 35 && $collected < 40,
                'exchangeUnlocked'=>$collected >= 30 && $collected < 40,
                'exchangeAvailable'=>$collected >= 30 && $collected < 40 && $duplicates >= self::DUPLICATES_PER_EXCHANGE && !$used,
                'exchangeUsedThisWeek'=>$used,
            ];
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $default;
        }
    }

    public function exchangeDuplicatesForNew(int $userId): array
    {
        if ($userId <= 0) return ['success'=>false,'message'=>'Sessão inválida.'];

        $pdo = Database::getConnection();
        $weekKey = $this->weekKey();

        try {
            $pdo->beginTransaction();

            $collected = $this->collectedSaints($pdo,$userId);
            if ($collected < 30) throw new RuntimeException('A troca de repetidas é liberada a partir de 30/40 cartas.');
            if ($collected >= 40) throw new RuntimeException('Seu álbum normal já está completo.');

            $duplicates = $this->duplicateCopies($pdo,$userId);
            if ($duplicates < self::DUPLICATES_PER_EXCHANGE) {
                throw new RuntimeException('Você precisa de pelo menos 5 cópias repetidas.');
            }

            if (!$this->rewards->markOnce(
                $pdo,$userId,'duplicate-exchange:' . $weekKey,'Troca semanal de 5 repetidas'
            )) {
                throw new RuntimeException('Você já fez a troca de repetidas nesta semana.');
            }

            $rows = $pdo->prepare(
                'SELECT uc.card_edition_id,uc.quantity
                 FROM cq_user_cards uc
                 JOIN cq_card_editions ce ON ce.id=uc.card_edition_id
                 WHERE uc.user_id=:u
                   AND ce.edition_type="normal"
                   AND uc.quantity>1
                 ORDER BY uc.quantity DESC,uc.card_edition_id
                 FOR UPDATE'
            );
            $rows->execute(['u'=>$userId]);

            $remaining = self::DUPLICATES_PER_EXCHANGE;
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ($remaining <= 0) break;
                $available = max(0,(int)$row['quantity']-1);
                $take = min($available,$remaining);
                if ($take <= 0) continue;

                $pdo->prepare(
                    'UPDATE cq_user_cards
                     SET quantity = quantity - :take
                     WHERE user_id=:u AND card_edition_id=:e'
                )->execute([
                    'take'=>$take,
                    'u'=>$userId,
                    'e'=>(int)$row['card_edition_id'],
                ]);
                $remaining -= $take;
            }

            if ($remaining > 0) throw new RuntimeException('Não foi possível reunir 5 cópias repetidas.');

            $card = $this->rewards->grantCard(
                $pdo,$userId,'duplicate-exchange-card-' . $weekKey,null,true
            );
            if (!$card) throw new RuntimeException('Não foi possível entregar a nova carta.');

            $pdo->commit();
            return ['success'=>true,'message'=>'Troca concluída: 5 repetidas viraram 1 carta nova.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success'=>false,'message'=>$e->getMessage() ?: 'Não foi possível concluir a troca.'];
        }
    }

    private function grantDuePacks(PDO $pdo, int $userId, int $activeDays): array
    {
        $due = intdiv($activeDays,self::DAYS_PER_PACK);
        $packsAwarded = 0;
        $cardsAwarded = 0;

        for ($pack=1; $pack<=$due; $pack++) {
            $packChanged = false;

            for ($slot=1; $slot<=2; $slot++) {
                $collected = $this->collectedSaints($pdo,$userId);
                $protected = $slot === 2 || $collected >= 35;

                $card = $this->rewards->grantCard(
                    $pdo,
                    $userId,
                    'album-pack-' . $pack . '-slot-' . $slot,
                    null,
                    $protected
                );

                if ($card) {
                    $cardsAwarded++;
                    $packChanged = true;
                }
            }

            if ($packChanged) $packsAwarded++;
        }

        return ['packsAwarded'=>$packsAwarded,'cardsAwarded'=>$cardsAwarded];
    }

    private function backfillUserActivity(PDO $pdo, int $userId): void
    {
        $mission = $pdo->prepare(
            'INSERT IGNORE INTO cq_album_activity (user_id,activity_date)
             SELECT user_id,DATE(completed_at)
             FROM cq_mission_completions
             WHERE user_id=:u'
        );
        $mission->execute(['u'=>$userId]);

        $spark = $pdo->prepare(
            'INSERT IGNORE INTO cq_album_activity (user_id,activity_date)
             SELECT user_id,activity_date
             FROM cq_spark_completions
             WHERE user_id=:u'
        );
        $spark->execute(['u'=>$userId]);
    }

    private function activeDays(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cq_album_activity WHERE user_id=:u');
        $stmt->execute(['u'=>$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function collectedSaints(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(DISTINCT ce.card_id)
             FROM cq_user_cards uc
             JOIN cq_card_editions ce ON ce.id=uc.card_edition_id
             JOIN cq_saint_cards sc ON sc.id=ce.card_id
             WHERE uc.user_id=:u AND uc.quantity>0 AND sc.active=1'
        );
        $stmt->execute(['u'=>$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function duplicateCopies(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(GREATEST(uc.quantity-1,0)),0)
             FROM cq_user_cards uc
             JOIN cq_card_editions ce ON ce.id=uc.card_edition_id
             WHERE uc.user_id=:u
               AND uc.quantity>1
               AND ce.edition_type="normal"'
        );
        $stmt->execute(['u'=>$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function exchangeUsedThisWeek(PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM cq_reward_events
             WHERE user_id=:u AND reward_key=:k'
        );
        $stmt->execute(['u'=>$userId,'k'=>'duplicate-exchange:' . $this->weekKey()]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function weekKey(): string
    {
        return (new DateTimeImmutable('now',new DateTimeZone(self::TZ)))->format('o-\WW');
    }
}
