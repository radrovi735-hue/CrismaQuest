<?php

declare(strict_types=1);
require getenv('CQ_TEST_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';

use App\Service\CrismaQuestGameAccess;
use App\Service\CrismaQuestGameSetupService;
use App\Service\CrismaQuestJourneyService;
use App\Service\Database;

foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) $_ENV[$key] = (string)getenv($key);
$_SESSION = [];
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "OK: {$message}\n";
}
$pdo = Database::getConnection();
$setup = new CrismaQuestGameSetupService();
check(!CrismaQuestGameAccess::enabled(), 'gameplay fechado antes da instalação');
$fallback = (new CrismaQuestJourneyService())->getSeasonData();
check(count($fallback['steps']) === 22, 'mapa pré-instalação usa as 22 etapas canônicas');
try { $setup->runStep(11); throw new LogicException('ativação incompleta aceita'); }
catch (RuntimeException $e) { check(!CrismaQuestGameAccess::enabled(), 'ativação incompleta bloqueada'); }
for ($step=1; $step<=10; $step++) {
    $setup->runStep($step);
    check(!CrismaQuestGameAccess::enabled(), "etapa {$step} não ativa dados incompletos");
}
$setup->runStep(11);
check($setup->status()['ready'] && CrismaQuestGameAccess::enabled(), 'instalação completa validada e ativada');
$counts = $setup->status()['counts'];
check($counts === ['steps'=>22,'missions'=>56,'sparks'=>60,'levels'=>8,'badges'=>14,'chests'=>9], 'catálogos completos');
$original = $pdo->query("SELECT title FROM cq_missions WHERE slug='c1e1-palavra'")->fetchColumn();
$pdo->exec("UPDATE cq_missions SET title='Correção do catequista',active=0 WHERE slug='c1e1-palavra'");
$setup->runStep(7);
$setup->runStep(8);
check($pdo->query("SELECT title FROM cq_missions WHERE slug='c1e1-palavra'")->fetchColumn() === 'Correção do catequista', 'retomada preserva edições');
check((int)$pdo->query("SELECT active FROM cq_missions WHERE slug='c1e1-palavra'")->fetchColumn() === 0, 'retomada preserva missão pausada');
check($setup->status()['ready'] && $setup->status()['phase'] === 11, 'missão pausada não invalida instalação nem reduz fase');
$pdo->prepare("UPDATE cq_missions SET title=?,active=1 WHERE slug='c1e1-palavra'")->execute([$original]);
$journey = (new CrismaQuestJourneyService())->getSeasonData();
check(array_column($journey['chapters'],'totalSteps') === [4,4,3,4,4,3], 'capítulos seguem divisão 4/4/3/4/4/3');
$token = CrismaQuestGameAccess::token();
check(CrismaQuestGameAccess::validToken($token), 'formulário da sessão aceito');
check(!CrismaQuestGameAccess::validToken(null) && !CrismaQuestGameAccess::validToken('invalid'), 'requisição sem token ou com token inválido bloqueada');
// Simulate the authoritative Composer map that existed before new App classes.
$loader = new Composer\Autoload\ClassLoader();
$loader->addPsr4('App\\', dirname(__DIR__) . '/src');
$loader->setClassMapAuthoritative(true);
check($loader->findFile('App\\Service\\CrismaQuestGameAccess') === false, 'reproduz carregador antigo');
$loader->setClassMapAuthoritative(false);
check(is_string($loader->findFile('App\\Service\\CrismaQuestGameAccess')), 'fallback PSR-4 encontra classe nova');
echo "PASS: instalação retomável, ativação, conteúdo, CSRF e carregamento\n";
