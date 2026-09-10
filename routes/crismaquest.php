<?php

use App\Controller\CrismaQuestSocialController;

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


// Motor canônico do jogo CrismaQuest.
$router->get('/studenti/missoes', [CrismaQuestGameController::class, 'studentIndex']);
$router->post('/studenti/missoes/{id}/concluir', [CrismaQuestGameController::class, 'completeMission']);
$router->post('/studenti/centelha/concluir', [CrismaQuestGameController::class, 'completeSpark']);
$router->post('/studenti/baus/{id}/abrir', [CrismaQuestGameController::class, 'claimChest']);
$router->post('/studenti/chama/intercessao', [CrismaQuestGameController::class, 'sendIntercession']);
$router->post('/studenti/chama/intercessao/{id}/usar', [CrismaQuestGameController::class, 'useIntercession']);
$router->post('/studenti/chama/rosario', [CrismaQuestGameController::class, 'useRosary']);

$router->get('/docenti/jogo', [CrismaQuestGameController::class, 'teacherIndex']);
$router->post('/docenti/jogo/missao/{id}/toggle', [CrismaQuestGameController::class, 'toggleMission']);
$router->post('/docenti/jogo/missao/{id}/duplicar', [CrismaQuestGameController::class, 'duplicateMission']);
$router->post('/docenti/jogo/pausa', [CrismaQuestGameController::class, 'pauseClass']);
