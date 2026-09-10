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

// ChronoQuest owns the base schema. CrismaQuest adds isolated extension tables
// and starter content on the first request after installation. This check is
// idempotent and verifies the extension is complete before the app continues.
CrismaQuestBootstrapService::ensureInstalled();

session_start();

$router = new Router();
require __DIR__ . '/../routes/web.php';
$router->dispatch();
