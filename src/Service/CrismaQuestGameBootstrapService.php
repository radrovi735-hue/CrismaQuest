<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Instala o motor canônico de gameplay do CrismaQuest sem tocar no schema legado.
 */
final class CrismaQuestGameBootstrapService
{
    private const LOCK_NAME = 'crismaquest_gameplay_bootstrap_v1';

    public static function ensureInstalled(): void
    {
        $pdo = Database::getConnection();
        if (self::isReady($pdo)) {
            return;
        }

        $lock = $pdo->prepare('SELECT GET_LOCK(:name, 10)');
        $lock->execute(['name' => self::LOCK_NAME]);
        if ((int)$lock->fetchColumn() !== 1) {
            throw new RuntimeException('Não foi possível obter o bloqueio do gameplay CrismaQuest.');
        }

        try {
            $root = dirname(__DIR__, 2);
            self::importSqlFile($pdo, $root . '/sql/crismaquest/004_gameplay.sql');
            self::importSqlFile($pdo, $root . '/sql/crismaquest/005_gameplay_seed.sql');

            if (!self::isReady($pdo)) {
                throw new RuntimeException('O motor de gameplay CrismaQuest não ficou completo após a migração.');
            }
        } finally {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
                $release->execute(['name' => self::LOCK_NAME]);
            } catch (Throwable) {
            }
        }
    }

    private static function isReady(PDO $pdo): bool
    {
        try {
            $tables = [
                'cq_game_config','cq_game_levels','cq_journey_steps','cq_missions',
                'cq_mission_completions','cq_reward_events','cq_chest_catalog',
                'cq_user_chests','cq_badge_catalog','cq_user_badges','cq_daily_sparks',
                'cq_spark_completions','cq_intercessions','cq_streak_recoveries',
                'cq_streak_pauses'
            ];
            $placeholders = implode(',', array_fill(0, count($tables), '?'));
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name IN ($placeholders)"
            );
            $stmt->execute($tables);
            if ((int)$stmt->fetchColumn() !== count($tables)) {
                return false;
            }

            return (int)$pdo->query('SELECT COUNT(*) FROM cq_journey_steps WHERE active=1')->fetchColumn() >= 22
                && (int)$pdo->query('SELECT COUNT(*) FROM cq_missions WHERE active=1')->fetchColumn() >= 56
                && (int)$pdo->query('SELECT COUNT(*) FROM cq_daily_sparks WHERE active=1')->fetchColumn() >= 60
                && (int)$pdo->query('SELECT COUNT(*) FROM cq_game_levels')->fetchColumn() >= 8
                && (int)$pdo->query('SELECT COUNT(*) FROM cq_chest_catalog WHERE active=1')->fetchColumn() >= 9
                && (int)$pdo->query('SELECT COUNT(*) FROM cq_badge_catalog WHERE active=1')->fetchColumn() >= 14;
        } catch (Throwable) {
            return false;
        }
    }

    private static function importSqlFile(PDO $pdo, string $path): void
    {
        if (!is_readable($path)) {
            throw new RuntimeException('Arquivo de gameplay não encontrado: ' . basename($path));
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Não foi possível ler ' . basename($path));
        }
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*#.*$/m', '', $sql) ?? $sql;
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }
}
