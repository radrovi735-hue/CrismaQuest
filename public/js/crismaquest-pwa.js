(() => {
  'use strict';

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(() => {});
    }, { once: true });
  }

  const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  if (standalone) return;

  let installPrompt = null;
  let button = null;

  const removeButton = () => {
    if (button) button.remove();
    button = null;
  };

  const showButton = () => {
    if (button || !installPrompt) return;
    button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('aria-label', 'Instalar CrismaQuest no celular');
    button.innerHTML = '<span aria-hidden="true">⬇</span> Instalar app';
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
      font: '600 14px system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
      boxShadow: '0 8px 24px rgba(0,0,0,.22)',
      cursor: 'pointer'
    });
    button.addEventListener('click', async () => {
      if (!installPrompt) return;
      button.disabled = true;
      installPrompt.prompt();
      try { await installPrompt.userChoice; } catch (_) {}
      installPrompt = null;
      removeButton();
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
    removeButton();
  });
})();
