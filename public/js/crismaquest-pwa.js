(() => {
  'use strict';

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(() => {});
    }, { once: true });
  }

  const standalone =
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true;

  if (standalone) return;

  const ua = navigator.userAgent || '';
  const isAndroid = /Android/i.test(ua);
  const isIOS = /iPhone|iPad|iPod/i.test(ua);

  let installPrompt = null;
  let button = null;
  let guide = null;

  const removeGuide = () => {
    if (guide) guide.remove();
    guide = null;
  };

  const showManualGuide = () => {
    removeGuide();
    guide = document.createElement('div');
    guide.setAttribute('role', 'dialog');
    guide.setAttribute('aria-modal', 'true');

    const text = isIOS
      ? 'No Safari, toque em Compartilhar e depois em “Adicionar à Tela de Início”.'
      : 'No Chrome, toque no menu ⋮ e escolha “Instalar app” ou “Adicionar à tela inicial”.';

    guide.innerHTML =
      '<div data-cq-pwa-card>' +
        '<button type="button" data-cq-pwa-close aria-label="Fechar">×</button>' +
        '<strong>Instalar CrismaQuest</strong>' +
        '<p>' + text + '</p>' +
        '<button type="button" data-cq-pwa-ok>Entendi</button>' +
      '</div>';

    Object.assign(guide.style, {
      position: 'fixed',
      inset: '0',
      zIndex: '2147483646',
      display: 'grid',
      placeItems: 'center',
      padding: '20px',
      background: 'rgba(7,24,31,.68)'
    });

    const card = guide.querySelector('[data-cq-pwa-card]');
    Object.assign(card.style, {
      position: 'relative',
      width: 'min(92vw,420px)',
      borderRadius: '18px',
      padding: '24px 22px 20px',
      background: '#fffaf1',
      color: '#17313a',
      boxShadow: '0 18px 50px rgba(0,0,0,.32)',
      font: '15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif'
    });

    const close = guide.querySelector('[data-cq-pwa-close]');
    Object.assign(close.style, {
      position: 'absolute',
      top: '8px',
      right: '12px',
      border: '0',
      background: 'transparent',
      color: '#17313a',
      fontSize: '28px',
      lineHeight: '1',
      cursor: 'pointer'
    });

    const ok = guide.querySelector('[data-cq-pwa-ok]');
    Object.assign(ok.style, {
      border: '0',
      borderRadius: '999px',
      padding: '10px 16px',
      background: '#0d3a4a',
      color: '#fff',
      fontWeight: '700',
      cursor: 'pointer'
    });

    close.addEventListener('click', removeGuide);
    ok.addEventListener('click', removeGuide);
    guide.addEventListener('click', (event) => {
      if (event.target === guide) removeGuide();
    });

    document.body.appendChild(guide);
  };

  const removeButton = () => {
    if (button) button.remove();
    button = null;
  };

  const showButton = () => {
    if (button || (!isAndroid && !isIOS && !installPrompt)) return;

    button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('aria-label', 'Instalar CrismaQuest no celular');
    button.innerHTML = '<span aria-hidden="true">⬇</span> Instalar CrismaQuest';

    Object.assign(button.style, {
      position: 'fixed',
      right: '14px',
      bottom: '86px',
      zIndex: '2147483000',
      border: '1px solid #c8a55c',
      borderRadius: '999px',
      padding: '10px 15px',
      background: '#0d3a4a',
      color: '#fffaf1',
      font: '700 14px system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
      boxShadow: '0 8px 24px rgba(0,0,0,.22)',
      cursor: 'pointer'
    });

    button.addEventListener('click', async () => {
      if (installPrompt) {
        button.disabled = true;
        installPrompt.prompt();
        try {
          const result = await installPrompt.userChoice;
          if (result && result.outcome === 'accepted') removeButton();
        } catch (_) {
          showManualGuide();
        } finally {
          button.disabled = false;
          installPrompt = null;
        }
        return;
      }

      showManualGuide();
    });

    document.body.appendChild(button);
  };

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt = event;
    showButton();
  });

  window.addEventListener('appinstalled', () => {
    installPrompt = null;
    removeGuide();
    removeButton();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showButton, { once: true });
  } else {
    showButton();
  }
})();
