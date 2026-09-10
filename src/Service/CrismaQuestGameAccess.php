<?php

namespace App\Service;

use Throwable;

final class CrismaQuestGameAccess
{
    public static function enabled(): bool
    {
        try {
            return (int)Database::getConnection()->query(
                "SELECT COUNT(*) FROM cq_game_config
                 WHERE config_key IN ('gameplay_ready','gameplay_enabled') AND config_value='1'"
            )->fetchColumn() === 2;
        } catch (Throwable) {
            return false;
        }
    }

    public static function token(): string
    {
        if (!is_string($_SESSION['cq_game_csrf'] ?? null)) {
            $_SESSION['cq_game_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['cq_game_csrf'];
    }

    public static function validToken(mixed $token): bool
    {
        return is_string($token) && is_string($_SESSION['cq_game_csrf'] ?? null)
            && hash_equals($_SESSION['cq_game_csrf'], $token);
    }
}
