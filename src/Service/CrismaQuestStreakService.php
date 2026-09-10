<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class CrismaQuestStreakService
{
    private const TIMEZONE = 'America/Fortaleza';

    public function getStatus(?int $userId = null): array
    {
        $userId ??= (int) (Session::get('user')['id'] ?? 0);
        if ($userId <= 0) {
            return $this->emptyStatus();
        }

        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT current_streak, longest_streak, last_qualified_activity_date, streak_freezes_available
                 FROM cq_streaks WHERE user_id = :user_id LIMIT 1'
            );
            $stmt->execute(['user_id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $this->emptyStatus();
            }

            return [
                'current' => (int) ($row['current_streak'] ?? 0),
                'longest' => (int) ($row['longest_streak'] ?? 0),
                'lastDate' => $row['last_qualified_activity_date'] ?? null,
                'freezes' => (int) ($row['streak_freezes_available'] ?? 0),
            ];
        } catch (Throwable) {
            // O ChronoQuest continua funcionando mesmo antes da migração CrismaQuest ser aplicada.
            return $this->emptyStatus();
        }
    }

    public function qualifyActivity(string $sourceType, string|int $sourceId, ?int $userId = null): array
    {
        $userId ??= (int) (Session::get('user')['id'] ?? 0);
        if ($userId <= 0) {
            return $this->emptyStatus();
        }

        $now = new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
        $today = $now->format('Y-m-d');
        $idempotencyKey = hash('sha256', implode('|', [$userId, $sourceType, (string) $sourceId, $today]));

        try {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();

            $event = $pdo->prepare(
                'INSERT IGNORE INTO cq_streak_events
                    (user_id, activity_date, source_type, source_id, idempotency_key, created_at)
                 VALUES (:user_id, :activity_date, :source_type, :source_id, :idempotency_key, NOW())'
            );
            $event->execute([
                'user_id' => $userId,
                'activity_date' => $today,
                'source_type' => mb_substr($sourceType, 0, 50),
                'source_id' => mb_substr((string) $sourceId, 0, 100),
                'idempotency_key' => $idempotencyKey,
            ]);

            $select = $pdo->prepare(
                'SELECT current_streak, longest_streak, last_qualified_activity_date, streak_freezes_available
                 FROM cq_streaks WHERE user_id = :user_id FOR UPDATE'
            );
            $select->execute(['user_id' => $userId]);
            $row = $select->fetch(PDO::FETCH_ASSOC);

            $current = (int) ($row['current_streak'] ?? 0);
            $longest = (int) ($row['longest_streak'] ?? 0);
            $lastDate = $row['last_qualified_activity_date'] ?? null;
            $freezes = (int) ($row['streak_freezes_available'] ?? 0);

            if ($lastDate !== $today) {
                $yesterday = $now->modify('-1 day')->format('Y-m-d');
                $current = ($lastDate === $yesterday) ? $current + 1 : 1;
                $longest = max($longest, $current);

                $upsert = $pdo->prepare(
                    'INSERT INTO cq_streaks
                        (user_id, current_streak, longest_streak, last_qualified_activity_date, streak_freezes_available, updated_at)
                     VALUES (:user_id, :current_streak, :longest_streak, :last_date, :freezes, NOW())
                     ON DUPLICATE KEY UPDATE
                        current_streak = VALUES(current_streak),
                        longest_streak = VALUES(longest_streak),
                        last_qualified_activity_date = VALUES(last_qualified_activity_date),
                        streak_freezes_available = VALUES(streak_freezes_available),
                        updated_at = NOW()'
                );
                $upsert->execute([
                    'user_id' => $userId,
                    'current_streak' => $current,
                    'longest_streak' => $longest,
                    'last_date' => $today,
                    'freezes' => $freezes,
                ]);
                $lastDate = $today;
            }

            $pdo->commit();
            return ['current'=>$current,'longest'=>$longest,'lastDate'=>$lastDate,'freezes'=>$freezes];
        } catch (Throwable) {
            try {
                $pdo = Database::getConnection();
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {
            }
            return $this->getStatus($userId);
        }
    }

    private function emptyStatus(): array
    {
        return ['current'=>0,'longest'=>0,'lastDate'=>null,'freezes'=>0];
    }
}
