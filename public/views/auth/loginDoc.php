<?php

use App\Service\Flash;
$flashes = Flash::all();
?>
<div class="cq-auth-shell">
    <section class="cq-auth-visual" aria-label="Jornada CrismaQuest">
        <div class="cq-visual-copy">
            <div class="cq-logo">Crisma<span>Quest</span></div>
            <div class="cq-sublogo">Jornada da Crisma</div>
            <div class="cq-tagline">Raízes na tradição. Olhos no presente. Coração em missão.</div>
        </div>
        <div class="cq-church" aria-hidden="true"></div>
        <div class="cq-path" aria-hidden="true"></div>
        <div class="cq-quote">“Formar hoje, testemunhas para amanhã.”</div>
    </section>
    <section class="cq-auth-panel">
        <div class="cq-card">
            <div class="cq-role-switch" aria-label="Escolha do acesso"><a href="/loginStud">Crismando</a><a class="active" href="/loginDoc">Catequista</a></div>
            <div class="cq-eyebrow"><span>✦</span> Área dos catequistas</div>
            <h1 class="cq-title">Acompanhe a jornada da turma.</h1>
            <p class="cq-copy">Entre para criar missões, revisar respostas, marcar presença e acompanhar o progresso dos crismandos.</p>
            <div class="cq-flashes">
                <?php foreach ($flashes as $f): ?>
                    <div class="alert alert-<?= htmlspecialchars($f['type']) ?>" role="alert"><?= htmlspecialchars((string)($f['message'] ?? '')) ?></div>
                <?php endforeach; ?>
            </div>
            <form role="form" action="/loginDoc" method="POST" autocomplete="on">
                <div class="cq-field"><label class="cq-label" for="inputUser">Usuário</label><input type="text" id="inputUser" name="inputUser" class="cq-input" placeholder="Seu usuário" required autofocus autocomplete="username"></div>
                <div class="cq-field"><label class="cq-label" for="inputPassword">Senha</label><input type="password" id="inputPassword" name="inputPassword" class="cq-input" placeholder="Sua senha" required autocomplete="current-password"></div>
                <button class="cq-primary" type="submit">Entrar no painel →</button>
            </form>
            <div class="cq-help">O acesso de catequista é criado e autorizado pela administração do CrismaQuest.</div>
            <div class="cq-meta" aria-label="Recursos do painel">
                <div class="cq-meta-card"><div class="cq-meta-icon">🧭</div><small>Planeje a Jornada e os capítulos da turma.</small></div>
                <div class="cq-meta-card"><div class="cq-meta-icon">📖</div><small>Crie missões catequéticas em poucos passos.</small></div>
                <div class="cq-meta-card"><div class="cq-meta-icon">✓</div><small>Revise respostas e registre presença.</small></div>
            </div>
            <div class="cq-footnote">Paróquia Nossa Senhora dos Remédios · Arquidiocese de Fortaleza</div>
        </div>
    </section>
</div>
