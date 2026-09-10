<?php

$classroom = $classroom ?? null;
$student = $student ?? null;
$availableCharacters = $availableCharacters ?? [];
$hero = $hero ?? null;
$crismaquestStreak = $crismaquestStreak ?? ['current'=>0,'longest'=>0,'lastDate'=>null,'freezes'=>0];
$journey = $crismaquestJourney ?? ['currentChapter'=>null,'completedSteps'=>0,'totalSteps'=>22,'progressPercent'=>0];
$home = $crismaquestHome ?? [];
$mission = $home['mission'] ?? null;
$meeting = $home['meeting'] ?? null;
$featuredCard = $home['featuredCard'] ?? null;
$currentChapter = is_array($journey['currentChapter'] ?? null) ? $journey['currentChapter'] : null;

$saintAvatars = [
    ['name'=>'São Carlo Acutis','icon'=>'fa-laptop','text'=>'Jovem testemunha de amor à Eucaristia e de evangelização no mundo digital.'],
    ['name'=>'Santa Teresinha do Menino Jesus','icon'=>'fa-seedling','text'=>'Recorda que a santidade também passa pelas pequenas coisas feitas com grande amor.'],
    ['name'=>'São Francisco de Assis','icon'=>'fa-dove','text'=>'Inspira simplicidade, fraternidade, cuidado com a criação e alegria no seguimento de Cristo.'],
    ['name'=>'São Pedro','icon'=>'fa-key','text'=>'Discípulo chamado por Jesus a amadurecer na fé e servir à Igreja com coragem.'],
    ['name'=>'Santa Faustina Kowalska','icon'=>'fa-heart','text'=>'Testemunha da misericórdia de Deus e do chamado a confiar em Jesus.'],
    ['name'=>'São João Paulo II','icon'=>'fa-globe','text'=>'Convidou os jovens a não terem medo de seguir Cristo e assumir sua missão no mundo.'],
    ['name'=>'Santa Gianna Beretta Molla','icon'=>'fa-hand-holding-heart','text'=>'Testemunha de vocação, serviço, responsabilidade e amor concreto ao próximo.'],
    ['name'=>'Santo Agostinho','icon'=>'fa-book-open','text'=>'Sua busca pela verdade recorda que fé, razão e conversão caminham juntas.'],
    ['name'=>'Santa Mônica','icon'=>'fa-hands-praying','text'=>'Exemplo de perseverança na oração, esperança e cuidado com a família.'],
    ['name'=>'São José','icon'=>'fa-hammer','text'=>'Modelo de fidelidade, trabalho, silêncio e disponibilidade ao projeto de Deus.'],
    ['name'=>'São Vicente de Paulo','icon'=>'fa-hands-holding-child','text'=>'Mostra como a fé se torna caridade concreta e serviço aos mais vulneráveis.'],
    ['name'=>'São Sebastião','icon'=>'fa-shield-heart','text'=>'Recorda a coragem de permanecer fiel a Cristo mesmo diante das dificuldades.'],
];
$saintForCharacter = static function (int $characterId) use ($saintAvatars): array {
    $count = count($saintAvatars);
    $index = $count > 0 ? abs($characterId) % $count : 0;
    return $saintAvatars[$index] ?? ['name'=>'Peregrino','icon'=>'fa-cross','text'=>'Um sinal de caminhada, participação e missão.'];
};
$selectedSaint = is_array($student) && (int)($student['fk_personaggio'] ?? 0) > 0
    ? $saintForCharacter((int)$student['fk_personaggio'])
    : null;

$displayName = is_array($hero) && !empty($hero['playerName'])
    ? (string) $hero['playerName']
    : (is_array($student) ? (string) ($student['username'] ?? 'Peregrino') : 'Peregrino');
