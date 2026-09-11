<?php
$cqPreviewEscape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$batch=($batch??'exemplos')==='album'?'album':'exemplos';
$base='/docenti/jogo/visual?lote='.$batch;
?>
<main class="cq-collection cq-visual-preview">
  <?php if (empty($embedded)): ?>
  <nav class="cq-preview-controls" aria-label="Tamanho da prévia"><a href="/docenti/jogo">← Missões</a><div><a href="<?= $base ?>" <?= empty($phone)?'aria-current="page"':'' ?>>Computador</a><a href="<?= $base ?>&amp;phone=1" <?= !empty($phone)?'aria-current="page"':'' ?>>Celular</a></div></nav>
  <nav class="cq-preview-batches" aria-label="Lotes visuais"><a href="/docenti/jogo/visual?lote=exemplos" <?= $batch==='exemplos'?'aria-current="page"':'' ?>>Três exemplos</a><a href="/docenti/jogo/visual?lote=album" <?= $batch==='album'?'aria-current="page"':'' ?>>40 santos</a></nav>
  <?php endif; ?>
  <?php if (!empty($phone)): ?>
    <p class="cq-preview-caption">Prévia responsiva · 390 × 844 px</p><iframe class="cq-preview-phone" src="<?= $base ?>&amp;frame=1" title="Álbum em largura de celular"></iframe>
  <?php else: ?>
  <section class="cq-collection-hero"><div><div class="cq-collection-eyebrow">Álbum dos Santos · Prévia do catequista</div><h1>Vidas que iluminam<br>o caminho.</h1><p><?= $batch==='album'?'40 testemunhos, uma mesma identidade visual.':'Fotografia, pintura e ícone religioso.' ?> Cada obra permanece inteira, com suas cores e proporções originais.</p></div><?php if($batch==='album'): ?><div class="cq-preview-count"><strong>40</strong><span>cartas verificadas</span></div><?php endif; ?></section>
  <?php if($batch==='exemplos')$cards=array_values(array_filter($cards,static fn($card)=>in_array($card['slug'],['santa-teresinha-menino-jesus','sao-francisco-assis','sao-pedro'],true))); ?>
  <div class="cq-collection-grid <?= $batch==='exemplos'?'cq-preview-three':'cq-preview-all' ?>">
    <?php foreach ($cards as $card): $card['quantity']=1; ?>
    <div><?php require __DIR__.'/../components/saintCard.php'; ?><p class="cq-preview-source"><?= $cqPreviewEscape($card['image_kind']) ?> · <a href="<?= $cqPreviewEscape($card['source_url']) ?>" target="_blank" rel="noopener">Fonte da imagem</a></p></div>
    <?php endforeach; ?>
  </div>
  <p class="cq-preview-note"><?= $batch==='album'?'Catálogo visual completo para revisão do catequista.':'Amostras visuais da coleção.' ?> A moldura, o nome e a numeração são elementos do jogo; as imagens dos santos são preservadas.</p>
  <?php endif; ?>
</main>
<style>.cq-visual-preview{max-width:1040px;margin:0 auto}.cq-preview-controls{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:0 0 14px;font:12px Arial,sans-serif}.cq-preview-controls div{display:flex;gap:8px}.cq-preview-controls a{display:inline-flex;align-items:center;min-height:40px;padding:0 13px;border:1px solid #d1c3a8;border-radius:99px;color:#23464d;text-decoration:none}.cq-preview-controls a[aria-current]{background:#163e47;color:#fff7e5;border-color:#163e47}.cq-preview-batches{display:flex;gap:6px;margin:0 0 28px;border-bottom:1px solid #d8ccb7}.cq-preview-batches a{padding:10px 12px;border-bottom:2px solid transparent;color:#49616a;font:600 12px Arial,sans-serif;text-decoration:none}.cq-preview-batches a[aria-current]{border-color:#a98745;color:#173e47}.cq-preview-three{grid-template-columns:repeat(3,minmax(0,1fr));gap:25px;max-width:950px}.cq-preview-source{font:11px/1.6 Arial,sans-serif;color:#737365;margin:12px 0 0;text-align:center}.cq-preview-source a{color:#516566}.cq-preview-note{font:12px/1.7 Arial,sans-serif;color:#787469;margin-top:25px;border-top:1px solid #dbcfb8;padding-top:18px}.cq-preview-count{min-width:150px;text-align:right}.cq-preview-count strong{display:block;font:36px/1 Georgia,serif}.cq-preview-count span{font:11px Arial,sans-serif;color:#6b6c62}.cq-preview-phone{display:block;width:390px;height:844px;max-width:100%;border:1px solid #c5b793;border-radius:22px;background:#f5efe3;box-shadow:0 15px 45px #233a4026;margin:auto}.cq-preview-caption{text-align:center;font:12px Arial,sans-serif;color:#53655c;margin:0 0 15px}@media(max-width:650px){.cq-preview-three,.cq-preview-all{grid-template-columns:repeat(2,minmax(0,1fr));gap:22px 13px}.cq-preview-source{font-size:9px}.cq-preview-note{font-size:10px}.cq-preview-controls{font-size:10px}.cq-preview-controls a{padding:0 9px}.cq-preview-batches a{font-size:11px}.cq-preview-count{text-align:left;margin-top:15px}}</style>
