# 🏠 TasadorIA

**Tasador online de propiedades con IA** · Open Source · MIT License · Argentina & LATAM

Sistema completo de valuación inmobiliaria inteligente con arquitectura basada en plugins. Core MIT open source + marketplace de módulos pagos. Wizard paso a paso, análisis de fotos con IA, buscador de propiedades, extractores multi-portal (Zonaprop, Argenprop, Ventafe, Mercado Único), panel admin unificado y widget embebible.

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
- 📥 **Importar XML / CSV** — WordPress/Houzez XML + CSV/Excel con plantilla descargable
- 👥 **Leads** — registro de contactos con datos de la propiedad + exportar CSV
- 📋 **Tasaciones** — historial completo
- 🤝 **Cierres reales** — precios de escritura/boleto/testimonio con estadísticas por zona
- 📐 **Croquis** — editor de planos SVG, exportar PNG, guardar en BD
- 🏗 **BIM Materiales** — cotización con ML + corralones locales + flete
- ⚙️ **Configuración** — tipo de cambio, SMTP, IA, URLs
- 🔌 **Gestor de plugins** — instalar, actualizar y activar módulos de mercado

### Datos de mercado reales
- 🔖 **Bookmarklet multi-portal** — extrae propiedades de cualquier portal en un clic
- **Portales soportados:** Zonaprop, Argenprop, Ventafe, Mercado Único, y cualquier otro (extractor genérico)
- 📥 **Importador WordPress XML** — importa exports de Houzez theme directamente
- 📊 **Importador CSV/Excel** — plantilla descargable, detección automática de columnas
- 🤖 **Apify opcional** — scraping automático mensual ($2.50/1000 resultados)
- 📈 **Motor híbrido** — 60% datos reales (si hay ≥3 listings) + 40% configuración
- 🤝 **Cierres reales** — calibrá el tasador con precios de escritura verificados

### Motor de consenso multi-IA

Cuando tenés varias API keys configuradas, `api/valuar_consensus.php` consulta todas las IAs en paralelo y devuelve un precio consensuado con confianza ponderada.

| Proveedor | Peso IA | Modelo default | API |
|-----------|---------|----------------|-----|
| **Motor local** | 30% (fijo) | TasadorIA + datos reales | — |
| **Claude** (Anthropic) | 35% | claude-opus-4-6 | console.anthropic.com |
| **GPT-4o** (OpenAI) | 30% | gpt-4o | platform.openai.com |
| **Gemini** (Google) | 20% | gemini-1.5-pro | aistudio.google.com |
| **Grok** (xAI) | 15% | grok-3-mini | console.x.ai |

Los pesos son configurables en `config/settings.php`. Si un proveedor falla, su peso se redistribuye entre los activos.

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

## 🔌 Sistema de Plugins

TasadorIA incluye un **sistema de plugins extensible** que permite agregar funcionalidad adicional sin modificar el core. El core es MIT open source y completamente gratuito. Los módulos de mercado son **pagos** ($19-$29) y se instalan como plugins.

### Arquitectura de plugins

Cada plugin es una carpeta con la siguiente estructura:

```
plugins/
├── bim-materiales-ml/
│   ├── plugin.json          ← Metadatos del plugin
│   ├── index.php            ← Lógica principal
│   └── assets/              ← Estilos, scripts, imágenes
├── icc-indec/
│   ├── plugin.json
│   ├── index.php
│   └── assets/
└── ...
```

### Instalación de plugins

1. **Acceder al panel admin:**
   ```
   https://tudominio.com/tasador/admin_plugins.php
   ```

2. **Descargar el plugin** desde el marketplace de TasadorIA

3. **Arrastrar el ZIP** a la zona de drop en `admin_plugins.php`

4. **Activar el plugin** — se ejecuta automáticamente después de la instalación

```
📦 plugin-name.zip
   ↓
   [Drag & drop] → admin_plugins.php
   ↓
   ✅ Activado y listo
```

### Módulos disponibles en marketplace

| Plugin | Precio | Descripción |
|--------|--------|-------------|
| **BIM Materiales ML** | $29 | Clasificación automática de materiales en fotos con ML. Detecta hormigón, ladrillo, cerámica, madera, etc. |
| **ICC INDEC** | $19 | Datos del Índice de Costo de la Construcción INDEC. Ajusta valores según inflación real. |
| **IA Fotos avanzada** | $29 | Análisis profundo de fotos: estructura, acabados, daños. Ajuste ±25% vs ±15% del core. |
| **Apify Sync** | $29 | Scraping automático mensual de portales. Actualiza BD sin intervención. |
| **Escrituras** | $19 | Integración con registros públicos. Verifica estado de escrituras e hipotecas. |
| **WP Publisher** | $19 | Plugin WordPress oficial. Shortcode `[tasador]` + customización de temas. |
| **Ciudades Extra** | $19 | Pack de ciudades: Mendoza, Córdoba, La Plata, Mar del Plata, Tucumán. |
| **CRM Export** | $29 | Exporta leads y tasaciones a CRM: Pipedrive, HubSpot, Salesforce, Zoho. |

### Desarrollo de plugins propios

Un plugin simple:

