<?php

use App\Service\Flash;

$flashes = Flash::all();
?>

<div class="cq-auth-shell">
    <section class="cq-auth-visual" aria-label="Jornada CrismaQuest">
        <div class="cq-visual-copy">
            <div class="cq-logo">Crisma<span>Quest</span></div>
            <div class="cq-sublogo">Jornada da Crisma</div>
            <div class="cq-tagline">Mais que um jogo, uma missão de verdade.</div>
        </div>
        <div class="cq-church" aria-hidden="true"></div>
        <div class="cq-path" aria-hidden="true"></div>
        <div class="cq-quote">“Recebereis a força do Espírito Santo e sereis minhas testemunhas.”<br><strong>At 1,8</strong></div>
    </section>

    <section class="cq-auth-panel">
        <div class="cq-card">
            <div class="cq-role-switch" aria-label="Escolha do acesso">
                <a class="active" href="/loginStud">Crismando</a>
                <a href="/loginDoc">Catequista</a>
            </div>

            <div class="cq-eyebrow"><span>✦</span> Bem-vindo</div>
            <h1 class="cq-title">Sua jornada começa aqui.</h1>
            <p class="cq-copy">Entre para continuar suas missões, manter sua Chama acesa e avançar na Jornada da Crisma.</p>

            <div class="cq-flashes">
                <?php foreach ($flashes as $f): ?>
                    <div class="alert alert-<?= htmlspecialchars($f['type']) ?>" role="alert">
                        <?= htmlspecialchars((string)($f['message'] ?? '')) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <form role="form" action="/loginStud" method="POST" autocomplete="on">
                <div class="cq-field">
                    <label class="cq-label" for="username">Usuário</label>
                    <input type="text" id="username" name="username" class="cq-input" placeholder="Seu usuário" required autofocus autocomplete="username">
                </div>
                <div class="cq-field">
                    <label class="cq-label" for="pass">Senha</label>
                    <input type="password" id="pass" name="pass" class="cq-input" placeholder="Sua senha" required autocomplete="current-password">
                </div>
                <button class="cq-primary" type="submit">Entrar na jornada →</button>
            </form>

            <div class="cq-help"><a href="#" onclick="return false;">Preciso de ajuda para entrar</a></div>

            <div class="cq-meta" aria-label="Como funciona o CrismaQuest">
                <div class="cq-meta-card"><div class="cq-meta-icon">✦</div><small>XP mostra participação, não mede santidade.</small></div>
                <div class="cq-meta-card"><div class="cq-meta-icon">🔥</div><small>Complete uma missão válida e mantenha sua Chama.</small></div>
                <div class="cq-meta-card"><div class="cq-meta-icon">📖</div><small>Bíblia, desafios, santos e ensinamentos do Evangelho.</small></div>
            </div>

            <div class="cq-footnote">Paróquia Nossa Senhora dos Remédios · Benfica · Fortaleza/CE</div>
        </div>
    </section>
</div>
