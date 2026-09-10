<?php

namespace App\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class CrismaQuestStreakService
{
    private const TIMEZONE = 'America/Fortaleza';

    public function getStatus(?int $userId = null): array
    {
        $userId ??= (int)(Session::get('user')['id'] ?? 0);
        if ($userId <= 0) return $this->emptyStatus();

        try {
            return $this->recomputeStatus($userId);
        } catch (Throwable) {
            return $this->readStoredStatus($userId);
        }
    }

    public function qualifyActivity(string $sourceType, string|int $sourceId, ?int $userId = null): array
    {
        $userId ??= (int)(Session::get('user')['id'] ?? 0);
        if ($userId <= 0) return $this->emptyStatus();

        $now = $this->now();
        $today = $now->format('Y-m-d');
        $idempotencyKey = hash('sha256', implode('|', [$userId,$sourceType,(string)$sourceId,$today]));

        try {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();

            $event = $pdo->prepare(
                'INSERT IGNORE INTO cq_streak_events
                    (user_id,activity_date,source_type,source_id,idempotency_key,created_at)
                 VALUES (:u,:d,:t,:s,:k,NOW())'
            );
            $event->execute([
                'u'=>$userId,
                'd'=>$today,
                't'=>mb_substr($sourceType,0,50),
                's'=>mb_substr((string)$sourceId,0,100),
                'k'=>$idempotencyKey,
            ]);

            // Escudo da Chama: se existe exatamente um dia perdido entre a última
            // atividade e hoje, ele é consumido automaticamente.
            $stored = $pdo->prepare(
                'SELECT last_qualified_activity_date, streak_freezes_available
                 FROM cq_streaks WHERE user_id=:u FOR UPDATE'
            );
            $stored->execute(['u'=>$userId]);
            $state = $stored->fetch(PDO::FETCH_ASSOC) ?: [];
            $last = (string)($state['last_qualified_activity_date'] ?? '');
            $freezes = (int)($state['streak_freezes_available'] ?? 0);

            if ($freezes > 0 && $last !== '') {
                $lastDate = new DateTimeImmutable($last, new DateTimeZone(self::TIMEZONE));
                $days = (int)$lastDate->diff($now)->format('%a');
                if ($days === 2) {
                    $missed = $now->sub(new DateInterval('P1D'))->format('Y-m-d');
                    if (!$this->isPaused($userId, $missed, $pdo) && !$this->isCovered($userId, $missed, $pdo)) {
                        $pdo->prepare(
                            'INSERT IGNORE INTO cq_streak_recoveries
                                (user_id,recovery_date,recovery_type,source_id,lumen_cost)
                             VALUES (:u,:d,"shield",NULL,0)'
                        )->execute(['u'=>$userId,'d'=>$missed]);
                        $pdo->prepare(
                            'UPDATE cq_streaks SET streak_freezes_available=GREATEST(0,streak_freezes_available-1)
                             WHERE user_id=:u'
                        )->execute(['u'=>$userId]);
                    }
                }
            }

            $pdo->commit();
            return $this->recomputeStatus($userId);
        } catch (Throwable) {
            try {
                $pdo = Database::getConnection();
                if ($pdo->inTransaction()) $pdo->rollBack();
            } catch (Throwable) {
            }
            return $this->readStoredStatus($userId);
        }
    }

