<?php
$chapters = [
    ['n'=>1,'title'=>'O Chamado','subtitle'=>'Deus fala e nós respondemos','class'=>'cq-ch1 done','icon'=>'fa-check'],
    ['n'=>2,'title'=>'Quem é Jesus?','subtitle'=>'O centro da nossa fé','class'=>'cq-ch2 current','icon'=>'fa-star'],
    ['n'=>3,'title'=>'A Igreja','subtitle'=>'Um povo reunido e enviado','class'=>'cq-ch3 locked','icon'=>'fa-lock'],
    ['n'=>4,'title'=>'Os Sacramentos','subtitle'=>'Sinais da graça no caminho','class'=>'cq-ch4 locked','icon'=>'fa-lock'],
    ['n'=>5,'title'=>'Vida em Cristo','subtitle'=>'Liberdade, verdade e caridade','class'=>'cq-ch5 locked','icon'=>'fa-lock'],
    ['n'=>6,'title'=>'Oração e Missão','subtitle'=>'Com o Espírito, enviados','class'=>'cq-ch6 locked','icon'=>'fa-lock'],
];
?>
<div class="cq-student-shell cq-journey-page">
    <section class="cq-map-hero" aria-labelledby="journey-title">
        <div class="cq-map-title">
            <div class="cq-kicker">22 etapas · 6 capítulos</div>
            <h1 id="journey-title">Sua Jornada</h1>
            <p>Do chamado à missão: conhecer, celebrar, viver e rezar a fé.</p>
        </div>
        <?php foreach ($chapters as $chapter): ?>
            <a href="<?= $chapter['n'] <= 2 ? '/studenti/quest' : '#' ?>" class="cq-chapter-node <?= htmlspecialchars($chapter['class']) ?>" <?= $chapter['n'] > 2 ? 'aria-disabled="true"' : '' ?>>
                <span class="num"><i class="fa-solid <?= htmlspecialchars($chapter['icon']) ?>"></i></span>
                <span><b><?= htmlspecialchars($chapter['n'] . '. ' . $chapter['title']) ?></b><small><?= htmlspecialchars($chapter['subtitle']) ?></small></span>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="cq-map-progress">
        <div class="rowline"><strong>Progresso da temporada</strong><span>3 de 22 etapas</span></div>
        <div class="cq-progress"><span style="width:14%"></span></div>
        <div class="mt-3 d-flex flex-column flex-sm-row justify-content-between gap-2 align-items-sm-center">
            <div>
                <div class="cq-card-eyebrow">Capítulo atual</div>
                <h3 class="mb-0" style="color:#17313d">Quem é Jesus?</h3>
                <small style="font-family:Arial,sans-serif;color:#6e6b64">Encarnação · Evangelho · Páscoa · Espírito Santo</small>
            </div>
            <a href="/studenti/quest" class="cq-primary-btn">Continuar <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </section>

    <section class="cq-card mt-3">
        <div class="cq-card-eyebrow">Destino da Jornada</div>
        <h3>Pentecostes — Igreja em missão</h3>
        <p class="mb-0">A igreja iluminada no alto do mapa não representa o “fim da fé”. Ela representa o envio: receber o Espírito Santo e seguir a caminhada como testemunha de Cristo.</p>
    </section>
</div>
