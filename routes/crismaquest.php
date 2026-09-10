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
