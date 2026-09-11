import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const destination = resolve(process.argv[2] ?? (() => { throw new Error('Informe a pasta de saída.'); })());
const cards = JSON.parse(await readFile(join(root, 'config/crismaquest/saints.json'), 'utf8'));
const css = await readFile(join(root, 'public/css/crismaquest-collection.css'), 'utf8');
const escape = (value) => String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);

await mkdir(join(destination, 'images'), { recursive: true });
for (const card of cards) {
  await copyFile(join(root, 'public', card.image_path), join(destination, 'images', basename(card.image_path)));
}

const cardMarkup = cards.map((card) => `
<article class="cq-saint-card is-collected" data-saint="${escape(card.slug)}">
  <div class="cq-saint-frame">
    <div class="cq-saint-serial"><span>CRISMAQUEST</span><span>Nº ${String(card.card_number).padStart(2, '0')}</span></div>
    <div class="cq-saint-window"><img src="images/${escape(basename(card.image_path))}" alt="${escape(card.name)}" loading="eager"></div>
    <div class="cq-saint-nameplate"><span class="cq-saint-category">${escape(card.category)}</span><h3>${escape(card.name)}</h3><span class="cq-saint-edition">Edição da Jornada</span></div>
  </div>
  <div class="cq-saint-card-footer"><span class="cq-collection-state">✓ Coletada</span><span class="qa-source">${escape(card.image_kind)}</span></div>
</article>`).join('');

const html = (mobile) => `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CrismaQuest · 40 santos</title><style>${css}
html,body{margin:0;background:#f5efe3}.qa-page{max-width:1120px;margin:auto;padding:28px 22px}.qa-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:26px}.qa-head h1{font:42px/1.08 Georgia,serif;color:#173e47;margin:0}.qa-head p{font:13px/1.6 Arial,sans-serif;color:#6b6c62;margin:8px 0 0}.qa-count{font:32px Georgia,serif;color:#173e47}.qa-source{font:9px/1.3 Arial,sans-serif;color:#737365;text-align:right}.cq-saint-card-footer{align-items:flex-start}.qa-mobile{width:390px;max-width:100%;padding:20px 16px}.qa-mobile .qa-head{display:block;margin-bottom:20px}.qa-mobile .qa-head h1{font-size:30px}.qa-mobile .qa-head p{font-size:12px}.qa-mobile .qa-count{display:block;font-size:24px;margin-top:12px}.qa-mobile .cq-collection-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:20px 13px}.qa-mobile .cq-saint-frame{padding:10px 10px 0;border-radius:13px}.qa-mobile .cq-saint-frame:before{inset:7px;border-radius:8px}.qa-mobile .cq-saint-serial{font-size:6px;letter-spacing:.11em;min-height:23px;padding-bottom:7px}.qa-mobile .cq-saint-nameplate{padding:13px 1px;min-height:103px;gap:6px}.qa-mobile .cq-saint-nameplate h3{font-size:17px;overflow-wrap:anywhere}.qa-mobile .cq-saint-category{font-size:7px;letter-spacing:.08em}.qa-mobile .cq-saint-edition{font-size:8px}.qa-mobile .cq-collection-state,.qa-mobile .qa-source{font-size:8px}
</style></head><body><main class="qa-page ${mobile ? 'qa-mobile' : ''}"><header class="qa-head"><div><h1>Vidas que iluminam o caminho.</h1><p>40 imagens reais, uma mesma identidade. Obras preservadas integralmente.</p></div><strong class="qa-count">40 cartas</strong></header><div class="cq-collection-grid">${cardMarkup}</div></main></body></html>`;

await writeFile(join(destination, 'album-40.html'), html(false));
await writeFile(join(destination, 'album-40-mobile.html'), html(true));
console.log(`Prévias geradas em ${destination}`);
