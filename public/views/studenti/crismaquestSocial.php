<?php

use App\Service\Database;

$allCards = [];
try {
    $allCards = Database::getConnection()->query(
        "SELECT ce.id card_edition_id, sc.name, ce.edition_type
         FROM cq_card_editions ce
         JOIN cq_saint_cards sc ON sc.id=ce.card_id
         WHERE ce.active=1
         ORDER BY sc.name, ce.edition_type"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable) {}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$duplicates = array_values(array_filter($cards ?? [], static fn($c) => (int)($c['quantity'] ?? 0) >= 2));
$ownedCosmeticIds = array_fill_keys(array_map(static fn($c) => (int)($c['gift_catalog_id'] ?? 0), $cosmetics ?? []), true);
?>
<div class="cq-social-shell">
  <section class="cq-social-hero">
    <div>
      <div class="cq-social-kicker">Comunidade</div>
      <h1>Correio da Jornada</h1>
      <p>Bilhetes, presentes e trocas entre os crismandos da sua turma.</p>
    </div>
    <div class="cq-lumen-balance"><i class="fa-solid fa-sun"></i><strong><?= (int)($balance ?? 0) ?></strong><span>Lúmens</span></div>
  </section>

  <div class="cq-social-grid">
    <section class="cq-social-card">
      <h2><i class="fa-regular fa-envelope"></i> Enviar bilhete</h2>
      <form method="post" action="/studenti/correio/bilhete" class="cq-form-stack">
        <label>Para
          <select name="recipient_user_id" required>
            <option value="">Escolha um colega</option>
            <?php foreach (($classmates ?? []) as $mate): ?><option value="<?= (int)$mate['id_utente'] ?>"><?= $h($mate['nome'].' '.$mate['cognome']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Mensagem
          <select name="message_key" required>
            <?php foreach (($messageOptions ?? []) as $key=>$text): ?><option value="<?= $h($key) ?>"><?= $h($text) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Complemento opcional <small>até 120 caracteres</small><textarea name="custom_text" maxlength="120" rows="2" placeholder="Escreva algo curto e respeitoso..."></textarea></label>
        <button class="cq-primary-btn" type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar bilhete</button>
      </form>
    </section>

    <section class="cq-social-card">
      <h2><i class="fa-solid fa-gift"></i> Enviar presente</h2>
      <form method="post" action="/studenti/correio/presente" class="cq-form-stack">
        <label>Para
          <select name="recipient_user_id" required><option value="">Escolha um colega</option><?php foreach (($classmates ?? []) as $mate): ?><option value="<?= (int)$mate['id_utente'] ?>"><?= $h($mate['nome'].' '.$mate['cognome']) ?></option><?php endforeach; ?></select>
        </label>
        <label>Presente
          <select name="gift_catalog_id" required><option value="">Escolha</option><?php foreach (($catalog ?? []) as $gift): ?><option value="<?= (int)$gift['id'] ?>"><?= $h($gift['name']) ?> — <?= (int)$gift['cost_lumens'] ?> Lúmens</option><?php endforeach; ?></select>
        </label>
        <label>Dedicatória opcional<textarea name="note" maxlength="120" rows="2"></textarea></label>
        <button class="cq-primary-btn" type="submit"><i class="fa-solid fa-gift"></i> Confirmar presente</button>
      </form>
    </section>
  </div>

  <section class="cq-social-card cq-wide">
    <div class="cq-section-head">
      <div><div class="cq-social-kicker">Presentes e personalização</div><h2>Catálogo da Jornada</h2></div>
      <span class="cq-info-chip"><?= count($catalog ?? []) ?> opções ativas</span>
    </div>
    <p class="cq-catalog-intro">Presentes simbólicos custam Lúmens. Os visuais mudam apenas a aparência do jogo e podem ser equipados depois de recebidos.</p>
    <div class="cq-gift-catalog-grid">
      <?php foreach (($catalog ?? []) as $gift):
        $isCosmetic = ($gift['category'] ?? '') === 'cosmetic';
        $owned = $isCosmetic && isset($ownedCosmeticIds[(int)$gift['id']]);
      ?>
        <article class="cq-gift-catalog-item <?= $isCosmetic ? 'is-cosmetic' : 'is-gift' ?>">
          <span class="cq-gift-icon"><i class="fa-solid <?= $h($gift['icon'] ?: 'fa-gift') ?>"></i></span>
          <div><small><?= $isCosmetic ? 'Visual' : 'Presente' ?></small><strong><?= $h($gift['name']) ?></strong></div>
          <span class="cq-gift-price"><i class="fa-solid fa-sun"></i> <?= (int)$gift['cost_lumens'] ?></span>
          <?php if ($owned): ?><span class="cq-owned-mark"><i class="fa-solid fa-check"></i> Você possui</span><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="cq-social-card cq-wide">
    <div class="cq-section-head"><div><div class="cq-social-kicker">Coleção</div><h2>Cartas: presentear ou trocar</h2></div><span class="cq-info-chip">Somente repetidas podem sair do seu álbum</span></div>
    <?php if ($duplicates === []): ?>
      <p class="cq-empty">Você ainda não tem cartas repetidas. Quando tiver, as opções de presente e troca aparecerão aqui.</p>
    <?php else: ?>
    <div class="cq-social-grid cq-inner-grid">
      <form method="post" action="/studenti/correio/presente-carta" class="cq-form-stack cq-subcard">
        <h3>Presentear uma repetida</h3>
        <select name="recipient_user_id" required><option value="">Colega</option><?php foreach (($classmates ?? []) as $mate): ?><option value="<?= (int)$mate['id_utente'] ?>"><?= $h($mate['nome'].' '.$mate['cognome']) ?></option><?php endforeach; ?></select>
        <select name="card_edition_id" required><option value="">Carta repetida</option><?php foreach ($duplicates as $card): ?><option value="<?= (int)$card['card_edition_id'] ?>"><?= $h($card['name']) ?> ×<?= (int)$card['quantity'] ?></option><?php endforeach; ?></select>
        <input name="note" maxlength="120" placeholder="Dedicatória opcional">
        <button type="submit" class="cq-secondary-btn">Presentear carta</button>
      </form>

      <form method="post" action="/studenti/correio/troca" class="cq-form-stack cq-subcard">
        <h3>Propor troca</h3>
        <select name="recipient_user_id" required><option value="">Colega</option><?php foreach (($classmates ?? []) as $mate): ?><option value="<?= (int)$mate['id_utente'] ?>"><?= $h($mate['nome'].' '.$mate['cognome']) ?></option><?php endforeach; ?></select>
        <select name="offered_card_edition_id" required><option value="">Você oferece</option><?php foreach ($duplicates as $card): ?><option value="<?= (int)$card['card_edition_id'] ?>"><?= $h($card['name']) ?> ×<?= (int)$card['quantity'] ?></option><?php endforeach; ?></select>
        <select name="requested_card_edition_id" required><option value="">Você deseja</option><?php foreach ($allCards as $card): ?><option value="<?= (int)$card['card_edition_id'] ?>"><?= $h($card['name']) ?><?= $card['edition_type']!=='normal'?' · '.$h($card['edition_type']):'' ?></option><?php endforeach; ?></select>
        <button type="submit" class="cq-secondary-btn">Enviar proposta</button>
      </form>
    </div>
    <?php endif; ?>
  </section>

  <?php if (($pendingTrades ?? []) !== []): ?>
  <section class="cq-social-card cq-wide">
    <h2><i class="fa-solid fa-right-left"></i> Trocas esperando sua resposta</h2>
    <div class="cq-feed">
      <?php foreach ($pendingTrades as $trade): ?>
      <div class="cq-feed-item cq-trade-row">
        <div><strong><?= $h($trade['offerer_name']) ?></strong><div>oferece <b><?= $h($trade['offered_name']) ?></b> por <b><?= $h($trade['requested_name']) ?></b></div></div>
        <div class="cq-actions-inline">
          <form method="post" action="/studenti/correio/troca/<?= (int)$trade['id'] ?>/aceitar"><button class="cq-mini-ok" type="submit">Aceitar</button></form>
          <form method="post" action="/studenti/correio/troca/<?= (int)$trade['id'] ?>/recusar"><button class="cq-mini-no" type="submit">Recusar</button></form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <div class="cq-social-grid">
    <section class="cq-social-card">
      <h2><i class="fa-solid fa-inbox"></i> Recebidos</h2>
      <div class="cq-feed">
        <?php foreach (($receivedNotes ?? []) as $note): ?><article class="cq-feed-item"><span class="cq-feed-icon"><i class="fa-regular fa-envelope"></i></span><div><strong><?= $h($note['other_name']) ?></strong><p><?= $h($note['message_text']) ?><?php if(!empty($note['custom_text'])): ?><br><em>“<?= $h($note['custom_text']) ?>”</em><?php endif; ?></p><small><?= $h($note['created_at']) ?></small></div></article><?php endforeach; ?>
        <?php foreach (($receivedGifts ?? []) as $gift): ?><article class="cq-feed-item cq-gift-item"><span class="cq-feed-icon"><i class="fa-solid <?= $h($gift['gift_icon'] ?: 'fa-gift') ?>"></i></span><div><strong><?= $h($gift['other_name']) ?></strong><p>enviou <b><?= $h($gift['gift_name'] ?: ('Carta de '.$gift['card_name'])) ?></b><?php if(!empty($gift['note'])): ?><br><em>“<?= $h($gift['note']) ?>”</em><?php endif; ?></p><small><?= $h($gift['created_at']) ?></small></div></article><?php endforeach; ?>
        <?php if (($receivedNotes ?? []) === [] && ($receivedGifts ?? []) === []): ?><p class="cq-empty">Seu correio ainda está vazio.</p><?php endif; ?>
      </div>
    </section>

    <section class="cq-social-card">
      <h2><i class="fa-solid fa-clock-rotate-left"></i> Trocas enviadas</h2>
      <div class="cq-feed">
        <?php foreach (($sentTrades ?? []) as $trade): ?><article class="cq-feed-item"><span class="cq-feed-icon"><i class="fa-solid fa-right-left"></i></span><div><strong><?= $h($trade['recipient_name']) ?></strong><p><?= $h($trade['offered_name']) ?> ⇄ <?= $h($trade['requested_name']) ?></p><small>Status: <?= $h($trade['status']) ?> · <?= $h($trade['created_at']) ?></small></div></article><?php endforeach; ?>
        <?php if (($sentTrades ?? []) === []): ?><p class="cq-empty">Nenhuma troca proposta ainda.</p><?php endif; ?>
      </div>
    </section>
  </div>

  <?php if (($cosmetics ?? []) !== []): ?>
  <section class="cq-social-card cq-wide">
    <h2><i class="fa-solid fa-wand-magic-sparkles"></i> Seus visuais recebidos</h2>
    <div class="cq-cosmetics-grid">
      <?php foreach ($cosmetics as $cos): ?><div class="cq-cosmetic"><i class="fa-solid <?= $h($cos['icon']) ?>"></i><strong><?= $h($cos['name']) ?></strong><small><?= $h(str_replace('_',' ',$cos['cosmetic_slot'])) ?></small><?php if((int)$cos['equipped']===1): ?><span class="cq-equipped">Em uso</span><?php else: ?><form method="post" action="/studenti/correio/cosmetico/<?= (int)$cos['gift_catalog_id'] ?>/usar"><button type="submit">Usar</button></form><?php endif; ?></div><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="cq-social-card cq-wide">
    <h2><i class="fa-solid fa-receipt"></i> Extrato de Lúmens</h2>
    <div class="cq-ledger">
      <?php foreach (($ledger ?? []) as $entry): ?><div><span><?= $h($entry['description']) ?></span><strong class="<?= (int)$entry['delta']>=0?'plus':'minus' ?>"><?= (int)$entry['delta']>0?'+':'' ?><?= (int)$entry['delta'] ?></strong><small>saldo <?= (int)$entry['balance_after'] ?> · <?= $h($entry['created_at']) ?></small></div><?php endforeach; ?>
      <?php if (($ledger ?? []) === []): ?><p class="cq-empty">Os próximos ganhos e gastos de Lúmens aparecerão aqui.</p><?php endif; ?>
    </div>
  </section>
</div>
