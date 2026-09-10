<?php
$journey = $crismaquestJourney ?? ['chapters'=>[],'completedSteps'=>0,'totalSteps'=>22,'progressPercent'=>0,'currentChapter'=>null];
$positionClasses = ['cq-ch1','cq-ch2','cq-ch3','cq-ch4','cq-ch5','cq-ch6'];
$iconByState = ['done'=>'fa-check','current'=>'fa-star','locked'=>'fa-lock'];
$current = $journey['currentChapter'] ?? null;
?>
<div class="cq-student-shell cq-journey-page">
    <section class="cq-map-hero" aria-labelledby="journey-title">
        <div class="cq-map-title">
            <div class="cq-kicker"><?= (int)($journey['totalSteps'] ?? 22) ?> etapas · 6 capítulos</div>
            <h1 id="journey-title">Sua Jornada</h1>
            <p>Do chamado à missão: conhecer, celebrar, viver e rezar a fé.</p>
        </div>
        <?php foreach (($journey['chapters'] ?? []) as $index => $chapter):
            $state = (string)($chapter['state'] ?? 'locked');
            $position = $positionClasses[$index] ?? '';
            $href = $state === 'locked' ? '#' : '/studenti/quest';
        ?>
            <a href="<?= $href ?>" class="cq-chapter-node <?= htmlspecialchars($position . ' ' . $state) ?>" <?= $state === 'locked' ? 'aria-disabled="true" onclick="return false"' : '' ?>>
                <span class="num"><i class="fa-solid <?= htmlspecialchars($iconByState[$state] ?? 'fa-lock') ?>"></i></span>
                <span>
                    <b><?= htmlspecialchars((string)$chapter['number'] . '. ' . (string)$chapter['title']) ?></b>
                    <small><?= htmlspecialchars((string)$chapter['subtitle']) ?> · <?= (int)($chapter['completedSteps'] ?? 0) ?>/<?= (int)($chapter['totalSteps'] ?? 0) ?></small>
                </span>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="cq-map-progress">
        <div class="rowline"><strong>Progresso da temporada</strong><span><?= (int)($journey['completedSteps'] ?? 0) ?> de <?= (int)($journey['totalSteps'] ?? 22) ?> etapas</span></div>
        <div class="cq-progress"><span style="width:<?= (int)($journey['progressPercent'] ?? 0) ?>%"></span></div>
        <div class="mt-3 d-flex flex-column flex-sm-row justify-content-between gap-2 align-items-sm-center">
            <div>
                <div class="cq-card-eyebrow">Capítulo atual</div>
                <h3 class="mb-1" style="color:#17313d"><?= htmlspecialchars((string)($current['title'] ?? 'O Chamado')) ?></h3>
                <small style="font-family:Arial,sans-serif;color:#6e6b64"><?= htmlspecialchars(implode(' · ', array_slice((array)($current['steps'] ?? []), 0, 4))) ?></small>
            </div>
            <a href="/studenti/quest" class="cq-primary-btn">Continuar <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </section>

    <section class="cq-card mt-3">
        <div class="cq-card-eyebrow">As 22 etapas</div>
        <div class="row g-3 mt-1">
            <?php $globalStep=0; foreach (($journey['chapters'] ?? []) as $chapter): ?>
                <div class="col-md-6">
                    <h3 style="font-size:17px"><?= htmlspecialchars((string)$chapter['number'] . '. ' . (string)$chapter['title']) ?></h3>
                    <ol style="font-family:Arial,sans-serif;font-size:12px;color:#625d56;padding-left:20px;margin-bottom:0">
                        <?php foreach ((array)$chapter['steps'] as $step): $globalStep++; ?>
                            <li style="margin:5px 0;<?= $globalStep <= (int)($journey['completedSteps'] ?? 0) ? 'color:#2f6d50;font-weight:700' : '' ?>">
                                <?= htmlspecialchars((string)$step) ?><?= $globalStep <= (int)($journey['completedSteps'] ?? 0) ? ' ✓' : '' ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cq-card mt-3">
        <div class="cq-card-eyebrow">Destino da Jornada</div>
        <h3>Pentecostes — Igreja em missão</h3>
        <p class="mb-0">A igreja iluminada no alto do mapa não representa o “fim da fé”. Ela representa o envio: receber o Espírito Santo e seguir a caminhada como testemunha de Cristo.</p>
    </section>
</div>
