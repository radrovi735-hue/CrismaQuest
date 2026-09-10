<?php
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="cq-teacher-panel">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
      <div class="cq-game-kicker">Motor da temporada</div>
      <h2>Jogo CrismaQuest</h2>
      <p class="mb-0">Controle as missões, acompanhe a adesão e pause a Chama da turma quando necessário.</p>
    </div>
    <div class="text-end">
      <strong><?= (int)($studentCount ?? 0) ?> crismandos</strong><br>
      <small>Temporada até 09/02/2027</small>
    </div>
  </div>
</div>

<div class="cq-teacher-panel">
  <h2>Pausa da Chama</h2>
  <p>Use para recesso, retiro, problema técnico ou situação pastoral. A pausa preserva a sequência e não concede XP.</p>
  <form method="post" action="/docenti/jogo/pausa" class="cq-teacher-pause">
    <input type="date" name="start_date" required>
    <input type="date" name="end_date" required>
    <input type="text" name="reason" maxlength="255" placeholder="Motivo opcional">
    <button type="submit">Criar pausa</button>
  </form>
  <?php if (($pauses ?? []) !== []): ?>
    <div class="mt-3 small">
      <?php foreach ($pauses as $pause): ?>
        <div><strong><?= $h($pause['start_date']) ?> → <?= $h($pause['end_date']) ?></strong><?= !empty($pause['reason']) ? ' · '.$h($pause['reason']) : '' ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="cq-teacher-panel">
  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
    <div><div class="cq-game-kicker">Programação completa</div><h2>56 missões da temporada</h2></div>
    <small>Duplicatas são criadas desativadas para edição segura.</small>
  </div>
  <div class="table-responsive">
    <table class="cq-teacher-game-table">
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
              <form method="post" action="/docenti/jogo/missao/<?= (int)$mission['id'] ?>/toggle"><button type="submit"><?= (int)$mission['active']===1 ? 'Pausar' : 'Ativar' ?></button></form>
              <form method="post" action="/docenti/jogo/missao/<?= (int)$mission['id'] ?>/duplicar"><button type="submit">Duplicar</button></form>
            </div>
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