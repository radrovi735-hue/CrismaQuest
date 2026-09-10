<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

final class CrismaQuestGameSetupService
{
    public const LAST_STEP = 11;

    private array $schemaTables = [
        'cq_game_config','cq_game_levels','cq_journey_steps','cq_missions',
        'cq_mission_completions','cq_reward_events','cq_chest_catalog',
        'cq_user_chests','cq_badge_catalog','cq_user_badges','cq_daily_sparks',
        'cq_spark_completions','cq_intercessions','cq_streak_recoveries',
        'cq_streak_pauses'
    ];

    public function status(): array
    {
        $pdo = Database::getConnection();
        $existing = 0;
        foreach ($this->schemaTables as $table) {
            if ($this->tableExists($pdo, $table)) $existing++;
        }

        $social = ['cq_streaks','cq_streak_events','cq_saint_cards','cq_user_cards','cq_gift_catalog','cq_lumen_ledger'];
        $socialExisting = 0;
        foreach ($social as $table) {
            if ($this->tableExists($pdo, $table)) $socialExisting++;
        }

        $counts = [
            'steps' => $this->safeCount($pdo, 'cq_journey_steps', 'step_no BETWEEN 1 AND 22'),
            'missions' => $this->catalogCount($pdo, 'cq_missions'),
            'sparks' => $this->catalogCount($pdo, 'cq_daily_sparks'),
            'levels' => $this->safeCount($pdo, 'cq_game_levels'),
            'badges' => $this->catalogCount($pdo, 'cq_badge_catalog'),
            'chests' => $this->catalogCount($pdo, 'cq_chest_catalog'),
        ];

        $phase = 0;
        if ($this->tableExists($pdo, 'cq_game_config')) {
            try {
                $phase = (int)($pdo->query(
                    "SELECT config_value FROM cq_game_config WHERE config_key='setup_phase' LIMIT 1"
                )->fetchColumn() ?: 0);
            } catch (Throwable) {
                $phase = 0;
            }
        }

        $ready = $existing === count($this->schemaTables)
            && $socialExisting === count($social)
            && $counts['steps'] === 22
            && $counts['missions'] === 56
            && $counts['sparks'] === 60
            && $counts['levels'] === 8
            && $counts['badges'] === 14
            && $counts['chests'] === 9;

        return [
            'ready'=>$ready,
            'enabled'=>CrismaQuestGameAccess::enabled(),
            'phase'=>$phase,
            'schemaTables'=>$existing,
            'schemaTablesExpected'=>count($this->schemaTables),
            'socialTables'=>$socialExisting,
            'socialTablesExpected'=>count($social),
            'counts'=>$counts,
        ];
    }

    public function runStep(int $step): array
    {
        if ($step < 1 || $step > self::LAST_STEP) {
            throw new RuntimeException('Etapa de instalação inválida.');
        }

        $pdo = Database::getConnection();
        $schema = $this->schemaStatements();
        $seed = $this->seedStatements();

        switch ($step) {
            case 1:
                $this->executeSchemaTables($pdo, $schema, [
                    'cq_game_config','cq_game_levels','cq_journey_steps','cq_missions',
                    'cq_mission_completions','cq_reward_events'
                ]);
                break;
            case 2:
                $this->executeSchemaTables($pdo, $schema, [
                    'cq_chest_catalog','cq_user_chests','cq_badge_catalog',
                    'cq_user_badges','cq_daily_sparks','cq_spark_completions'
                ]);
                break;
            case 3:
                $this->executeSchemaTables($pdo, $schema, [
                    'cq_intercessions','cq_streak_recoveries','cq_streak_pauses'
                ]);
                break;
            case 4:
                $this->executeSeedMatching($pdo, $seed, static fn(string $s): bool =>
                    str_contains($s,'cq_game_config') || str_contains($s,'cq_game_levels')
                );
                break;
            case 5:
                $this->executeSeedMatching($pdo, $seed, static fn(string $s): bool =>
                    str_contains($s,'cq_journey_steps')
                );
                break;
            case 6:
                $this->executeSeedMatching($pdo, $seed, static fn(string $s): bool =>
                    !str_contains($s,'cq_missions')
                    && !str_contains($s,'cq_daily_sparks')
                    && !str_contains($s,'cq_journey_steps')
                    && !str_contains($s,'cq_game_levels')
                    && !str_contains($s,'cq_game_config')
                );
                break;
            case 7:
            case 8:
                $missions = array_values(array_filter($seed, static fn(string $s): bool => str_contains($s,'cq_missions')));
                $slice = $step === 7 ? array_slice($missions,0,28) : array_slice($missions,28);
                $this->executeStatements($pdo,$slice);
                break;
            case 9:
            case 10:
                $sparks = array_values(array_filter($seed, static fn(string $s): bool => str_contains($s,'cq_daily_sparks')));
                $slice = $step === 9 ? array_slice($sparks,0,30) : array_slice($sparks,30);
                $this->executeStatements($pdo,$slice);
                break;
            case 11:
                $status = $this->status();
                if (!($status['ready'] ?? false)) {
                    throw new RuntimeException('Validação ainda incompleta: ' . json_encode($status, JSON_UNESCAPED_UNICODE));
                }
                $pdo->exec(
                    "INSERT INTO cq_game_config (config_key,config_value)
                     VALUES ('gameplay_ready','1'),('gameplay_enabled','1')
                     ON DUPLICATE KEY UPDATE config_value='1'"
                );
                break;
        }

        if ($this->tableExists($pdo,'cq_game_config')) {
            $stmt = $pdo->prepare(
                "INSERT INTO cq_game_config (config_key,config_value)
                 VALUES ('setup_phase',:phase)
                 ON DUPLICATE KEY UPDATE config_value=GREATEST(CAST(config_value AS UNSIGNED),CAST(VALUES(config_value) AS UNSIGNED))"
            );
            $stmt->execute(['phase'=>(string)$step]);
        }

        return ['step'=>$step,'status'=>$this->status()];
    }

