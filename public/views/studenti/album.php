<?php
$album = $crismaquestAlbum ?? ['cards'=>[],'collected'=>0,'total'=>0,'progressPercent'=>0,'stateCounts'=>[]];
$cards = $album['cards'] ?? [];
$stateCounts = $album['stateCounts'] ?? ['locked'=>0,'collected'=>0,'repeated'=>0,'illuminated'=>0];
$h = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$firstOwned = null;
$details = [];
foreach ($cards as $candidate) {
    if (($candidate['collection_state'] ?? 'locked') !== 'locked' && $firstOwned === null) $firstOwned = $candidate;
    $details[(string)$candidate['slug']] = [
        'name'=>(string)$candidate['name'],
        'category'=>(string)$candidate['category'],
        'number'=>(int)$candidate['card_number'],
        'bio'=>(string)$candidate['short_bio'],
        'teaching'=>(string)($candidate['short_teaching'] ?? ''),
        'image'=>(string)($candidate['image_path'] ?? ''),
        'fallback'=>(string)($candidate['fallback_image_path'] ?? ''),
        'state'=>(string)($candidate['collection_state'] ?? 'locked'),
        'normalQuantity'=>(int)($candidate['quantity'] ?? 0),
        'illuminatedQuantity'=>(int)($candidate['illuminated_quantity'] ?? 0),
    ];
}
?>
<div class="cq-student-shell">
<main class="cq-collection" id="cqAlbum">
  <section class="cq-collection-hero">
    <div>
      <div class="cq-collection-eyebrow">Coleção catequética</div>
      <h1>Álbum dos Santos</h1>
      <p>Vidas reais que acompanham a Jornada. Cada imagem é preservada em sua composição original; a moldura identifica apenas o estado da carta no jogo.</p>
    </div>
    <div class="cq-collection-progress">
      <strong><?= (int)($album['collected'] ?? 0) ?>/<?= (int)($album['total'] ?? 0) ?></strong>
      <span><?= (int)($album['progressPercent'] ?? 0) ?>% da coleção descoberta</span>
    </div>
  </section>

  <div class="cq-state-summary" aria-label="Resumo do álbum">
    <span class="cq-state-pill"><b><?= (int)($stateCounts['locked'] ?? 0) ?></b> por conquistar</span>
    <span class="cq-state-pill"><b><?= (int)($stateCounts['collected'] ?? 0) ?></b> coletadas</span>
    <span class="cq-state-pill is-repeat"><b><?= (int)($stateCounts['repeated'] ?? 0) ?></b> repetidas</span>
    <span class="cq-state-pill is-light"><b><?= (int)($stateCounts['illuminated'] ?? 0) ?></b> iluminadas</span>
  </div>

  <div class="cq-collection-toolbar" aria-label="Ferramentas do álbum">
    <input id="cqAlbumSearch" type="search" placeholder="Buscar santo ou categoria" autocomplete="off">
    <button type="button" aria-pressed="true" data-album-state="">Todas</button>
    <button type="button" aria-pressed="false" data-album-state="collected">Coletadas</button>
    <button type="button" aria-pressed="false" data-album-state="repeated">Repetidas</button>
    <button type="button" aria-pressed="false" data-album-state="illuminated">Iluminadas</button>
    <button type="button" aria-pressed="false" data-album-state="locked">Bloqueadas</button>
  </div>

  <?php if ($cards === []): ?>
    <section class="cq-album-empty"><i class="fa-solid fa-images"></i><h2>O Álbum está sendo preparado.</h2><p>As cartas aparecerão quando o catálogo catequético estiver disponível.</p></section>
  <?php else: ?>
    <div class="cq-collection-grid" id="cqAlbumGrid">
      <?php foreach ($cards as $card): ?>
        <div class="cq-album-cell"
             data-state="<?= $h($card['collection_state'] ?? 'locked') ?>"
             data-search="<?= $h(mb_strtolower(($card['name'] ?? '').' '.($card['category'] ?? ''), 'UTF-8')) ?>">
          <?php require __DIR__.'/../components/saintCard.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($firstOwned): ?>
    <section class="cq-album-detail" id="cqSaintDetail" aria-live="polite">
      <div class="cq-album-detail-art"><img id="cqDetailImage" src="<?= $h($firstOwned['image_path'] ?? '') ?>" alt="<?= $h($firstOwned['name']) ?>" referrerpolicy="no-referrer"<?= !empty($firstOwned['fallback_image_path']) ? ' data-fallback="'.$h($firstOwned['fallback_image_path']).'" onerror="if(this.dataset.fallback){this.onerror=null;this.src=this.dataset.fallback;}"' : '' ?>></div>
      <div class="cq-album-detail-copy">
        <div class="cq-collection-eyebrow" id="cqDetailCategory"><?= $h($firstOwned['category']) ?> · Carta <?= str_pad((string)$firstOwned['card_number'],2,'0',STR_PAD_LEFT) ?></div>
        <h2 id="cqDetailName"><?= $h($firstOwned['name']) ?></h2>
        <p id="cqDetailBio"><?= $h($firstOwned['short_bio']) ?></p>
        <blockquote id="cqDetailTeaching"><?= $h($firstOwned['short_teaching'] ?? '') ?></blockquote>
        <span class="cq-detail-state" id="cqDetailState"></span>
      </div>
    </section>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (($stateCounts['repeated'] ?? 0) > 0): ?>
    <section class="cq-album-trade"><div><strong>Você tem cartas repetidas.</strong><span>Elas podem ser presenteadas ou usadas em trocas no Correio da Jornada.</span></div><a href="/studenti/correio">Abrir Correio <i class="fa-solid fa-arrow-right"></i></a></section>
  <?php endif; ?>

  <section class="cq-album-rule">
    <strong>Uma vida, um testemunho.</strong>
    <span>Não existe “santo raro”. A edição iluminada muda apenas a moldura visual; todas as testemunhas têm o mesmo valor catequético.</span>
  </section>
