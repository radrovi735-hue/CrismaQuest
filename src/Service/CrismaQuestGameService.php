<?php

namespace App\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class CrismaQuestGameService
{
    private const TZ = 'America/Fortaleza';

    private CrismaQuestRewardService $rewards;
    private CrismaQuestStreakService $streaks;

    public function __construct()
    {
        $this->rewards = new CrismaQuestRewardService();
        $this->streaks = new CrismaQuestStreakService();
    }

    public function getStudentPageData(): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $ctx;

        $pdo = Database::getConnection();
        $today = $this->today();
        $missions = $this->availableMissions($pdo, $ctx['userId'], $today);
        $spark = $this->currentSpark($pdo, $ctx['userId'], $today);
        $streak = $this->streaks->getStatus($ctx['userId']);
        $progress = $this->progressData($pdo, $ctx['userId']);
        $chests = $this->availableChests($pdo, $ctx['userId'], $ctx['studentId']);
        $badges = $this->userBadges($pdo, $ctx['userId']);
        $intercessions = $this->availableIntercessions($pdo, $ctx['userId']);
        $rosary = $this->rosaryStatus($pdo, $ctx['userId'], $ctx['balance']);

        return $ctx + [
            'today'=>$today,
            'missions'=>$missions,
            'spark'=>$spark,
            'streak'=>$streak,
            'progress'=>$progress,
            'chests'=>$chests,
            'badges'=>$badges,
            'intercessions'=>$intercessions,
            'rosary'=>$rosary,
            'classmates'=>$this->classmates($pdo, $ctx['classId'], $ctx['userId']),
            'communityLight'=>$this->communityLight($pdo, $ctx['classId'], $today),
            'levels'=>$pdo->query('SELECT * FROM cq_game_levels ORDER BY level_no')->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'recess'=>$this->isRecess($pdo, $today),
        ];
    }

    public function completeMission(int $missionId, array $input): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Sessão inválida.');
        if ($missionId <= 0) return $this->error('Missão inválida.');

        $pdo = Database::getConnection();
        $today = $this->today();
        $stmt = $pdo->prepare(
            'SELECT * FROM cq_missions
             WHERE id=:id AND active=1 AND available_from<=:d1 AND available_until>=:d2
             LIMIT 1'
        );
        $stmt->execute(['id'=>$missionId,'d1'=>$today,'d2'=>$today]);
        $mission = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mission) return $this->error('Esta missão ainda não está disponível.');

        $already = $pdo->prepare(
            'SELECT COUNT(*) FROM cq_mission_completions WHERE user_id=:u AND mission_id=:m'
        );
        $already->execute(['u'=>$ctx['userId'],'m'=>$missionId]);
        if ((int)$already->fetchColumn() > 0) {
            return $this->success('Esta missão já foi concluída.');
        }

        $answer = trim((string)($input['answer'] ?? ''));
        $wasCorrect = null;
        $type = (string)$mission['mission_type'];

        if ($type === 'quiz') {
            $normalized = mb_strtoupper(mb_substr($answer, 0, 1));
            $correct = mb_strtoupper(trim((string)$mission['correct_answer']));
            $wasCorrect = $normalized === $correct;
            if (!$wasCorrect) {
                return $this->error(
                    trim((string)$mission['feedback']) !== ''
                        ? (string)$mission['feedback'] . ' Tente novamente.'
                        : 'Ainda não. Leia a explicação e tente novamente.'
                );
            }
        } elseif (($input['confirm'] ?? '') !== '1') {
            return $this->error('Confirme a realização da missão para concluir.');
        }

        $xp = (int)$mission['xp_reward'] + (($wasCorrect === true) ? (int)$mission['bonus_xp_correct'] : 0);
        $lumens = (int)$mission['lumen_reward'];

        try {
            $pdo->beginTransaction();

            $insert = $pdo->prepare(
                'INSERT IGNORE INTO cq_mission_completions
                    (user_id,mission_id,answer_text,was_correct,xp_awarded,lumens_awarded)
                 VALUES (:u,:m,:a,:c,0,0)'
            );
            $insert->execute([
                'u'=>$ctx['userId'],
                'm'=>$missionId,
                'a'=>$answer === '' ? null : mb_substr($answer,0,2000),
                'c'=>$wasCorrect === null ? null : ($wasCorrect ? 1 : 0),
            ]);
            if ($insert->rowCount() === 0) {
                $pdo->rollBack();
                return $this->success('Esta missão já foi concluída.');
            }

            $reward = $this->rewards->grant(
                $pdo,
                $ctx['userId'],
                $ctx['studentId'],
                'mission:' . $ctx['userId'] . ':' . $missionId,
                $xp,
                $lumens,
                'Missão: ' . (string)$mission['title']
            );

            $pdo->prepare(
                'UPDATE cq_mission_completions
                 SET xp_awarded=:xp, lumens_awarded=:l
                 WHERE user_id=:u AND mission_id=:m'
            )->execute(['xp'=>$xp,'l'=>$lumens,'u'=>$ctx['userId'],'m'=>$missionId]);

            $special = trim((string)($mission['special_reward'] ?? ''));
            $specialMessage = null;
            if (str_starts_with($special, 'card:')) {
                $slug = substr($special, 5);
                $card = $this->rewards->grantCard(
                    $pdo,
                    $ctx['userId'],
                    'mission-special-' . $missionId,
                    $slug,
                    true
                );
                if ($card) $specialMessage = ' Carta conquistada: ' . $card['name'] . '.';
            } elseif (str_starts_with($special, 'badge:')) {
                $slug = substr($special, 6);
                if ($this->rewards->grantBadge($pdo, $ctx['userId'], $slug)) {
                    $specialMessage = ' Nova conquista liberada.';
                }
            }

            $pdo->commit();

            $streak = ((int)$mission['grants_streak'] === 1)
                ? $this->streaks->qualifyActivity('cq_mission', $missionId, $ctx['userId'])
                : $this->streaks->getStatus($ctx['userId']);

            $milestones = $this->processStreakMilestones($ctx, $streak);
            $this->evaluateBadges($ctx);

            $message = 'Missão concluída: +' . $xp . ' XP e +' . $lumens . ' Lúmens.';
            if ($wasCorrect === true && (int)$mission['bonus_xp_correct'] > 0) {
                $message .= ' Inclui +' . (int)$mission['bonus_xp_correct'] . ' XP pelo acerto.';
            }
            if ($specialMessage) $message .= $specialMessage;
            if ($milestones !== []) $message .= ' ' . implode(' ', $milestones);

            return $this->success($message) + ['reward'=>$reward,'streak'=>$streak];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error($e->getMessage() ?: 'Não foi possível concluir a missão.');
        }
    }

    public function completeSpark(int $sparkId): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Sessão inválida.');
        $pdo = Database::getConnection();
        $today = $this->today();

        if ($this->isRecess($pdo, $today)) {
            return $this->error('A Chama está protegida durante o recesso. Não há Centelha obrigatória hoje.');
        }

        $spark = $this->currentSpark($pdo, $ctx['userId'], $today);
        if (!$spark || (int)$spark['id'] !== $sparkId) {
            return $this->error('Centelha do dia inválida.');
        }
        if ((int)($spark['completed'] ?? 0) === 1) {
            return $this->success('A Centelha de hoje já foi concluída.');
        }

        $monday = (new DateTimeImmutable($today, new DateTimeZone(self::TZ)))->modify('monday this week')->format('Y-m-d');
        $sunday = (new DateTimeImmutable($monday, new DateTimeZone(self::TZ)))->modify('+6 days')->format('Y-m-d');
        $weekly = $pdo->prepare(
            'SELECT COUNT(*) FROM cq_spark_completions
             WHERE user_id=:u AND activity_date BETWEEN :a AND :b
               AND (xp_awarded>0 OR lumens_awarded>0)'
        );
        $weekly->execute(['u'=>$ctx['userId'],'a'=>$monday,'b'=>$sunday]);
        $rewardedThisWeek = (int)$weekly->fetchColumn();
        $rewardLimit = (int)($this->config($pdo,'spark_rewarded_weekly') ?? 3);
        $xp = $rewardedThisWeek < $rewardLimit ? (int)$spark['xp_reward'] : 0;
        $lumens = $rewardedThisWeek < $rewardLimit ? (int)$spark['lumen_reward'] : 0;

        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare(
                'INSERT IGNORE INTO cq_spark_completions
                    (user_id,spark_id,activity_date,xp_awarded,lumens_awarded)
                 VALUES (:u,:s,:d,:xp,:l)'
            );
            $insert->execute([
                'u'=>$ctx['userId'],'s'=>$sparkId,'d'=>$today,'xp'=>$xp,'l'=>$lumens
            ]);
            if ($insert->rowCount() === 0) {
                $pdo->rollBack();
                return $this->success('A Centelha de hoje já foi concluída.');
            }

            if ($xp !== 0 || $lumens !== 0) {
                $this->rewards->grant(
                    $pdo,
                    $ctx['userId'],
                    $ctx['studentId'],
                    'spark:' . $ctx['userId'] . ':' . $today,
                    $xp,
                    $lumens,
                    'Centelha do dia'
                );
            } else {
                $this->rewards->markOnce(
                    $pdo,
                    $ctx['userId'],
                    'spark:' . $ctx['userId'] . ':' . $today,
                    'Centelha do dia sem recompensa semanal'
                );
            }
            $pdo->commit();

            $streak = $this->streaks->qualifyActivity('cq_spark', $today, $ctx['userId']);
            $milestones = $this->processStreakMilestones($ctx, $streak);
            $this->evaluateBadges($ctx);

            $message = $xp > 0
                ? 'Centelha concluída: +' . $xp . ' XP e +' . $lumens . ' Lúmen.'
                : 'Centelha concluída. Sua Chama continua acesa, sem recompensa extra porque você já recebeu as 3 Centelhas premiadas da semana.';
            if ($milestones !== []) $message .= ' ' . implode(' ', $milestones);
            return $this->success($message);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error($e->getMessage() ?: 'Não foi possível concluir a Centelha.');
        }
    }

    public function claimChest(int $chestId): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Sessão inválida.');
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();
            $student = $pdo->prepare('SELECT xp FROM ct_studenti WHERE id_studente=:s FOR UPDATE');
            $student->execute(['s'=>$ctx['studentId']]);
            $xp = (int)$student->fetchColumn();

            $stmt = $pdo->prepare('SELECT * FROM cq_chest_catalog WHERE id=:id AND active=1 LIMIT 1');
            $stmt->execute(['id'=>$chestId]);
            $chest = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$chest) throw new RuntimeException('Baú indisponível.');
            if ($xp < (int)$chest['threshold_xp']) throw new RuntimeException('Você ainda não alcançou o XP necessário.');

            $insert = $pdo->prepare('INSERT IGNORE INTO cq_user_chests (user_id,chest_id,result_json) VALUES (:u,:c,NULL)');
            $insert->execute(['u'=>$ctx['userId'],'c'=>$chestId]);
            if ($insert->rowCount() === 0) throw new RuntimeException('Este baú já foi aberto.');

            $result = ['lumens'=>(int)$chest['reward_lumens'],'cards'=>[],'cosmetic'=>null,'shield'=>0];
            if ((int)$chest['reward_lumens'] > 0) {
                $this->rewards->grant(
                    $pdo,$ctx['userId'],$ctx['studentId'],
                    'chest-lumens:' . $ctx['userId'] . ':' . $chestId,
                    0,(int)$chest['reward_lumens'],
                    (string)$chest['name']
                );
            }

            $cardCount = (int)$chest['card_count'];
            for ($i=1; $i<=$cardCount; $i++) {
                $card = $this->rewards->grantCard(
                    $pdo,
                    $ctx['userId'],
                    'chest-' . $chestId . '-card-' . $i,
                    null,
                    (int)$chest['guaranteed_new'] === 1 && $i === 1
                );
                if ($card) $result['cards'][] = ['name'=>$card['name'],'edition'=>$card['edition_type']];
            }

            if (!empty($chest['cosmetic_slug'])) {
                if ($this->rewards->grantCosmetic(
                    $pdo,$ctx['userId'],(string)$chest['cosmetic_slug'],'chest-' . $chestId
                )) {
                    $result['cosmetic'] = (string)$chest['cosmetic_slug'];
                }
            }

            if ((string)$chest['slug'] === 'servico') {
                if ($this->rewards->markOnce($pdo,$ctx['userId'],'shield:chest-servico','Escudo da Chama')) {
                    $pdo->prepare(
                        'INSERT INTO cq_streaks
                            (user_id,current_streak,longest_streak,last_qualified_activity_date,streak_freezes_available)
                         VALUES (:u,0,0,NULL,1)
                         ON DUPLICATE KEY UPDATE streak_freezes_available=streak_freezes_available+1'
                    )->execute(['u'=>$ctx['userId']]);
                    $result['shield'] = 1;
                }
            }

            $pdo->prepare(
                'UPDATE cq_user_chests SET result_json=:r WHERE user_id=:u AND chest_id=:c'
            )->execute([
                'r'=>json_encode($result,JSON_UNESCAPED_UNICODE),
                'u'=>$ctx['userId'],'c'=>$chestId
            ]);
            $pdo->commit();
            $this->evaluateBadges($ctx);

            $parts = [];
            if ($result['lumens'] > 0) $parts[] = '+' . $result['lumens'] . ' Lúmens';
            if ($result['cards'] !== []) $parts[] = count($result['cards']) . (count($result['cards'])===1?' carta':' cartas');
            if ($result['cosmetic']) $parts[] = 'novo visual';
            if ($result['shield']) $parts[] = 'Escudo da Chama';
            return $this->success((string)$chest['name'] . ' aberto: ' . implode(', ', $parts) . '.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error($e->getMessage() ?: 'Não foi possível abrir o baú.');
        }
    }

    public function sendIntercession(int $recipientUserId): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Sessão inválida.');
        if (!$this->isClassmate($ctx['classId'],$ctx['userId'],$recipientUserId)) {
            return $this->error('Escolha um colega da sua turma.');
        }

        $pdo = Database::getConnection();
        $weekKey = $this->now()->format('o-W');
        $stockMax = (int)($this->config($pdo,'intercession_stock_max') ?? 2);

        try {
            $pdo->beginTransaction();

            $sent = $pdo->prepare('SELECT COUNT(*) FROM cq_intercessions WHERE sender_user_id=:u AND week_key=:w');
            $sent->execute(['u'=>$ctx['userId'],'w'=>$weekKey]);
            if ((int)$sent->fetchColumn() >= 1) throw new RuntimeException('Você já enviou sua Vela de Intercessão desta semana.');

            $stock = $pdo->prepare(
                'SELECT COUNT(*) FROM cq_intercessions
                 WHERE recipient_user_id=:u AND status="available" AND expires_at>NOW()'
            );
            $stock->execute(['u'=>$recipientUserId]);
            if ((int)$stock->fetchColumn() >= $stockMax) {
                throw new RuntimeException('Esse colega já possui o máximo de Velas disponíveis.');
            }

            $pdo->prepare(
                'INSERT INTO cq_intercessions
                    (class_id,sender_user_id,recipient_user_id,week_key,status,expires_at)
                 VALUES (:c,:s,:r,:w,"available",DATE_ADD(NOW(), INTERVAL 7 DAY))'
            )->execute([
                'c'=>$ctx['classId'],'s'=>$ctx['userId'],'r'=>$recipientUserId,'w'=>$weekKey
            ]);
            $pdo->commit();
            $this->evaluateBadges($ctx);
            return $this->success('Vela de Intercessão enviada. Ela pode proteger um dia perdido da Chama do seu colega.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error($e->getMessage());
        }
    }

    public function useIntercession(int $intercessionId): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Sessão inválida.');
        $missed = $this->streaks->findMostRecentMissedDate($ctx['userId'], 48);
        if ($missed === null) return $this->error('Não há um dia perdido elegível nas últimas 48 horas.');

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT * FROM cq_intercessions
                 WHERE id=:id AND recipient_user_id=:u AND status="available" AND expires_at>NOW()
                 FOR UPDATE'
            );
            $stmt->execute(['id'=>$intercessionId,'u'=>$ctx['userId']]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new RuntimeException('Esta Vela não está disponível.');

            $recovery = $pdo->prepare(
                'INSERT IGNORE INTO cq_streak_recoveries
                    (user_id,recovery_date,recovery_type,source_id,lumen_cost)
                 VALUES (:u,:d,"intercession",:s,0)'
            );
            $recovery->execute(['u'=>$ctx['userId'],'d'=>$missed,'s'=>$intercessionId]);
            if ($recovery->rowCount() === 0) throw new RuntimeException('Esse dia já foi protegido.');

            $pdo->prepare(
                'UPDATE cq_intercessions SET status="used",used_at=NOW() WHERE id=:id'
            )->execute(['id'=>$intercessionId]);
            $pdo->commit();

            $this->streaks->recomputeStatus($ctx['userId']);
            return $this->success('Vela de Intercessão usada. A Chama foi protegida no dia ' . $this->formatDate($missed) . '.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error($e->getMessage());
        }
    }

    public function useRosary(): array
    {
        $ctx = $this->studentContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Sessão inválida.');
        $pdo = Database::getConnection();
        $cost = (int)($this->config($pdo,'rosary_cost') ?? 90);
        $window = (int)($this->config($pdo,'rosary_window_hours') ?? 48);
        $cooldown = (int)($this->config($pdo,'rosary_cooldown_days') ?? 30);
        $missed = $this->streaks->findMostRecentMissedDate($ctx['userId'], $window);
        if ($missed === null) return $this->error('Não há um dia perdido que possa ser recuperado agora.');

        try {
            $pdo->beginTransaction();
            $last = $pdo->prepare(
                'SELECT created_at FROM cq_streak_recoveries
                 WHERE user_id=:u AND recovery_type="rosary"
                 ORDER BY created_at DESC LIMIT 1 FOR UPDATE'
            );
            $last->execute(['u'=>$ctx['userId']]);
            $lastDate = $last->fetchColumn();
            if ($lastDate) {
                $allowedAt = (new DateTimeImmutable((string)$lastDate, new DateTimeZone(self::TZ)))->modify('+' . $cooldown . ' days');
                if ($this->now() < $allowedAt) throw new RuntimeException('O Rosário da Jornada pode ser usado apenas uma vez a cada 30 dias.');
            }

            $student = $pdo->prepare('SELECT monete FROM ct_studenti WHERE id_studente=:s FOR UPDATE');
            $student->execute(['s'=>$ctx['studentId']]);
            $balance = (int)$student->fetchColumn();
            if ($balance < $cost) throw new RuntimeException('Você precisa de ' . $cost . ' Lúmens para usar o Rosário da Jornada.');

            $insert = $pdo->prepare(
                'INSERT IGNORE INTO cq_streak_recoveries
                    (user_id,recovery_date,recovery_type,source_id,lumen_cost)
                 VALUES (:u,:d,"rosary",NULL,:c)'
            );
            $insert->execute(['u'=>$ctx['userId'],'d'=>$missed,'c'=>$cost]);
            if ($insert->rowCount() === 0) throw new RuntimeException('Esse dia já foi protegido.');

            $newBalance = $balance - $cost;
            $pdo->prepare('UPDATE ct_studenti SET monete=:b WHERE id_studente=:s')
                ->execute(['b'=>$newBalance,'s'=>$ctx['studentId']]);
            $pdo->prepare(
                'INSERT INTO cq_lumen_ledger
                    (user_id,delta,balance_after,reason_type,reason_ref,description)
                 VALUES (:u,:d,:b,"rosary",:r,"Rosário da Jornada")'
            )->execute([
                'u'=>$ctx['userId'],'d'=>-$cost,'b'=>$newBalance,'r'=>$missed
            ]);

            $pdo->commit();
            $this->streaks->recomputeStatus($ctx['userId']);
            return $this->success(
                'Rosário da Jornada usado. ' . $cost . ' Lúmens foram gastos e o dia ' . $this->formatDate($missed) . ' foi recuperado.'
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->error($e->getMessage());
        }
    }

    public function getTeacherPageData(): array
    {
        $perm = new PermissionService();
        if ($perm->checkPermissionsTeacher() !== PermissionService::STATUS_OK) {
            return ['ok'=>false,'permissionStatus'=>$perm->checkPermissionsTeacher()];
        }
        $classId = (int)($perm->getCurrentClassId() ?? 0);
        if ($classId <= 0) return ['ok'=>false,'permissionStatus'=>PermissionService::STATUS_NO_CLASS];

        $pdo = Database::getConnection();
        $missions = $pdo->prepare(
            'SELECT m.*,
               (SELECT COUNT(*) FROM cq_mission_completions mc
                JOIN ct_studenti s ON s.fk_utente=mc.user_id
                JOIN ct_studenti_classi sc ON sc.fk_studente=s.id_studente
                WHERE mc.mission_id=m.id AND sc.fk_classe=:c) AS completion_count
             FROM cq_missions m
             ORDER BY m.chapter_no, COALESCE(m.step_no,99), m.sort_order'
        );
        $missions->execute(['c'=>$classId]);

        $students = $pdo->prepare(
            'SELECT COUNT(*) FROM ct_studenti_classi WHERE fk_classe=:c'
        );
        $students->execute(['c'=>$classId]);

        $pauses = $pdo->prepare(
            'SELECT p.*, CONCAT(COALESCE(u.nome,"")," ",COALESCE(u.cognome,"")) creator_name
             FROM cq_streak_pauses p
             LEFT JOIN ct_utenti u ON u.id_utente=p.created_by
             WHERE p.scope_type="class" AND p.scope_id=:c
             ORDER BY p.start_date DESC LIMIT 20'
        );
        $pauses->execute(['c'=>$classId]);

        return [
            'ok'=>true,
            'permissionStatus'=>PermissionService::STATUS_OK,
            'classId'=>$classId,
            'studentCount'=>(int)$students->fetchColumn(),
            'missions'=>$missions->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'students'=>$this->classmates($pdo,$classId,0),
            'pauses'=>$pauses->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'levels'=>$pdo->query('SELECT * FROM cq_game_levels ORDER BY level_no')->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    public function toggleMission(int $missionId): array
    {
        $ctx = $this->teacherContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Acesso restrito aos catequistas.');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT active FROM cq_missions WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$missionId]);
        $active = $stmt->fetchColumn();
        if ($active === false) return $this->error('Missão não encontrada.');
        $new = (int)$active === 1 ? 0 : 1;
        $pdo->prepare('UPDATE cq_missions SET active=:a WHERE id=:id')->execute(['a'=>$new,'id'=>$missionId]);
        return $this->success($new ? 'Missão ativada.' : 'Missão pausada.');
    }

    public function duplicateMission(int $missionId): array
    {
        $ctx = $this->teacherContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Acesso restrito aos catequistas.');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM cq_missions WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$missionId]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$m) return $this->error('Missão não encontrada.');

        $slug = mb_substr((string)$m['slug'] . '-copia-' . date('YmdHis'),0,120);
        $insert = $pdo->prepare(
            'INSERT INTO cq_missions
             (slug,step_no,chapter_no,mission_type,title,body,question,options_json,correct_answer,feedback,
              xp_reward,lumen_reward,bonus_xp_correct,special_reward,available_from,available_until,
              repeatable,grants_streak,active,sort_order)
             VALUES
             (:slug,:step,:chapter,:type,:title,:body,:question,:options,:correct,:feedback,
              :xp,:lumens,:bonus,NULL,:from,:until,:repeatable,:streak,0,:sort)'
        );
        $insert->execute([
            'slug'=>$slug,'step'=>$m['step_no'],'chapter'=>$m['chapter_no'],'type'=>$m['mission_type'],
            'title'=>mb_substr((string)$m['title'] . ' — cópia',0,180),'body'=>$m['body'],
            'question'=>$m['question'],'options'=>$m['options_json'],'correct'=>$m['correct_answer'],
            'feedback'=>$m['feedback'],'xp'=>$m['xp_reward'],'lumens'=>$m['lumen_reward'],
            'bonus'=>$m['bonus_xp_correct'],'from'=>$m['available_from'],'until'=>$m['available_until'],
            'repeatable'=>$m['repeatable'],'streak'=>$m['grants_streak'],'sort'=>(int)$m['sort_order'] + 1,
        ]);
        return $this->success('Missão duplicada como rascunho. Ela ficou desativada para edição segura.');
    }

    public function createMission(array $input): array
    {
        $ctx = $this->teacherContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Acesso restrito aos catequistas.');
        $data = $this->validateMissionInput($input);
        if (!($data['ok'] ?? false)) return $this->error((string)$data['message']);

        $pdo = Database::getConnection();
        $slug = 'custom-' . date('YmdHis') . '-' . substr(hash('sha256', microtime(true) . ':' . $ctx['userId']), 0, 8);
        $stmt = $pdo->prepare(
            'INSERT INTO cq_missions
             (slug,step_no,chapter_no,mission_type,title,body,question,options_json,correct_answer,feedback,
              xp_reward,lumen_reward,bonus_xp_correct,special_reward,available_from,available_until,
              repeatable,grants_streak,active,sort_order)
             VALUES
             (:slug,:step,:chapter,:type,:title,:body,:question,:options,:correct,:feedback,
              :xp,:lumens,:bonus,NULL,:from,:until,0,1,:active,999)'
        );
        $stmt->execute([
            'slug'=>$slug,
            'step'=>$data['step_no'],
            'chapter'=>$data['chapter_no'],
            'type'=>$data['mission_type'],
            'title'=>$data['title'],
            'body'=>$data['body'],
            'question'=>$data['question'],
            'options'=>$data['options_json'],
            'correct'=>$data['correct_answer'],
            'feedback'=>$data['feedback'],
            'xp'=>$data['xp_reward'],
            'lumens'=>$data['lumen_reward'],
            'bonus'=>$data['bonus_xp_correct'],
            'from'=>$data['available_from'],
            'until'=>$data['available_until'],
            'active'=>$data['active'],
        ]);
        return $this->success('Nova missão criada' . ($data['active'] ? ' e publicada.' : ' como rascunho.'));
    }

    public function updateMission(int $missionId, array $input): array
    {
        $ctx = $this->teacherContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Acesso restrito aos catequistas.');
        if ($missionId <= 0) return $this->error('Missão inválida.');
        $data = $this->validateMissionInput($input);
        if (!($data['ok'] ?? false)) return $this->error((string)$data['message']);

        $stmt = Database::getConnection()->prepare(
            'UPDATE cq_missions SET
               step_no=:step,chapter_no=:chapter,mission_type=:type,title=:title,body=:body,
               question=:question,options_json=:options,correct_answer=:correct,feedback=:feedback,
               xp_reward=:xp,lumen_reward=:lumens,bonus_xp_correct=:bonus,
               available_from=:from,available_until=:until,active=:active
             WHERE id=:id'
        );
        $stmt->execute([
            'step'=>$data['step_no'],
            'chapter'=>$data['chapter_no'],
            'type'=>$data['mission_type'],
            'title'=>$data['title'],
            'body'=>$data['body'],
            'question'=>$data['question'],
            'options'=>$data['options_json'],
            'correct'=>$data['correct_answer'],
            'feedback'=>$data['feedback'],
            'xp'=>$data['xp_reward'],
            'lumens'=>$data['lumen_reward'],
            'bonus'=>$data['bonus_xp_correct'],
            'from'=>$data['available_from'],
            'until'=>$data['available_until'],
            'active'=>$data['active'],
            'id'=>$missionId,
        ]);
        return $this->success('Missão atualizada.');
    }

    public function pauseStudent(int $userId, string $startDate, string $endDate, string $reason): array
    {
        $ctx = $this->teacherContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Acesso restrito aos catequistas.');
        if (!$this->isClassmate($ctx['classId'], $ctx['userId'], $userId) && $userId !== $ctx['userId']) {
            // O helper isClassmate exige remetente diferente, então confirmamos diretamente para o estudante.
            $check = Database::getConnection()->prepare(
                'SELECT COUNT(*) FROM ct_studenti s
                 JOIN ct_studenti_classi sc ON sc.fk_studente=s.id_studente
                 WHERE s.fk_utente=:u AND sc.fk_classe=:c'
            );
            $check->execute(['u'=>$userId,'c'=>$ctx['classId']]);
            if ((int)$check->fetchColumn() === 0) return $this->error('Crismando inválido.');
        }
        if (!$this->validDate($startDate) || !$this->validDate($endDate) || $endDate < $startDate) {
            return $this->error('Informe um intervalo de datas válido.');
        }
        if ((int)(new DateTimeImmutable($startDate))->diff(new DateTimeImmutable($endDate))->format('%a') > 90) {
            return $this->error('A pausa não pode superar 90 dias.');
        }

        Database::getConnection()->prepare(
            'INSERT INTO cq_streak_pauses
             (scope_type,scope_id,start_date,end_date,reason,created_by)
             VALUES ("user",:u,:a,:b,:r,:creator)'
        )->execute([
            'u'=>$userId,'a'=>$startDate,'b'=>$endDate,
            'r'=>mb_substr(trim($reason),0,255) ?: null,'creator'=>$ctx['userId']
        ]);
        return $this->success('Pausa pastoral aplicada apenas a este crismando.');
    }

    public function pauseClass(string $startDate, string $endDate, string $reason): array
    {
        $ctx = $this->teacherContext();
        if (!($ctx['ok'] ?? false)) return $this->error('Acesso restrito aos catequistas.');
        if (!$this->validDate($startDate) || !$this->validDate($endDate) || $endDate < $startDate) {
            return $this->error('Informe um intervalo de datas válido.');
        }
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        if ((int)$start->diff($end)->format('%a') > 90) {
            return $this->error('A pausa não pode superar 90 dias.');
        }

        Database::getConnection()->prepare(
            'INSERT INTO cq_streak_pauses
                (scope_type,scope_id,start_date,end_date,reason,created_by)
             VALUES ("class",:c,:a,:b,:r,:u)'
        )->execute([
            'c'=>$ctx['classId'],'a'=>$startDate,'b'=>$endDate,
            'r'=>mb_substr(trim($reason),0,255) ?: null,'u'=>$ctx['userId']
        ]);
        return $this->success('Pausa da Chama cadastrada para toda a turma.');
    }

    public function getNextMissionForHome(int $userId): ?array
    {
        if ($userId <= 0) return null;
        try {
            $pdo = Database::getConnection();
            $rows = $this->availableMissions($pdo,$userId,$this->today(),1);
            if ($rows === []) return null;
            $m = $rows[0];
            return [
                'id'=>$m['id'],
                'title'=>$m['title'],
                'chapter_title'=>'Capítulo ' . (int)$m['chapter_no'],
                'url'=>'/studenti/missoes#missao-' . (int)$m['id'],
                'xp_reward'=>(int)$m['xp_reward'],
                'lumen_reward'=>(int)$m['lumen_reward'],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function countCompletedSteps(int $userId): int
    {
        if ($userId <= 0) return 0;
        try {
            $pdo = Database::getConnection();
            return $this->countCompletedStepsWithPdo($pdo,$userId);
        } catch (Throwable) {
            return 0;
        }
    }

    private function processStreakMilestones(array $ctx, array $streak): array
    {
        $current = (int)($streak['current'] ?? 0);
        if ($current <= 0) return [];

        $pdo = Database::getConnection();
        $messages = [];
        try {
            $pdo->beginTransaction();

            if ($current >= 3) {
                $r = $this->rewards->grant(
                    $pdo,$ctx['userId'],$ctx['studentId'],
                    'streak-3:' . $ctx['userId'],0,3,'Chama de 3 dias'
                );
                if ($r['applied']) $messages[] = 'Marco de 3 dias: +3 Lúmens.';
            }
            if ($current >= 7 && $this->rewards->markOnce($pdo,$ctx['userId'],'streak-7:' . $ctx['userId'],'Chama de 7 dias')) {
                $pdo->prepare(
                    'INSERT INTO cq_streaks
                        (user_id,current_streak,longest_streak,last_qualified_activity_date,streak_freezes_available)
                     VALUES (:u,0,0,NULL,1)
                     ON DUPLICATE KEY UPDATE streak_freezes_available=streak_freezes_available+1'
                )->execute(['u'=>$ctx['userId']]);
                $messages[] = 'Marco de 7 dias: Escudo da Chama recebido.';
            }
            if ($current >= 14) {
                $r = $this->rewards->grant(
                    $pdo,$ctx['userId'],$ctx['studentId'],
                    'streak-14-lumens:' . $ctx['userId'],0,10,'Baú Brasa'
                );
                $card = $this->rewards->grantCard(
                    $pdo,$ctx['userId'],'streak-14-card',null,true
                );
                if ($r['applied'] || $card) $messages[] = 'Marco de 14 dias: Baú Brasa aberto.';
            }
            if ($current >= 30 && $this->rewards->grantCosmetic(
                $pdo,$ctx['userId'],'chama-dourada','streak-30'
            )) {
                $messages[] = 'Marco de 30 dias: Chama Dourada liberada.';
            }
            if ($current >= 60) {
                $card = $this->rewards->grantIlluminatedCard($pdo,$ctx['userId'],'streak-60');
                if ($card) $messages[] = 'Marco de 60 dias: carta em edição iluminada.';
            }
            if ($current >= 90 && $this->rewards->grantCosmetic(
                $pdo,$ctx['userId'],'moldura-album','streak-90'
            )) {
                $messages[] = 'Marco de 90 dias: moldura especial liberada.';
            }

            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
        return $messages;
    }

    private function evaluateBadges(array $ctx): void
    {
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $userId = $ctx['userId'];

            $missionCount = $this->scalar($pdo,'SELECT COUNT(*) FROM cq_mission_completions WHERE user_id=?',[$userId]);
            if ($missionCount >= 1) $this->rewards->grantBadge($pdo,$userId,'primeiro-passo');

            $streak = $this->streaks->getStatus($userId);
            if ((int)$streak['current'] >= 7) $this->rewards->grantBadge($pdo,$userId,'chama-acesa');

            $wordCount = $this->scalar($pdo,
                'SELECT COUNT(*) FROM cq_mission_completions mc JOIN cq_missions m ON m.id=mc.mission_id WHERE mc.user_id=? AND m.mission_type="palavra"',
                [$userId]
            );
            if ($wordCount >= 10) $this->rewards->grantBadge($pdo,$userId,'palavra-viva');

            $actionCount = $this->scalar($pdo,
                'SELECT COUNT(*) FROM cq_mission_completions mc JOIN cq_missions m ON m.id=mc.mission_id WHERE mc.user_id=? AND m.mission_type="acao"',
                [$userId]
            );
            if ($actionCount >= 5) $this->rewards->grantBadge($pdo,$userId,'evangelho-em-acao');

            $attendance = $this->scalar($pdo,
                'SELECT COUNT(*) FROM cq_attendance WHERE user_id=? AND status IN ("presente","atrasado")',
                [$userId]
            );
            if ($attendance >= 4) $this->rewards->grantBadge($pdo,$userId,'peregrino-comunidade');

            $cards = $this->scalar($pdo,
                'SELECT COUNT(DISTINCT card_edition_id) FROM cq_user_cards WHERE user_id=? AND quantity>0',
                [$userId]
            );
            if ($cards >= 1) $this->rewards->grantBadge($pdo,$userId,'testemunhas');
            if ($cards >= 10) $this->rewards->grantBadge($pdo,$userId,'colecionador');

            $intercessions = $this->scalar($pdo,'SELECT COUNT(*) FROM cq_intercessions WHERE sender_user_id=?',[$userId]);
            if ($intercessions >= 3) $this->rewards->grantBadge($pdo,$userId,'comunhao');

            $patrons = $this->scalar($pdo,
                'SELECT COUNT(DISTINCT sc.slug)
                 FROM cq_user_cards uc
                 JOIN cq_card_editions ce ON ce.id=uc.card_edition_id
                 JOIN cq_saint_cards sc ON sc.id=ce.card_id
                 WHERE uc.user_id=? AND uc.quantity>0 AND sc.slug IN ("sao-carlo-acutis","santa-joana-darc")',
                [$userId]
            );
            if ($patrons >= 2) $this->rewards->grantBadge($pdo,$userId,'padroeiros');

            if ($this->hasMission($pdo,$userId,'especial-advento')) $this->rewards->grantBadge($pdo,$userId,'advento');
            if ($this->hasCompletionAfter($pdo,$userId,'2027-01-23')) $this->rewards->grantBadge($pdo,$userId,'retorno');
            if ($this->chapterComplete($pdo,$userId,5)) $this->rewards->grantBadge($pdo,$userId,'vida-em-cristo');
            if ($this->chapterComplete($pdo,$userId,6)) $this->rewards->grantBadge($pdo,$userId,'oracao-missao');
            if ($this->hasMission($pdo,$userId,'final-enviados')) $this->rewards->grantBadge($pdo,$userId,'portas-quaresma');

            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }

    private function availableMissions(PDO $pdo, int $userId, string $today, ?int $limit = null): array
    {
        $sql =
            'SELECT m.*,
                    CASE WHEN mc.id IS NULL THEN 0 ELSE 1 END completed
             FROM cq_missions m
             LEFT JOIN cq_mission_completions mc ON mc.mission_id=m.id AND mc.user_id=:u
             WHERE m.active=1 AND m.available_from<=:d1 AND m.available_until>=:d2
               AND mc.id IS NULL
             ORDER BY m.chapter_no, COALESCE(m.step_no,99), m.sort_order';
        if ($limit !== null) $sql .= ' LIMIT ' . max(1,$limit);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['u'=>$userId,'d1'=>$today,'d2'=>$today]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['options'] = [];
            if (!empty($row['options_json'])) {
                $decoded = json_decode((string)$row['options_json'], true);
                if (is_array($decoded)) $row['options'] = $decoded;
            }
        }
        unset($row);
        return $rows;
    }

    private function currentSpark(PDO $pdo, int $userId, string $today): ?array
    {
        $start = $this->config($pdo,'season_start');
        $end = $this->config($pdo,'season_end');
        if (($start && $today < $start) || ($end && $today > $end) || $this->isRecess($pdo,$today)) return null;

        $count = (int)$pdo->query('SELECT COUNT(*) FROM cq_daily_sparks WHERE active=1')->fetchColumn();
        if ($count <= 0) return null;
        $seed = (int)sprintf('%u', crc32($today));
        $offset = $seed % $count;
        $spark = $pdo->query(
            'SELECT * FROM cq_daily_sparks WHERE active=1 ORDER BY sort_order,id LIMIT 1 OFFSET ' . $offset
        )->fetch(PDO::FETCH_ASSOC);
        if (!$spark) return null;

        $done = $pdo->prepare(
            'SELECT COUNT(*) FROM cq_spark_completions WHERE user_id=:u AND activity_date=:d'
        );
        $done->execute(['u'=>$userId,'d'=>$today]);
        $spark['completed'] = (int)$done->fetchColumn() > 0 ? 1 : 0;
        return $spark;
    }

    private function progressData(PDO $pdo, int $userId): array
    {
        $completed = $this->countCompletedStepsWithPdo($pdo,$userId);
        $total = 22;
        return [
            'completedSteps'=>$completed,
            'totalSteps'=>$total,
            'percent'=>(int)floor(($completed/$total)*100),
            'completedMissions'=>$this->scalar($pdo,'SELECT COUNT(*) FROM cq_mission_completions WHERE user_id=?',[$userId]),
        ];
    }

    private function countCompletedStepsWithPdo(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM (
               SELECT m.step_no
               FROM cq_missions m
               LEFT JOIN cq_mission_completions mc ON mc.mission_id=m.id AND mc.user_id=?
               WHERE m.step_no IS NOT NULL AND m.active=1
               GROUP BY m.step_no
               HAVING COUNT(m.id)=SUM(CASE WHEN mc.id IS NULL THEN 0 ELSE 1 END)
             ) completed_steps'
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function chapterComplete(PDO $pdo, int $userId, int $chapter): bool
    {
        $total = $this->scalar($pdo,'SELECT COUNT(*) FROM cq_journey_steps WHERE chapter_no=? AND active=1',[$chapter]);
        if ($total <= 0) return false;
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM (
               SELECT m.step_no
               FROM cq_missions m
               LEFT JOIN cq_mission_completions mc ON mc.mission_id=m.id AND mc.user_id=?
               WHERE m.step_no IS NOT NULL AND m.chapter_no=? AND m.active=1
               GROUP BY m.step_no
               HAVING COUNT(m.id)=SUM(CASE WHEN mc.id IS NULL THEN 0 ELSE 1 END)
             ) x'
        );
        $stmt->execute([$userId,$chapter]);
        return (int)$stmt->fetchColumn() >= $total;
    }

    private function availableChests(PDO $pdo, int $userId, int $studentId): array
    {
        $xpStmt = $pdo->prepare('SELECT xp FROM ct_studenti WHERE id_studente=:s');
        $xpStmt->execute(['s'=>$studentId]);
        $xp = (int)$xpStmt->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT c.*
             FROM cq_chest_catalog c
             LEFT JOIN cq_user_chests uc ON uc.chest_id=c.id AND uc.user_id=:u
             WHERE c.active=1 AND c.threshold_xp<=:xp AND uc.id IS NULL
             ORDER BY c.threshold_xp'
        );
        $stmt->execute(['u'=>$userId,'xp'=>$xp]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function userBadges(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            'SELECT b.*, ub.earned_at
             FROM cq_user_badges ub JOIN cq_badge_catalog b ON b.id=ub.badge_id
             WHERE ub.user_id=:u ORDER BY ub.earned_at DESC'
        );
        $stmt->execute(['u'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function availableIntercessions(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            'SELECT i.*, CONCAT(u.nome," ",u.cognome) sender_name
             FROM cq_intercessions i JOIN ct_utenti u ON u.id_utente=i.sender_user_id
             WHERE i.recipient_user_id=:u1 AND i.status="available" AND i.expires_at>NOW()
             ORDER BY i.created_at DESC'
        );
        $stmt->execute(['u1'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function rosaryStatus(PDO $pdo, int $userId, int $balance): array
    {
        $cost = (int)($this->config($pdo,'rosary_cost') ?? 90);
        $last = $pdo->prepare(
            'SELECT created_at FROM cq_streak_recoveries
             WHERE user_id=:u AND recovery_type="rosary"
             ORDER BY created_at DESC LIMIT 1'
        );
        $last->execute(['u'=>$userId]);
        $lastDate = $last->fetchColumn() ?: null;
        $available = $balance >= $cost;
        $nextAt = null;
        if ($lastDate) {
            $next = (new DateTimeImmutable((string)$lastDate,new DateTimeZone(self::TZ)))->modify('+30 days');
            $nextAt = $next->format('Y-m-d H:i:s');
            if ($this->now() < $next) $available = false;
        }
        return ['cost'=>$cost,'available'=>$available,'lastUsed'=>$lastDate,'nextAvailable'=>$nextAt];
    }

    private function validateMissionInput(array $input): array
    {
        $types = ['palavra','quiz','reflexao','acao','igreja','testemunhas','grande','especial'];
        $title = trim((string)($input['title'] ?? ''));
        $body = trim((string)($input['body'] ?? ''));
        $type = trim((string)($input['mission_type'] ?? ''));
        $chapter = (int)($input['chapter_no'] ?? 0);
        $stepRaw = trim((string)($input['step_no'] ?? ''));
        $step = $stepRaw === '' ? null : (int)$stepRaw;
        $from = (string)($input['available_from'] ?? '');
        $until = (string)($input['available_until'] ?? '');
        $xp = max(0,min(50,(int)($input['xp_reward'] ?? 0)));
        $lumens = max(0,min(20,(int)($input['lumen_reward'] ?? 0)));
        $bonus = max(0,min(10,(int)($input['bonus_xp_correct'] ?? 0)));
        $active = (($input['active'] ?? '') === '1') ? 1 : 0;

        if ($title === '' || mb_strlen($title) > 180) return ['ok'=>false,'message'=>'Informe um título de até 180 caracteres.'];
        if ($body === '') return ['ok'=>false,'message'=>'Escreva o conteúdo da missão.'];
        if (!in_array($type,$types,true)) return ['ok'=>false,'message'=>'Tipo de missão inválido.'];
        if ($chapter < 1 || $chapter > 6) return ['ok'=>false,'message'=>'Capítulo inválido.'];
        if ($step !== null && ($step < 1 || $step > 22)) return ['ok'=>false,'message'=>'Etapa inválida.'];
        if (!$this->validDate($from) || !$this->validDate($until) || $until < $from) return ['ok'=>false,'message'=>'Datas de publicação inválidas.'];

        $question = trim((string)($input['question'] ?? ''));
        $feedback = trim((string)($input['feedback'] ?? ''));
        $correct = mb_strtoupper(mb_substr(trim((string)($input['correct_answer'] ?? '')),0,1));
        $optionsJson = null;
        if ($type === 'quiz') {
            $raw = trim((string)($input['options'] ?? ''));
            $parts = array_values(array_filter(array_map('trim',explode('|',$raw)),static fn($x)=>$x!==''));
            if ($question === '' || count($parts) < 2 || !preg_match('/^[A-F]$/',$correct)) {
                return ['ok'=>false,'message'=>'Quiz precisa de pergunta, pelo menos duas opções separadas por | e resposta A–F.'];
            }
            $optionsJson = json_encode($parts,JSON_UNESCAPED_UNICODE);
            $bonus = max(1,$bonus);
        } else {
            $question = '';
            $correct = '';
            $bonus = 0;
        }

        return [
            'ok'=>true,'title'=>$title,'body'=>$body,'mission_type'=>$type,
            'chapter_no'=>$chapter,'step_no'=>$step,'available_from'=>$from,'available_until'=>$until,
            'xp_reward'=>$xp,'lumen_reward'=>$lumens,'bonus_xp_correct'=>$bonus,'active'=>$active,
            'question'=>$question === '' ? null : $question,
            'options_json'=>$optionsJson,
            'correct_answer'=>$correct === '' ? null : $correct,
            'feedback'=>$feedback === '' ? null : $feedback,
        ];
    }

    private function communityLight(PDO $pdo, int $classId, string $today): array
    {
        $date = new DateTimeImmutable($today,new DateTimeZone(self::TZ));
        $monday = $date->modify('monday this week')->format('Y-m-d');
        $sunday = $date->modify('sunday this week')->format('Y-m-d');
        $total = $this->scalar($pdo,'SELECT COUNT(*) FROM ct_studenti_classi WHERE fk_classe=?',[$classId]);
        if ($total <= 0) return ['percent'=>0,'activeStudents'=>0,'totalStudents'=>0,'tier'=>0,'label'=>'Começando'];

        $stmt = $pdo->prepare(
            'SELECT COUNT(DISTINCT mc.user_id)
             FROM cq_mission_completions mc
             JOIN ct_studenti s ON s.fk_utente=mc.user_id
             JOIN ct_studenti_classi sc ON sc.fk_studente=s.id_studente
             WHERE sc.fk_classe=:c AND DATE(mc.completed_at) BETWEEN :a AND :b'
        );
        $stmt->execute(['c'=>$classId,'a'=>$monday,'b'=>$sunday]);
        $active = (int)$stmt->fetchColumn();
        $percent = (int)floor(($active/$total)*100);
        $tier = $percent >= 90 ? 3 : ($percent >= 75 ? 2 : ($percent >= 60 ? 1 : 0));
        $label = match($tier) {
            3 => 'Baú da Comunhão liberado',
            2 => 'Comunidade iluminada',
            1 => 'Primeira luz acesa',
            default => 'A caminho da primeira luz',
        };
        return ['percent'=>$percent,'activeStudents'=>$active,'totalStudents'=>$total,'tier'=>$tier,'label'=>$label];
    }

    private function studentContext(): array
    {
        $perm = new PermissionService();
        $status = $perm->checkPermissionsStudent();
        if ($status !== PermissionService::STATUS_OK) return ['ok'=>false,'permissionStatus'=>$status];
        $userId = (int)($perm->getCurrentUserId() ?? 0);
        $classId = (int)($perm->getCurrentClassId() ?? 0);
        if ($userId <= 0 || $classId <= 0) return ['ok'=>false,'permissionStatus'=>PermissionService::STATUS_NO_CLASS];

        $stmt = Database::getConnection()->prepare(
            'SELECT s.id_studente,s.xp,s.monete,s.livello,u.nome,u.cognome
             FROM ct_studenti s
             JOIN ct_utenti u ON u.id_utente=s.fk_utente
             JOIN ct_studenti_classi sc ON sc.fk_studente=s.id_studente
             WHERE u.id_utente=:u AND sc.fk_classe=:c LIMIT 1'
        );
        $stmt->execute(['u'=>$userId,'c'=>$classId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['ok'=>false,'permissionStatus'=>PermissionService::STATUS_NOT_CLASS_OWNER];
        return [
            'ok'=>true,'permissionStatus'=>PermissionService::STATUS_OK,
            'userId'=>$userId,'classId'=>$classId,'studentId'=>(int)$row['id_studente'],
            'xp'=>(int)$row['xp'],'balance'=>(int)$row['monete'],'level'=>(int)$row['livello'],
            'student'=>$row
        ];
    }

    private function teacherContext(): array
    {
        $perm = new PermissionService();
        $status = $perm->checkPermissionsTeacher();
        if ($status !== PermissionService::STATUS_OK) return ['ok'=>false,'permissionStatus'=>$status];
        return [
            'ok'=>true,'permissionStatus'=>$status,
            'userId'=>(int)($perm->getCurrentUserId() ?? 0),
            'classId'=>(int)($perm->getCurrentClassId() ?? 0)
        ];
    }

    private function classmates(PDO $pdo, int $classId, int $userId): array
    {
        $stmt = $pdo->prepare(
            'SELECT u.id_utente,u.nome,u.cognome
             FROM ct_studenti_classi sc
             JOIN ct_studenti s ON s.id_studente=sc.fk_studente
             JOIN ct_utenti u ON u.id_utente=s.fk_utente
             WHERE sc.fk_classe=:c AND u.id_utente<>:u
             ORDER BY u.nome,u.cognome'
        );
        $stmt->execute(['c'=>$classId,'u'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function isClassmate(int $classId, int $sender, int $recipient): bool
    {
        if ($recipient <= 0 || $recipient === $sender) return false;
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM ct_studenti_classi sc
             JOIN ct_studenti s ON s.id_studente=sc.fk_studente
             WHERE sc.fk_classe=:c AND s.fk_utente=:u'
        );
        $stmt->execute(['c'=>$classId,'u'=>$recipient]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function isRecess(PDO $pdo, string $date): bool
    {
        $a = $this->config($pdo,'recess_start');
        $b = $this->config($pdo,'recess_end');
        return $a !== null && $b !== null && $date >= $a && $date <= $b;
    }

    private function config(PDO $pdo, string $key): ?string
    {
        $stmt = $pdo->prepare('SELECT config_value FROM cq_game_config WHERE config_key=:k LIMIT 1');
        $stmt->execute(['k'=>$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    }

    private function scalar(PDO $pdo, string $sql, array $params): int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function hasMission(PDO $pdo, int $userId, string $slug): bool
    {
        return $this->scalar(
            $pdo,
            'SELECT COUNT(*) FROM cq_mission_completions mc
             JOIN cq_missions m ON m.id=mc.mission_id
             WHERE mc.user_id=? AND m.slug=?',
            [$userId,$slug]
        ) > 0;
    }

    private function hasCompletionAfter(PDO $pdo, int $userId, string $date): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM cq_mission_completions
             WHERE user_id=:u AND DATE(completed_at)>=:d'
        );
        $stmt->execute(['u'=>$userId,'d'=>$date]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function validDate(string $date): bool
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d',$date,new DateTimeZone(self::TZ));
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now',new DateTimeZone(self::TZ));
    }

    private function today(): string
    {
        return $this->now()->format('Y-m-d');
    }

    private function formatDate(string $date): string
    {
        try {
            return (new DateTimeImmutable($date))->format('d/m/Y');
        } catch (Throwable) {
            return $date;
        }
    }

    private function success(string $message): array
    {
        return ['ok'=>true,'success'=>true,'message'=>$message];
    }

    private function error(string $message): array
    {
        return ['ok'=>false,'success'=>false,'message'=>$message];
    }
}
