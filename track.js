(function () {
  const d = document;
  const w = window;
  const n = navigator;

  const script = d.currentScript;
  const endpoint = script?.getAttribute('data-endpoint');
  const token = script?.getAttribute('data-token');
  const redirectUrl = script?.getAttribute('data-redirect');

  if (!endpoint || !token || !redirectUrl) return;

  const signals = {
    token,
    url: w.location.href,
    referrer: d.referrer || '',
    lang: n.language || '',
    tz: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
    platform: n.platform || '',
    userAgent: n.userAgent || '',
    screen: `${screen.width || 0}x${screen.height || 0}`,
    webdriver: !!n.webdriver,
    maxTouchPoints: n.maxTouchPoints || 0,
    ts: Date.now()
  };

  function showStatus(msg, kind) {
    const el = d.getElementById('traffic-status');
    if (!el) return;
    el.textContent = msg;
    el.className = kind ? `traffic-status ${kind}` : 'traffic-status';
  }

  showStatus('Verificando acceso regional de forma segura…', 'info');

  fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(signals)
  })
    .then((res) => res.json())
    .then((result) => {
      if (result.action === 'redirect' && result.target) {
        showStatus(`Acceso permitido para ${result.country}. Redirigiendo…`, 'ok');
        setTimeout(() => w.location.assign(result.target), 800);
        return;
      }

      if (result.action === 'stay') {
        showStatus(result.message || 'Acceso no redirigido por reglas de seguridad.', 'warn');
        return;
      }

      showStatus('No se pudo determinar el estado del tráfico.', 'warn');
    })
    .catch(() => {
      showStatus('No se pudo validar el tráfico en este momento.', 'warn');
    });
})();
