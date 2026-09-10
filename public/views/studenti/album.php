<?php
$album = $crismaquestAlbum ?? ['cards'=>[],'collected'=>0,'total'=>0,'progressPercent'=>0];
$cards = $album['cards'] ?? [];
$iconFor = static function (string $category): string {
    $c = mb_strtolower($category);
    if (str_contains($c,'apóst')) return 'fa-key';
    if (str_contains($c,'mártir')) return 'fa-shield-heart';
    if (str_contains($c,'doutor')) return 'fa-book-open';
    if (str_contains($c,'jov')) return 'fa-star';
    if (str_contains($c,'papa')) return 'fa-church';
    if (str_contains($c,'mission')) return 'fa-earth-americas';
    if (str_contains($c,'fund')) return 'fa-seedling';
    return 'fa-cross';
};
$firstCollected = null;
foreach ($cards as $candidate) {
    if ((int)($candidate['quantity'] ?? 0) > 0) { $firstCollected = $candidate; break; }
}
?>
<div class="cq-student-shell">
    <section class="cq-card mb-3">
        <div class="d-flex flex-column flex-sm-row justify-content-between gap-3 align-items-sm-center">
            <div>
                <div class="cq-card-eyebrow">Coleção catequética</div>
                <h2>Álbum dos Santos</h2>
                <p class="mb-0">Conheça testemunhas reais da fé. As cartas são descobertas ao longo da Jornada.</p>
            </div>
            <div style="min-width:190px">
                <div class="d-flex justify-content-between" style="font-family:Arial,sans-serif;font-size:12px;color:#625d56"><strong><?= (int)($album['collected'] ?? 0) ?> de <?= (int)($album['total'] ?? 0) ?> cartas</strong><span><?= (int)($album['progressPercent'] ?? 0) ?>%</span></div>
                <div class="cq-progress mt-2" style="background:#e8dcc9"><span style="width:<?= (int)($album['progressPercent'] ?? 0) ?>%"></span></div>
            </div>
        </div>
    </section>

    <div class="d-flex flex-wrap gap-2 mb-3" aria-label="Categorias do álbum">
        <span class="cq-chip">Todos</span><span class="cq-chip">Jovens</span><span class="cq-chip">Apóstolos</span><span class="cq-chip">Mártires</span><span class="cq-chip">Missionários</span><span class="cq-chip">Doutores</span>
    </div>

    <?php if ($cards === []): ?>
        <section class="cq-card"><h3>O Álbum está sendo preparado.</h3><p class="mb-0">As cartas aparecerão aqui assim que o catálogo catequético for carregado.</p></section>
    <?php else: ?>
        <div class="row g-3" id="cqAlbumGrid">
            <?php foreach ($cards as $card):
                $collected = (int)($card['quantity'] ?? 0) > 0;
                $icon = $iconFor((string)($card['category'] ?? ''));
            ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <button type="button"
                        class="cq-card h-100 w-100 text-center cq-saint-card-btn"
                        style="padding:10px;border-style:solid;<?= !$collected ? 'filter:grayscale(.82);opacity:.7;cursor:default' : 'cursor:pointer' ?>"
                        <?= !$collected ? 'disabled' : '' ?>
                        data-name="<?= htmlspecialchars((string)$card['name'], ENT_QUOTES) ?>"
                        data-category="<?= htmlspecialchars((string)$card['category'], ENT_QUOTES) ?>"
                        data-bio="<?= htmlspecialchars((string)$card['short_bio'], ENT_QUOTES) ?>"
                        data-teaching="<?= htmlspecialchars((string)($card['short_teaching'] ?? ''), ENT_QUOTES) ?>"
                        data-number="<?= (int)$card['card_number'] ?>">
                        <div class="cq-saint-art" style="height:150px;font-size:42px;<?= !$collected ? 'background:linear-gradient(145deg,#5e625d,#8d887f);color:#e7e0d4' : '' ?>">
                            <i class="fa-solid <?= $collected ? htmlspecialchars($icon) : 'fa-lock' ?>"></i>
                        </div>
                        <div class="cq-card-eyebrow mt-2"><?= htmlspecialchars((string)$card['category']) ?></div>
                        <h3 style="font-size:16px;margin-top:4px"><?= $collected ? htmlspecialchars((string)$card['name']) : 'Carta ' . str_pad((string)$card['card_number'],2,'0',STR_PAD_LEFT) ?></h3>
                        <p style="font-size:12px;margin-bottom:4px"><?= $collected ? htmlspecialchars((string)($card['short_teaching'] ?? '')) : 'Descubra esta testemunha em uma missão ou recompensa.' ?></p>
                        <?php if ($collected): ?><span class="cq-chip mt-1"><i class="fa-solid fa-check"></i> Coletada<?= (int)$card['quantity'] > 1 ? ' · x'.(int)$card['quantity'] : '' ?></span><?php endif; ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($firstCollected): ?>
            <section class="cq-card mt-3" id="cqSaintDetail">
                <div class="row g-3 align-items-center">
                    <div class="col-sm-4"><div class="cq-saint-art" style="height:220px;font-size:68px"><i id="cqDetailIcon" class="fa-solid <?= htmlspecialchars($iconFor((string)$firstCollected['category'])) ?>"></i></div></div>
                    <div class="col-sm-8">
                        <div class="cq-card-eyebrow" id="cqDetailCategory"><?= htmlspecialchars((string)$firstCollected['category']) ?> · Carta <?= str_pad((string)$firstCollected['card_number'],2,'0',STR_PAD_LEFT) ?></div>
                        <h2 id="cqDetailName"><?= htmlspecialchars((string)$firstCollected['name']) ?></h2>
                        <p id="cqDetailBio"><?= htmlspecialchars((string)$firstCollected['short_bio']) ?></p>
                        <div style="border-left:3px solid #c8a55c;padding-left:13px;color:#315044;font-weight:700" id="cqDetailTeaching"><?= htmlspecialchars((string)($firstCollected['short_teaching'] ?? '')) ?></div>
                        <small class="d-block mt-3" style="font-family:Arial,sans-serif;color:#7a746b">As imagens definitivas só serão publicadas após verificação de licença ou autorização de uso.</small>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <section class="cq-card mt-3">
        <div class="cq-card-eyebrow">Regra do Álbum</div>
        <h3>Não existe “santo raro”.</h3>
        <p class="mb-0">Quando houver versões especiais, a diferença será apenas a edição visual da carta. O valor catequético de cada testemunho permanece o mesmo.</p>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const detail=document.getElementById('cqSaintDetail');
  if(!detail) return;
  document.querySelectorAll('.cq-saint-card-btn:not(:disabled)').forEach(btn=>btn.addEventListener('click',()=>{
    document.getElementById('cqDetailName').textContent=btn.dataset.name||'';
    document.getElementById('cqDetailCategory').textContent=(btn.dataset.category||'')+' · Carta '+String(btn.dataset.number||'').padStart(2,'0');
    document.getElementById('cqDetailBio').textContent=btn.dataset.bio||'';
    document.getElementById('cqDetailTeaching').textContent=btn.dataset.teaching||'';
    detail.scrollIntoView({behavior:'smooth',block:'center'});
  }));
});
</script>
