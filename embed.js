/**
 * TasadorIA — Widget embebible
 * Uso: <script src="https://anperprimo.com/tasador/embed.js"></script>
 *      <div data-tasador></div>
 *
 * Opciones: data-altura="780" data-ciudad="santa_fe_capital" data-color="#c9a84c"
 * Open Source — MIT License
 */
(function(){
  const TASADOR_URL = 'https://anperprimo.com/tasador/'; // ← cambiar al instalar

  document.querySelectorAll('[data-tasador]').forEach(el=>{
    const h     = el.dataset.altura   || '780';
    const city  = el.dataset.ciudad   || '';
    const color = el.dataset.color    || '#c9a84c';
    const src   = TASADOR_URL + (city ? `?ciudad=${encodeURIComponent(city)}` : '');

    const wrap = document.createElement('div');
    wrap.style.cssText='width:100%;border-radius:12px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.2)';

    const iframe = document.createElement('iframe');
    iframe.src    = src;
    iframe.width  = '100%';
    iframe.height = h + 'px';
    iframe.setAttribute('frameborder','0');
    iframe.setAttribute('scrolling','auto');
    iframe.setAttribute('title','Tasador de Propiedades IA');
    iframe.style.cssText='border:none;display:block';

    wrap.appendChild(iframe);
    el.appendChild(wrap);
  });

  // También responder a postMessage para comunicación iframe ↔ padre
  window.addEventListener('message', e=>{
    if(e.data && e.data.type === 'tasadorIA:result'){
      const ev = new CustomEvent('tasadorResult', {detail: e.data.result});
      document.dispatchEvent(ev);
    }
    if(e.data && e.data.type === 'tasadorIA:resize'){
      document.querySelectorAll('[data-tasador] iframe').forEach(f=>{
        f.height = e.data.height + 'px';
      });
    }
  });
})();