    private function executeSchemaTables(PDO $pdo, array $statements, array $tables): void
    {
        $wanted = array_fill_keys($tables,true);
        $selected = [];
        foreach ($statements as $statement) {
            if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+([a-zA-Z0-9_]+)/i',$statement,$m)
                && isset($wanted[$m[1]])) {
                $selected[] = $statement;
            }
        }
        if (count($selected) !== count($tables)) {
            throw new RuntimeException('Pacote de schema incompleto nesta etapa.');
        }
        $this->executeStatements($pdo,$selected,false);
    }

    private function executeSeedMatching(PDO $pdo, array $statements, callable $filter): void
    {
        $selected = array_values(array_filter($statements,$filter));
        if ($selected === []) throw new RuntimeException('Nenhum seed encontrado para esta etapa.');
        $this->executeStatements($pdo,$selected);
    }

    private function executeStatements(PDO $pdo, array $statements, bool $transactional=true): void
    {
        if ($transactional) $pdo->beginTransaction();
        try {
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement !== '') $pdo->exec($statement);
            }
            if ($transactional && $pdo->inTransaction()) $pdo->commit();
        } catch (Throwable $e) {
            if ($transactional && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private function schemaStatements(): array
    {
        return $this->readStatements(dirname(__DIR__,2) . '/sql/crismaquest/004_gameplay.sql');
    }

    private function seedStatements(): array
    {
        return array_map(static function (string $statement): string {
            // Repeating setup must preserve mission corrections and paused content.
            if (preg_match('/^INSERT INTO (cq_missions|cq_daily_sparks) /', $statement)) {
                return preg_replace('/ON DUPLICATE KEY UPDATE .+$/s', 'ON DUPLICATE KEY UPDATE slug=slug', $statement) ?? $statement;
            }
            return $statement;
        }, $this->readStatements(dirname(__DIR__,2) . '/sql/crismaquest/005_gameplay_seed.sql'));
    }

    private function catalogCount(PDO $pdo, string $table): int
    {
        if (!$this->tableExists($pdo, $table)) return 0;
        $slugs = [];
        foreach ($this->seedStatements() as $statement) {
            if (preg_match("/^INSERT INTO " . preg_quote($table, '/') . " \\(.*?\\) VALUES \\('([^']+)'/s", $statement, $match)) {
                $slugs[] = $match[1];
            }
        }
        if ($slugs === []) return 0;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')');
        $stmt->execute($slugs);
        return (int)$stmt->fetchColumn();
    }

    private function readStatements(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) throw new RuntimeException('Arquivo de instalação não encontrado.');
        $sql = preg_replace('/^\s*--.*$/m','',$sql) ?? $sql;
        $parts = preg_split('/;\s*(?:\r?\n|$)/',$sql) ?: [];
        return array_values(array_filter(array_map('trim',$parts),static fn(string $s): bool => $s !== ''));
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:t'
            );
            $stmt->execute(['t'=>$table]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (Throwable) {
            return false;
        }
    }

    private function safeCount(PDO $pdo, string $table, string $where='1=1'): int
    {
        if (!$this->tableExists($pdo,$table)) return 0;
        try {
            return (int)$pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
