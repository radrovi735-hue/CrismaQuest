<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Instala, atualiza e saneia as extensões próprias do CrismaQuest de forma idempotente. */
class CrismaQuestBootstrapService
{
    private const LOCK_NAME = 'crismaquest_schema_bootstrap_v3';

    public static function ensureInstalled(): void
    {
        $pdo = Database::getConnection();

        // A base ChronoQuest vem com uma turma de demonstração. Em produção,
        // reaproveitamos somente esse registro exato para preservar a associação
        // do administrador sem manter conteúdo de teste visível.
        self::sanitizeLegacySeed($pdo);

        if (self::isCoreReady($pdo) && self::isSocialReady($pdo)) return;

        $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $lock->execute(['lock_name'=>self::LOCK_NAME]);
        if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Não foi possível obter o bloqueio de atualização do CrismaQuest.');

        try {
            $root = dirname(__DIR__, 2);
            if (!self::isCoreReady($pdo)) {
                self::importSqlFile($pdo, $root.'/sql/crismaquest/001_core.sql');
                self::importSqlFile($pdo, $root.'/sql/crismaquest/002_saints_seed.sql');
            }
            if (!self::isSocialReady($pdo)) self::importSqlFile($pdo, $root.'/sql/crismaquest/003_social_economy.sql');

            if (!self::isCoreReady($pdo) || !self::isSocialReady($pdo)) {
                throw new RuntimeException('A atualização do banco do CrismaQuest não foi concluída.');
            }
        } finally {
            try { $release=$pdo->prepare('SELECT RELEASE_LOCK(:lock_name)'); $release->execute(['lock_name'=>self::LOCK_NAME]); } catch (Throwable) {}
        }
    }

    private static function sanitizeLegacySeed(PDO $pdo): void
    {
        try {
            // Só toca no registro de demonstração original. Turmas criadas pelo usuário nunca são alteradas.
            $stmt = $pdo->prepare(
                "SELECT c.id_classe, c.fk_anno_scolastico
                 FROM ct_classi c
                 WHERE c.nome_classe = 'Test Class' AND c.eliminata = 0
                 LIMIT 1"
            );
            $stmt->execute();
            $demo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$demo) return;

            $pdo->beginTransaction();

            $rename = $pdo->prepare(
                "UPDATE ct_classi
                 SET nome_classe = 'Crisma 2026–2027',
                     icona = 'fa-dove',
                     colore = '#6f1d2a'
                 WHERE id_classe = :id_classe
                   AND nome_classe = 'Test Class'"
            );
            $rename->execute(['id_classe'=>(int)$demo['id_classe']]);

            $year = $pdo->prepare(
                "UPDATE ct_anni_scolastici
                 SET anno_scolastico = '2026/2027'
                 WHERE id_anno = :id_anno
                   AND anno_scolastico = '2025/2026'"
            );
            $year->execute(['id_anno'=>(int)$demo['fk_anno_scolastico']]);

            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // Saneamento visual nunca deve derrubar a aplicação.
        }
    }

    private static function isCoreReady(PDO $pdo): bool
    {
        try {
            $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('cq_streaks','cq_streak_events','cq_saint_cards','cq_card_editions','cq_user_cards','cq_meetings','cq_attendance','cq_attendance_audit')");
            if ((int)$q->fetchColumn() !== 8) return false;
            return (int)$pdo->query('SELECT COUNT(*) FROM cq_saint_cards')->fetchColumn() >= 20
                && (int)$pdo->query("SELECT COUNT(*) FROM cq_card_editions WHERE edition_type='normal'")->fetchColumn() >= 20;
        } catch (Throwable) { return false; }
    }

    private static function isSocialReady(PDO $pdo): bool
    {
        try {
            $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('cq_lumen_ledger','cq_gift_catalog','cq_peer_notes','cq_gifts','cq_user_cosmetics','cq_trade_offers')");
            if ((int)$q->fetchColumn() !== 6) return false;
            return (int)$pdo->query('SELECT COUNT(*) FROM cq_gift_catalog WHERE active=1')->fetchColumn() >= 11;
        } catch (Throwable) { return false; }
    }

    private static function importSqlFile(PDO $pdo, string $path): void
    {
        if (!is_readable($path)) throw new RuntimeException('Arquivo SQL do CrismaQuest não encontrado: '.basename($path));
        $sql=file_get_contents($path); if ($sql===false) throw new RuntimeException('Não foi possível ler '.basename($path));
        $sql=preg_replace('/^\s*--.*$/m','',$sql) ?? $sql;
        $sql=preg_replace('/^\s*#.*$/m','',$sql) ?? $sql;
        foreach(explode(';',$sql) as $statement){$statement=trim($statement);if($statement!=='')$pdo->exec($statement);}
    }
}
