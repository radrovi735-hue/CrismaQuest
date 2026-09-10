<?php

use App\Service\PermissionService;
$permissionStatus = $permissionStatus ?? PermissionService::STATUS_NOT_LOGGED;
$classroom = $classroom ?? null;
$students = $students ?? [];
$totalStudents = count($students);
$withCharacter = count(array_filter($students, static fn($s) => !empty($s['hasCharacter'])));
?>
<?php if ($permissionStatus === PermissionService::STATUS_OK): ?>
    <div class="mb-3">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.11em;color:#6f1d2a;font-weight:700">Painel do catequista</div>
        <h1 style="font-family:Georgia,serif;color:#17313d;font-size:32px;margin:4px 0 2px">Crisma <?= htmlspecialchars((string)($classroom['nome_classe'] ?? '2026')) ?></h1>
        <div style="color:#716b62;font-size:13px">Acompanhe a jornada da turma sem transformar participação em nota escolar.</div>
    </div>

    <section class="cq-teacher-cards">
        <article class="cq-kpi"><i class="fa-solid fa-users"></i><strong><?= $totalStudents ?></strong><span>crismandos na turma</span></article>
        <article class="cq-kpi"><i class="fa-solid fa-person-walking"></i><strong><?= $withCharacter ?></strong><span>já iniciaram a Jornada</span></article>
        <article class="cq-kpi"><i class="fa-solid fa-fire-flame-curved"></i><strong>—</strong><span>Chamas ativas · integração em andamento</span></article>
        <article class="cq-kpi"><i class="fa-regular fa-calendar"></i><strong>12/09</strong><span>próximo encontro da turma</span></article>
    </section>

    <section class="cq-actions">
        <a class="cq-action red" href="/docenti/quest"><i class="fa-solid fa-plus"></i> Criar missão</a>
        <a class="cq-action blue" href="/docenti/dashboard?view=attendance"><i class="fa-solid fa-calendar-check"></i> Marcar presença</a>
        <a class="cq-action gold" href="/docenti/quest"><i class="fa-solid fa-comment-dots"></i> Revisar respostas</a>
        <a class="cq-action green" href="/docenti/studenti"><i class="fa-solid fa-users"></i> Ver turma</a>
    </section>

    <section class="cq-panel">
        <div class="d-flex justify-content-between gap-3 align-items-center mb-2">
            <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:#6f1d2a;font-weight:700">Minha turma</div><h2 style="font-size:23px;margin:2px 0">Caminhada dos crismandos</h2></div>
            <a href="/docenti/studenti" style="font-size:12px;color:#0d3a4a;text-decoration:none;font-weight:700">Ver cadastro →</a>
        </div>
        <div class="table-responsive">
            <table class="cq-teacher-table">
                <thead><tr><th>Nome</th><th>Nível</th><th>Lúmens</th><th>Progresso</th><th>Jornada</th></tr></thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars(trim((string)($student['name'] ?? '') . ' ' . (string)($student['surname'] ?? ''))) ?></strong></td>
                        <?php if (empty($student['hasCharacter'])): ?>
                            <td>—</td><td>—</td><td><span style="color:#8a8177">Ainda não iniciou</span></td><td><span class="cq-dot warn"></span>Primeiro acesso</td>
                        <?php else: ?>
                            <td><span class="cq-level-pill"><?= htmlspecialchars((string)($student['level'] ?? '1')) ?></span></td>
                            <td><i class="fa-solid fa-coins" style="color:#b98d34"></i> <?= (int)($student['coins'] ?? 0) ?></td>
                            <td style="min-width:150px"><div class="progress" style="height:8px"><div class="progress-bar" style="width:<?= (int)($student['nextLevel']['percent'] ?? 0) ?>%;background:#2f6d50"></div></div><small><?= htmlspecialchars((string)($student['nextLevel']['label'] ?? '')) ?></small></td>
                            <td><span class="cq-dot ok"></span>Em caminhada</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="cq-panel">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:#6f1d2a;font-weight:700">Próximas ações</div>
        <h3 style="font-size:21px;margin:3px 0 10px">Rotina simples da semana</h3>
        <div class="row g-3" style="font-size:12px;color:#625d56">
            <div class="col-md-4"><strong>1. Programar</strong><br>Prepare 2–4 missões curtas da semana.</div>
            <div class="col-md-4"><strong>2. Acompanhar</strong><br>Revise respostas que precisam de retorno humano.</div>
            <div class="col-md-4"><strong>3. Encontrar</strong><br>No encontro presencial, registre presença sem retirar XP por falta.</div>
        </div>
    </section>
<?php else: ?>
    <div class="alert alert-danger">Não foi possível abrir esta turma. Volte ao login ou selecione uma turma válida.</div>
<?php endif; ?>
