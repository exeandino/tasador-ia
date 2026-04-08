# 🏠 TasadorIA

**Tasador online de propiedades con IA** · Open Source · MIT License · Argentina & LATAM

Sistema completo de valuación inmobiliaria inteligente. Wizard paso a paso, análisis de fotos con IA, buscador de propiedades, extractores multi-portal (Zonaprop, Argenprop, Ventafe, Mercado Único), panel admin unificado y widget embebible.

**Demo:** [anperprimo.com/tasador](https://anperprimo.com/tasador) · **Hecho en Santa Fe, Argentina 🇦🇷**

---

## ✨ Características

### Tasador público
- 🧙 **Wizard 7 pasos** — ubicación con mapa, tipo, características, estado, amenities, fotos IA, contacto
- 📸 **Análisis de fotos con IA** — Claude Vision o GPT-4o analiza el estado real y ajusta ±15%
- 🗺 **Mapa interactivo** — Leaflet + OpenStreetMap, geocoding automático
- 💬 **Datos completos** — ambientes, dormitorios, baños, cocheras, expensas, escritura, deuda
- 📊 **Precio inteligente** — blend 60% datos reales del mercado + 40% configuración
- 📍 **POI por zona** — escuelas, parques, shoppings, hospitales del barrio
- 🔗 **Widget embebible** — un `<script>` en cualquier sitio

### Panel admin unificado
- 📊 **Dashboard** — stats globales: tasaciones, leads, listings, USD/ARS
- 🗺 **Precios y zonas** — comparación config vs datos reales + editor inline
- 🔍 **Buscador de propiedades** — busca en todos los listings importados por tipo, precio, superficie, zona
- 📥 **Importar XML** — WordPress/Houzez export directo a la BD
- 👥 **Leads** — registro de contactos con datos de la propiedad + exportar CSV
- 📋 **Tasaciones** — historial completo
- ⚙️ **Configuración** — tipo de cambio, SMTP, IA, URLs

### Datos de mercado reales
- 🔖 **Bookmarklet multi-portal** — extrae propiedades de cualquier portal en un clic
- **Portales soportados:** Zonaprop, Argenprop, Ventafe, Mercado Único, y cualquier otro (extractor genérico)
- 📥 **Importador WordPress XML** — importa exports de Houzez theme directamente
- 🤖 **Apify opcional** — scraping automático mensual ($2.50/1000 resultados)
- 📈 **Motor híbrido** — 60% datos reales (si hay ≥3 listings) + 40% configuración

### Factores de valuación
| Factor | Impacto |
|--------|---------|
| Zona y ciudad | Base del precio (USD/m² por zona) |
| Superficie | Factor por tamaño (pequeño = +15%, grande = -12%) |
| Antigüedad | A estrenar +20% → 60+ años -28% |
| Estado | Excelente +12% → A refaccionar -25% |
| Ambientes | 1 amb -8% → 4+ amb +8-10% |
| Baños | +4% por baño extra (máx +8%) |
| Cochera | +6-9% |
| Vista | Río/mar +18% → Interior -4% |
| Orientación | Norte +5% → Sur -5% |
| Piso | Alto c/ascensor +8-12% → PB -4% |
| Amenities | 2-6+ amenities +4-10% |
| Expensas | Exceso c/30 USD base → hasta -15% |
| Escritura | Boleto -6% · Posesión -12% · Sucesión -15% |
| Deuda hipotecaria | Se descuenta del precio final |
| IA fotos | -15% a +15% según estado real |

---

## 🚀 Instalación rápida

### Requisitos
- PHP 7.4+ (testeado en PHP 8.4)
- MySQL 5.7+ / MariaDB 10+
- `mail()` habilitado o credenciales SMTP
- cURL habilitado

### En 5 minutos

```bash
# 1. Clonar
git clone https://github.com/TU_USUARIO/tasador-ia.git

# 2. Subir al servidor (cPanel / CloudPanel)
scp -r tasador-ia/ usuario@tuservidor:/public_html/tasador/

# 3. Crear BD en cPanel
#    Nombre: tasador_db  |  Usuario: tasador_user

# 4. Importar esquema
mysql -u tasador_user -p tasador_db < install.sql
mysql -u tasador_user -p tasador_db < market_data.sql

# 5. Configurar
cp config/settings.example.php config/settings.php
nano config/settings.php  # completar BD, email, API key

# 6. ¡Listo!
# https://tudominio.com/tasador/
```

### Verificar instalación
```
https://tudominio.com/tasador/api/valuar.php  →  {"status":"ok","php":"8.4.x","version":"5.0"}
```

---

## 📁 Estructura

```
tasador-ia/
├── index.php              ← Wizard de tasación (público)
├── admin.php              ← Panel admin unificado (protegido)
├── admin_market.php       ← Panel datos de mercado + extractores
├── wp_import.php          ← Importador WordPress XML (usar y borrar)
├── embed.js               ← Widget embebible
├── multi_extractor.js     ← Bookmarklet universal para portales
├── install.sql            ← Esquema principal de BD
├── market_data.sql        ← Tablas para datos de mercado
│
├── api/
│   ├── valuar.php         ← Motor de tasación (algoritmo + mercado)
│   ├── analyze.php        ← Análisis IA de fotos (Claude/OpenAI)
│   ├── send_email.php     ← Email de resultados y leads
│   ├── import_market.php  ← Importar datos de scraping CSV/JSON
│   └── search_properties.php ← Buscador AJAX de propiedades
│
└── config/
    ├── settings.example.php  ← Template (copiar a settings.php)
    └── zones.php             ← Precios USD/m² por zona y ciudad
```

---

## 🗺 Ciudades incluidas

| Ciudad | Zonas configuradas |
|--------|--------------------|
| **Santa Fe Capital** | Centro, Candioti N/S, Costanera, El Pozo, Villa del Parque, Alto Verde, Zona Sur |
| **Buenos Aires CABA** | Palermo, Recoleta, Belgrano, Núñez, Villa Crespo, San Telmo, Almagro, y más |
| **Puerto Madero** | PM Este (Torres), PM Oeste (Diques) |
| **GBA Norte** | San Isidro, Vicente López, Tigre/Nordelta |
| **Rosario** | Centro/Pichincha, Echesortu/Fisherton |
| **Córdoba** | Nueva Córdoba, Cerro de las Rosas |

### Agregar una ciudad

```php
// En config/zones.php:
'mendoza' => [
    'label'    => 'Mendoza Capital',
    'country'  => 'AR',
    'currency' => 'USD',
    'updated'  => '2025-01',
    'bounds'   => ['lat_min'=>-32.95,'lat_max'=>-32.85,'lng_min'=>-68.90,'lng_max'=>-68.80],
    'zones' => [
        'centro' => [
            'label'       => 'Centro',
            'price_m2'    => ['min'=>1200,'avg'=>1500,'max'=>1800],
            'description' => 'Zona céntrica de Mendoza.',
            'coords'      => ['lat'=>-32.8895,'lng'=>-68.8458],
            'keywords'    => ['centro mendoza'],
            'multipliers' => [],
        ],
        'general' => [
            'label'       => 'Mendoza Capital (general)',
            'price_m2'    => ['min'=>900,'avg'=>1200,'max'=>1600],
            'description' => 'Promedio ciudad.',
            'coords'      => ['lat'=>-32.8895,'lng'=>-68.8458],
            'keywords'    => [],
            'multipliers' => [],
        ],
    ],
],
```

---

## 🔖 Extractores de portales

### Setup del bookmarklet

1. Subir `multi_extractor.js` al servidor
2. En el admin → pestaña Buscador → arrastrar botón del portal a la barra de favoritos
3. Ir al portal → hacer una búsqueda → clic en el favorito
4. Confirmar → los datos se importan automáticamente a la BD

### Portales soportados

| Portal | Método | Precisión |
|--------|--------|-----------|
| Zonaprop | JSON Next.js | ⭐⭐⭐⭐⭐ |
| Argenprop | JSON Next.js + DOM | ⭐⭐⭐⭐ |
| Ventafe | DOM WordPress | ⭐⭐⭐ |
| Mercado Único | DOM HTML | ⭐⭐⭐ |
| Cualquier otro | DOM genérico | ⭐⭐ |

### Importar WordPress XML (Houzez)

1. Subir `wp_import.php` al servidor
2. Abrir `https://tudominio.com/tasador/wp_import.php`
3. Subir el archivo XML de WordPress
4. Clic en "Importar" — procesa en lotes sin timeout
5. **Borrar `wp_import.php`** del servidor después de usar

---

## 🔗 Embeber en otro sitio

```html
<!-- Script tag (recomendado) -->
<script src="https://tudominio.com/tasador/embed.js"></script>
<div data-tasador data-ciudad="santa_fe_capital"></div>

<!-- iframe directo -->
<iframe src="https://tudominio.com/tasador/" width="100%" height="940"
        style="border:none;border-radius:12px"></iframe>

<!-- Recibir resultado en el sitio padre -->
<script>
document.addEventListener('tasadorResult', function(e) {
    console.log(e.detail.price.suggested); // USD
    console.log(e.detail.zone.zone);       // barrio
    console.log(e.detail.code);            // TA-XXXXXXXX
});
</script>
```

---

## 🤖 API

### `POST /api/valuar.php`

```json
{
  "city": "santa_fe_capital",
  "zone": "candioti_norte",
  "property_type": "departamento",
  "operation": "venta",
  "covered_area": 65,
  "age_years": 10,
  "condition": "bueno",
  "ambientes": 3,
  "bedrooms": 2,
  "bathrooms": 1,
  "garages": 1,
  "view": "exterior",
  "orientation": "norte",
  "amenities": {"ascensor": true, "pileta": false},
  "expensas_ars": 80000,
  "escritura": "escriturado",
  "tiene_deuda": false
}
```

**Respuesta:**
```json
{
  "success": true,
  "code": "TA-A1B2C3D4",
  "zone": {"city": "Santa Fe Capital", "zone": "Candioti Norte"},
  "price": {"currency": "USD", "min": 55000, "suggested": 65000, "max": 75000, "ppm2": 1000},
  "price_ars": {"suggested": 94250000},
  "multipliers": {"Antigüedad": {"factor": 1.0, "label": "10 años"}, ...},
  "market_data": {"used": true, "count": 23, "avg_ppm2": 1050},
  "comparables": [...],
  "poi": {"escuelas": [...], "parques": [...], "shoppings": [...]},
  "expensas": {"ars_mes": 80000, "impacto_pct": 1.5}
}
```

Ver [docs/API.md](docs/API.md) para documentación completa.

---

## 🤝 Contribuir

¡Las contribuciones son bienvenidas! Áreas prioritarias:

- 🗺 **Nuevas ciudades** — Argentina, Uruguay, Chile, Colombia, México, Miami
- 📊 **Más portales** — Properati, MercadoLibre Inmuebles, Inmuebles24
- 🎨 **Temas visuales** — modo claro, branding personalizable
- 🔌 **Plugin WordPress** — shortcode `[tasador_ia]`
- 📱 **PWA** — app móvil instalable
- 📈 **APIs de tipo de cambio** — actualización automática BCRA/bluelytics
- 🌐 **i18n** — inglés, portugués

```bash
# Fork → clone → branch → PR
git checkout -b feature/agregar-zonas-miami
git commit -m "feat: agregar zonas de Miami, Florida"
git push origin feature/agregar-zonas-miami
```

Ver [CONTRIBUTING.md](CONTRIBUTING.md) para más detalles.

---

## 📜 Licencia

MIT — libre para uso comercial, modificación y distribución. Ver [LICENSE](LICENSE).

---

## ⚠️ Aviso legal

Las tasaciones son **orientativas**. No constituyen oferta ni documento legal. Para valuaciones oficiales, consultar a un martillero matriculado.

---

**Hecho con ❤️ en Santa Fe, Argentina**
[github.com/exeandino](https://github.com/exeandino)
