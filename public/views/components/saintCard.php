<?php
/* A obra e a moldura são camadas separadas. Nunca recortar, filtrar ou retocar a imagem. */
$cqEsc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$cqOwned = (int)($card['quantity'] ?? 0) > 0 || (int)($card['illuminated_quantity'] ?? 0) > 0;
$cqLit = ($card['display_edition'] ?? 'normal') === 'illuminated';
$cqQuantity = (int)($cqLit ? ($card['illuminated_quantity'] ?? 0) : ($card['quantity'] ?? 0));
$cqNumber = str_pad((string)($card['card_number'] ?? 0), 2, '0', STR_PAD_LEFT);
$cqImage = $card['image_path'] ?? $card['image_url'] ?? '';
?>
<article class="cq-saint-card <?= $cqOwned ? 'is-collected' : 'is-locked' ?> <?= $cqLit ? 'is-illuminated' : '' ?>" data-saint="<?= $cqEsc($card['slug']) ?>">
  <div class="cq-saint-frame">
    <div class="cq-saint-serial"><span>CRISMAQUEST</span><span>Nº <?= $cqNumber ?></span></div>
    <div class="cq-saint-window">
      <?php if ($cqOwned && $cqImage): ?>
        <img src="<?= $cqEsc($cqImage) ?>" alt="<?= $cqEsc($card['name']) ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer">
      <?php else: ?>
        <div class="cq-card-back" aria-label="Carta ainda não conquistada"><span class="cq-card-back-cross" aria-hidden="true">✦</span><span>UMA VIDA<br>UM TESTEMUNHO</span><small>Jornada da Crisma</small></div>
      <?php endif; ?>
    </div>
    <div class="cq-saint-nameplate">
      <span class="cq-saint-category"><?= $cqOwned ? $cqEsc($card['category']) : 'Álbum dos Santos' ?></span>
      <h3><?= $cqOwned ? $cqEsc($card['name']) : 'Carta '.$cqNumber ?></h3>
      <span class="cq-saint-edition"><?= $cqOwned ? ($cqLit ? '✦ Edição iluminada' : 'Edição da Jornada') : 'A descobrir' ?></span>
    </div>
  </div>
  <div class="cq-saint-card-footer">
    <span class="cq-collection-state <?= $cqQuantity > 1 ? 'is-duplicate' : '' ?>"><?= !$cqOwned ? 'Por conquistar' : ($cqQuantity > 1 ? 'Repetida · ×'.$cqQuantity : '✓ Coletada') ?></span>
    <?php if ($cqOwned): ?><button type="button" class="cq-saint-open" data-open-saint="<?= $cqEsc($card['slug']) ?>" aria-label="Conhecer <?= $cqEsc($card['name']) ?>">Conhecer <span aria-hidden="true">↗</span></button><?php endif; ?>
  </div>
</article>