```php
// plugins/mi-plugin/plugin.json
{
  "name": "Mi Plugin",
  "version": "1.0.0",
  "author": "Tu Nombre",
  "license": "MIT",
  "description": "Mi extensión personalizada",
  "hooks": {
    "valuation_complete": "function_on_valuation_done",
    "admin_panel": "add_custom_admin_section"
  }
}

// plugins/mi-plugin/index.php
<?php
function function_on_valuation_done($valuation_data) {
    // Hacer algo con los datos de valuación
    error_log('Valuación completada: ' . $valuation_data['code']);
}

function add_custom_admin_section() {
    echo '<div class="admin-section">
            <h3>Mi Plugin</h3>
            <p>Bienvenido a mi extensión personalizada</p>
          </div>';
}
```

### Sistema de hooks

Los plugins pueden registrarse en diferentes puntos del flujo:

| Hook | Parámetros | Descripción |
|------|-----------|-------------|
| `valuation_complete` | `$valuation_data` | Se ejecuta después de completar una tasación |
| `admin_panel` | ninguno | Para agregar secciones al panel admin |
| `before_price_calculate` | `$property_data` | Antes de calcular precio |
| `after_price_calculate` | `$price, $property_data` | Después del cálculo |
| `lead_submitted` | `$lead_data` | Cuando se envía un lead |
| `wizard_step` | `$step, $data` | En cada paso del wizard |

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
https://tudominio.com/tasador/api/valuar.php  →  {"status":"ok","php":"8.4.x","version":"5.2"}
```

---

## 📁 Estructura

```
tasador-ia/
├── index.php              ← Wizard de tasación (público)
├── admin.php              ← Panel admin unificado (protegido)
├── admin_market.php       ← Panel datos de mercado + extractores
├── admin_cierres.php      ← Cierres reales (escrituras, boletos, testimonios)
├── admin_croquis.php      ← Editor de planos SVG
├── admin_plugins.php      ← Gestor de plugins (protegido)
├── admin_bim.php          ← BIM Materiales + corralones locales + flete
├── wp_import.php          ← Importador WordPress XML (usar y borrar)
├── embed.js               ← Widget embebible
├── multi_extractor.js     ← Bookmarklet universal para portales
├── install.sql            ← Esquema completo de BD (tablas + cierres + croquis + IA log)
├── market_data.sql        ← Datos de mercado iniciales
│
├── plugins/               ← Directorio de plugins
│   ├── plugin-loader.php  ← Sistema de carga de plugins
│   └── [plugins instalados]
│
├── api/
│   ├── valuar.php              ← Motor de tasación (algoritmo + mercado)
│   ├── valuar_consensus.php    ← Consenso multi-IA (Claude+GPT+Gemini+Grok)
│   ├── closing_prices.php      ← CRUD precios de cierre reales
│   ├── croquis.php             ← REST para planos de planta
│   ├── local_suppliers.php     ← Corralones + materiales + flete Haversine
│   ├── analyze.php             ← Análisis IA de fotos (Claude/OpenAI)
│   ├── send_email.php          ← Email de resultados y leads
│   ├── import_market.php       ← Importar datos CSV/JSON
│   ├── import_csv.php          ← Importador CSV con detección de columnas
│   └── search_properties.php  ← Buscador AJAX de propiedades
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

### `POST /api/valuar_consensus.php`

Mismo body que `valuar.php`. Llama en paralelo a Claude, GPT-4o, Gemini y Grok (los que tengan API key), y devuelve precio consensuado.

```json
{
  "success": true,
  "consensus": {
    "suggested": 68000, "min": 61000, "max": 76000,
    "per_m2": 1046, "confidence": 87, "providers_ok": 4
  },
  "providers": {
    "claude":  {"status":"ok","suggested":67000,"reasoning":"...","weight_pct":24.5},
    "openai":  {"status":"ok","suggested":69500,"reasoning":"...","weight_pct":21.0},
    "gemini":  {"status":"ok","suggested":66000,"reasoning":"...","weight_pct":14.0},
    "grok":    {"status":"ok","suggested":70000,"reasoning":"...","weight_pct":10.5},
    "local":   {"status":"ok","suggested":65000,"reasoning":"Motor TasadorIA","weight_pct":30.0}
  }
}
```

### `POST /api/closing_prices.php`

```json
// action: "save" — guardar cierre
{"action":"save","address":"Rivadavia 1234","city":"Santa Fe Capital","zone":"Candioti Norte",
 "property_type":"departamento","operation":"venta","covered_area":65,
 "price_usd":75000,"close_date":"2025-03-15","source":"escritura"}

// action: "list" — listar con filtros (GET params: city, zone, type, operation, limit)
// action: "stats" — estadísticas por zona (GET params: city)
// action: "delete" — eliminar {"action":"delete","id":123}
```

Ver [docs/API.md](docs/API.md) para documentación completa.

---

## 🤝 Contribuir

¡Las contribuciones son bienvenidas! Áreas prioritarias:

- 🗺 **Nuevas ciudades** — Argentina, Uruguay, Chile, Colombia, México, Miami
- 📊 **Más portales** — Properati, MercadoLibre Inmuebles, Inmuebles24
- 🎨 **Temas visuales** — modo claro, branding personalizable
- 🔌 **Plugins comunitarios** — desarrolla tu propio módulo con el sistema de hooks
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

Los módulos de marketplace están sujetos a sus propios términos de licencia (ver `plugin.json` en cada plugin).

---

## ⚠️ Aviso legal

Las tasaciones son **orientativas**. No constituyen oferta ni documento legal. Para valuaciones oficiales, consultar a un martillero matriculado.

---

**Hecho con ❤️ en Santa Fe, Argentina**
[github.com/exeandino](https://github.com/exeandino)
