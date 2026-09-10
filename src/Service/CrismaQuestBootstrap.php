<?php

namespace App\Service;

use PDO;
use Throwable;

final class CrismaQuestBootstrap
{
    private static bool $checked = false;

    public static function ensureSchema(): void
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;

        try {
            $pdo = Database::getConnection();
            $exists = $pdo->query("SHOW TABLES LIKE 'cq_streaks'")->fetchColumn();
            $root = dirname(__DIR__, 2);
            $sqlFiles = glob($root . '/sql/crismaquest/*.sql') ?: [];
            sort($sqlFiles, SORT_NATURAL);

            if (!$exists) {
                foreach ($sqlFiles as $file) {
                    self::applySqlFile($pdo, $file);
                }
                return;
            }

            // Seed files are idempotent (INSERT IGNORE), so applying them also
            // keeps an existing CrismaQuest installation in sync with new cards.
            foreach ($sqlFiles as $file) {
                if (preg_match('/\/00[2-9]_/', str_replace('\\', '/', $file))) {
                    self::applySqlFile($pdo, $file);
                }
            }
        } catch (Throwable) {
            // Never make the upstream application unavailable because an optional
            // CrismaQuest extension could not be prepared. Admin diagnostics can
            // surface the missing schema separately.
        }
    }

    private static function applySqlFile(PDO $pdo, string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            return;
        }

        foreach (self::splitStatements($sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    private static function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $statements = [];
        $current = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $current .= "\n";
                }
                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                }
                continue;
            }
            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $next !== '') {
                    $current .= $next;
                    $i++;
                    continue;
                }
                if ($char === $quote && $next === $quote) {
                    $current .= $next;
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '-' && $next === '-') {
                $lineComment = true;
                $i++;
                continue;
            }
            if ($char === '#') {
                $lineComment = true;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = $current;
        }
        return $statements;
    }
}
