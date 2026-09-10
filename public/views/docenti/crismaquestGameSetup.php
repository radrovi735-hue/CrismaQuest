<?php
$ready = (bool)($status['ready'] ?? false) && (bool)($status['enabled'] ?? false);
$counts = $status['counts'] ?? [];
?>
<div class="cq-game-shell">
  <section class="cq-game-card">
    <div class="cq-game-kicker">Temporada 2026–2027</div>
    <h1 style="font-family:Georgia,serif;color:#173541">Preparar a Jornada</h1>
    <p>Carregue as 22 etapas, 56 missões, 60 Centelhas e recompensas. A Jornada será ativada ao concluir a validação.</p>

    <div class="cq-game-stats" style="color:#173541;min-width:0;max-width:680px">
      <div><strong id="cq-setup-tables"><?= (int)($status['schemaTables'] ?? 0) ?>/<?= (int)($status['schemaTablesExpected'] ?? 15) ?></strong><span>tabelas novas</span></div>
      <div><strong id="cq-setup-steps"><?= (int)($counts['steps'] ?? 0) ?>/22</strong><span>etapas</span></div>
      <div><strong id="cq-setup-missions"><?= (int)($counts['missions'] ?? 0) ?>/56</strong><span>missões</span></div>
      <div><strong id="cq-setup-sparks"><?= (int)($counts['sparks'] ?? 0) ?>/60</strong><span>Centelhas</span></div>
    </div>

    <div id="cq-setup-status" class="<?= $ready ? 'cq-done' : 'cq-progress-chip' ?>" style="margin-top:18px">
      <?= $ready ? 'Gameplay instalado e validado.' : 'Pronto para instalar com segurança.' ?>
    </div>

    <div style="height:12px;background:#eee3cf;border-radius:999px;overflow:hidden;margin:16px 0;max-width:720px">
      <div id="cq-setup-bar" style="height:100%;width:<?= $ready ? '100' : '0' ?>%;background:#8b2433;transition:width .2s"></div>
    </div>

    <?php if (!$ready): ?>
      <button id="cq-setup-start" class="cq-game-primary" type="button">Instalar e ativar a Jornada</button>
    <?php endif; ?>
    <a id="cq-setup-open" class="cq-game-primary" href="/docenti/jogo" <?= !$ready ? 'hidden' : '' ?>>Abrir missões da temporada</a>
    <pre id="cq-setup-log" style="margin-top:16px;white-space:pre-wrap;font-size:.78rem"></pre>
  </section>
</div>
<script>
(() => {
  const btn = document.getElementById('cq-setup-start');
  if (!btn) return;
  const lastStep = <?= (int)$lastStep ?>;
  const csrf = <?= json_encode(\App\Service\CrismaQuestGameAccess::token()) ?>;
  let nextStep = <?= max(1, min(11, (int)($status['phase'] ?? 0) + 1)) ?>;
  const bar = document.getElementById('cq-setup-bar');
  const status = document.getElementById('cq-setup-status');
  const log = document.getElementById('cq-setup-log');

  const paint = (data) => {
    const s = data.status || {};
    const c = s.counts || {};
    document.getElementById('cq-setup-tables').textContent = (s.schemaTables || 0) + '/' + (s.schemaTablesExpected || 15);
    document.getElementById('cq-setup-steps').textContent = (c.steps || 0) + '/22';
    document.getElementById('cq-setup-missions').textContent = (c.missions || 0) + '/56';
    document.getElementById('cq-setup-sparks').textContent = (c.sparks || 0) + '/60';
  };

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    for (let step=nextStep; step<=lastStep; step++) {
      status.textContent = 'Instalando etapa ' + step + ' de ' + lastStep + '…';
      bar.style.width = Math.round(((step-1)/lastStep)*100) + '%';
      try {
        const response = await fetch('/docenti/jogo/setup/' + step, {
          method:'POST',
          headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':csrf},
          credentials:'same-origin'
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'Falha na etapa ' + step);
        paint(data);
        nextStep = step + 1;
        log.textContent += '✓ etapa ' + step + ' concluída\n';
      } catch (e) {
        status.textContent = 'Instalação interrompida com segurança.';
        log.textContent += '✗ ' + e.message + '\nClique novamente para retomar; as etapas são idempotentes.';
        btn.disabled = false;
        return;
      }
    }
    bar.style.width = '100%';
    status.className = 'cq-done';
    status.textContent = 'Gameplay instalado e validado. 22 etapas · 56 missões · 60 Centelhas.';
    btn.remove();
    document.getElementById('cq-setup-open').hidden = false;
  });
})();
</script>
