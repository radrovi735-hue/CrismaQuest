<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Service\CrismaQuestGameService;
use App\Service\CrismaQuestRewardService;
use App\Service\CrismaQuestSocialService;
use App\Service\Database;
use App\Service\Session;

foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) {
    $_ENV[$key] = getenv($key) !== false ? (string)getenv($key) : '';
}

$_SESSION = [];

function ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// -----------------------------------------------------------------------------
// 1) Stress fixture: 105 crismandos in one class.
// -----------------------------------------------------------------------------
$password = password_hash('stress', PASSWORD_DEFAULT);
$userIds = [];
$studentIds = [];

for ($i=1; $i<=105; $i++) {
    $username = 'cqstress' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare(
        'INSERT INTO ct_utenti
         (nome,cognome,username,password,email,codice_conf,validato,fk_tipo_utente,ricevi_mail,template_stampa,sesso,API_gemini,language)
         VALUES (:n,"Teste",:u,:p,"",NULL,1,2,0,"","U","", "en")'
    );
    $stmt->execute(['n'=>'Stress '.$i,'u'=>$username,'p'=>$password]);
    $uid = (int)$pdo->lastInsertId();
    $userIds[$i] = $uid;

    $pdo->prepare('INSERT INTO ct_utenti_tipi (fk_utente,fk_tipo_utente) VALUES (:u,2)')
        ->execute(['u'=>$uid]);

    $pdo->prepare(
        'INSERT INTO ct_studenti
         (fk_utente,xp,livello,fk_personaggio,vite,mana,pot_da_scegliere,monete,vite_massime,mana_massimo,l104,vite_ultima_visita,scudi,scudi_massimi)
         VALUES (:u,0,1,NULL,5,5,0,0,5,5,0,5,0,0)'
    )->execute(['u'=>$uid]);
    $sid = (int)$pdo->lastInsertId();
    $studentIds[$i] = $sid;

    $pdo->prepare('INSERT INTO ct_studenti_classi (fk_studente,fk_classe,esercizi_cons) VALUES (:s,1,0)')
        ->execute(['s'=>$sid]);
}

ok(count($userIds) === 105, '105 usuários de stress criados');

// -----------------------------------------------------------------------------
// 2) 100 users x 10 rewards x duplicate replay = 2,000 reward attempts.
//    Exactly 1,000 rewards may be applied.
// -----------------------------------------------------------------------------
$reward = new CrismaQuestRewardService();
for ($i=1; $i<=100; $i++) {
    for ($j=1; $j<=10; $j++) {
        $key = "stress:{$userIds[$i]}:{$j}";
        $pdo->beginTransaction();
        $a = $reward->grant($pdo,$userIds[$i],$studentIds[$i],$key,5,1,'Stress reward');
        $pdo->commit();
        ok((bool)$a['applied'], "reward inicial {$i}/{$j}");

        $pdo->beginTransaction();
        $b = $reward->grant($pdo,$userIds[$i],$studentIds[$i],$key,5,1,'Stress reward replay');
        $pdo->commit();
        ok(!$b['applied'], "replay idempotente {$i}/{$j}");
    }
}
$countRewards = (int)$pdo->query("SELECT COUNT(*) FROM cq_reward_events WHERE reward_key LIKE 'stress:%'")->fetchColumn();
ok($countRewards === 1000, '1.000 reward events únicos após 2.000 tentativas');

$stmt = $pdo->prepare('SELECT xp,monete FROM ct_studenti WHERE id_studente=:s');
$stmt->execute(['s'=>$studentIds[1]]);
$state = $stmt->fetch(PDO::FETCH_ASSOC);
ok((int)$state['xp'] === 50 && (int)$state['monete'] === 10, 'XP e Lúmens não duplicaram');

// -----------------------------------------------------------------------------
// 3) Level thresholds / theoretical trajectory.
// -----------------------------------------------------------------------------
$trajectoryMax = 2250;
$envioThreshold = (int)$pdo->query('SELECT xp_min FROM cq_game_levels WHERE level_no=8')->fetchColumn();
ok($envioThreshold === 1800, 'Enviado em 1.800 XP');
ok($envioThreshold / $trajectoryMax >= .75 && $envioThreshold / $trajectoryMax <= .85, 'nível final dentro da janela canônica 75–85%');

