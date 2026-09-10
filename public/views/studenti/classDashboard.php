<?php

$classroom = $classroom ?? null;
$student = $student ?? null;
$availableCharacters = $availableCharacters ?? [];
$hero = $hero ?? null;

$displayName = is_array($hero) && !empty($hero['playerName'])
    ? (string) $hero['playerName']
    : (is_array($student) ? (string) ($student['username'] ?? 'Peregrino') : 'Peregrino');
$firstName = trim(explode(' ', $displayName)[0] ?? 'Peregrino');
$level = is_array($hero) ? (int) ($hero['level'] ?? 1) : 1;
$xpPercent = is_array($hero) ? max(0, min(100, (int) ($hero['xpPercent'] ?? 0))) : 0;
$xpLabel = is_array($hero) ? (string) ($hero['xpLabel'] ?? '0 XP') : '0 XP';
$coins = is_array($hero) ? (int) ($hero['coins'] ?? 0) : 0;
$avatarSrc = is_array($hero) ? (string) ($hero['avatar']['src'] ?? '') : '';
$levelNames = [1=>'Peregrino',2=>'Caminhante',3=>'Discípulo',4=>'Servidor',5=>'Mensageiro',6=>'Missionário',7=>'Testemunha',8=>'Enviado'];
$levelTitle = $levelNames[min(8, max(1, $level))] ?? 'Peregrino';
?>
<div class="cq-student-shell">
    <?php if (is_array($student) && (int) ($student['fk_personaggio'] ?? 0) === 0): ?>
        <section class="cq-card mb-3">
            <div class="cq-card-eyebrow">Primeiro passo</div>
            <h2>Escolha seu peregrino</h2>
            <p>Seu personagem acompanha a Jornada. Ele representa participação e caminhada — nunca mede fé ou santidade.</p>
        </section>
        <div class="row g-3">
            <?php foreach ($availableCharacters as $character): ?>
                <div class="col-md-6">
                    <div class="cq-card h-100">
                        <form method="post" action="/studenti/classe/personaggio" class="m-0">
                            <input type="hidden" name="character_id" value="<?= (int) $character['id_personaggio'] ?>">
                            <div class="d-flex gap-3 align-items-center">
                                <div class="cq-avatar">
                                    <img src="<?= htmlspecialchars('/' . ltrim(preg_replace('#^(\./|\.\./)+#', '', (string) $character['immagine']), '/')) ?>" alt="<?= htmlspecialchars((string) $character['nome_personaggio']) ?>">
                                </div>
                                <div class="flex-grow-1">
                                    <h3><?= htmlspecialchars((string) $character['nome_personaggio']) ?></h3>
                                    <p class="mb-2"><?= strip_tags(html_entity_decode((string) $character['descrizione'])) ?></p>
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
                <div class="cq-avatar">
                    <?php if ($avatarSrc !== ''): ?>
                        <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Avatar de <?= htmlspecialchars($firstName) ?>">
                    <?php else: ?>
                        <i class="fa-solid fa-person-walking"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="cq-kicker">Sua caminhada hoje</div>
                    <h1 class="cq-greeting" id="cq-greeting">Olá, <strong><?= htmlspecialchars($firstName) ?></strong></h1>
                    <p class="cq-motto">“Jovens de hoje. Discípulos sempre.”</p>
                </div>
            </div>

            <div class="cq-stats">
                <div class="cq-stat"><i class="fa-solid fa-star"></i><div><strong><?= htmlspecialchars($xpLabel) ?></strong><span>Experiência</span></div></div>
                <div class="cq-stat"><i class="fa-solid fa-coins"></i><div><strong><?= $coins ?></strong><span>Lúmens</span></div></div>
                <div class="cq-stat"><i class="fa-solid fa-fire-flame-curved"></i><div><strong>0 dias</strong><span>Chama</span></div></div>
            </div>

            <div class="cq-level-row">
                <div class="cq-level-badge"><div><small>Nível</small><b><?= $level ?></b></div></div>
                <div>
                    <div class="cq-level-title"><strong><?= htmlspecialchars($levelTitle) ?></strong><span><?= $xpPercent ?>%</span></div>
                    <div class="cq-progress" aria-label="Progresso do nível"><span style="width:<?= $xpPercent ?>%"></span></div>
                </div>
            </div>
        </section>

        <div class="cq-grid">
            <section class="cq-card cq-mission-card">
                <div class="cq-card-head">
                    <div class="cq-card-eyebrow"><i class="fa-solid fa-book-bible me-1"></i> Missão de hoje</div>
                    <span class="cq-chip"><i class="fa-regular fa-clock"></i> 3 min</span>
                </div>
                <h2>Uma Palavra para você</h2>
                <p>Leia uma passagem curta, descubra o que ela anuncia e responda ao desafio do dia.</p>
                <div class="cq-rewards">
                    <span class="cq-chip"><i class="fa-solid fa-star"></i> +10 XP</span>
                    <span class="cq-chip"><i class="fa-solid fa-coins"></i> +5 Lúmens</span>
                    <span class="cq-chip"><i class="fa-solid fa-fire-flame-curved"></i> mantém a Chama</span>
                </div>
                <a href="/studenti/quest" class="cq-primary-btn">Começar missão <i class="fa-solid fa-arrow-right"></i></a>
            </section>

            <section class="cq-card cq-journey-preview">
                <div class="cq-card-eyebrow" style="color:#ead39a">Continue sua Jornada</div>
                <h3>Capítulo 1 — O Chamado</h3>
                <p>Deus fala, revela-se e chama cada pessoa a responder com fé.</p>
                <div class="cq-journey-line" aria-hidden="true">
                    <span class="cq-node current">1</span><span class="cq-node">2</span><span class="cq-node">3</span><span class="cq-node">4</span><span class="cq-node">5</span>
                </div>
                <a href="/studenti/classe/dashboard?view=journey" class="cq-secondary-btn">Abrir mapa <i class="fa-solid fa-map"></i></a>
            </section>
        </div>

        <div class="cq-mini-grid">
            <section class="cq-card">
                <div class="cq-card-head"><div class="cq-card-eyebrow"><i class="fa-solid fa-image-portrait me-1"></i> Carta em destaque</div></div>
                <div class="cq-saint-art" aria-hidden="true"><i class="fa-solid fa-cross"></i></div>
                <h3 class="mt-3">São Carlo Acutis</h3>
                <p>Uma vida jovem marcada pela Eucaristia e pelo anúncio do Evangelho também no mundo digital.</p>
                <a href="/studenti/classe/dashboard?view=album" class="cq-secondary-btn">Ver Álbum</a>
            </section>

            <section class="cq-card">
                <div class="cq-card-head"><div class="cq-card-eyebrow"><i class="fa-regular fa-calendar me-1"></i> Próximo encontro</div></div>
                <div class="cq-meeting-date"><span>SÁB</span><strong>12</strong><span>SET</span></div>
                <h3>Caminhando juntos</h3>
                <p>Veja o tema do encontro, prepare-se durante a semana e leve suas perguntas.</p>
                <div class="clearfix"></div>
                <a href="/studenti/quest" class="cq-secondary-btn mt-2">Preparar-me</a>
            </section>
        </div>
    <?php endif; ?>
</div>
