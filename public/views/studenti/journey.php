<?php
$h = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$journey = $crismaquestJourney ?? ['chapters'=>[], 'steps'=>[], 'completedSteps'=>0,'totalSteps'=>22,'progressPercent'=>0,'currentChapter'=>null];
if (!empty($preview)) {
    foreach ($journey['steps'] as &$item) $item['url']='/docenti/jogo/previa';
    unset($item);
    foreach ($journey['chapters'] as &$chapter) {
        foreach ($chapter['items'] as &$item) $item['url']='/docenti/jogo/previa';
        unset($item);
    }
    unset($chapter);
}
$current = $journey['currentChapter'] ?? null;
$next = null;
foreach (($journey['steps'] ?? []) as $item) if ($item['state'] === 'current') { $next=$item; break; }
?>
<link rel="stylesheet" href="/css/crismaquest-journey-visuals.css?v=20260910c">
<div class="cq-student-shell cq-journey-page">
  <section class="cq-valley-hero" aria-labelledby="journey-title">
    <img src="/assets/crismaquest/art/journey-valley-v2.webp" width="1672" height="941" alt="Vale ilustrado da Jornada: a capela, a Palavra, a comunidade e a Igreja em missão.">
    <div class="cq-valley-title"><span>22 ETAPAS · 6 CAPÍTULOS</span><h1 id="journey-title">Sua Jornada</h1><p>Do chamado ao envio. Um passo de fé por vez.</p></div>
  </section>
  <section class="cq-map-progress cq-progress-panel">
    <div class="rowline"><strong>Temporada 2026–2027</strong><span><?= (int)$journey['completedSteps'] ?> de 22 etapas</span></div>
    <div class="cq-progress" role="progressbar" aria-label="Progresso da Jornada" aria-valuenow="<?= (int)$journey['completedSteps'] ?>" aria-valuemin="0" aria-valuemax="22"><span style="width:<?= (int)$journey['progressPercent'] ?>%"></span></div>
    <div class="cq-progress-current"><div><div class="cq-card-eyebrow">Seu próximo passo</div><h2><?= $h($next['title'] ?? 'Continue sua caminhada') ?></h2><small><?= $h($next['subtitle'] ?? 'Confira as missões e os próximos encontros.') ?></small></div><a href="<?= $h($next['url'] ?? '/studenti/missoes') ?>" class="cq-primary-btn">Continuar →</a></div>
  </section>
  <nav class="cq-journey-chapter-nav" aria-label="Capítulos da Jornada">
    <?php foreach ($journey['chapters'] as $chapter): ?><a href="#capitulo-<?= (int)$chapter['number'] ?>"><?= (int)$chapter['number'] ?>. <?= $h($chapter['title']) ?></a><?php endforeach; ?>
  </nav>
  <div class="cq-canonical-chapters">
    <?php foreach ($journey['chapters'] as $chapter): ?>
    <section class="cq-canonical-chapter" id="capitulo-<?= (int)$chapter['number'] ?>">
      <header><span class="cq-chapter-index"><?= (int)$chapter['number'] ?></span><div><div class="cq-card-eyebrow">Capítulo <?= (int)$chapter['number'] ?></div><h2><?= $h($chapter['title']) ?></h2><p><?= $h($chapter['subtitle']) ?></p></div><span class="cq-chapter-total"><?= (int)$chapter['completedSteps'] ?>/<?= (int)$chapter['totalSteps'] ?></span></header>
      <ol class="cq-real-steps">
        <?php foreach ($chapter['items'] as $item): $locked=$item['state']==='locked'; ?>
        <li class="cq-real-step <?= $h($item['state']) ?>">
          <?php if (!$locked): ?><a href="<?= $h($item['url']) ?>"><?php else: ?><div aria-disabled="true"><?php endif; ?>
            <span class="cq-step-number"><?= $item['state']==='done' ? '✓' : (int)$item['step_no'] ?></span>
            <span class="cq-step-description"><strong><?= $h($item['title']) ?></strong><small><?= $item['state']==='done' ? 'Concluída' : ($locked ? 'Abre em '.date('d/m',strtotime($item['opens_at'])) : 'Disponível · '.(int)($item['completed_count'] ?? 0).'/'.(int)($item['mission_count'] ?? 2).' missões') ?></small></span>
            <span aria-hidden="true"><?= $locked ? '◇' : '→' ?></span>
          <?php if (!$locked): ?></a><?php else: ?></div><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ol>
    </section>
    <?php endforeach; ?>
  </div>
  <section class="cq-card mt-3 cq-destination-card"><div class="cq-card-eyebrow">Igreja em missão</div><h2>Enviados para testemunhar</h2><p>Conhecer, celebrar, viver e rezar a fé. A temporada segue até 9 de fevereiro de 2027, às portas da Quaresma.</p></section>
</div>
