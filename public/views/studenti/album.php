<?php
$cards = [
    ['name'=>'São Carlo Acutis','group'=>'Jovens','icon'=>'fa-laptop','locked'=>false,'note'=>'Eucaristia e evangelização digital'],
    ['name'=>'Santa Teresinha do Menino Jesus','group'=>'Doutores','icon'=>'fa-seedling','locked'=>false,'note'=>'Pequena via, confiança e amor'],
    ['name'=>'São Francisco de Assis','group'=>'Missionários','icon'=>'fa-dove','locked'=>false,'note'=>'Paz, simplicidade e fraternidade'],
    ['name'=>'São Pedro','group'=>'Apóstolos','icon'=>'fa-key','locked'=>false,'note'=>'Fé, Igreja e missão apostólica'],
    ['name'=>'Santa Faustina','group'=>'Místicos','icon'=>'fa-heart','locked'=>true,'note'=>'Misericórdia'],
    ['name'=>'São João Paulo II','group'=>'Papas','icon'=>'fa-cross','locked'=>true,'note'=>'Coragem e dignidade humana'],
    ['name'=>'Santa Gianna Beretta Molla','group'=>'Pais e Mães Santos','icon'=>'fa-hand-holding-heart','locked'=>true,'note'=>'Vocação e cuidado com a vida'],
    ['name'=>'Santo Agostinho','group'=>'Doutores','icon'=>'fa-book-open','locked'=>true,'note'=>'Busca de Deus e conversão'],
];
?>
<div class="cq-student-shell">
    <section class="cq-card mb-3">
        <div class="d-flex flex-column flex-sm-row justify-content-between gap-3 align-items-sm-center">
            <div>
                <div class="cq-card-eyebrow">Coleção catequética</div>
                <h2>Álbum dos Santos</h2>
                <p class="mb-0">Conheça testemunhas reais da fé. As cartas são descobertas ao longo da Jornada.</p>
            </div>
            <div style="min-width:180px">
                <div class="d-flex justify-content-between" style="font-family:Arial,sans-serif;font-size:12px;color:#625d56"><strong>4 de 50</strong><span>8%</span></div>
                <div class="cq-progress mt-2" style="background:#e8dcc9"><span style="width:8%"></span></div>
            </div>
        </div>
    </section>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="cq-chip">Todos</span><span class="cq-chip">Jovens</span><span class="cq-chip">Apóstolos</span><span class="cq-chip">Mártires</span><span class="cq-chip">Missionários</span><span class="cq-chip">Doutores</span>
    </div>

    <div class="row g-3">
        <?php foreach ($cards as $card): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <article class="cq-card h-100 text-center" style="padding:10px;<?= $card['locked'] ? 'filter:grayscale(.8);opacity:.72' : '' ?>">
                    <div class="cq-saint-art" style="height:150px;font-size:42px;<?= $card['locked'] ? 'background:linear-gradient(145deg,#5e625d,#8d887f);color:#e7e0d4' : '' ?>">
                        <i class="fa-solid <?= $card['locked'] ? 'fa-lock' : htmlspecialchars($card['icon']) ?>"></i>
                    </div>
                    <div class="cq-card-eyebrow mt-2"><?= htmlspecialchars($card['group']) ?></div>
                    <h3 style="font-size:16px;margin-top:4px"><?= htmlspecialchars($card['name']) ?></h3>
                    <p style="font-size:12px;margin-bottom:4px"><?= $card['locked'] ? 'Descubra esta carta em uma missão ou recompensa.' : htmlspecialchars($card['note']) ?></p>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="cq-card mt-3">
        <div class="cq-card-eyebrow">Regra do Álbum</div>
        <h3>Não existe “santo raro”.</h3>
        <p class="mb-0">Quando houver versões especiais, a diferença será apenas a edição visual da carta. O valor catequético de cada testemunho permanece o mesmo.</p>
    </section>
</div>
