<?php
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="cq-teacher-panel">
  <nav class="cq-game-section-head"><a href="/docenti/jogo/previa">Ver como crismando →</a><a href="/docenti/jogo/visual">Prévia do Álbum →</a><a href="/docenti/jogo/jornada">Ver Jornada →</a></nav>
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
      <div class="cq-game-kicker">Motor da temporada</div>
      <h2>Jogo CrismaQuest</h2>
      <p class="mb-0">Controle as missões, acompanhe a adesão e pause a Chama da turma quando necessário.</p>
    </div>
    <div class="text-end">
      <strong><?= (int)($studentCount ?? 0) ?> <?= (int)($studentCount ?? 0) === 1 ? 'crismando' : 'crismandos' ?></strong><br>
      <small>Temporada até 09/02/2027</small>
    </div>
  </div>
</div>

<details class="cq-teacher-panel cq-admin-fold"><summary>Premiar crismandos com carta</summary><div class="cq-admin-fold-body">
  <div class="cq-game-kicker">Reconhecimento da catequese</div>
  <h2>Entregar carta a alguns crismandos</h2>
  <p>Selecione somente quem deve receber. A opção aleatória prioriza uma carta que cada crismando ainda não possui.</p>
  <form method="post" action="/docenti/jogo/premiar-carta">
    <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>">

    <div class="mb-3">
      <strong class="d-block mb-2">1. Selecione os crismandos</strong>
      <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2">
        <?php foreach (($students ?? []) as $s): ?>
          <div class="col">
            <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 bg-white h-100">
              <input class="form-check-input m-0" type="checkbox" name="user_ids[]" value="<?= (int)$s['id_utente'] ?>">
              <span><?= $h(trim($s['nome'].' '.$s['cognome'])) ?></span>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row g-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label"><strong>2. Carta</strong></label>
        <select class="form-select" name="card_choice" required>
          <option value="__random_new__">Aleatória nova para cada um — recomendado</option>
          <?php foreach (($rewardCards ?? []) as $card): ?>
            <option value="<?= $h($card['slug']) ?>"><?= $h($card['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label"><strong>3. Motivo opcional</strong></label>
        <input class="form-control" type="text" name="reason" maxlength="180" placeholder="Ex.: participação no encontro, ajuda na dinâmica">
      </div>
    </div>

    <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
      <button type="submit" class="cq-game-primary">Entregar carta aos selecionados</button>
      <small class="text-muted">A entrega vale apenas para os crismandos marcados.</small>
    </div>
  </form>
</div></details>

<details class="cq-teacher-panel cq-admin-fold"><summary>Criar uma nova missão</summary><div class="cq-admin-fold-body">
  <div class="cq-game-kicker">Criador rápido</div>
  <h2>Nova missão</h2>
  <p>Crie uma missão em menos de dois minutos. Ela pode nascer como rascunho ou já publicada.</p>
  <form method="post" action="/docenti/jogo/missao/nova" class="cq-builder-grid">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>">
    <label>Título<input name="title" maxlength="180" required></label>
    <label>Tipo
      <select name="mission_type" required>
        <option value="palavra">Palavra Viva</option>
        <option value="quiz">Entenda a Fé</option>
        <option value="reflexao">Desafio da Fé</option>
        <option value="acao">Evangelho em Ação</option>
        <option value="igreja">Igreja por Dentro</option>
        <option value="testemunhas">Testemunhas</option>
        <option value="grande">Grande Missão</option>
        <option value="especial">Especial</option>
      </select>
    </label>
    <label>Capítulo<input type="number" name="chapter_no" min="1" max="6" value="1" required></label>
    <label>Etapa opcional<input type="number" name="step_no" min="1" max="22"></label>
    <label class="cq-builder-wide">Conteúdo<textarea name="body" rows="3" required></textarea></label>
    <label class="cq-builder-wide">Pergunta do quiz <small>deixe vazio se não for quiz</small><input name="question"></label>
    <label class="cq-builder-wide">Opções do quiz <small>separe por |</small><input name="options" placeholder="A - opção | B - opção | C - opção"></label>
    <label>Resposta correta<input name="correct_answer" maxlength="1" placeholder="A"></label>
    <label>Feedback<input name="feedback" placeholder="Explicação após erro"></label>
    <label>XP<input type="number" name="xp_reward" min="0" max="50" value="10" required></label>
    <label>Lúmens<input type="number" name="lumen_reward" min="0" max="20" value="3" required></label>
    <label>Bônus do quiz<input type="number" name="bonus_xp_correct" min="0" max="10" value="5"></label>
    <label>Abre em<input type="date" name="available_from" value="2026-09-10" required></label>
    <label>Fecha em<input type="date" name="available_until" value="2027-02-09" required></label>
    <label class="cq-builder-check"><input type="checkbox" name="active" value="1"> Publicar agora</label>
    <button type="submit" class="cq-game-primary">Criar missão</button>
  </form>
</div></details>

<details class="cq-teacher-panel cq-admin-fold"><summary>Pausas da Chama</summary><div class="cq-admin-fold-body">
  <h2>Pausa da Chama</h2>
  <p>Use para recesso, retiro, problema técnico ou situação pastoral. A pausa preserva a sequência e não concede XP.</p>
  <form method="post" action="/docenti/jogo/pausa" class="cq-teacher-pause">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>">
    <input type="date" name="start_date" required>
    <input type="date" name="end_date" required>
    <input type="text" name="reason" maxlength="255" placeholder="Motivo opcional">
    <button type="submit">Criar pausa</button>
  </form>

  <hr>
  <h3 style="font-family:Georgia,serif">Pausa pastoral individual</h3>
  <p>Preserva a Chama de um crismando sem expor o motivo aos colegas.</p>
  <form method="post" action="/docenti/jogo/pausa-crismando" class="cq-teacher-pause">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>">
    <select name="user_id" required>
      <option value="">Escolha o crismando</option>
      <?php foreach (($students ?? []) as $s): ?><option value="<?= (int)$s['id_utente'] ?>"><?= $h(trim($s['nome'].' '.$s['cognome'])) ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="start_date" required>
    <input type="date" name="end_date" required>
    <input type="text" name="reason" maxlength="255" placeholder="Motivo pastoral opcional">
    <button type="submit">Aplicar pausa</button>
  </form>

  <?php if (($pauses ?? []) !== []): ?>
    <div class="mt-3 small">
      <?php foreach ($pauses as $pause): ?>
        <div><strong><?= $h($pause['start_date']) ?> → <?= $h($pause['end_date']) ?></strong><?= !empty($pause['reason']) ? ' · '.$h($pause['reason']) : '' ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div></details>

<div class="cq-teacher-panel">
  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
    <div><div class="cq-game-kicker">Programação completa</div><h2><?= count($missions ?? []) ?> missões da temporada</h2></div>
    <small>Duplicatas são criadas desativadas para edição segura.</small>
  </div>
  <div class="cq-admin-search">
    <label for="cq-mission-search">Buscar nas missões<input type="search" id="cq-mission-search" placeholder="Título, tema ou tipo de missão" aria-controls="cq-mission-list"></label>
    <span id="cq-mission-count" aria-live="polite"><?= count($missions ?? []) ?> missões carregadas</span>
  </div>
  <div class="table-responsive">
    <table class="cq-teacher-game-table" id="cq-mission-list">
      <thead><tr><th>Cap.</th><th>Etapa</th><th>Missão</th><th>Tipo</th><th>Recompensa</th><th>Abre</th><th>Concl.</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody>
      <?php foreach (($missions ?? []) as $mission): ?>
        <tr>
          <td><?= (int)$mission['chapter_no'] ?></td>
          <td><?= $mission['step_no'] !== null ? (int)$mission['step_no'] : '—' ?></td>
          <td><strong><?= $h($mission['title']) ?></strong><br><small><?= $h($mission['slug']) ?></small></td>
          <td><?= $h($mission['mission_type']) ?></td>
          <td><?= (int)$mission['xp_reward'] ?> XP · <?= (int)$mission['lumen_reward'] ?> L<?= (int)$mission['bonus_xp_correct']>0 ? ' · +'.(int)$mission['bonus_xp_correct'].' acerto' : '' ?></td>
          <td><?= $h($mission['available_from']) ?></td>
          <td><?= (int)$mission['completion_count'] ?></td>
          <td><?= (int)$mission['active']===1 ? '<span class="text-success">ativa</span>' : '<span class="text-secondary">pausada</span>' ?></td>
          <td>
            <div class="cq-teacher-actions">
              <form method="post" action="/docenti/jogo/missao/<?= (int)$mission['id'] ?>/toggle">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>"><button type="submit"><?= (int)$mission['active']===1 ? 'Pausar' : 'Ativar' ?></button></form>
              <form method="post" action="/docenti/jogo/missao/<?= (int)$mission['id'] ?>/duplicar">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>"><button type="submit">Duplicar</button></form>
            </div>
            <details class="cq-edit-mission">
              <summary>Editar</summary>
              <form method="post" action="/docenti/jogo/missao/<?= (int)$mission['id'] ?>/atualizar" class="cq-edit-grid">
          <input type="hidden" name="csrf_token" value="<?= \App\Service\CrismaQuestGameAccess::token() ?>">
                <label class="cq-builder-wide">Título<input name="title" maxlength="180" value="<?= $h($mission['title']) ?>" required></label>
                <label class="cq-builder-wide">Tipo<select name="mission_type">
                  <?php foreach (['palavra'=>'Palavra Viva','quiz'=>'Entenda a Fé','reflexao'=>'Desafio da Fé','acao'=>'Evangelho em Ação','igreja'=>'Igreja por Dentro','testemunhas'=>'Testemunhas','grande'=>'Grande Missão','especial'=>'Especial'] as $t=>$label): ?><option value="<?= $h($t) ?>" <?= $mission['mission_type']===$t?'selected':'' ?>><?= $h($label) ?></option><?php endforeach; ?>
                </select></label>
                <label>Capítulo<input type="number" name="chapter_no" min="1" max="6" value="<?= (int)$mission['chapter_no'] ?>" required></label>
                <label>Etapa opcional<input type="number" name="step_no" min="1" max="22" value="<?= $mission['step_no']!==null?(int)$mission['step_no']:'' ?>"></label>
                <label class="cq-builder-wide">Conteúdo<textarea name="body" rows="4" required><?= $h($mission['body']) ?></textarea></label>
                <label class="cq-builder-wide">Pergunta do quiz<input name="question" value="<?= $h($mission['question'] ?? '') ?>" placeholder="Deixe vazio se não for quiz"></label>
                <label class="cq-builder-wide">Opções do quiz<input name="options" value="<?= $h(is_string($mission['options_json'] ?? null) ? implode(' | ', json_decode($mission['options_json'], true) ?: []) : '') ?>" placeholder="Separe as opções por |"></label>
                <label>Resposta correta<input name="correct_answer" maxlength="1" value="<?= $h($mission['correct_answer'] ?? '') ?>" placeholder="A"></label>
                <label>Explicação após erro<input name="feedback" value="<?= $h($mission['feedback'] ?? '') ?>"></label>
                <label>XP<input type="number" name="xp_reward" min="0" max="50" value="<?= (int)$mission['xp_reward'] ?>" required></label>
                <label>Lúmens<input type="number" name="lumen_reward" min="0" max="20" value="<?= (int)$mission['lumen_reward'] ?>" required></label>
                <label>Bônus do quiz (XP)<input type="number" name="bonus_xp_correct" min="0" max="10" value="<?= (int)$mission['bonus_xp_correct'] ?>"></label>
                <label>Abre em<input type="date" name="available_from" value="<?= $h($mission['available_from']) ?>" required></label>
                <label>Fecha em<input type="date" name="available_until" value="<?= $h($mission['available_until']) ?>" required></label>
                <label class="cq-builder-check"><input type="checkbox" name="active" value="1" <?= (int)$mission['active']===1?'checked':'' ?>> Missão ativa</label>
                <button type="submit" class="cq-game-primary cq-builder-wide">Salvar</button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="cq-teacher-panel">
  <div class="cq-game-kicker">Progressão canônica</div>
  <h2>Níveis</h2>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach (($levels ?? []) as $level): ?>
      <span class="cq-progress-chip">N<?= (int)$level['level_no'] ?> · <?= $h($level['name']) ?> · <?= (int)$level['xp_min'] ?> XP</span>
    <?php endforeach; ?>
  </div>
</div>
