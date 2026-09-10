<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Router;
use App\Service\CrismaQuestBootstrapService;

if (!file_exists(__DIR__ . '/../.env')) {
    header('Location: /install.php');
    exit;
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Schema maintenance must never take the public app offline.
try {
    CrismaQuestBootstrapService::ensureInstalled();
} catch (Throwable $e) {
    error_log('[CrismaQuest bootstrap] ' . $e->getMessage());
}

session_start();

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($requestPath === '/') {
    header('Location: /loginStud');
    exit;
}

$router = new Router();
require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/crismaquest.php';
$router->dispatch();
