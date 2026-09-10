<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Router;
use App\Service\CrismaQuestBootstrap;

if (!file_exists(__DIR__ . '/../.env')) {
    header('Location: /install.php');
    exit;
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// CrismaQuest keeps its own tables isolated from the ChronoQuest core. The
// bootstrap is idempotent and silently leaves the upstream app usable if the
// database account cannot create/alter optional extension tables.
CrismaQuestBootstrap::ensureSchema();

session_start();

$router = new Router();
require __DIR__ . '/../routes/web.php';
$router->dispatch();
