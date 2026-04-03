// tasador/multi_extractor.js
// Extractor universal para múltiples portales inmobiliarios argentinos
// Portales soportados: Zonaprop, Argenprop, Ventafe, Mercado Único, y más

(function() {
    var IMPORT_URL = 'https://yourdomain.com/tasador/api/import_market.php';
    var ADMIN_KEY  = 'YOUR_ADMIN_PASSWORD';
    var ARS_RATE   = 1450;

    var hostname = window.location.hostname.toLowerCase();
    var listings = [];
    var portalName = 'desconocido';

    // ── Detectar portal ───────────────────────────────────────────────────────
    if (hostname.includes('zonaprop'))       { portalName = 'zonaprop';      listings = extractZonaprop(); }
    else if (hostname.includes('argenprop')) { portalName = 'argenprop';     listings = extractArgenprop(); }
    else if (hostname.includes('ventafe'))   { portalName = 'ventafe';       listings = extractVentafe(); }
    else if (hostname.includes('mercado-unico') || hostname.includes('mercadounico')) { portalName = 'mercado_unico'; listings = extractMercadoUnico(); }
    else if (hostname.includes('properati')) { portalName = 'properati';     listings = extractGenerico('properati'); }
    else {
        // Extractor genérico — intenta en cualquier portal
        listings = extractGenerico(hostname.split('.')[0]);
        portalName = hostname.split('.')[0] || 'desconocido';
    }

    // ── Resultado ─────────────────────────────────────────────────────────────
    if (listings.length === 0) {
        alert('❌ No se encontraron propiedades en ' + hostname + '\n\nEste portal puede no estar soportado aún.\nContactar al admin para agregar soporte.'); return;
    }

    var conArea = listings.filter(l => l.covered_area > 0).length;
    var conExp  = listings.filter(l => l.expenses > 0).length;
    var ppm2s   = listings.filter(l => l.covered_area > 0 && l.price > 0).map(l => Math.round(l.price/l.covered_area));
    var avgPpm2 = ppm2s.length ? Math.round(ppm2s.reduce((a,b)=>a+b,0)/ppm2s.length) : 0;

    if (!confirm(
        '📊 TasadorIA — Extractor ' + portalName + '\n\n'
        + '✓ ' + listings.length + ' propiedades encontradas\n'
        + '📍 Ciudad: ' + (listings[0]?.city || '?') + '\n'
        + '📐 Con superficie: ' + conArea + ' / ' + listings.length + '\n'
        + '💰 Con expensas: ' + conExp + ' / ' + listings.length + '\n'
        + (avgPpm2 ? '📈 Promedio USD/m²: $' + avgPpm2.toLocaleString('es-AR') + '\n' : '')
        + '\n¿Enviar a la BD?'
    )) return;

    var ov = makeOverlay('⏳ Enviando ' + listings.length + ' propiedades de ' + portalName + '...');

    fetch(IMPORT_URL, {
        method:  'POST',
        headers: {'Content-Type':'application/json','X-Admin-Key':ADMIN_KEY},
        body:    JSON.stringify({source: portalName + '_bm', ars_usd_rate: ARS_RATE, listings: listings})
    })
    .then(r => r.json())
    .then(d => {
        ov.style.borderColor = '#00c896';
        ov.innerHTML = '<strong style="color:#c9a84c">TasadorIA ✅</strong><br>'
            + '<strong>' + (d.inserted||0) + ' nuevas</strong> · ' + (d.updated||0) + ' actualizadas<br>'
            + (d.errors ? '⚠ ' + d.errors + ' errores<br>' : '')
            + (avgPpm2 ? 'Promedio: $' + avgPpm2.toLocaleString('es-AR') + '/m²<br>' : '')
            + '<small style="color:#7a7a9a">Cierra en 5s</small>';
        setTimeout(() => ov.remove(), 5000);
    })
    .catch(e => { ov.style.borderColor='#ff4f6e'; ov.textContent='❌ '+e.message; setTimeout(()=>ov.remove(),5000); });

    // ════════════════════════════════════════════════════════════════════════
    // EXTRACTORES POR PORTAL
    // ════════════════════════════════════════════════════════════════════════

    function extractZonaprop() {
        var items = getNextData(['listPostings','postings','listings']);
        if (items) return items.map(p => parseZonapropItem(p)).filter(Boolean);
        return extractDomGenerico('zonaprop');
    }

    function extractArgenprop() {
        // Argenprop usa Next.js similar a Zonaprop
        var items = getNextData(['listPostings','postings','listings','props']);
        if (items) return items.map(p => parseArgenpropItem(p)).filter(Boolean);
        return extractDomArgenprop();
    }

    function extractVentafe() {
        // ventafe.com.ar — portal local de Santa Fe, usa WordPress/PHP clásico
        var listings = [];
        var cards = document.querySelectorAll('.property-item, .listing-item, .propiedad, [class*="property"], article');
        if (cards.length === 0) cards = document.querySelectorAll('.col-md-4, .col-sm-6');

        cards.forEach(function(card) {
            var raw = (card.innerText || card.textContent || '').trim();
            if (!raw || raw.length < 30) return;

            var pMatch = raw.match(/(?:USD|U\$D|\$)\s*([\d\.]+)/i);
            if (!pMatch) return;
            var priceRaw = pMatch[1].replace(/\./g,'');
            var price = parseFloat(priceRaw);
            var currency = pMatch[0].toUpperCase().includes('USD') || pMatch[0].includes('U$D') ? 'USD' : 'ARS';
            if (!price || price < 1000) return;

            var areaMatch = raw.match(/(\d+(?:[,\.]\d+)?)\s*m[²2]?\s*(?:tot|cub|total|cubierto|cov)?/i);
            var area = areaMatch ? parseFloat(areaMatch[1].replace(',','.')) : null;

            var dormMatch = raw.match(/(\d+)\s*(?:dorm|hab|dormitorio)/i);
            var ambMatch  = raw.match(/(\d+)\s*amb/i);
            var bathMatch = raw.match(/(\d+)\s*ba[ñn]/i);
            var cochMatch = raw.match(/(\d+)\s*coch|cochera/i);

            var link = card.querySelector('a[href]');
            var titleEl = card.querySelector('h2, h3, h4, .title, .address, [class*="title"]');

            listings.push({
                source: 'ventafe_bm',
                title:  titleEl ? titleEl.textContent.trim().slice(0,200) : '',
                price:  price, currency: currency,
                covered_area: area,
                bedrooms: dormMatch ? parseInt(dormMatch[1]) : null,
                ambientes: ambMatch ? parseInt(ambMatch[1]) : null,
                bathrooms: bathMatch ? parseInt(bathMatch[1]) : null,
                garages:   cochMatch ? 1 : null,
                city:  'Santa Fe', zone: detectZone(),
                operation: window.location.href.includes('alquiler') ? 'alquiler' : 'venta',
                property_type: 'departamento',
                url:  link ? link.href : '',
                scraped_at: nowISO(),
            });
        });
        return listings;
    }

    function extractMercadoUnico() {
        // mercado-unico.com — portal de Santa Fe
        var listings = [];
        // Mercado Único usa PHP/HTML clásico, buscar por estructura de grillas
        var cards = document.querySelectorAll(
            '.ficha, .propiedad, .inmueble, [class*="propiedad"], [class*="listing"], ' +
            '.property, .card, article, .item-inmueble'
        );
        if (cards.length < 2) {
            // Fallback: buscar cualquier elemento con precio en USD
            cards = Array.from(document.querySelectorAll('div, li, article')).filter(function(el) {
                var t = el.textContent;
                return (t.includes('USD') || t.includes('U$S') || t.includes('U$D')) &&
                       t.includes('m²') && t.length < 2000 && t.length > 50;
            });
        }

        var seen = new Set();
        cards.forEach(function(card) {
            var raw = (card.innerText || card.textContent || '').trim();
            if (!raw || raw.length < 40 || raw.length > 3000) return;
            var key = raw.slice(0,60);
            if (seen.has(key)) return;
            seen.add(key);

            var pMatch = raw.match(/(?:USD|U\$S|U\$D)\s*[\$]?\s*([\d\.]+)/i);
            if (!pMatch) return;
            var price = parseFloat(pMatch[1].replace(/\./g,''));
            if (!price || price < 1000) return;

            var areaMatch = raw.match(/(\d+)\s*m[²2]/i);
            var dormMatch = raw.match(/(\d+)\s*(?:dorm|hab)/i);
            var ambMatch  = raw.match(/(\d+)\s*amb/i);
            var link      = card.querySelector('a[href]');

            listings.push({
                source: 'mercado_unico_bm',
                title:  '',
                price:  price, currency: 'USD',
                covered_area: areaMatch ? parseFloat(areaMatch[1]) : null,
                bedrooms:     dormMatch ? parseInt(dormMatch[1]) : null,
                ambientes:    ambMatch  ? parseInt(ambMatch[1])  : null,
                city: 'Santa Fe', zone: detectZone(),
                operation:     window.location.href.includes('alquiler') ? 'alquiler' : 'venta',
                property_type: 'departamento',
                url:  link ? link.href : '',
                scraped_at: nowISO(),
            });
        });
        return listings;
    }

    function extractDomArgenprop() {
        var listings = [];
        document.querySelectorAll('[class*="posting"],[class*="property"],[data-item]').forEach(function(card) {
            var raw = (card.innerText||'').trim();
            if (!raw || raw.length < 30 || raw.length > 3000) return;

            var pMatch = raw.match(/(?:USD|U\$D|\$)\s*([\d\.]+)/i);
            if (!pMatch) return;
            var price = parseFloat(pMatch[1].replace(/\./g,''));
            if (!price || price < 1000) return;
            var curr = pMatch[0].toUpperCase().includes('USD') ? 'USD' : 'ARS';

            var areaMatch = raw.match(/(\d+)\s*m[²2]/i);
            var dormMatch = raw.match(/(\d+)\s*dorm/i);
            var ambMatch  = raw.match(/(\d+)\s*amb/i);
            var expMatch  = raw.match(/\$\s*([\d\.]+)\s*Expensas/i);
            var link      = card.querySelector('a[href*="/propiedades/"],a[href*="/inmueble/"]');

            listings.push({
                source: 'argenprop_bm',
                price: price, currency: curr,
                covered_area: areaMatch ? parseFloat(areaMatch[1]) : null,
                bedrooms: dormMatch ? parseInt(dormMatch[1]) : null,
                ambientes: ambMatch ? parseInt(ambMatch[1]) : null,
                expenses: expMatch ? parseFloat(expMatch[1].replace(/\./g,'')) : null,
                city: detectCity(), zone: detectZone(),
                operation: window.location.href.includes('alquiler') ? 'alquiler' : 'venta',
                property_type: 'departamento',
                url: link ? link.href : '',
                scraped_at: nowISO(),
            });
        });
        return listings;
    }

    function extractDomGenerico(src) {
        var listings = [];
        var seen = new Set();
        // Buscar cualquier elemento con precio en USD/ARS y metros cuadrados
        var candidates = Array.from(document.querySelectorAll('article, li, div, section')).filter(function(el) {
            var t = el.textContent;
            return (t.includes('USD') || t.includes('U$D') || t.includes('U$S')) &&
                   (t.includes('m²') || t.includes('m2')) &&
                   t.length > 40 && t.length < 3000;
        });

        // Tomar solo los más pequeños (cards específicos, no wrappers)
        candidates = candidates.filter(function(el) {
            return !candidates.some(function(other) {
                return other !== el && other.contains(el) && other.textContent.length > el.textContent.length;
            });
        });

        candidates.slice(0,50).forEach(function(el) {
            var raw = (el.innerText || el.textContent || '').trim();
            var key = raw.slice(0,60);
            if (seen.has(key)) return;
            seen.add(key);

            var pMatch = raw.match(/(?:USD|U\$D|U\$S)\s*[\$]?\s*([\d\.]+)/i);
            if (!pMatch) return;
            var price = parseFloat(pMatch[1].replace(/\./g,''));
            if (!price || price < 1000) return;

            var areaMatch = raw.match(/(\d+(?:[,\.]\d+)?)\s*m[²2]/i);
            listings.push({
                source: src + '_bm',
                price: price, currency: 'USD',
                covered_area: areaMatch ? parseFloat(areaMatch[1].replace(',','.')) : null,
                city: detectCity(), zone: detectZone(),
                property_type: 'departamento',
                operation: window.location.href.includes('alquiler') ? 'alquiler' : 'venta',
                url: el.querySelector('a')?.href || window.location.href,
                scraped_at: nowISO(),
            });
        });
        return listings;
    }

    // ── Zonaprop item parser ──────────────────────────────────────────────────
    function parseZonapropItem(p) {
        var price = 0, currency = 'USD';
        if (p.price) {
            if (typeof p.price === 'object') { price = parseFloat(p.price.amount||p.price.value||0); currency = (p.price.currency||'USD').replace('U$D','USD').toUpperCase(); }
            else price = parseFloat(p.price)||0;
        }
        if (!price) return null;
        var area = null;
        for (var f of ['totalArea','roofedArea','coveredArea','surface']) { var v=parseFloat(p[f]||0); if (v>5&&v<5000){area=v;break;} }
        return {
            source:'zonaprop_bm', title:p.title||p.address||'',
            price, currency, covered_area:area,
            ambientes: parseInt(p.ambiences||p.rooms||0)||null,
            bedrooms:  parseInt(p.bedrooms||0)||null,
            bathrooms: parseInt(p.bathrooms||0)||null,
            garages:   parseInt(p.parkingLots||p.garages||0)||null,
            expenses:  parseFloat(p.expenses?.amount||p.maintenanceFee||0)||null,
            address:   p.address||'', city:p.postingLocation?.city?.name||detectCity(),
            zone:      p.postingLocation?.neighborhood?.name||detectZone(),
            lat:       parseFloat(p.geo?.lat||0)||null, lng:parseFloat(p.geo?.lon||0)||null,
            property_type: normType(p.propertyType||''),
            operation: normOp(p.operationType||''),
            url: p.url?'https://www.zonaprop.com.ar'+p.url:'',
            scraped_at: nowISO(),
        };
    }

    function parseArgenpropItem(p) {
        var price = parseFloat(p.precio||p.price||0);
        if (!price) return null;
        var curr = (p.currency||p.moneda||'USD').toUpperCase().includes('USD') ? 'USD' : 'ARS';
        return {
            source:'argenprop_bm', title:p.titulo||p.title||'',
            price, currency:curr,
            covered_area: parseFloat(p.superficie||p.area||p.m2||0)||null,
            bedrooms:  parseInt(p.dormitorios||p.bedrooms||0)||null,
            bathrooms: parseInt(p.banos||p.bathrooms||0)||null,
            garages:   parseInt(p.cocheras||p.garages||0)||null,
            address:   p.direccion||p.address||'',
            city:      p.ciudad||detectCity(), zone:p.barrio||detectZone(),
            property_type: normType(p.tipo||'departamento'),
            operation: normOp(p.tipoOperacion||'venta'),
            url: p.url||'', scraped_at: nowISO(),
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function getNextData(paths) {
        try {
            var nd = document.getElementById('__NEXT_DATA__');
            if (!nd) return null;
            var json = JSON.parse(nd.textContent);
            for (var path of paths) {
                var items = json?.props?.pageProps?.[path] || json?.props?.initialProps?.[path];
                if (Array.isArray(items) && items.length > 0) return items;
            }
        } catch(e) {}
        return null;
    }

    function detectCity() {
        var u = window.location.pathname.toLowerCase() + ' ' + window.location.hostname.toLowerCase();
        if (u.includes('capital-federal')||u.includes('buenos-aires')||u.includes('palermo')||u.includes('recoleta')) return 'Buenos Aires';
        if (u.includes('santa-fe')||u.includes('candioti')||u.includes('ventafe')) return 'Santa Fe';
        if (u.includes('rosario')) return 'Rosario';
        if (u.includes('cordoba')) return 'Córdoba';
        return 'Santa Fe';
    }

    function detectZone() {
        var u = window.location.pathname.toLowerCase();
        var z = {'candioti-norte':'Candioti Norte','candioti':'Candioti Sur','microcentro':'Centro','palermo':'Palermo','recoleta':'Recoleta','belgrano':'Belgrano'};
        for (var k in z) if (u.includes(k)) return z[k];
        return '';
    }

    function normType(t) {
        t=(t||'').toLowerCase();
        if (t.includes('depto')||t.includes('apart')||t==='apartment') return 'departamento';
        if (t.includes('casa')||t==='house') return 'casa';
        if (t.includes('ph')) return 'ph';
        if (t.includes('terreno')||t==='land') return 'terreno';
        if (t.includes('local')||t.includes('comercial')) return 'local-comercial';
        if (t.includes('cochera')) return 'cochera';
        return 'departamento';
    }

    function normOp(o) { return ((o||'').toLowerCase().includes('rent')||(o||'').toLowerCase().includes('alquil'))?'alquiler':'venta'; }

    function nowISO() { return new Date().toISOString().slice(0,19).replace('T',' '); }

    function makeOverlay(text) {
        var ov = document.createElement('div');
        ov.style.cssText = 'position:fixed;top:20px;right:20px;background:#1e2235;border:2px solid #c9a84c;border-radius:12px;padding:16px 20px;z-index:99999;color:#e8e8f0;font-family:system-ui;font-size:14px;min-width:280px;box-shadow:0 8px 32px rgba(0,0,0,.6);line-height:1.6';
        ov.innerHTML = '<strong style="color:#c9a84c">TasadorIA</strong><br>' + text;
        document.body.appendChild(ov);
        return ov;
    }
})();
