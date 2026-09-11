<?php
/* Prévia estática: nenhum acesso ao banco, nenhuma autenticação ou recompensa. */
require __DIR__ . '/../../src/Service/CrismaQuestSaintCatalog.php';
$root=dirname(__DIR__,2);
$destination=$argv[1] ?? throw new RuntimeException('Informe a pasta de saída.');
if (!is_dir($destination)) mkdir($destination,0775,true);
$catalog=\App\Service\CrismaQuestSaintCatalog::all();
$cards=array_filter($catalog,static fn($card)=>in_array($card['slug'],['santa-teresinha-menino-jesus','sao-francisco-assis','sao-pedro'],true));
ob_start();
?>
<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CrismaQuest · Primeiras cartas</title><link rel="stylesheet" href="collection.css"><style>html,body{margin:0;background:#f5efe3}body{padding:32px;font-family:Arial,sans-serif}.preview-main{max-width:1040px;margin:auto}.preview-nav{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #d9cdb7;padding:0 0 20px;margin-bottom:32px;font:700 21px Georgia,serif;color:#153f49}.preview-nav small{font:10px Arial,sans-serif;color:#7a7467}.cq-collection-grid{grid-template-columns:repeat(3,minmax(0,1fr));max-width:950px;gap:30px}.preview-source{font:11px/1.6 Arial,sans-serif;color:#737365;margin-top:11px;text-align:center}.preview-source a{color:#516566}.preview-note{font:12px/1.7 Arial,sans-serif;color:#787469;margin-top:25px;border-top:1px solid #dbcfb8;padding-top:18px}@media(max-width:650px){body{padding:20px 16px}.preview-nav{margin-bottom:25px;padding-bottom:16px;font-size:20px}.preview-nav small{max-width:130px;text-align:right;font-size:9px;line-height:1.5}.cq-collection-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:22px 13px}.preview-source{font-size:9px}.preview-note{font-size:10px}}</style>
<main class="preview-main cq-collection"><header class="preview-nav"><span>CrismaQuest</span><small>Paróquia Nossa Senhora dos Remédios<br>Arquidiocese de Fortaleza</small></header><section class="cq-collection-hero"><div><div class="cq-collection-eyebrow">Álbum dos Santos · Estudo visual</div><h1>Vidas que iluminam<br>o caminho.</h1><p>Três imagens reais, uma mesma identidade. Cada obra permanece inteira, com suas cores e proporções originais.</p></div></section><div class="cq-collection-grid">
<?php foreach ($cards as $card): $card['quantity']=1; if(!empty($card['image_path']))$card['image_path']='images/'.basename($card['image_path']); ?>
<div><?php require $root.'/public/views/components/saintCard.php'; ?><div class="preview-source"><?= htmlspecialchars($card['image_kind']) ?> · <a href="<?= htmlspecialchars($card['source_url']) ?>">Fonte da imagem</a></div></div>
<?php endforeach; ?></div><p class="preview-note">Amostras visuais da coleção. A moldura, o nome e a numeração são elementos do jogo; as imagens dos santos são preservadas.</p></main></html>
<?php
file_put_contents($destination.'/cards.html',ob_get_clean());
copy($root.'/public/css/crismaquest-collection.css',$destination.'/collection.css');
if (!is_dir($destination.'/images')) mkdir($destination.'/images');
foreach($cards as $card) if(!empty($card['image_path'])) copy($root.'/public'.$card['image_path'],$destination.'/images/'.basename($card['image_path']));
file_put_contents($destination.'/mobile.html','<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>CrismaQuest · Conferência no celular</title><style>body{margin:0;background:#e9e4da;font-family:Arial,sans-serif;display:flex;justify-content:center;padding:18px}main{text-align:center}p{color:#4c625e;font-size:12px;margin:0 0 12px}iframe{display:block;width:390px;height:844px;max-width:calc(100vw - 36px);border:1px solid #c5b793;border-radius:22px;background:#f5efe3;box-shadow:0 15px 45px #233a4026}</style><main><p>Prévia responsiva · 390 × 844 px</p><iframe src="cards.html" title="Álbum no celular"></iframe></main></html>');
echo "Prévia gerada em {$destination}\n";
