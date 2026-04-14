/**
 * TasadorIA — zp_extractor.js
 * Extractor dedicado para Zonaprop.com.ar
 * Funciona como bookmarklet: se inyecta desde el admin y corre en el contexto de Zonaprop.
 *
 * Bookmarklet:
 * javascript:(function(){var s=document.createElement('script');s.src='https://anperprimo.com/tasador/zp_extractor.js?key=anper2025&t='+Date.now();document.head.appendChild(s);})();
 */
(function () {
    'use strict';

    var IMPORT_URL = 'https://anperprimo.com/tasador/api/import_market.php';
    var ADMIN_KEY  = 'anper2025';
    var SOURCE     = 'zonaprop_bm';

    // ── 1. Extraer datos ────────────────────────────────────────────────────
    var listings = [];

    listings = tryNextData() || tryDOM();

    if (!listings || listings.length === 0) {
        alert('❌ TasadorIA · Zonaprop\n\nNo se encontraron propiedades.\n\nAsegurate de estar en una página de resultados (no en un aviso individual).\nEj: zonaprop.com.ar/departamentos-venta-santa-fe.html');
        return;
    }

    // ── 2. Confirmar ────────────────────────────────────────────────────────
    var conArea  = listings.filter(l => l.covered_area > 0).length;
    var ppm2List = listings.filter(l => l.covered_area > 0 && l.price > 0).map(l => Math.round(l.price / l.covered_area));
    var avgPpm2  = ppm2List.length ? Math.round(ppm2List.reduce((a, b) => a + b, 0) / ppm2List.length) : 0;
    var city     = listings[0]?.city || detectCity();

    if (!confirm(
        '🏠 TasadorIA · Zonaprop\n\n'
        + '✓ ' + listings.length + ' propiedades encontradas\n'
        + '📍 Ciudad detectada: ' + city + '\n'
        + '📐 Con superficie: ' + conArea + '/' + listings.length + '\n'
        + (avgPpm2 ? '💵 Promedio: USD ' + avgPpm2.toLocaleString('es-AR') + '/m²\n' : '')
        + '\n¿Enviar a TasadorIA?'
    )) return;

    // ── 3. Enviar ───────────────────────────────────────────────────────────
    var overlay = showOverlay('⏳ Enviando ' + listings.length + ' propiedades…');

    fetch(IMPORT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Admin-Key': ADMIN_KEY },
        body: JSON.stringify({ source: SOURCE, listings: listings })
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        overlay.style.borderColor = '#00c896';
        overlay.innerHTML =
            '<strong style="color:#c9a84c;font-size:16px">TasadorIA ✅</strong><br><br>'
            + '<strong style="font-size:18px">' + (d.inserted || 0) + ' nuevas</strong> · '
            + (d.updated || 0) + ' actualizadas<br>'
            + (d.errors ? '⚠ ' + d.errors + ' errores<br>' : '')
            + (avgPpm2 ? '📈 Promedio: $' + avgPpm2.toLocaleString('es-AR') + '/m²<br>' : '')
            + '<small style="color:#888;margin-top:8px;display:block">Cierra en 5s</small>';
        setTimeout(function () { overlay.remove(); }, 5000);
    })
    .catch(function (e) {
        overlay.style.borderColor = '#ff4f6e';
        overlay.innerHTML = '<strong style="color:#ff4f6e">❌ Error</strong><br>' + e.message
            + '<br><small style="color:#888">Cierra en 8s</small>';
        setTimeout(function () { overlay.remove(); }, 8000);
    });

    // ════════════════════════════════════════════════════════════════════════
    // EXTRACCIÓN NEXT.JS
    // ════════════════════════════════════════════════════════════════════════
    function tryNextData() {
        try {
            var el = document.getElementById('__NEXT_DATA__');
            if (!el) return null;
            var json = JSON.parse(el.textContent);

            // Rutas conocidas donde Zonaprop guarda los listings (van cambiando)
            var rutas = [
                // 2025 - estructura actual
                ['props', 'pageProps', 'postings'],
                ['props', 'pageProps', 'listPostings'],
                ['props', 'pageProps', 'searchResult', 'listings'],
                ['props', 'pageProps', 'searchResult', 'postings'],
                ['props', 'pageProps', 'results'],
                ['props', 'pageProps', 'data', 'postings'],
                ['props', 'pageProps', 'data', 'listings'],
                // Fallbacks antiguos
                ['props', 'initialProps', 'listPostings'],
                ['props', 'initialProps', 'postings'],
                ['props', 'initialProps', 'pageProps', 'listPostings'],
                ['props', 'initialProps', 'pageProps', 'postings'],
            ];

            for (var i = 0; i < rutas.length; i++) {
                var items = deepGet(json, rutas[i]);
                if (Array.isArray(items) && items.length > 0) {
                    console.log('[TasadorIA] Zonaprop: encontré ' + items.length + ' items en ruta:', rutas[i].join('.'));
                    // Log del primer item para diagnóstico
                    console.log('[TasadorIA] Primer item raw:', JSON.stringify(items[0], null, 2));
                    var parsed = items.map(parseZPItem).filter(Boolean);
                    if (parsed.length > 0) return parsed;
                }
            }

            // Búsqueda recursiva de arrays grandes que parecen listings
            var found = searchForListings(json, 0);
            if (found && found.length > 0) {
                console.log('[TasadorIA] Zonaprop: encontré listings por búsqueda recursiva:', found.length);
                return found.map(parseZPItem).filter(Boolean);
            }

        } catch (e) {
            console.warn('[TasadorIA] Error parseando __NEXT_DATA__:', e);
        }
        return null;
    }

    // Navega recursivamente el JSON buscando arrays que parezcan listings
    function searchForListings(obj, depth) {
        if (!obj || typeof obj !== 'object' || depth > 8) return null;
        if (Array.isArray(obj)) {
            if (obj.length >= 3 && obj[0] && typeof obj[0] === 'object') {
                // ¿Parece un listing? Tiene price o price.amount
                var sample = obj[0];
                if (sample.price !== undefined || sample.operationType !== undefined || sample.address !== undefined) {
                    return obj;
                }
            }
            for (var i = 0; i < Math.min(obj.length, 5); i++) {
                var r = searchForListings(obj[i], depth + 1);
                if (r) return r;
            }
        } else {
            for (var key in obj) {
                if (['_events','children','__N_SSP','buildId','runtimeConfig'].includes(key)) continue;
                var r = searchForListings(obj[key], depth + 1);
                if (r) return r;
            }
        }
        return null;
    }

    function parseZPItem(p) {
        if (!p || typeof p !== 'object') return null;

        // Precio
        var price = 0, currency = 'USD';
        if (p.price) {
            if (typeof p.price === 'object') {
                price    = parseFloat(p.price.amount || p.price.value || p.price.total || 0);
                currency = normCurrency(p.price.currency || p.price.moneda || 'USD');
            } else {
                price = parseFloat(p.price) || 0;
            }
        } else if (p.priceTotal) {
            price    = parseFloat(p.priceTotal.amount || p.priceTotal || 0);
            currency = normCurrency(p.priceTotal.currency || 'USD');
        }
        if (!price || price < 1000) return null;

        // Superficie — estrategia en cascada, auto-descubre el campo correcto
        var area = null;

        // 1. Campos directos — nombres conocidos (viejo y nuevo)
        var areaFields = [
            'roofedSurface','totalSurface','coveredSurface','builtSurface',
            'roofedArea','coveredArea','totalArea','builtArea',
            'surface','superficie','superficieCubierta','superficieTotal',
            'squareMeters','sqm','m2','meters',
        ];
        for (var fi = 0; fi < areaFields.length; fi++) {
            var fv = parseFloat(p[areaFields[fi]] || 0);
            if (fv > 5 && fv < 5000) { area = fv; break; }
        }

        // 2. Array "attributes" [{id/key, value/valueLabel}]
        if (!area) {
            var attrArrays = [p.attributes, p.features, p.characteristics, p.detalles, p.specs];
            for (var ai = 0; ai < attrArrays.length; ai++) {
                if (!Array.isArray(attrArrays[ai])) continue;
                for (var aj = 0; aj < attrArrays[ai].length; aj++) {
                    var attr = attrArrays[ai][aj];
                    var attrKey = (attr.id || attr.key || attr.name || attr.label || '').toLowerCase();
                    var isArea  = attrKey.includes('surface') || attrKey.includes('area') ||
                                  attrKey.includes('m2') || attrKey.includes('sup') ||
                                  attrKey.includes('meter') || attrKey.includes('sqm');
                    if (isArea) {
                        var attrVal = parseFloat(
                            (attr.value || attr.valueLabel || attr.amount || attr.text || '')
                            .toString().replace(/[^\d.]/g, '')
                        ) || 0;
                        if (attrVal > 5 && attrVal < 5000) { area = attrVal; break; }
                    }
                }
                if (area) break;
            }
        }

        // 3. Escaneo automático: buscar CUALQUIER campo numérico razonable para superficie
        //    — prioriza campos con 'surface'/'area'/'sup' en el nombre
        if (!area) {
            var scanCandidates = [];
            (function scanObj(obj, prefix, depth) {
                if (!obj || typeof obj !== 'object' || depth > 3) return;
                if (Array.isArray(obj)) return;
                Object.keys(obj).forEach(function(k) {
                    var fullKey = prefix ? prefix + '.' + k : k;
                    var val = obj[k];
                    if (typeof val === 'number' && val > 5 && val < 5000 && val === Math.floor(val)) {
                        scanCandidates.push({ key: fullKey, val: val });
                    } else if (typeof val === 'string') {
                        var num = parseFloat(val.replace(/[^\d.]/g,''));
                        if (num > 5 && num < 5000 && /^\d+(\.\d+)?\s*m/i.test(val.trim())) {
                            scanCandidates.push({ key: fullKey, val: num });
                        }
                    } else if (val && typeof val === 'object' && !Array.isArray(val)) {
                        scanObj(val, fullKey, depth + 1);
                    }
                });
            })(p, '', 0);

            // Primero campos cuyo nombre sugiere superficie
            var areaPriority = scanCandidates.filter(function(c) {
                var k = c.key.toLowerCase();
                return k.includes('surface') || k.includes('area') || k.includes('sup') ||
                       k.includes('m2') || k.includes('sqm') || k.includes('meter');
            });
            // Si no, usar el primer entero razonable (>20m², típico para depto)
            var areaFallback = scanCandidates.filter(function(c) { return c.val > 20 && c.val < 800; });

            if (areaPriority.length)  { area = areaPriority[0].val;  console.log('[TasadorIA] área auto:', areaPriority[0]); }
            else if (areaFallback.length) { area = areaFallback[0].val; console.log('[TasadorIA] área fallback:', areaFallback[0]); }
        }

        // 4. Regex en texto del aviso
        if (!area) {
            var textM2 = (p.title || p.description || p.titulo || p.address || '');
            var m2rx   = textM2.match(/(\d{2,4})\s*m[²2]/i);
            if (m2rx) { var m2v = parseFloat(m2rx[1]); if (m2v > 5 && m2v < 5000) area = m2v; }
        }

        // Ubicación
        var loc   = p.postingLocation || p.location || p.ubicacion || {};
        var city  = (loc.city && (loc.city.name || loc.city)) || (loc.ciudad && (loc.ciudad.name || loc.ciudad)) || detectCity();
        var zone  = (loc.neighborhood && (loc.neighborhood.name || loc.neighborhood)) || (loc.barrio && (loc.barrio.name || loc.barrio)) || loc.zona || detectZone();
        var lat   = parseFloat(loc.lat || loc.latitude || (loc.geo && loc.geo.lat) || p.lat || 0) || null;
        var lng   = parseFloat(loc.lon || loc.lng || loc.longitude || (loc.geo && loc.geo.lon) || p.lng || 0) || null;

        // Tipo de operación
        var op    = normOp(p.operationType || p.operation || p.tipoOperacion || '');
        var tipo  = normType(p.propertyType || p.type || p.tipo || p.propertyTypeName || '');

        // Ambientes — también puede estar en attributes
        var ambs  = parseInt(p.ambiences || p.rooms || p.ambientes || p.totalRooms || 0) || null;
        if (!ambs && Array.isArray(p.attributes)) {
            var ambAttr = p.attributes.find(function(a){ return (a.id||'').toLowerCase().includes('room') || (a.id||'').toLowerCase().includes('amb'); });
            if (ambAttr) ambs = parseInt(ambAttr.value) || null;
        }
        var beds  = parseInt(p.bedrooms  || p.dormitorios || 0) || null;
        var baths = parseInt(p.bathrooms || p.banos || 0) || null;
        var cars  = parseInt(p.parkingLots || p.garages || p.cocheras || 0) || null;

        // Expensas
        var exp   = null;
        if (p.expenses) {
            exp = typeof p.expenses === 'object'
                ? parseFloat(p.expenses.amount || p.expenses.value || 0) || null
                : parseFloat(p.expenses) || null;
        } else if (p.maintenanceFee) {
            exp = parseFloat(p.maintenanceFee) || null;
        } else if (Array.isArray(p.attributes)) {
            var expAttr = p.attributes.find(function(a){ return (a.id||'').toLowerCase().includes('expens') || (a.id||'').toLowerCase().includes('maintenance'); });
            if (expAttr) exp = parseFloat((expAttr.value||'').replace(/[^\d.]/g,'')) || null;
        }

        // URL
        var url = '';
        if (p.url) url = p.url.startsWith('http') ? p.url : 'https://www.zonaprop.com.ar' + p.url;
        else if (p.link) url = p.link.startsWith('http') ? p.link : 'https://www.zonaprop.com.ar' + p.link;

        return {
            source:        SOURCE,
            title:         p.title || p.titulo || p.address || p.direccion || '',
            price:         price,
            currency:      currency,
            covered_area:  area,
            ambientes:     ambs,
            bedrooms:      beds,
            bathrooms:     baths,
            garages:       cars,
            expenses:      exp,
            address:       p.address || p.direccion || '',
            city:          city,
            zone:          zone,
            lat:           lat,
            lng:           lng,
            property_type: tipo,
            operation:     op,
            url:           url,
            scraped_at:    new Date().toISOString(),
        };
    }

    // ════════════════════════════════════════════════════════════════════════
    // EXTRACTOR DOM — estructura actual de Zonaprop (2025)
    // ════════════════════════════════════════════════════════════════════════
    function tryDOM() {
        // Zonaprop 2025: cada aviso es un article con data-id o data-qa="posting"
        var cards = document.querySelectorAll([
            'article[data-id]',
            'article[data-qa="posting"]',
            'div[data-qa="posting"]',
            '[data-id][class*="Posting"]',
            '[data-id][class*="posting"]',
            'div[class*="postingCard"]',
            'div[class*="PostingCard"]',
        ].join(','));

        if (!cards || cards.length === 0) {
            cards = document.querySelectorAll('[data-id],[data-posting-id]');
        }
        if (!cards || cards.length === 0) return [];

        // Filtrar los que no son listings reales (anuncios, banners)
        var realCards = Array.from(cards).filter(function(c) {
            return c.querySelector('a[href*="/propiedades/"]') ||
                   c.querySelector('a[href*="-venta-"]') ||
                   c.querySelector('a[href*="-alquiler-"]') ||
                   c.querySelector('[data-qa="posting-price"]') ||
                   c.querySelector('[class*="Price"]');
        });
        if (realCards.length === 0) realCards = Array.from(cards);

        console.log('[TasadorIA] Zonaprop DOM: procesando', realCards.length, 'cards');
        if (realCards.length > 0) {
            console.log('[TasadorIA] Primer card HTML (primeros 800 chars):', realCards[0].innerHTML.substring(0, 800));
        }

        var results = [];
        var city    = detectCity();
        var zone    = detectZone();
        var tipo    = detectTypeFromUrl();
        var op      = detectOpFromUrl();

        // ── Helpers de parseo formato argentino ─────────────────────────────
        // "85.000" → 85000 | "1.200.000" → 1200000 | "85,5" → 85.5
        function parseArPrice(s) {
            s = s.trim().replace(/\s/g, '');
            // Patrón miles con punto: dígitos separados por grupos de 3 después del punto
            if (/^\d{1,3}(\.\d{3})+$/.test(s)) return parseFloat(s.replace(/\./g, ''));
            // Coma decimal: "85,5"
            if (/,/.test(s)) return parseFloat(s.replace(/\./g, '').replace(',', '.'));
            return parseFloat(s) || 0;
        }

        function parseArArea(s) {
            s = (s || '').trim().replace(/\s/g, '');
            // "1.200" → miles → 1200
            if (/^\d{1,3}(\.\d{3})+$/.test(s)) return parseFloat(s.replace(/\./g, ''));
            // "65,5" → decimal
            if (/,/.test(s)) return parseFloat(s.replace(/\./g, '').replace(',', '.'));
            return parseFloat(s) || 0;
        }

        realCards.forEach(function (card) {
            try {
                // ── Datos desde el ALT de la imagen (muy confiable en Zonaprop) ──
                // Zonaprop escribe: "Departamento · 114m² · 3 Ambientes · 1 Cochera · Venta..."
                var altText = '';
                var imgEl = card.querySelector('img[alt]');
                if (imgEl) altText = imgEl.getAttribute('alt') || '';

                // ── Precio ──────────────────────────────────────────────
                var priceNum = 0, currency = 'USD';
                var cardText = card.textContent;

                // Regex sobre TODO el texto del card — busca token "USD NNN.NNN" o "$ NNN.NNN.NNN"
                // Captura el PRIMERO que aparezca para evitar mezclar USD + ARS
                var usdMatch = cardText.match(/(?:USD|U\$D|u\$s)\s*([\d.,]+)/i);
                var arsMatch = cardText.match(/\$\s*([\d.,]+)/);

                if (usdMatch) {
                    priceNum = parseArPrice(usdMatch[1]);
                    currency = 'USD';
                } else if (arsMatch) {
                    priceNum = parseArPrice(arsMatch[1]);
                    currency = 'ARS';
                }

                if (!priceNum || priceNum < 5000) return; // sin precio válido, saltar

                // ── Superficie ───────────────────────────────────────────
                var areaNum = null;

                // 1. Desde el alt de la imagen: "114m²" o "114 m²"
                if (altText) {
                    var altM2 = altText.match(/([\d]{2,4}(?:[.,]\d{1,3})?)\s*m[²2]/i);
                    if (altM2) areaNum = parseArArea(altM2[1]);
                }

                // 2. Desde el texto del card (con soporte miles argentino)
                if (!areaNum) {
                    var m2regex = /([\d]{1,4}(?:[.,]\d{1,3})?)\s*m[²2]/gi;
                    var m2match;
                    while ((m2match = m2regex.exec(cardText)) !== null) {
                        var mval = parseArArea(m2match[1]);
                        if (mval > 5 && mval < 5000) { areaNum = mval; break; }
                    }
                }

                // ── Ambientes ────────────────────────────────────────────
                var ambs = null;
                var ambSrc = altText || cardText;
                var ambMatch = ambSrc.match(/(\d+)\s*amb/i);
                if (ambMatch) ambs = parseInt(ambMatch[1]) || null;

                // ── Cocheras ─────────────────────────────────────────────
                var cars = null;
                var carMatch = ambSrc.match(/(\d+)\s*coch/i);
                if (carMatch) cars = parseInt(carMatch[1]) || null;

                // ── Tipo desde alt ────────────────────────────────────────
                var tipoCard = tipo;
                if (altText) {
                    var altLow = altText.toLowerCase();
                    if (altLow.startsWith('departamento') || altLow.startsWith('depto')) tipoCard = 'departamento';
                    else if (altLow.startsWith('casa'))      tipoCard = 'casa';
                    else if (altLow.startsWith('ph'))        tipoCard = 'ph';
                    else if (altLow.startsWith('local'))     tipoCard = 'local';
                    else if (altLow.startsWith('terreno') || altLow.startsWith('lote')) tipoCard = 'terreno';
                    else if (altLow.startsWith('oficina'))   tipoCard = 'oficina';
                }

                // ── Expensas ─────────────────────────────────────────────
                var exp = null;
                var expMatch = cardText.match(/exp[^$\d]*\$?\s*([\d.,]+)/i);
                if (expMatch) exp = parseArPrice(expMatch[1]);

                // ── Dirección ────────────────────────────────────────────
                var address = '';
                var addrSelectors = [
                    '[data-qa="posting-location"]',
                    '[class*="Location"]',
                    '[class*="location"]',
                    '[class*="address"]',
                    '[class*="Address"]',
                ];
                for (var as = 0; as < addrSelectors.length; as++) {
                    var ael = card.querySelector(addrSelectors[as]);
                    if (ael && ael.textContent.trim().length > 3) { address = ael.textContent.trim(); break; }
                }

                // ── Zona desde dirección ─────────────────────────────────
                var cardZone = zone;
                if (address) {
                    // Ej: "Palermo, Capital Federal" → "Palermo"
                    var zonePart = address.split(',')[0].trim();
                    if (zonePart.length > 2) cardZone = zonePart;
                }

                // ── URL ──────────────────────────────────────────────────
                var url = '';
                var linkEl = card.querySelector('a[href*="/propiedades/"],a[href*=".html"]');
                if (!linkEl) linkEl = card.querySelector('a[href]');
                if (linkEl) {
                    var href = linkEl.getAttribute('href') || '';
                    url = href.startsWith('http') ? href : 'https://www.zonaprop.com.ar' + href;
                }

                results.push({
                    source:        SOURCE,
                    title:         address || url,
                    price:         priceNum,
                    currency:      currency,
                    covered_area:  areaNum,
                    ambientes:     ambs,
                    bedrooms:      null,
                    bathrooms:     null,
                    garages:       cars,
                    expenses:      exp,
                    address:       address,
                    city:          city,
                    zone:          cardZone,
                    lat:           null,
                    lng:           null,
                    property_type: tipoCard,
                    operation:     op,
                    url:           url,
                    scraped_at:    new Date().toISOString(),
                });
            } catch (e) { console.warn('[TasadorIA] Error en card:', e); }
        });

        console.log('[TasadorIA] DOM extraídos:', results.length,
            '| con área:', results.filter(function(r){return r.covered_area > 0;}).length);
        return results;
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════════
    function deepGet(obj, path) {
        var cur = obj;
        for (var i = 0; i < path.length; i++) {
            if (!cur || typeof cur !== 'object') return undefined;
            cur = cur[path[i]];
        }
        return cur;
    }

    // Parsea el slug de la URL de Zonaprop:
    // "departamentos-venta-puerto-madero" → { city, zone, tipo, op }
    function parseZpSlug() {
        var filename = window.location.pathname.replace(/.*\//, '').replace('.html','').toLowerCase();

        // Quitar prefijos de tipo de propiedad y operación
        var tipoWords = ['departamentos','departamento','casas','casa','phs','ph',
                         'locales','local','terrenos','terreno','oficinas','oficina',
                         'galpones','galpon','cocheras','cochera','hoteles','hotel'];
        var opWords   = ['venta','alquiler','alquiler-temporario','temporario'];

        var slug = filename;
        tipoWords.forEach(function(w){ slug = slug.replace(new RegExp('^' + w + '-?'), ''); });
        opWords.forEach(function(w){   slug = slug.replace(new RegExp('^' + w + '-?'), ''); });
        // slug ahora es solo la parte de ubicación, ej: "puerto-madero" o "palermo-capital-federal"

        return slug;
    }

    function detectCity() {
        var slug     = parseZpSlug();
        var u        = window.location.pathname.toLowerCase();
        var title    = (document.title || '').toLowerCase();
        var h1       = (document.querySelector('h1') || {}).textContent || '';
        var pageText = (slug + ' ' + u + ' ' + title + ' ' + h1).toLowerCase();

        // CABA / Buenos Aires — barrios y nombres alternativos
        var caba = [
            'capital-federal','caba','buenos-aires',
            'palermo','recoleta','belgrano','nunez','nuñez','colegiales',
            'villa-crespo','almagro','caballito','flores','floresta',
            'villa-del-parque','villa-urquiza','devoto','saavedra',
            'san-telmo','la-boca','barracas','boedo','parque-patricios',
            'monserrat','san-nicolas','retiro','puerto-madero',
            'villa-pueyrredon','paternal','villa-general-mitre',
            'constitucion','once','balvanera','villa-lugano','mataderos',
            'liniers','versalles','monte-castro','velez-sarsfield',
        ];
        for (var i = 0; i < caba.length; i++) {
            if (pageText.includes(caba[i])) return 'Buenos Aires';
        }

        // GBA
        if (pageText.includes('san-isidro') || pageText.includes('san isidro'))   return 'San Isidro';
        if (pageText.includes('vicente-lopez') || pageText.includes('vicente lópez')) return 'Vicente López';
        if (pageText.includes('tigre') || pageText.includes('nordelta'))           return 'Tigre';
        if (pageText.includes('san-martin') && !pageText.includes('santa'))        return 'San Martín';
        if (pageText.includes('lomas-de-zamora'))                                  return 'Lomas de Zamora';
        if (pageText.includes('quilmes'))                                           return 'Quilmes';
        if (pageText.includes('avellaneda'))                                        return 'Avellaneda';
        if (pageText.includes('la-plata') || pageText.includes('la plata'))        return 'La Plata';

        // Interior
        if (pageText.includes('santa-fe') || pageText.includes('santa fe'))        return 'Santa Fe';
        if (pageText.includes('rosario'))                                           return 'Rosario';
        if (pageText.includes('cordoba') || pageText.includes('córdoba'))          return 'Córdoba';
        if (pageText.includes('mendoza'))                                           return 'Mendoza';
        if (pageText.includes('mar-del-plata') || pageText.includes('mar del plata')) return 'Mar del Plata';
        if (pageText.includes('tucuman') || pageText.includes('tucumán'))          return 'Tucumán';
        if (pageText.includes('salta'))                                             return 'Salta';
        if (pageText.includes('neuquen') || pageText.includes('neuquén'))          return 'Neuquén';
        if (pageText.includes('bariloche'))                                         return 'Bariloche';
        if (pageText.includes('posadas'))                                           return 'Posadas';

        // Default: en Zonaprop sin match → probablemente CABA
        return 'Buenos Aires';
    }

    function detectZone() {
        var slug = parseZpSlug();
        var map = {
            // CABA
            'puerto-madero':        'Puerto Madero',
            'palermo':              'Palermo',
            'recoleta':             'Recoleta',
            'belgrano':             'Belgrano',
            'nunez':                'Núñez',
            'nuñez':                'Núñez',
            'colegiales':           'Colegiales',
            'villa-crespo':         'Villa Crespo',
            'almagro':              'Almagro',
            'caballito':            'Caballito',
            'flores':               'Flores',
            'villa-urquiza':        'Villa Urquiza',
            'villa-devoto':         'Villa Devoto',
            'saavedra':             'Saavedra',
            'san-telmo':            'San Telmo',
            'la-boca':              'La Boca',
            'barracas':             'Barracas',
            'boedo':                'Boedo',
            'monserrat':            'Monserrat',
            'retiro':               'Retiro',
            'villa-del-parque':     'Villa del Parque',
            'villa-urquiza':        'Villa Urquiza',
            'paternal':             'Paternal',
            'chacarita':            'Chacarita',
            'parque-chas':          'Parque Chas',
            'villa-ortuzar':        'Villa Ortúzar',
            'villa-pueyrredon':     'Villa Pueyrredón',
            'parque-patricios':     'Parque Patricios',
            'nueva-pompeya':        'Nueva Pompeya',
            'constitucion':         'Constitución',
            'balvanera':            'Balvanera',
            'san-nicolas':          'San Nicolás',
            'microcentro':          'Microcentro',
            'villa-lugano':         'Villa Lugano',
            'mataderos':            'Mataderos',
            'liniers':              'Liniers',
            'monte-castro':         'Monte Castro',
            // GBA
            'nordelta':             'Nordelta',
            'martinez':             'Martínez',
            'olivos':               'Olivos',
            'florida':              'Florida',
            'munro':                'Munro',
            'boulogne':             'Boulogne',
            // Santa Fe
            'candioti-norte':       'Candioti Norte',
            'candioti-sur':         'Candioti Sur',
            'candioti':             'Candioti Sur',
            'alto-verde':           'Alto Verde',
            'el-pozo':              'El Pozo',
            'costanera':            'Costanera',
            'villa-del-parque-sf':  'Villa del Parque',
            'zona-sur':             'Zona Sur',
            // Rosario
            'pichincha':            'Pichincha',
            'echesortu':            'Echesortu',
            'fisherton':            'Fisherton',
            'norte':                'Norte',
            // Córdoba
            'nueva-cordoba':        'Nueva Córdoba',
            'cerro-de-las-rosas':   'Cerro de las Rosas',
            'general-paz':          'General Paz',
        };
        // Buscar en el slug (más preciso que la URL completa)
        for (var k in map) {
            if (slug.includes(k)) return map[k];
        }
        return '';
    }

    function detectTypeFromUrl() {
        var u = window.location.pathname.toLowerCase();
        if (u.includes('departamento') || u.includes('depto')) return 'departamento';
        if (u.includes('casa'))                                  return 'casa';
        if (u.includes('ph'))                                    return 'ph';
        if (u.includes('local') || u.includes('comercial'))     return 'local';
        if (u.includes('terreno') || u.includes('lote'))        return 'terreno';
        return 'departamento';
    }

    function detectOpFromUrl() {
        var u = window.location.pathname.toLowerCase();
        if (u.includes('alquiler') || u.includes('alquil')) return 'alquiler';
        if (u.includes('venta'))                             return 'venta';
        return 'venta';
    }

    function normCurrency(s) {
        s = (s || '').toString().toUpperCase();
        if (s.includes('AR') || s === 'ARS' || s === '$') return 'ARS';
        return 'USD';
    }

    function normType(t) {
        t = (t || '').toLowerCase();
        if (t.includes('depto') || t.includes('apart') || t === 'apartment') return 'departamento';
        if (t.includes('casa') || t === 'house')                              return 'casa';
        if (t === 'ph')                                                        return 'ph';
        if (t.includes('local') || t.includes('shop'))                        return 'local';
        if (t.includes('terreno') || t.includes('land') || t.includes('lot')) return 'terreno';
        if (t.includes('oficina') || t.includes('office'))                    return 'oficina';
        return t || 'departamento';
    }

    function normOp(o) {
        o = (o || '').toLowerCase();
        if (o.includes('alq') || o === 'rent' || o === 'rental') return 'alquiler';
        if (o.includes('tmp') || o.includes('temporad'))          return 'temporario';
        return 'venta';
    }

    function showOverlay(msg) {
        var el = document.createElement('div');
        el.id  = 'ta-overlay';
        el.style.cssText = [
            'position:fixed','top:20px','right:20px','z-index:2147483647',
            'background:#111','border:2px solid #c9a84c','border-radius:12px',
            'padding:18px 22px','color:#eee','font-family:system-ui,sans-serif',
            'font-size:14px','line-height:1.6','max-width:280px','text-align:center',
            'box-shadow:0 8px 32px rgba(0,0,0,.7)',
        ].join(';');
        el.innerHTML = '<strong style="color:#c9a84c">TasadorIA 🏠</strong><br>' + msg;
        document.body.appendChild(el);
        return el;
    }

})();
