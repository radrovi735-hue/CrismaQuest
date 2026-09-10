<?php
$journey = $crismaquestJourney ?? [
    'chapters'=>[],
    'completedSteps'=>0,
    'totalSteps'=>22,
    'progressPercent'=>0,
    'currentChapter'=>null,
];
$chapterCovers = [
    '/assets/crismaquest/chapters/chapter-1.svg',
    '/assets/crismaquest/chapters/chapter-2.svg',
    '/assets/crismaquest/chapters/chapter-3.svg',
    '/assets/crismaquest/chapters/chapter-4.svg',
    '/assets/crismaquest/chapters/chapter-5.svg',
    '/assets/crismaquest/chapters/chapter-6.svg',
];
$current = $journey['currentChapter'] ?? null;
?>
<link rel="stylesheet" href="/css/crismaquest-journey-visuals.css?v=20260910b">
<div class="cq-student-shell cq-journey-page">
    <section class="cq-journey-art-shell" aria-labelledby="journey-title">
        <h1 id="journey-title" class="visually-hidden">Sua Jornada</h1>
        <img
            class="cq-journey-map-final"
            src="/assets/crismaquest/journey-map-v3.svg?v=20260910b"
            alt="Mapa da Jornada da Crisma com caminho contínuo de 22 etapas, seis capítulos e destino em Pentecostes — Igreja em missão."
        >
    </section>

    <section class="cq-map-progress cq-progress-panel">
        <div class="rowline">
            <strong>Progresso da temporada</strong>
            <span><?= (int)($journey['completedSteps'] ?? 0) ?> de <?= (int)($journey['totalSteps'] ?? 22) ?> etapas</span>
        </div>
        <div class="cq-progress"><span style="width:<?= max(0,min(100,(int)($journey['progressPercent'] ?? 0))) ?>%"></span></div>
        <div class="cq-progress-current">
            <div>
                <div class="cq-card-eyebrow">Capítulo atual</div>
                <h3><?= htmlspecialchars((string)($current['title'] ?? 'O Chamado')) ?></h3>
                <small><?= htmlspecialchars(implode(' · ', array_slice((array)($current['steps'] ?? []), 0, 4))) ?></small>
            </div>
            <a href="/studenti/quest" class="cq-primary-btn">Continuar <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </section>

    <section class="cq-card mt-3">
        <div class="cq-card-eyebrow">Os 6 capítulos</div>
        <h2 class="cq-journey-section-title">Da descoberta ao envio</h2>
        <div class="cq-chapter-grid">
            <?php foreach (($journey['chapters'] ?? []) as $index=>$chapter): ?>
                <article class="cq-chapter-summary">
                    <img class="cq-chapter-cover" src="<?= htmlspecialchars($chapterCovers[$index] ?? $chapterCovers[0]) ?>" alt="" loading="lazy">
                    <div class="cq-chapter-summary-body">
                        <div class="cq-chapter-number">Capítulo <?= (int)($chapter['number'] ?? ($index+1)) ?></div>
                        <h3><?= htmlspecialchars((string)$chapter['title']) ?></h3>
                        <p><?= htmlspecialchars((string)$chapter['subtitle']) ?></p>
                        <div class="cq-chapter-meter"><span><?= (int)($chapter['completedSteps'] ?? 0) ?>/<?= (int)($chapter['totalSteps'] ?? 0) ?> etapas</span></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cq-card mt-3">
        <div class="cq-card-eyebrow">As 22 etapas</div>
        <div class="row g-3 mt-1">
            <?php $globalStep=0; foreach (($journey['chapters'] ?? []) as $chapter): ?>
                <div class="col-md-6">
                    <h3 class="cq-step-group-title"><?= htmlspecialchars((string)$chapter['number'].'. '.(string)$chapter['title']) ?></h3>
                    <ol class="cq-step-list">
                        <?php foreach ((array)$chapter['steps'] as $step): $globalStep++; ?>
                            <li class="<?= $globalStep <= (int)($journey['completedSteps'] ?? 0) ? 'done' : '' ?>">
                                <?= htmlspecialchars((string)$step) ?><?= $globalStep <= (int)($journey['completedSteps'] ?? 0) ? ' ✓' : '' ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cq-card mt-3 cq-destination-card">
        <div class="cq-card-eyebrow">Destino da Jornada</div>
        <h3>Pentecostes — Igreja em missão</h3>
        <p class="mb-0">O destino visual representa o envio: receber o Espírito Santo e seguir a caminhada como testemunha de Cristo.</p>
    </section>
</div>
