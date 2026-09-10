<?php
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$missionLabels = [
    'palavra'=>['fa-book-bible','Palavra Viva'],
    'quiz'=>['fa-circle-question','Entenda a Fé'],
    'reflexao'=>['fa-compass','Desafio da Fé'],
    'acao'=>['fa-hand-holding-heart','Evangelho em Ação'],
    'igreja'=>['fa-church','Igreja por Dentro'],
    'testemunhas'=>['fa-image-portrait','Testemunhas'],
    'grande'=>['fa-flag-checkered','Grande Missão'],
    'especial'=>['fa-star','Missão Especial'],
];
$studentName = trim((string)($student['nome'] ?? '') . ' ' . (string)($student['cognome'] ?? ''));
?>
<?php if (!empty($preview)): ?>
<p class="alert alert-info">Prévia das missões disponíveis hoje para um crismando que está começando. Os botões de conclusão ficam desativados nesta prévia.</p>
<fieldset disabled style="border:0;margin:0;padding:0;min-width:0">
<?php endif; ?>
<div class="cq-game-shell">
  <nav class="cq-game-section-head"><a href="<?= !empty($preview) ? '/docenti/jogo/jornada' : '/studenti/classe/dashboard?view=journey' ?>">← Ver Jornada</a><?php if (!empty($selectedStep)): ?><a href="/studenti/missoes">Todas as missões disponíveis</a><?php endif; ?></nav>
  <section class="cq-game-hero">
    <div>
      <div class="cq-game-kicker">Temporada 2026–2027</div>
      <h1><?= !empty($selectedStep) ? 'Etapa ' . (int)$selectedStep : 'Missões da Jornada' ?></h1>
      <p><?= $h($studentName ?: 'Sua caminhada') ?> · do chamado ao envio, uma etapa de cada vez.</p>
    </div>
    <div class="cq-game-stats">
      <div><i class="fa-solid fa-star"></i><strong><?= (int)($xp ?? 0) ?></strong><span>XP</span></div>
      <div><i class="fa-solid fa-sun"></i><strong><?= (int)($balance ?? 0) ?></strong><span>Lúmens</span></div>
      <div><i class="fa-solid fa-fire-flame-curved"></i><strong><?= (int)($streak['current'] ?? 0) ?></strong><span>dias</span></div>
      <div><i class="fa-solid fa-route"></i><strong><?= (int)($progress['completedSteps'] ?? 0) ?>/22</strong><span>etapas</span></div>
    </div>
  </section>

  <?php if (!empty($recess)): ?>
    <section class="cq-game-card cq-recess">
      <i class="fa-solid fa-shield-heart"></i>
      <div><strong>Sua Chama está protegida durante o recesso.</strong><p>Aproveite este tempo com sua família. As missões especiais são opcionais.</p></div>
    </section>
  <?php elseif (is_array($spark ?? null)): ?>
    <section class="cq-game-card cq-spark">
      <div class="cq-game-section-head">
        <div><div class="cq-game-kicker">Centelha de hoje</div><h2><?= $h($spark['title'] ?? 'Centelha do dia') ?></h2></div>
        <div class="cq-reward-pills"><span>+<?= (int)($spark['xp_reward'] ?? 5) ?> XP</span><span>+<?= (int)($spark['lumen_reward'] ?? 1) ?> L</span></div>
      </div>
      <p><?= $h($spark['body'] ?? '') ?></p>
      <?php if ((int)($spark['completed'] ?? 0) === 1): ?>
        <div class="cq-done"><i class="fa-solid fa-circle-check"></i> Feita hoje. Sua Chama está acesa.</div>
      <?php else: ?>
        <form method="post" action="/studenti/centelha/concluir">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>">
          <input type="hidden" name="spark_id" value="<?= (int)($spark['id'] ?? 0) ?>">
          <button class="cq-game-primary" type="submit"><i class="fa-solid fa-fire"></i> Concluir Centelha</button>
        </form>
      <?php endif; ?>
      <small>As três primeiras Centelhas da semana dão XP e Lúmens. As demais mantêm a Chama sem recompensa extra.</small>
    </section>
  <?php endif; ?>

  <div class="cq-game-section-head cq-mission-heading">
    <div><div class="cq-game-kicker">Caminho aberto</div><h2>Missões disponíveis</h2></div>
    <span class="cq-progress-chip"><?= (int)($progress['completedMissions'] ?? 0) ?> concluídas</span>
  </div>

  <?php if (($missions ?? []) === []): ?>
    <section class="cq-game-card cq-empty-game">
      <i class="fa-solid fa-circle-check"></i>
      <h3>Tudo em dia</h3>
      <p>Você concluiu todas as missões já liberadas. Volte quando a próxima etapa abrir.</p>
    </section>
  <?php else: ?>
    <div class="cq-missions-list">
      <?php foreach ($missions as $mission):
        [$icon,$label] = $missionLabels[$mission['mission_type']] ?? ['fa-compass','Missão'];
        $options = is_array($mission['options'] ?? null) ? $mission['options'] : [];
      ?>
      <article class="cq-game-card cq-mission" id="missao-<?= (int)$mission['id'] ?>">
        <div class="cq-mission-meta">
          <span class="cq-type"><i class="fa-solid <?= $h($icon) ?>"></i> <?= $h($label) ?></span>
          <span>Cap. <?= (int)$mission['chapter_no'] ?><?= !empty($mission['step_no']) ? ' · Etapa '.(int)$mission['step_no'] : '' ?></span>
        </div>
        <h3><?= $h($mission['title']) ?></h3>
        <p><?= nl2br($h($mission['body'])) ?></p>
        <div class="cq-reward-pills">
          <span><i class="fa-solid fa-star"></i> +<?= (int)$mission['xp_reward'] ?> XP</span>
          <span><i class="fa-solid fa-sun"></i> +<?= (int)$mission['lumen_reward'] ?> L</span>
          <?php if ((int)$mission['bonus_xp_correct'] > 0): ?><span>+<?= (int)$mission['bonus_xp_correct'] ?> XP acerto</span><?php endif; ?>
          <?php if (!empty($mission['special_reward'])): ?><span><i class="fa-solid fa-gift"></i> prêmio especial</span><?php endif; ?>
        </div>

        <form method="post" action="/studenti/missoes/<?= (int)$mission['id'] ?>/concluir" class="cq-mission-form">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>">
          <?php if ($mission['mission_type'] === 'quiz'): ?>
            <fieldset>
              <legend><?= $h($mission['question'] ?? 'Escolha uma resposta') ?></legend>
              <?php foreach ($options as $option):
                $letter = mb_strtoupper(mb_substr(trim((string)$option),0,1));
              ?>
                <label class="cq-option"><input type="radio" name="answer" value="<?= $h($letter) ?>" required><span><?= $h($option) ?></span></label>
              <?php endforeach; ?>
            </fieldset>
          <?php else: ?>
            <label class="cq-confirm">
              <input type="checkbox" name="confirm" value="1" required>
              <span>Realizei esta missão. Se a reflexão for pessoal, ela permanece comigo e não preciso escrever detalhes íntimos.</span>
            </label>
          <?php endif; ?>
          <button class="cq-game-primary" type="submit">Concluir missão <i class="fa-solid fa-arrow-right"></i></button>
        </form>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <section class="cq-game-card" id="chama">
    <div class="cq-game-section-head">
      <div><div class="cq-game-kicker">Comunhão</div><h2>Proteger a Chama</h2></div>
      <span class="cq-progress-chip">recorde: <?= (int)($streak['longest'] ?? 0) ?> dias</span>
    </div>
    <div class="cq-game-two">
      <div class="cq-subgame">
        <i class="fa-solid fa-dove"></i>
        <h3>Vela de Intercessão</h3>
        <p>Uma vez por semana, envie gratuitamente a um colega. Ela pode proteger um dia perdido da Chama.</p>
        <?php if (($classmates ?? []) !== []): ?>
        <form method="post" action="/studenti/chama/intercessao" class="cq-inline-form">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>">
          <select name="recipient_user_id" required>
            <option value="">Escolha um colega</option>
            <?php foreach ($classmates as $mate): ?><option value="<?= (int)$mate['id_utente'] ?>"><?= $h(trim($mate['nome'].' '.$mate['cognome'])) ?></option><?php endforeach; ?>
          </select>
          <button type="submit" class="cq-game-secondary">Enviar Vela</button>
        </form>
        <?php else: ?><small>Quando houver outros crismandos na turma, eles aparecerão aqui.</small><?php endif; ?>

        <?php foreach (($intercessions ?? []) as $item): ?>
          <div class="cq-received-aid">
            <span><strong><?= $h($item['sender_name']) ?></strong> enviou uma Vela para você.</span>
            <form method="post" action="/studenti/chama/intercessao/<?= (int)$item['id'] ?>/usar">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>"><button type="submit">Usar</button></form>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="cq-subgame">
        <i class="fa-solid fa-hands-praying"></i>
        <h3>Rosário da Jornada</h3>
        <p>Recupera um único dia perdido nas últimas 48 horas. Custa <strong><?= (int)($rosary['cost'] ?? 90) ?> Lúmens</strong> e só pode ser usado uma vez a cada 30 dias.</p>
        <form method="post" action="/studenti/chama/rosario">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>">
          <button type="submit" class="cq-game-secondary" <?= empty($rosary['available']) ? 'disabled' : '' ?>>Usar Rosário da Jornada</button>
        </form>
        <small>Item simbólico do jogo. Lúmens não compram oração, graça ou objeto religioso real: apenas recuperam a sequência digital.</small>
      </div>
    </div>
  </section>

  <section class="cq-game-card" id="baus">
    <div class="cq-game-section-head">
      <div><div class="cq-game-kicker">Marcos de XP</div><h2>Baús disponíveis</h2></div>
    </div>
    <?php if (($chests ?? []) === []): ?>
      <p class="cq-muted">Nenhum baú novo disponível agora. Continue as missões para alcançar o próximo marco.</p>
    <?php else: ?>
      <div class="cq-chest-grid">
      <?php foreach ($chests as $chest): ?>
        <div class="cq-chest">
          <i class="fa-solid fa-box-open"></i>
          <div><strong><?= $h($chest['name']) ?></strong><p><?= $h($chest['description'] ?? '') ?></p><small>liberado em <?= (int)$chest['threshold_xp'] ?> XP</small></div>
          <form method="post" action="/studenti/baus/<?= (int)$chest['id'] ?>/abrir">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>"><button type="submit">Abrir</button></form>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="cq-game-card">
    <div class="cq-game-section-head"><div><div class="cq-game-kicker">Sua história</div><h2>Conquistas</h2></div></div>
    <?php if (($badges ?? []) === []): ?><p class="cq-muted">Sua primeira conquista aparece quando você concluir uma missão.</p>
    <?php else: ?><div class="cq-badge-grid">
      <?php foreach ($badges as $badge): ?><div class="cq-badge"><i class="fa-solid <?= $h($badge['icon']) ?>"></i><strong><?= $h($badge['name']) ?></strong><span><?= $h($badge['description']) ?></span></div><?php endforeach; ?>
    </div><?php endif; ?>
  </section>
</div>
<?php if (!empty($preview)): ?></fieldset><?php endif; ?>
