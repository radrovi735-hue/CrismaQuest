(() => {
  'use strict';

  const endpoint = '/studenti/recompensas/pendentes';
  let csrf = '';

  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  })[char]);

  const ensureStyles = () => {
    if (document.getElementById('cqRewardNotificationStyles')) return;
    const style = document.createElement('style');
    style.id = 'cqRewardNotificationStyles';
    style.textContent = `
      .cq-reward-overlay{position:fixed;inset:0;z-index:2147483500;background:rgba(5,22,29,.78);display:grid;place-items:center;padding:20px;backdrop-filter:blur(5px)}
      .cq-reward-modal{width:min(92vw,430px);max-height:88vh;overflow:auto;background:#fffaf1;color:#17313a;border:1px solid #d7b66d;border-radius:24px;box-shadow:0 28px 80px rgba(0,0,0,.4);padding:24px;text-align:center;animation:cqRewardIn .28s ease-out}
      .cq-reward-kicker{font:800 12px/1.2 system-ui,sans-serif;letter-spacing:.13em;text-transform:uppercase;color:#8a641f;margin-bottom:8px}
      .cq-reward-modal h2{font:700 28px/1.1 Georgia,serif;margin:6px 0 10px;color:#0d3a4a}
      .cq-reward-modal p{font:15px/1.55 system-ui,sans-serif;margin:0 auto 18px;max-width:34ch}
      .cq-reward-art{width:190px;aspect-ratio:3/4;margin:10px auto 16px;border-radius:18px;overflow:hidden;border:3px solid #c8a55c;background:#eadfc7;box-shadow:0 12px 30px rgba(13,58,74,.22)}
      .cq-reward-art.is-light{box-shadow:0 0 0 4px rgba(222,183,79,.18),0 12px 38px rgba(190,139,24,.35)}
      .cq-reward-art img{width:100%;height:100%;object-fit:cover;display:block}
      .cq-reward-icon{width:92px;height:92px;border-radius:50%;margin:12px auto 18px;display:grid;place-items:center;background:#0d3a4a;color:#efd890;font-size:38px;border:3px solid #c8a55c}
      .cq-reward-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
      .cq-reward-actions button,.cq-reward-actions a{border:0;border-radius:999px;padding:11px 17px;font:700 14px system-ui,sans-serif;text-decoration:none;cursor:pointer}
      .cq-reward-primary{background:#0d3a4a;color:#fffaf1}
      .cq-reward-secondary{background:#eadfc7;color:#17313a}
      .cq-reward-counter{margin-top:13px;font:600 12px system-ui,sans-serif;color:#6d6b64}
      @keyframes cqRewardIn{from{opacity:0;transform:translateY(15px) scale(.97)}to{opacity:1;transform:none}}
      @media(max-width:480px){.cq-reward-modal{padding:20px 16px}.cq-reward-art{width:160px}.cq-reward-modal h2{font-size:24px}}
    `;
    document.head.appendChild(style);
  };

  const markSeen = async id => {
    if (!csrf || !id) return;
    try {
      await fetch('/studenti/recompensas/' + encodeURIComponent(id) + '/vista', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
        body: new URLSearchParams({csrf_token: csrf}),
        credentials: 'same-origin'
      });
    } catch (_) {}
  };

  const modalFor = item => {
    const payload = item.payload || {};
    const type = item.notification_type || '';
    const overlay = document.createElement('div');
    overlay.className = 'cq-reward-overlay';
    overlay.setAttribute('role','dialog');
    overlay.setAttribute('aria-modal','true');

    let kicker = 'RECOMPENSA';
    let visual = '<div class="cq-reward-icon"><i class="fa-solid fa-gift"></i></div>';

    if (type === 'card') {
      const illuminated = payload.edition_type === 'illuminated';
      kicker = illuminated ? 'EDIÇÃO ILUMINADA' : (payload.is_new ? 'NOVA CARTA' : 'CARTA REPETIDA');
      const fallback = payload.fallback ? ` data-fallback="${esc(payload.fallback)}"` : '';
      const onerror = payload.fallback
        ? ` onerror="if(this.dataset.fallback){this.onerror=null;this.src=this.dataset.fallback;}"`
        : '';
      visual = `<div class="cq-reward-art${illuminated ? ' is-light' : ''}"><img src="${esc(payload.image || '')}" alt="${esc(payload.name || '')}" referrerpolicy="no-referrer"${fallback}${onerror}></div>`;
    } else if (type === 'badge') {
      kicker = 'NOVA CONQUISTA';
      visual = `<div class="cq-reward-icon"><i class="fa-solid ${esc(payload.icon || 'fa-award')}"></i></div>`;
    } else if (type === 'album_complete') {
      kicker = '40 DE 40';
      visual = '<div class="cq-reward-icon"><i class="fa-solid fa-trophy"></i></div>';
    } else if (type === 'milestone') {
      kicker = 'NOVO MARCO';
      visual = `<div class="cq-reward-icon"><i class="fa-solid ${esc(payload.icon || 'fa-star')}"></i></div>`;
    }

    overlay.innerHTML = `
      <div class="cq-reward-modal">
        <div class="cq-reward-kicker">${kicker}</div>
        ${visual}
        <h2>${esc(type === 'card' && payload.name ? payload.name : item.title)}</h2>
        <p>${esc(item.message || '')}</p>
        <div class="cq-reward-actions">
          ${payload.url ? `<a class="cq-reward-secondary" href="${esc(payload.url)}">${type === 'card' || type === 'album_complete' ? 'Ver no álbum' : 'Ver agora'}</a>` : ''}
          <button type="button" class="cq-reward-primary" data-cq-reward-continue>Continuar</button>
        </div>
      </div>
    `;
    return overlay;
  };

  const showQueue = async notifications => {
    if (!Array.isArray(notifications) || notifications.length === 0) return;
    ensureStyles();

    for (let index = 0; index < notifications.length; index++) {
      const item = notifications[index];
      await new Promise(resolve => {
        const overlay = modalFor(item);
        const modal = overlay.querySelector('.cq-reward-modal');
        const counter = document.createElement('div');
        counter.className = 'cq-reward-counter';
        counter.textContent = notifications.length > 1 ? `${index + 1} de ${notifications.length}` : '';
        modal.appendChild(counter);

        const close = async () => {
          await markSeen(item.id);
          overlay.remove();
          resolve();
        };

        overlay.querySelector('[data-cq-reward-continue]')?.addEventListener('click', close);
        overlay.addEventListener('click', event => {
          if (event.target === overlay) close();
        });
        document.body.appendChild(overlay);
      });
    }
  };

  const load = async () => {
    try {
      const response = await fetch(endpoint, {credentials:'same-origin',cache:'no-store'});
      if (!response.ok) return;
      const data = await response.json();
      if (!data || !data.ok) return;
      csrf = data.csrf_token || '';
      await showQueue(data.notifications || []);
    } catch (_) {}
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load, {once:true});
  } else {
    load();
  }
})();
