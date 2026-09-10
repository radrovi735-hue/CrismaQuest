<?php
$profile = $profile ?? [];
$h = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="cq-student-shell">
    <section class="cq-card mb-3">
        <div class="cq-card-eyebrow">Seu espaço</div>
        <h2>Perfil</h2>
        <p class="mb-0">Confira seus dados e altere sua senha quando precisar.</p>
    </section>

    <div class="cq-grid">
        <section class="cq-card">
            <div class="cq-card-eyebrow">Identificação</div>
            <h3><?= $h(trim(($profile['nome'] ?? '').' '.($profile['cognome'] ?? ''))) ?></h3>
            <p class="mb-2"><strong>Usuário:</strong> <?= $h($profile['username'] ?? '') ?></p>
            <p class="mb-0" style="font-size:12px">Seu nível, XP, Lúmens e Chama representam participação na Jornada, nunca fé ou santidade.</p>
        </section>

        <section class="cq-card">
            <div class="cq-card-eyebrow">Comunidade</div>
            <h3>Correio da Jornada</h3>
            <p>Bilhetes, presentes, cartas repetidas e propostas de troca ficam reunidos em um único lugar.</p>
            <a href="/studenti/correio" class="cq-secondary-btn">Abrir Correio <i class="fa-regular fa-envelope"></i></a>
        </section>
    </div>

    <section class="cq-card mt-3">
        <div class="cq-card-eyebrow">Segurança</div>
        <h3>Alterar senha</h3>
        <form method="POST" action="/studenti/profilo" class="cq-form-stack mt-3" style="max-width:520px">
            <label>Nova senha
                <input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Mínimo de 8 caracteres">
            </label>
            <label>Repita a nova senha
                <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
            </label>
            <button type="submit" class="cq-primary-btn">Salvar nova senha</button>
        </form>
    </section>

    <section class="cq-card mt-3">
        <div class="cq-card-eyebrow">Sua coleção</div>
        <h3>Álbum dos Santos</h3>
        <p>Veja as cartas conquistadas durante a Jornada e descubra quais você tem repetidas para presentear ou trocar.</p>
        <a href="/studenti/classe/dashboard?view=album" class="cq-secondary-btn">Abrir Álbum <i class="fa-solid fa-images"></i></a>
    </section>
</div>
