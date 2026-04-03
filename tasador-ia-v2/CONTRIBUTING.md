# Cómo contribuir a TasadorIA

¡Gracias! Las contribuciones son bienvenidas.

## Lo que más se necesita

### 🗺 Nuevas ciudades/zonas (la más fácil)

Agregar un bloque en `config/zones.php` con precios locales. Ver `README.md` para el formato. Ciudades buscadas: Mendoza, Mar del Plata, Tucumán, Salta, Neuquén, Montevideo, Santiago, Bogotá, Miami.

### 🔖 Nuevos extractores de portales

Si hay un portal inmobiliario popular en tu ciudad que no está soportado, podés agregar el extractor en `multi_extractor.js`. Seguir el patrón de `extractVentafe()` o `extractArgenprop()`.

### 🐛 Reportar bugs

Abrir una [Issue](../../issues) con:
- URL donde ocurre
- Pasos para reproducir
- PHP version del servidor
- Mensaje de error exacto

## Proceso de Pull Request

```bash
git fork
git clone https://github.com/TU_USUARIO/tasador-ia.git
git checkout -b feat/nueva-ciudad-mendoza
# hacer cambios
git commit -m "feat: agregar 4 zonas de Mendoza Capital"
git push origin feat/nueva-ciudad-mendoza
# abrir Pull Request en GitHub
```

## Convenciones

- **PHP:** sin frameworks, sin Composer, compatible con PHP 7.4+
- **SQL:** siempre usar prepared statements
- **API:** siempre devolver `{"success": true/false, ...}`
- **No subir:** `config/settings.php`, API keys, contraseñas

## Antes de hacer PR

- [ ] `api/valuar.php` responde con GET: `{"status":"ok",...}`
- [ ] No hay credenciales en el código (`settings.example.php` como template)
- [ ] `.gitignore` incluye `config/settings.php`
- [ ] Los precios de nuevas zonas tienen fuente documentada (Zonaprop, CUCICBA, etc.)