$uid103 = $userIds[103];
$sid103 = $studentIds[103];
$pdo->beginTransaction();
$reward->grant($pdo,$uid103,$sid103,'trajectory:envio',1800,0,'Trajetória completa');
$pdo->commit();
$level = (int)$pdo->query("SELECT livello FROM ct_studenti WHERE id_studente={$sid103}")->fetchColumn();
ok($level === 8, 'trajetória de 1.800 XP chega a Enviado');

// -----------------------------------------------------------------------------
// 4) Mission completion idempotency.
// -----------------------------------------------------------------------------
$uid101 = $userIds[101];
$sid101 = $studentIds[101];
Session::set('user',['id'=>$uid101]);
Session::set('class',['id'=>1]);
$pdo->prepare('UPDATE cq_missions SET available_from="2000-01-01",available_until="2099-12-31" WHERE slug="c1e1-palavra"')->execute();
$missionId = (int)$pdo->query('SELECT id FROM cq_missions WHERE slug="c1e1-palavra"')->fetchColumn();
$game = new CrismaQuestGameService();
$r1 = $game->completeMission($missionId,['confirm'=>'1']);
ok((bool)$r1['success'], 'missão canônica concluída');
$xpAfterFirst = (int)$pdo->query("SELECT xp FROM ct_studenti WHERE id_studente={$sid101}")->fetchColumn();
$r2 = $game->completeMission($missionId,['confirm'=>'1']);
$xpAfterSecond = (int)$pdo->query("SELECT xp FROM ct_studenti WHERE id_studente={$sid101}")->fetchColumn();
ok((bool)$r2['success'] && $xpAfterFirst === $xpAfterSecond, 'duplo clique/replay não duplica XP');

// -----------------------------------------------------------------------------
// 5) Intercession weekly cap and stock rules.
// -----------------------------------------------------------------------------
$r = $game->sendIntercession($userIds[102]);
ok((bool)$r['success'], 'primeira Vela de Intercessão enviada');
$r = $game->sendIntercession($userIds[102]);
ok(!($r['success'] ?? false), 'segunda Vela na mesma semana bloqueada');

// -----------------------------------------------------------------------------
// 6) Rosary: balance never negative, 48h recovery, 30d cooldown.
// -----------------------------------------------------------------------------
$pdo->prepare('UPDATE ct_studenti SET monete=200 WHERE id_studente=:s')->execute(['s'=>$sid101]);
$r = $game->useRosary();
ok((bool)$r['success'], 'Rosário recupera um dia elegível');
$balance = (int)$pdo->query("SELECT monete FROM ct_studenti WHERE id_studente={$sid101}")->fetchColumn();
ok($balance === 110, 'Rosário cobra exatamente 90 Lúmens');
$r = $game->useRosary();
ok(!($r['success'] ?? false), 'Rosário não pode ser reutilizado imediatamente');
$balance2 = (int)$pdo->query("SELECT monete FROM ct_studenti WHERE id_studente={$sid101}")->fetchColumn();
ok($balance2 === 110, 'falha do Rosário não altera saldo');

// -----------------------------------------------------------------------------
// 7) Card gift weekly limit and patron protection.
// -----------------------------------------------------------------------------
$normalCard = (int)$pdo->query(
    'SELECT ce.id FROM cq_card_editions ce JOIN cq_saint_cards sc ON sc.id=ce.card_id
     WHERE sc.slug="santa-teresinha-menino-jesus" AND ce.edition_type="normal" LIMIT 1'
)->fetchColumn();
$pdo->prepare(
    'INSERT INTO cq_user_cards (user_id,card_edition_id,quantity) VALUES (:u,:e,3)
     ON DUPLICATE KEY UPDATE quantity=3'
)->execute(['u'=>$uid101,'e'=>$normalCard]);

$social = new CrismaQuestSocialService();
$r = $social->sendCardGift(['recipient_user_id'=>$userIds[102],'card_edition_id'=>$normalCard,'note'=>'Paz e bem']);
ok((bool)$r['success'], 'uma carta repetida pode ser presenteada');
$r = $social->sendCardGift(['recipient_user_id'=>$userIds[102],'card_edition_id'=>$normalCard,'note'=>'segunda']);
ok(!($r['success'] ?? false), 'segunda carta presenteada na semana é bloqueada');

