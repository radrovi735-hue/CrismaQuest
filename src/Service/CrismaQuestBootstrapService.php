<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Instala e atualiza as extensões próprias do CrismaQuest de forma idempotente.
 */
class CrismaQuestBootstrapService
{
    private const LOCK_NAME = 'crismaquest_schema_bootstrap_v2';

    public static function ensureInstalled(): void
    {
        $pdo = Database::getConnection();

        if (self::isCoreReady($pdo) && self::isSocialReady($pdo)) {
            return;
        }

        $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $lock->execute(['lock_name' => self::LOCK_NAME]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new RuntimeException('Não foi possível obter o bloqueio de atualização do CrismaQuest.');
        }

        try {
            $root = dirname(__DIR__, 2);

            if (!self::isCoreReady($pdo)) {
                self::importSqlFile($pdo, $root . '/sql/crismaquest/001_core.sql');
                self::importSqlFile($pdo, $root . '/sql/crismaquest/002_saints_seed.sql');
            }

            if (!self::isSocialReady($pdo)) {
                self::importSqlFile($pdo, $root . '/sql/crismaquest/003_social_economy.sql');
            }

            if (!self::isCoreReady($pdo) || !self::isSocialReady($pdo)) {
                throw new RuntimeException('A atualização do banco do CrismaQuest não foi concluída.');
            }
        } finally {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $release->execute(['lock_name' => self::LOCK_NAME]);
            } catch (Throwable) {
                // O MySQL pode liberar o lock automaticamente ao encerrar a conexão.
            }
        }
    }

    private static function isCoreReady(PDO $pdo): bool
    {
        try {
            $tables = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
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

    private static function isSocialReady(PDO $pdo): bool
    {
        try {
            $tables = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN (
                     'cq_lumen_ledger','cq_gift_catalog','cq_peer_notes','cq_gifts',
                     'cq_user_cosmetics','cq_trade_offers'
                   )"
            );
            if ((int) $tables->fetchColumn() !== 6) {
                return false;
            }
            $catalog = $pdo->query('SELECT COUNT(*) FROM cq_gift_catalog WHERE active = 1');
            return (int) $catalog->fetchColumn() >= 11;
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