$firstName = trim(explode(' ', $displayName)[0] ?? 'Peregrino');
$level = is_array($hero) ? (int) ($hero['level'] ?? 1) : 1;
$xpPercent = is_array($hero) ? max(0, min(100, (int) ($hero['xpPercent'] ?? 0))) : 0;
$xpLabel = is_array($hero) ? (string) ($hero['xpLabel'] ?? '0 XP') : '0 XP';
$coins = is_array($hero) ? (int) ($hero['coins'] ?? 0) : 0;
$streakDays = max(0, (int)($crismaquestStreak['current'] ?? 0));
$levelNames = [1=>'Peregrino',2=>'Caminhante',3=>'Discípulo',4=>'Servidor',5=>'Mensageiro',6=>'Missionário',7=>'Testemunha',8=>'Enviado'];
$levelTitle = $levelNames[min(8, max(1, $level))] ?? 'Peregrino';
$h = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$meetingDate = null;
if (is_array($meeting) && !empty($meeting['meeting_at'])) {
    try {
        $dt = new DateTimeImmutable((string)$meeting['meeting_at']);
        $months = [1=>'JAN',2=>'FEV',3=>'MAR',4=>'ABR',5=>'MAI',6=>'JUN',7=>'JUL',8=>'AGO',9=>'SET',10=>'OUT',11=>'NOV',12=>'DEZ'];
        $weekdays = [0=>'DOM',1=>'SEG',2=>'TER',3=>'QUA',4=>'QUI',5=>'SEX',6=>'SÁB'];
        $meetingDate = [
            'weekday' => $weekdays[(int)$dt->format('w')] ?? '',
            'day' => $dt->format('d'),
            'month' => $months[(int)$dt->format('n')] ?? '',
            'time' => $dt->format('H:i'),
        ];
    } catch (Throwable) {}
}
?>
<div class="cq-student-shell">
    <?php if (is_array($student) && (int) ($student['fk_personaggio'] ?? 0) === 0): ?>
        <section class="cq-card mb-3">
            <div class="cq-card-eyebrow">Primeiro passo</div>
            <h2>Escolha seu santo inspirador</h2>
            <p>Ele será a referência visual do seu perfil durante a Jornada. A escolha não mede fé, santidade ou desempenho.</p>
        </section>
        <div class="row g-3">
            <?php foreach ($availableCharacters as $character): ?>
                <?php $saint = $saintForCharacter((int)$character['id_personaggio']); ?>
                <div class="col-md-6">
                    <div class="cq-card h-100">
                        <form method="post" action="/studenti/classe/personaggio" class="m-0">
                            <input type="hidden" name="character_id" value="<?= (int) $character['id_personaggio'] ?>">
                            <div class="d-flex gap-3 align-items-center">
                                <div class="cq-avatar" aria-hidden="true" style="display:flex;align-items:center;justify-content:center;font-size:1.65rem;">
                                    <i class="fa-solid <?= $h($saint['icon']) ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="cq-card-eyebrow">Avatar da Jornada</div>
                                    <h3><?= $h($saint['name']) ?></h3>
                                    <p class="mb-2"><?= $h($saint['text']) ?></p>
                                    <button class="cq-primary-btn" type="submit">Escolher <i class="fa-solid fa-arrow-right"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <section class="cq-hero-banner" aria-labelledby="cq-greeting">
            <div class="cq-hero-content">
                <div class="cq-avatar" aria-hidden="true" style="display:flex;align-items:center;justify-content:center;font-size:1.65rem;">
                    <i class="fa-solid <?= $h($selectedSaint['icon'] ?? 'fa-cross') ?>"></i>
                </div>
                <div>
                    <div class="cq-kicker">Sua caminhada hoje</div>
                    <h1 class="cq-greeting" id="cq-greeting">Olá, <strong><?= $h($firstName) ?></strong></h1>
                    <?php if ($selectedSaint): ?><p class="cq-motto mb-1">Avatar: <strong><?= $h($selectedSaint['name']) ?></strong></p><?php endif; ?>
                    <p class="cq-motto">Uma jornada de participação, descoberta e comunidade.</p>
                </div>
            </div>

            <div class="cq-stats">
                <div class="cq-stat"><i class="fa-solid fa-star"></i><div><strong><?= $h($xpLabel) ?></strong><span>Experiência</span></div></div>
                <div class="cq-stat"><i class="fa-solid fa-sun"></i><div><strong><?= $coins ?></strong><span>Lúmens</span></div></div>
                <div class="cq-stat"><i class="fa-solid fa-fire-flame-curved"></i><div><strong><?= $streakDays ?> <?= $streakDays === 1 ? 'dia' : 'dias' ?></strong><span>Chama</span></div></div>
            </div>

            <div class="cq-level-row">
                <div class="cq-level-badge"><div><small>Nível</small><b><?= $level ?></b></div></div>
                <div>
                    <div class="cq-level-title"><strong><?= $h($levelTitle) ?></strong><span><?= $xpPercent ?>%</span></div>
                    <div class="cq-progress" aria-label="Progresso do nível"><span style="width:<?= $xpPercent ?>%"></span></div>
                </div>
            </div>
        </section>

        <div class="cq-grid">
            <section class="cq-card cq-mission-card">
                <div class="cq-card-head">
                    <div class="cq-card-eyebrow"><i class="fa-solid fa-book-bible me-1"></i> Próxima missão</div>
                </div>
                <?php if (is_array($mission)): ?>
                    <h2><?= $h($mission['title'] ?? 'Missão') ?></h2>
                    <?php if (!empty($mission['chapter_title'])): ?><p><?= $h($mission['chapter_title']) ?></p><?php endif; ?>
                    <div class="cq-rewards"><span class="cq-chip"><i class="fa-solid fa-compass"></i> Missão publicada</span></div>
                    <a href="<?= $h($mission['url']) ?>" class="cq-primary-btn">Continuar missão <i class="fa-solid fa-arrow-right"></i></a>
                <?php else: ?>
                    <h2>Tudo em dia</h2>
                    <p>Não há uma missão nova publicada para você neste momento.</p>
                    <div class="cq-rewards"><span class="cq-chip"><i class="fa-solid fa-circle-check"></i> Volte quando uma nova missão for liberada</span></div>
                <?php endif; ?>
            </section>

            <section class="cq-card cq-journey-preview">
                <div class="cq-card-eyebrow" style="color:#ead39a">Sua Jornada</div>
                <?php if ($currentChapter): ?>
                    <h3>Capítulo <?= (int)($currentChapter['number'] ?? 1) ?> — <?= $h($currentChapter['title'] ?? '') ?></h3>
                    <p><?= $h($currentChapter['subtitle'] ?? '') ?></p>
                    <div class="cq-map-progress" style="background:rgba(255,250,241,.08);border-color:rgba(255,255,255,.15);color:#fff">
                        <div class="rowline" style="color:#e8dfcc"><span><?= (int)($journey['completedSteps'] ?? 0) ?> de <?= (int)($journey['totalSteps'] ?? 22) ?> etapas</span><strong><?= (int)($journey['progressPercent'] ?? 0) ?>%</strong></div>
                        <div class="cq-progress"><span style="width:<?= max(0,min(100,(int)($journey['progressPercent'] ?? 0))) ?>%"></span></div>
                    </div>
                <?php else: ?>
                    <h3>A Jornada está começando</h3><p>Seu progresso aparecerá aqui conforme você concluir as missões.</p>
                <?php endif; ?>
                <a href="/studenti/classe/dashboard?view=journey" class="cq-secondary-btn mt-3">Abrir mapa <i class="fa-solid fa-map"></i></a>
            </section>
        </div>

        <div class="cq-mini-grid">
            <section class="cq-card">
                <div class="cq-card-head"><div class="cq-card-eyebrow"><i class="fa-solid fa-image-portrait me-1"></i> Seu Álbum</div></div>
                <?php if (is_array($featuredCard)): ?>
                    <div class="cq-saint-art" aria-hidden="true"><i class="fa-solid fa-cross"></i></div>
                    <h3 class="mt-3"><?= $h($featuredCard['name'] ?? '') ?></h3>
                    <p><?= $h($featuredCard['short_teaching'] ?: ($featuredCard['short_bio'] ?? '')) ?></p>
                    <a href="/studenti/classe/dashboard?view=album" class="cq-secondary-btn">Abrir Álbum</a>
                <?php else: ?>
                    <div class="cq-saint-art" aria-hidden="true"><i class="fa-solid fa-images"></i></div>
                    <h3 class="mt-3">Sua coleção começa aqui</h3>
                    <p>Quando você conquistar sua primeira carta de santo, ela aparecerá nesta área.</p>
                    <a href="/studenti/classe/dashboard?view=album" class="cq-secondary-btn">Ver Álbum</a>
                <?php endif; ?>
            </section>

            <section class="cq-card">
                <div class="cq-card-head"><div class="cq-card-eyebrow"><i class="fa-regular fa-calendar me-1"></i> Próximo encontro</div></div>
                <?php if (is_array($meeting) && $meetingDate): ?>
                    <div class="cq-meeting-date"><span><?= $h($meetingDate['weekday']) ?></span><strong><?= $h($meetingDate['day']) ?></strong><span><?= $h($meetingDate['month']) ?></span></div>
                    <h3><?= $h($meeting['title'] ?? 'Encontro da Crisma') ?></h3>
                    <p><?= $h($meeting['theme'] ?: ('Às ' . $meetingDate['time'])) ?></p>
                    <div class="clearfix"></div>
                <?php else: ?>
                    <div class="cq-meeting-date"><i class="fa-regular fa-calendar" style="font-size:24px"></i></div>
                    <h3>Aguardando programação</h3>
                    <p>O próximo encontro aparecerá aqui assim que for cadastrado pelos catequistas.</p>
                    <div class="clearfix"></div>
                <?php endif; ?>
            </section>
        </div>

        <section class="cq-card mt-3">
            <div class="cq-card-head"><div class="cq-card-eyebrow"><i class="fa-regular fa-envelope me-1"></i> Comunidade</div></div>
            <h3>Correio da Jornada</h3>
            <p>Envie um bilhete, presenteie uma carta repetida ou proponha uma troca para alguém da sua turma.</p>
            <a href="/studenti/correio" class="cq-secondary-btn">Abrir Correio <i class="fa-solid fa-arrow-right"></i></a>
        </section>
    <?php endif; ?>
</div>