$patronCard = (int)$pdo->query(
    'SELECT ce.id FROM cq_card_editions ce JOIN cq_saint_cards sc ON sc.id=ce.card_id
     WHERE sc.slug="sao-carlo-acutis" AND ce.edition_type="normal" LIMIT 1'
)->fetchColumn();
$pdo->prepare(
    'INSERT INTO cq_user_cards (user_id,card_edition_id,quantity) VALUES (:u,:e,3)
     ON DUPLICATE KEY UPDATE quantity=3'
)->execute(['u'=>$uid101,'e'=>$patronCard]);
$r = $social->sendCardGift(['recipient_user_id'=>$userIds[102],'card_edition_id'=>$patronCard]);
ok(!($r['success'] ?? false), 'carta de padroeiro não pode ser presenteada');

// -----------------------------------------------------------------------------
// 8) Trade: both sides need duplicates, three proposals/week, atomic accept.
// -----------------------------------------------------------------------------
$otherCard = (int)$pdo->query(
    'SELECT ce.id FROM cq_card_editions ce JOIN cq_saint_cards sc ON sc.id=ce.card_id
     WHERE sc.slug="sao-francisco-assis" AND ce.edition_type="normal" LIMIT 1'
)->fetchColumn();
$pdo->prepare(
    'INSERT INTO cq_user_cards (user_id,card_edition_id,quantity) VALUES (:u,:e,4)
     ON DUPLICATE KEY UPDATE quantity=4'
)->execute(['u'=>$uid101,'e'=>$normalCard]);
$pdo->prepare(
    'INSERT INTO cq_user_cards (user_id,card_edition_id,quantity) VALUES (:u,:e,4)
     ON DUPLICATE KEY UPDATE quantity=4'
)->execute(['u'=>$userIds[102],'e'=>$otherCard]);

$tradeIds = [];
for ($i=0; $i<3; $i++) {
    $r = $social->proposeTrade([
        'recipient_user_id'=>$userIds[102],
        'offered_card_edition_id'=>$normalCard,
        'requested_card_edition_id'=>$otherCard,
    ]);
    ok((bool)$r['success'], 'proposta de troca ' . ($i+1) . '/3 aceita');
}
$r = $social->proposeTrade([
    'recipient_user_id'=>$userIds[102],
    'offered_card_edition_id'=>$normalCard,
    'requested_card_edition_id'=>$otherCard,
]);
ok(!($r['success'] ?? false), 'quarta proposta semanal bloqueada');

$tradeId = (int)$pdo->query(
    "SELECT id FROM cq_trade_offers WHERE offerer_user_id={$uid101} AND recipient_user_id={$userIds[102]} ORDER BY id LIMIT 1"
)->fetchColumn();
Session::set('user',['id'=>$userIds[102]]);
Session::set('class',['id'=>1]);
$socialRecipient = new CrismaQuestSocialService();
$r = $socialRecipient->respondTrade($tradeId,true);
ok((bool)$r['success'], 'troca é aceita atomicamente');
$r = $socialRecipient->respondTrade($tradeId,true);
ok(!($r['success'] ?? false), 'mesma troca não pode ser aceita duas vezes');

// -----------------------------------------------------------------------------
// 9) Chest idempotency.
// -----------------------------------------------------------------------------
Session::set('user',['id'=>$uid103]);
Session::set('class',['id'=>1]);
$game103 = new CrismaQuestGameService();
$chestId = (int)$pdo->query('SELECT id FROM cq_chest_catalog WHERE slug="semente"')->fetchColumn();
$r = $game103->claimChest($chestId);
ok((bool)$r['success'], 'baú elegível abre uma vez');
$r = $game103->claimChest($chestId);
ok(!($r['success'] ?? false), 'baú não abre duas vezes');

// -----------------------------------------------------------------------------
// 10) Structural content checks.
// -----------------------------------------------------------------------------
ok((int)$pdo->query('SELECT COUNT(*) FROM cq_journey_steps WHERE active=1')->fetchColumn() === 22, '22 etapas');
ok((int)$pdo->query('SELECT COUNT(*) FROM cq_missions WHERE active=1')->fetchColumn() >= 56, '56 missões');
ok((int)$pdo->query('SELECT COUNT(*) FROM cq_daily_sparks WHERE active=1')->fetchColumn() === 60, '60 Centelhas');
ok((int)$pdo->query('SELECT COUNT(*) FROM cq_badge_catalog WHERE active=1')->fetchColumn() === 14, '14 conquistas');
ok((int)$pdo->query('SELECT COUNT(*) FROM cq_chest_catalog WHERE active=1')->fetchColumn() === 9, '9 baús');

fwrite(STDOUT, "PASS: CrismaQuest gameplay stress + trajectories\n");
