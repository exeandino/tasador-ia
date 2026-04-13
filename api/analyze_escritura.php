<?php
/**
 * TasadorIA — analyze_escritura.php v3.0
 * ──────────────────────────────────────────────────────────────────────────────
 * Analiza con IA documentos inmobiliarios:
 *   tipo 'escritura'      → Escritura pública (desglose completo + irregularidades)
 *   tipo 'compraventa'    → Boleto/contrato de compraventa
 *   tipo 'subdivisión'    → Planos de subdivisión / mensura
 *   tipo 'expensas'       → Boleta de expensas (dato rápido)
 *   tipo 'auto'           → El modelo determina el tipo
 *
 * POST { images: string[], tipo?: string, paginas?: number }
 * ──────────────────────────────────────────────────────────────────────────────
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }

function jout(array $d): void { ob_end_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

// ── PROMPTS ───────────────────────────────────────────────────────────────────
function promptForTipo(string $tipo): string
{
    if ($tipo === 'compraventa') {
        return <<<'PROMPT'
Sos un abogado inmobiliario y notario experto en contratos inmobiliarios argentinos.
Estás analizando imágenes de un boleto de compraventa o contrato de venta de inmueble.

Extraé TODA la información que encuentres en el documento.
Si algo no está en el documento, usá null.
Detectá activamente IRREGULARIDADES, cláusulas abusivas, inconsistencias o datos faltantes obligatorios.

Respondé SOLO con este JSON exacto (sin markdown, sin texto extra):
{
  "tipo_documento": "boleto_compraventa | contrato_privado | cesion_derechos | otro",
  "estado": "completo | incompleto | con_irregularidades",

  "inmueble": {
    "direccion": "dirección exacta del inmueble según el documento",
    "descripcion": "descripción del bien tal como figura en el contrato",
    "matricula": "número de matrícula o folio real si figura",
    "nomenclatura_catastral": "partida/clave catastral si figura",
    "superficie": "superficie indicada en el contrato",
    "medidas": "frente × fondo u otras medidas detalladas si figuran"
  },

  "partes": {
    "vendedor": {
      "nombre": "Nombre completo del vendedor",
      "dni_cuit": "DNI/CUIT si figura",
      "domicilio": "domicilio legal si figura",
      "estado_civil": "soltero/casado/etc si figura"
    },
    "comprador": {
      "nombre": "Nombre completo del comprador",
      "dni_cuit": "DNI/CUIT si figura",
      "domicilio": "domicilio legal si figura",
      "estado_civil": "soltero/casado/etc si figura"
    },
    "intermediario": {
      "nombre": "Inmobiliaria o corredor si figura",
      "matricula": "matrícula del corredor si figura"
    }
  },

  "condiciones_economicas": {
    "precio_total": "monto total de la venta (número y en letras si figura)",
    "moneda": "ARS | USD | mixto",
    "seña": "monto de la seña o anticipo pagado",
    "forma_de_pago": "descripción completa de la forma de pago (cuotas, plazos, etc.)",
    "fecha_escrituracion": "plazo o fecha acordada para la escritura definitiva",
    "posesion": "fecha o condición de entrega de la posesión"
  },

  "garantias_y_condiciones": {
    "libre_de_gravamenes": true,
    "hipotecas": ["descripción de hipotecas si existen"],
    "embargos": ["descripción de embargos si existen"],
    "otras_cargas": ["usufructo, servidumbre, etc."],
    "clausulas_especiales": ["cláusulas adicionales relevantes"],
    "multas_o_penalidades": "descripción de penalidades por incumplimiento"
  },

  "datos_notariales": {
    "escribano": "nombre del escribano que debe escriturar",
    "registro_notarial": "registro notarial designado",
    "fecha_firma": "fecha de firma del boleto",
    "lugar_firma": "ciudad donde se firmó",
    "testigos": ["nombre de testigos si figuran"]
  },

  "historial_transmisiones": [
    {
      "tipo": "compra | herencia | donacion | permuta | otro",
      "fecha": "fecha si figura",
      "vendedor": "de quién proviene el dominio",
      "precio": "precio en esa transacción si figura",
      "escritura_ref": "referencia a escritura anterior si figura"
    }
  ],

  "irregularidades": [
    {
      "severidad": "alta | media | baja",
      "tipo": "falta_dato | clausula_abusiva | inconsistencia | incompleto | riesgo_legal",
      "descripcion": "descripción clara de la irregularidad",
      "recomendacion": "qué hacer al respecto"
    }
  ],

  "resumen_legal": "párrafo breve con evaluación legal general del contrato: si es válido, si tiene riesgos, qué falta",
  "transcripcion_clausulas_principales": "transcripción fiel de las cláusulas más importantes (máx 1500 caracteres)"
}
PROMPT;
    }

    // escritura (default)
    return <<<'PROMPT'
Sos un escribano público, abogado inmobiliario y experto en registros de la propiedad argentino.
Estás analizando imágenes de páginas de una escritura pública de traslado de dominio de un inmueble.

Leé con MÁXIMO DETALLE todas las páginas. Extraé TODA la información disponible.
Si algo no está en el documento, ponelo como null. No inventes datos.
Detectá activamente IRREGULARIDADES: inconsistencias, datos que no coinciden, falta de firmas, cláusulas inusuales, gravámenes no declarados, subdivisiones no aprobadas, discrepancias entre superficies, etc.

Respondé SOLO con este JSON exacto (sin markdown, sin texto extra, sin ```):
{
  "tipo_documento": "escritura_traslado_dominio | escritura_hipoteca | escritura_donacion | escritura_subdivision | poder_venta | otro",
  "estado_documento": "completo | incompleto | con_observaciones | con_irregularidades_graves",

  "inmueble": {
    "direccion_completa": "calle, número, piso, depto, ciudad, provincia tal como figura literalmente en la escritura",
    "descripcion_juridica": "descripción jurídica completa del bien como figura en la escritura",
    "matricula": "número de matrícula o folio real",
    "nomenclatura_catastral": "partida inmobiliaria / clave catastral",
    "numero_cuenta_municipal": "número de cuenta corriente municipal o catastral si figura",
    "circunscripcion": "circunscripción / sección / manzana / parcela si figura",
    "superficie_total": "superficie total del terreno según escritura",
    "superficie_cubierta": "superficie cubierta construida según escritura",
    "superficie_semicubierta": "superficie semicubierta si figura",
    "medidas_terreno": {
      "frente": "medida del frente",
      "fondo": "medida del fondo",
      "lateral_derecho": "medida lateral derecha si figura",
      "lateral_izquierdo": "medida lateral izquierda si figura",
      "perimetro": "perímetro total si figura"
    },
    "limites": {
      "norte": "descripción del límite Norte tal como figura",
      "sur":   "descripción del límite Sur tal como figura",
      "este":  "descripción del límite Este tal como figura",
      "oeste": "descripción del límite Oeste tal como figura"
    },
    "piso_numero": "número de piso si aplica",
    "unidad_funcional": "número de unidad funcional si aplica (PH, depto, etc.)",
    "porcentaje_indiviso": "porcentaje indiviso en el total del edificio si aplica",
    "localidad": "localidad o partido",
    "provincia": "provincia"
  },

  "titulares_actuales": [
    {
      "nombre_apellido": "Nombre Apellido",
      "tipo_doc": "DNI | CUIT | Pasaporte",
      "numero_doc": "número del documento",
      "estado_civil": "soltero | casado | divorciado | viudo",
      "conyuge": "nombre del cónyuge si figura",
      "domicilio": "domicilio del titular",
      "porcentaje_titularidad": "100% | 50% | etc.",
      "tipo_adquisicion": "compra | herencia | donacion | permuta | usucapion | otro"
    }
  ],

  "historial_transmisiones": [
    {
      "orden": 1,
      "tipo": "compra | herencia | donacion | permuta | otro",
      "fecha": "fecha de la transmisión anterior",
      "transmitente": "quién vendió/donó/cedió (nombre)",
      "adquirente": "quién adquirió (nombre)",
      "precio": "precio pagado en esa transmisión si figura",
      "escritura_numero": "número de la escritura anterior",
      "folio": "folio del registro anterior",
      "escribano": "escribano de la transmisión anterior",
      "registro": "registro notarial de la transmisión anterior",
      "observaciones": "observaciones sobre esa transmisión"
    }
  ],

  "gravamenes_y_cargas": {
    "libre_gravamenes": true,
    "hipotecas": [
      {
        "acreedor": "banco o entidad acreedora",
        "monto": "monto de la hipoteca",
        "fecha": "fecha de constitución",
        "vencimiento": "fecha de vencimiento",
        "inscripcion": "número de inscripción"
      }
    ],
    "embargos": [
      {
        "embargante": "quien embargó",
        "monto": "monto del embargo",
        "expediente": "expediente judicial si figura",
        "fecha": "fecha del embargo"
      }
    ],
    "usufructo": "descripción del usufructo si existe",
    "servidumbres": ["descripción de servidumbres si existen"],
    "restricciones_dominio": ["restricciones al dominio (country, barrio cerrado, etc.)"],
    "inhibiciones": "inhibiciones sobre los titulares si se mencionan"
  },

  "datos_notariales": {
    "escribano_actuante": "Nombre completo del escribano",
    "registro_notarial": "Registro Notarial N°",
    "numero_escritura": "número de escritura",
    "fecha_escritura": "fecha de otorgamiento de la escritura",
    "lugar_otorgamiento": "ciudad donde se otorgó",
    "folio_inscripcion_registro": "folio de inscripción en Registro de la Propiedad",
    "fecha_inscripcion": "fecha de inscripción en el Registro",
    "testigos": ["nombres de testigos si figuran"],
    "precio_declarado": "precio de venta declarado en la escritura",
    "moneda": "ARS | USD"
  },

  "permisos_construccion": [
    {
      "tipo": "permiso de obra | plano aprobado | habilitación | otro",
      "numero": "número del permiso",
      "fecha": "fecha del permiso",
      "organismo": "municipalidad / GCBA / etc."
    }
  ],

  "informacion_catastral_fiscal": {
    "valuacion_fiscal": "valuación fiscal si figura",
    "impuesto_inmobiliario": "deuda de impuesto inmobiliario si menciona",
    "aportes_municipales": "ABL u otros aportes si mencionan",
    "deuda_declarada": "deuda declarada al momento de la escritura"
  },

  "irregularidades": [
    {
      "severidad": "alta | media | baja",
      "tipo": "inconsistencia_superficies | firma_faltante | gravamen_no_cancelado | clausula_inusual | dato_incompleto | discrepancia_nombres | escritura_no_inscripta | otro",
      "descripcion": "descripción clara y específica de la irregularidad encontrada",
      "ubicacion": "en qué parte del documento aparece",
      "recomendacion": "qué acción se recomienda tomar"
    }
  ],

  "resumen_ejecutivo": "Párrafo completo con evaluación del estado del título: antigüedad del dominio, cadena de transmisiones, estado de cargas, observaciones del escribano, y evaluación general del riesgo legal del documento.",

  "transcripcion_completa": "Transcripción fiel del texto principal de la escritura. Incluir los considerandos, la descripción del bien y las cláusulas de la transmisión. Máximo 3000 caracteres, priorizando la parte dispositiva.",

  "advertencias_legales": [
    "Advertencia importante para el comprador/vendedor que se desprende del análisis"
  ]
}
PROMPT;
}

// ── MAIN ──────────────────────────────────────────────────────────────────────
try {
    $cfg    = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $images = array_slice($data['images'] ?? [], 0, 12);
    $tipo   = strtolower(trim((string)($data['tipo'] ?? 'escritura')));

    if (!in_array($tipo, ['escritura','compraventa','subdivision','expensas','auto'])) {
        $tipo = 'escritura';
    }
    if ($tipo === 'auto') $tipo = 'escritura'; // default

    $aiCfg = $cfg['ai'] ?? [];
    $aiEnabled = !empty($aiCfg['enabled']);

    // Verificar que haya al menos un provider con API key configurada
    $hasAnyKey = !empty($aiCfg['api_key']);
    if (!$hasAnyKey && !empty($aiCfg['providers'])) {
        foreach ($aiCfg['providers'] as $p) {
            if (!empty($p['api_key'])) { $hasAnyKey = true; break; }
        }
    }
    if (!$aiEnabled || !$hasAnyKey) {
        jout(['success' => false, 'error' => 'IA no configurada. Activá la API en configuración.']);
    }
    if (empty($images)) {
        jout(['success' => false, 'error' => 'No se recibieron imágenes del documento.']);
    }

    $prompt = promptForTipo($tipo);

    // ── Extraer base64 puro + mimeTypes ──────────────────────────────────────
    require_once __DIR__ . '/ai_provider.php';

    $b64Images = [];
    $mimeTypes = [];
    foreach ($images as $img) {
        if (preg_match('/^data:(image\/[\w]+);base64,(.+)$/', (string)$img, $m)) {
            $mimeTypes[] = $m[1];
            $b64Images[] = $m[2];
        }
    }
    if (empty($b64Images)) {
        jout(['success' => false, 'error' => 'No se recibieron imágenes válidas (base64).']);
    }

    // ── API call via helper unificado ─────────────────────────────────────────
    // Usamos 8192 tokens para escrituras y compraventas (documentos largos)
    $maxTok = in_array($tipo, ['escritura','compraventa']) ? 8192 : 4096;
    $aiResult = ai_call($cfg, $prompt, 'Analizá el documento según las instrucciones.', $b64Images, $mimeTypes, null, $maxTok);

    if (!$aiResult['ok']) {
        throw new \Exception('Error de IA (' . $aiResult['provider'] . '): ' . $aiResult['error']);
    }
    $respText = $aiResult['text'];

    // Limpiar posible markdown
    $respText = preg_replace('/^```(?:json)?\s*/m', '', $respText);
    $respText = preg_replace('/\s*```\s*$/m', '', trim($respText));
    // Extraer el primer objeto JSON si hay texto extra
    if (preg_match('/(\{[\s\S]+\})/m', $respText, $jm)) {
        $respText = $jm[1];
    }

    $report = json_decode($respText, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($report)) {
        throw new \Exception('La IA no devolvió JSON válido. Intentá con imágenes más nítidas o en menor cantidad.');
    }

    // ── Guardar imágenes en servidor ───────────────────────────────────────────
    $savedFiles  = [];
    $storagePath = dirname(__DIR__) . '/uploads/escrituras/' . date('Ym') . '/';
    $storageUrl  = '';
    try {
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
            // .htaccess: bloquear acceso directo
            file_put_contents($storagePath . '.htaccess', "Options -Indexes\nOrder deny,allow\nDeny from all\n");
        }
        $prefix = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        foreach ($b64Images as $idx => $b64) {
            $ext      = ($mimeTypes[$idx] ?? 'image/jpeg') === 'image/png' ? 'png' : 'jpg';
            $filename = "{$prefix}_p" . str_pad($idx + 1, 2, '0', STR_PAD_LEFT) . ".{$ext}";
            $dest     = $storagePath . $filename;
            if (file_put_contents($dest, base64_decode($b64)) !== false) {
                $savedFiles[] = "uploads/escrituras/" . date('Ym') . "/{$filename}";
            }
        }
    } catch (\Throwable $ignored) { /* almacenamiento opcional */ }

    // ── Guardar en BD ──────────────────────────────────────────────────────────
    $insertId = null;
    try {
        if (!empty($cfg['db']['host'])) {
            $db = new PDO(
                "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
                $cfg['db']['user'], $cfg['db']['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // Crear/migrar tabla
            $db->exec("CREATE TABLE IF NOT EXISTS escrituras_analizadas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tipo VARCHAR(30) DEFAULT 'escritura',
                report_json LONGTEXT,
                irregularidades_count INT DEFAULT 0,
                archivo_paths TEXT DEFAULT NULL,
                file_count TINYINT DEFAULT 0,
                ip VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            // Agregar columnas si faltan (migracion silenciosa)
            try { $db->exec("ALTER TABLE escrituras_analizadas ADD COLUMN archivo_paths TEXT DEFAULT NULL"); } catch(\Throwable $x) {}
            try { $db->exec("ALTER TABLE escrituras_analizadas ADD COLUMN file_count TINYINT DEFAULT 0"); } catch(\Throwable $x) {}

            $irCount = count($report['irregularidades'] ?? []);
            $stmt = $db->prepare("INSERT INTO escrituras_analizadas
                (tipo, report_json, irregularidades_count, archivo_paths, file_count, ip, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $tipo,
                json_encode($report, JSON_UNESCAPED_UNICODE),
                $irCount,
                $savedFiles ? json_encode($savedFiles) : null,
                count($savedFiles),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            $insertId = $db->lastInsertId();
        }
    } catch (\Throwable $ignored) { /* tabla opcional */ }

    jout([
        'success'      => true,
        'report'       => $report,
        'tipo'         => $tipo,
        'files_saved'  => count($savedFiles),
        'record_id'    => $insertId,
    ]);

} catch (\Throwable $e) {
    jout(['success' => false, 'error' => $e->getMessage()]);
}