</main>
</div>
<script>
(() => {
  const cards = <?= json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
  const grid = document.getElementById('cqAlbumGrid');
  if (!grid) return;

  const cells = [...grid.querySelectorAll('.cq-album-cell')];
  const search = document.getElementById('cqAlbumSearch');
  const filters = [...document.querySelectorAll('[data-album-state]')];
  let state = '';

  const apply = () => {
    const query = (search?.value || '').trim().toLocaleLowerCase('pt-BR');
    cells.forEach(cell => {
      const stateOk = state === '' || cell.dataset.state === state;
      const searchOk = query === '' || (cell.dataset.search || '').includes(query);
      cell.hidden = !(stateOk && searchOk);
    });
  };

  search?.addEventListener('input', apply);
  filters.forEach(button => button.addEventListener('click', () => {
    state = button.dataset.albumState || '';
    filters.forEach(item => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
    apply();
  }));

  const detail = document.getElementById('cqSaintDetail');
  const stateText = card => {
    if (card.state === 'illuminated') return '✦ Edição iluminada';
    if (card.state === 'repeated') return 'Repetida · ×' + Math.max(2, card.normalQuantity);
    return '✓ Coletada';
  };

  document.querySelectorAll('[data-open-saint]').forEach(button => button.addEventListener('click', () => {
    const card = cards[button.dataset.openSaint || ''];
    if (!card || !detail) return;
    const detailImage = document.getElementById('cqDetailImage');
    detailImage.onerror = card.fallback ? function(){ this.onerror=null; this.src=card.fallback; } : null;
    detailImage.dataset.fallback = card.fallback || '';
    detailImage.src = card.image;
    detailImage.alt = card.name;
    document.getElementById('cqDetailCategory').textContent = card.category + ' · Carta ' + String(card.number).padStart(2,'0');
    document.getElementById('cqDetailName').textContent = card.name;
    document.getElementById('cqDetailBio').textContent = card.bio;
    document.getElementById('cqDetailTeaching').textContent = card.teaching;
    document.getElementById('cqDetailState').textContent = stateText(card);
    detail.scrollIntoView({behavior:'smooth',block:'center'});
  }));

  <?php if ($firstOwned): ?>
  const first = cards[<?= json_encode((string)$firstOwned['slug'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>];
  if (first && document.getElementById('cqDetailState')) document.getElementById('cqDetailState').textContent = stateText(first);
  <?php endif; ?>
})();
</script>
