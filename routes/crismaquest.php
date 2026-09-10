<?php

use App\Controller\CrismaQuestSocialController;
use App\Controller\CrismaQuestGameSetupController;
use App\Controller\CrismaQuestGameController;

// Recursos próprios do CrismaQuest. Mantidos separados do upstream ChronoQuest.
$router->get('/studenti/correio', [CrismaQuestSocialController::class, 'index']);
$router->post('/studenti/correio/bilhete', [CrismaQuestSocialController::class, 'sendNote']);
$router->post('/studenti/correio/presente', [CrismaQuestSocialController::class, 'sendGift']);
$router->post('/studenti/correio/presente-carta', [CrismaQuestSocialController::class, 'sendCardGift']);
$router->post('/studenti/correio/troca', [CrismaQuestSocialController::class, 'proposeTrade']);
$router->post('/studenti/correio/troca/{id}/aceitar', [CrismaQuestSocialController::class, 'acceptTrade']);
$router->post('/studenti/correio/troca/{id}/recusar', [CrismaQuestSocialController::class, 'declineTrade']);
$router->post('/studenti/correio/cosmetico/{id}/usar', [CrismaQuestSocialController::class, 'equipCosmetic']);
$router->get('/docenti/correio', [CrismaQuestSocialController::class, 'teacherAudit']);


// Instalador isolado do gameplay. Nunca é executado pelo index.php.
$router->get('/docenti/jogo/setup', [CrismaQuestGameSetupController::class, 'index']);
$router->post('/docenti/jogo/setup/{step}', [CrismaQuestGameSetupController::class, 'run']);


// Gameplay canônico CrismaQuest — ativado somente após setup validado.
$router->get('/studenti/missoes', [CrismaQuestGameController::class, 'studentIndex']);
$router->post('/studenti/missoes/{id}/concluir', [CrismaQuestGameController::class, 'completeMission']);
$router->post('/studenti/centelha/concluir', [CrismaQuestGameController::class, 'completeSpark']);
$router->post('/studenti/baus/{id}/abrir', [CrismaQuestGameController::class, 'claimChest']);
$router->post('/studenti/chama/intercessao', [CrismaQuestGameController::class, 'sendIntercession']);
$router->post('/studenti/chama/intercessao/{id}/usar', [CrismaQuestGameController::class, 'useIntercession']);
$router->post('/studenti/chama/rosario', [CrismaQuestGameController::class, 'useRosary']);

$router->get('/docenti/jogo', [CrismaQuestGameController::class, 'teacherIndex']);
$router->get('/docenti/jogo/previa', [CrismaQuestGameController::class, 'teacherPreview']);
$router->get('/docenti/jogo/jornada', [CrismaQuestGameController::class, 'teacherJourney']);
$router->post('/docenti/jogo/missao/{id}/toggle', [CrismaQuestGameController::class, 'toggleMission']);
$router->post('/docenti/jogo/missao/{id}/duplicar', [CrismaQuestGameController::class, 'duplicateMission']);
$router->post('/docenti/jogo/missao/nova', [CrismaQuestGameController::class, 'createMission']);
$router->post('/docenti/jogo/missao/{id}/atualizar', [CrismaQuestGameController::class, 'updateMission']);
$router->post('/docenti/jogo/pausa', [CrismaQuestGameController::class, 'pauseClass']);
$router->post('/docenti/jogo/pausa-crismando', [CrismaQuestGameController::class, 'pauseStudent']);
