<?php

use App\Service\PermissionService;
$permissionStatus = $permissionStatus ?? PermissionService::STATUS_NOT_LOGGED;
$classroom = $classroom ?? null;
$quests = $quests ?? [];
?>
<div class="cq-student-shell">
<?php if ($permissionStatus === PermissionService::STATUS_OK): ?>
    <section class="cq-card mb-3">
        <div class="cq-card-eyebrow">Missões</div>
        <h2>Descobrir, compreender e viver.</h2>
        <p class="mb-0">Aqui ficam as missões publicadas pelos catequistas: Bíblia, catequese, santos, oração, desafios e Evangelho em ação.</p>
    </section>

    <?php if ($quests === []): ?>
        <section class="cq-card">
            <div class="cq-card-eyebrow">Nenhuma missão publicada</div>
            <h3>A próxima etapa está sendo preparada.</h3>
            <p>Quando o catequista publicar uma missão, ela aparecerá aqui. Enquanto isso, você pode explorar a Jornada e o Álbum dos Santos.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="/studenti/classe/dashboard?view=journey" class="cq-primary-btn">Abrir Jornada</a>
                <a href="/studenti/classe/dashboard?view=album" class="cq-secondary-btn">Ver Álbum</a>
            </div>
        </section>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($quests as $index => $quest): ?>
                <div class="col-12 col-md-6">
                    <article class="cq-card h-100 cq-mission-card">
                        <div class="cq-card-head">
                            <div class="cq-card-eyebrow"><i class="fa-solid <?= $index % 3 === 0 ? 'fa-book-bible' : ($index % 3 === 1 ? 'fa-lightbulb' : 'fa-hands-helping') ?> me-1"></i> Missão publicada</div>
                        </div>
                        <?php if (!empty($quest['image_quest'])): ?>
                            <div class="mb-3" style="height:120px;border-radius:16px;overflow:hidden;background:#0d3a4a">
                                <img src="<?= htmlspecialchars((string)$quest['image_quest']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;opacity:.82">
                            </div>
                        <?php endif; ?>
                        <h3><?= htmlspecialchars((string)($quest['nome_quest'] ?? 'Missão')) ?></h3>
                        <p>Entre nesta etapa para ver os capítulos e desafios que estão realmente disponíveis para sua turma.</p>
                        <a class="cq-primary-btn" href="/studenti/quest/<?= (int)($quest['id_quest'] ?? 0) ?>/piantina">Abrir missão <i class="fa-solid fa-arrow-right"></i></a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>