    public function addFreeze(int $userId, int $quantity = 1): void
    {
        if ($userId <= 0 || $quantity <= 0) return;
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO cq_streaks
                (user_id,current_streak,longest_streak,last_qualified_activity_date,streak_freezes_available)
             VALUES (:u,0,0,NULL,:q)
             ON DUPLICATE KEY UPDATE streak_freezes_available=streak_freezes_available+VALUES(streak_freezes_available)'
        )->execute(['u'=>$userId,'q'=>$quantity]);
    }

    public function findMostRecentMissedDate(int $userId, int $windowHours = 48): ?string
    {
        if ($userId <= 0) return null;
        $pdo = Database::getConnection();
        $maxDays = max(1, (int)ceil($windowHours / 24));
        $now = $this->now();

        for ($i=1; $i<=$maxDays; $i++) {
            $candidate = $now->sub(new DateInterval('P'.$i.'D'));
            $date = $candidate->format('Y-m-d');
            if ($this->isPaused($userId, $date, $pdo)) continue;
            if ($this->isCovered($userId, $date, $pdo)) continue;

            // Não permite comprar/criar uma sequência que nunca existiu.
            // O dia anterior ao buraco precisa ter sido efetivamente coberto.
            $previous = $candidate->sub(new DateInterval('P1D'))->format('Y-m-d');
            if ($this->isCovered($userId, $previous, $pdo) || $this->isPaused($userId, $previous, $pdo)) {
                return $date;
            }
        }
        return null;
    }

    public function recoverDate(int $userId, string $date, string $type, ?int $sourceId = null, int $lumenCost = 0): bool
    {
        if ($userId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO cq_streak_recoveries
                (user_id,recovery_date,recovery_type,source_id,lumen_cost)
             VALUES (:u,:d,:t,:s,:c)'
        );
        $stmt->execute([
            'u'=>$userId,
            'd'=>$date,
            't'=>mb_substr($type,0,30),
            's'=>$sourceId,
            'c'=>$lumenCost,
        ]);
        $this->recomputeStatus($userId);
        return $stmt->rowCount() > 0;
    }

    public function recomputeStatus(int $userId): array
    {
        if ($userId <= 0) return $this->emptyStatus();
        $pdo = Database::getConnection();
        $now = $this->now();
        $today = $now->format('Y-m-d');
        $from = $now->sub(new DateInterval('P180D'))->format('Y-m-d');

        $events = $pdo->prepare(
            'SELECT DISTINCT activity_date FROM cq_streak_events
             WHERE user_id=:u AND activity_date>=:d'
        );
        $events->execute(['u'=>$userId,'d'=>$from]);
        $covered = array_fill_keys($events->fetchAll(PDO::FETCH_COLUMN) ?: [], true);

        try {
            $recoveries = $pdo->prepare(
                'SELECT DISTINCT recovery_date FROM cq_streak_recoveries
                 WHERE user_id=:u AND recovery_date>=:d'
            );
            $recoveries->execute(['u'=>$userId,'d'=>$from]);
            foreach ($recoveries->fetchAll(PDO::FETCH_COLUMN) ?: [] as $date) {
                $covered[$date] = true;
            }
        } catch (Throwable) {
        }

        $cursor = $now;
        if (!isset($covered[$today]) && !$this->isPaused($userId, $today, $pdo)) {
            $cursor = $cursor->sub(new DateInterval('P1D'));
        }

        $current = 0;
        for ($i=0; $i<180; $i++) {
            $date = $cursor->format('Y-m-d');
            if ($this->isPaused($userId, $date, $pdo)) {
                $cursor = $cursor->sub(new DateInterval('P1D'));
                continue;
            }
            if (!isset($covered[$date])) break;
            $current++;
            $cursor = $cursor->sub(new DateInterval('P1D'));
        }

        $lastEvent = $pdo->prepare(
            'SELECT MAX(activity_date) FROM cq_streak_events WHERE user_id=:u'
        );
        $lastEvent->execute(['u'=>$userId]);
        $lastDate = $lastEvent->fetchColumn() ?: null;

        $stored = $this->readStoredStatus($userId);
        $longest = max((int)$stored['longest'], $current);
        $freezes = (int)$stored['freezes'];

        $pdo->prepare(
            'INSERT INTO cq_streaks
                (user_id,current_streak,longest_streak,last_qualified_activity_date,streak_freezes_available,updated_at)
             VALUES (:u,:c,:l,:d,:f,NOW())
             ON DUPLICATE KEY UPDATE
                current_streak=VALUES(current_streak),
                longest_streak=GREATEST(longest_streak,VALUES(longest_streak)),
                last_qualified_activity_date=VALUES(last_qualified_activity_date),
                updated_at=NOW()'
        )->execute([
            'u'=>$userId,
            'c'=>$current,
            'l'=>$longest,
            'd'=>$lastDate,
            'f'=>$freezes,
        ]);

        return ['current'=>$current,'longest'=>$longest,'lastDate'=>$lastDate,'freezes'=>$freezes];
    }

    private function isCovered(int $userId, string $date, PDO $pdo): bool
    {
        $stmt = $pdo->prepare(
            'SELECT
               (SELECT COUNT(*) FROM cq_streak_events WHERE user_id=:u1 AND activity_date=:d1) +
               (SELECT COUNT(*) FROM cq_streak_recoveries WHERE user_id=:u2 AND recovery_date=:d2)'
        );
        $stmt->execute(['u1'=>$userId,'d1'=>$date,'u2'=>$userId,'d2'=>$date]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function isPaused(int $userId, string $date, PDO $pdo): bool
    {
        try {
            $recessStart = $this->config($pdo, 'recess_start');
            $recessEnd = $this->config($pdo, 'recess_end');
            if ($recessStart && $recessEnd && $date >= $recessStart && $date <= $recessEnd) return true;

            $classes = $pdo->prepare(
                'SELECT DISTINCT sc.fk_classe
                 FROM ct_studenti s
                 JOIN ct_studenti_classi sc ON sc.fk_studente=s.id_studente
                 WHERE s.fk_utente=:u'
            );
            $classes->execute(['u'=>$userId]);
            $classIds = array_map('intval', $classes->fetchAll(PDO::FETCH_COLUMN) ?: []);

            $sql =
                'SELECT COUNT(*) FROM cq_streak_pauses
                 WHERE :d BETWEEN start_date AND end_date
                   AND ((scope_type="user" AND scope_id=:u)';
            $params = ['d'=>$date,'u'=>$userId];
            if ($classIds !== []) {
                $marks = implode(',', array_fill(0, count($classIds), '?'));
                // Named + positional parameters should not be mixed in PDO.
                $sql =
                    'SELECT COUNT(*) FROM cq_streak_pauses
                     WHERE ? BETWEEN start_date AND end_date
                       AND ((scope_type="user" AND scope_id=?) OR
                            (scope_type="class" AND scope_id IN ('.$marks.')))';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge([$date,$userId],$classIds));
                return (int)$stmt->fetchColumn() > 0;
            }
            $sql .= ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function config(PDO $pdo, string $key): ?string
    {
        try {
            $stmt = $pdo->prepare('SELECT config_value FROM cq_game_config WHERE config_key=:k LIMIT 1');
            $stmt->execute(['k'=>$key]);
            $value = $stmt->fetchColumn();
            return $value === false ? null : (string)$value;
        } catch (Throwable) {
            return null;
        }
    }

    private function readStoredStatus(int $userId): array
    {
        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT current_streak,longest_streak,last_qualified_activity_date,streak_freezes_available
                 FROM cq_streaks WHERE user_id=:u LIMIT 1'
            );
            $stmt->execute(['u'=>$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return $this->emptyStatus();
            return [
                'current'=>(int)$row['current_streak'],
                'longest'=>(int)$row['longest_streak'],
                'lastDate'=>$row['last_qualified_activity_date'] ?: null,
                'freezes'=>(int)$row['streak_freezes_available'],
            ];
        } catch (Throwable) {
            return $this->emptyStatus();
        }
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
    }

    private function emptyStatus(): array
    {
        return ['current'=>0,'longest'=>0,'lastDate'=>null,'freezes'=>0];
    }
}
