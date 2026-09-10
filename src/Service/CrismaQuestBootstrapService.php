<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Ensures the CrismaQuest extension tables and starter catalogue exist after
 * the original ChronoQuest installer has created the base database.
 */
class CrismaQuestBootstrapService
{
    private const LOCK_NAME = 'crismaquest_schema_bootstrap_v1';

    public static function ensureInstalled(): void
    {
        $pdo = Database::getConnection();

        if (self::isReady($pdo)) {
            return;
        }

        $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $lock->execute(['lock_name' => self::LOCK_NAME]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new RuntimeException('Não foi possível obter o bloqueio de instalação do CrismaQuest.');
        }

        try {
            if (!self::isReady($pdo)) {
                $root = dirname(__DIR__, 2);
                self::importSqlFile($pdo, $root . '/sql/crismaquest/001_core.sql');
                self::importSqlFile($pdo, $root . '/sql/crismaquest/002_saints_seed.sql');
            }

            if (!self::isReady($pdo)) {
                throw new RuntimeException('A extensão do banco do CrismaQuest não foi instalada completamente.');
            }
        } finally {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $release->execute(['lock_name' => self::LOCK_NAME]);
            } catch (Throwable) {
                // The request may end safely even if MySQL already released the lock.
            }
        }
    }

    private static function isReady(PDO $pdo): bool
    {
        try {
            $tables = $pdo->query(
                "SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN (
                     'cq_streaks','cq_streak_events','cq_saint_cards','cq_card_editions',
                     'cq_user_cards','cq_meetings','cq_attendance','cq_attendance_audit'
                   )"
            );

            if ((int) $tables->fetchColumn() !== 8) {
                return false;
            }

            $saints = $pdo->query('SELECT COUNT(*) FROM cq_saint_cards');
            $editions = $pdo->query("SELECT COUNT(*) FROM cq_card_editions WHERE edition_type = 'normal'");

            return (int) $saints->fetchColumn() >= 20 && (int) $editions->fetchColumn() >= 20;
        } catch (Throwable) {
            return false;
        }
    }

    private static function importSqlFile(PDO $pdo, string $path): void
    {
        if (!is_readable($path)) {
            throw new RuntimeException('Arquivo SQL do CrismaQuest não encontrado: ' . basename($path));
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Não foi possível ler ' . basename($path));
        }

        // The CrismaQuest migration files intentionally contain no stored
        // procedures; stripping whole-line comments and splitting on ; is safe.
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
