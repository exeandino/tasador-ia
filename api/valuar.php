<?php
// tasador/api/valuar.php — v5 EXPANDIDO
// Nuevos factores: expensas, ambientes, cochera, deuda, POI, escritura

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ob_end_clean();
    echo json_encode(['status'=>'ok','php'=>PHP_VERSION,'version'=>'5.0']);
    exit;
}
function jOut(array $d): void { ob_end_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

try {
    $cfg   = require_once __DIR__ . '/../config/settings.php';
    $zones = require_once __DIR__ . '/../config/zones.php';
    $data  = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) jOut(['success'=>false,'error'=>'JSON inválido']);

    // ── Resolver zona ─────────────────────────────────────────────────────────
    $lat=$lng=0;
    $lat = (float)($data['lat']??0); $lng=(float)($data['lng']??0);
    $text = strtolower(trim((string)($data['zone_text']??$data['address']??'')));
    $cityKey=(string)($data['city']??'santa_fe_capital');
    $zoneKey=(string)($data['zone']??'');
    $rCity=$rZone=null; $rCityKey=$rZoneKey='';

    if ($zoneKey && isset($zones[$cityKey]['zones'][$zoneKey])) {
        [$rCity,$rZone,$rCityKey,$rZoneKey] = [$zones[$cityKey],$zones[$cityKey]['zones'][$zoneKey],$cityKey,$zoneKey];
    }
    if (!$rZone) {
        $order = isset($zones[$cityKey]) ? [$cityKey] : [];
        foreach (array_keys($zones) as $k) if ($k!==$cityKey) $order[]=$k;
        foreach ($order as $ck) {
            $city=$zones[$ck];
            $b=$city['bounds'];
            $inB=($lat&&$lng&&$lat>=$b['lat_min']&&$lat<=$b['lat_max']&&$lng>=$b['lng_min']&&$lng<=$b['lng_max']);
            if ($inB||$ck===$cityKey) {
                foreach ($city['zones'] as $zk=>$z) {
                    if ($zk==='general') continue;
                    foreach ($z['keywords'] as $kw) if ($text&&str_contains($text,$kw)) { [$rCity,$rZone,$rCityKey,$rZoneKey]=[$city,$z,$ck,$zk]; break 3; }
                }
                [$rCity,$rZone,$rCityKey,$rZoneKey]=[$city,$city['zones']['general'],$ck,'general'];
                break;
            }
        }
    }
    if (!$rZone) { $rCityKey='santa_fe_capital';$rCity=$zones['santa_fe_capital'];$rZone=$zones['santa_fe_capital']['zones']['general'];$rZoneKey='general'; }

    // ── Precio base + mercado ─────────────────────────────────────────────────
    $pr=$rZone['price_m2'];
    $area=max(1,(float)($data['covered_area']??$data['total_area']??50));
    $tot=max($area,(float)($data['total_area']??$area));
    $ppm2Cfg=(float)$pr['avg'];
    $ppm2=$ppm2Cfg; $mktInfo=['used'=>false];

    $pdo=null;
    try {
        $pdo=new PDO("mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",$cfg['db']['user'],$cfg['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]);
        $st=$pdo->prepare("SELECT COUNT(*) c,ROUND(AVG(price_per_m2),0) avg FROM market_listings WHERE active=1 AND scraped_at>=DATE_SUB(NOW(),INTERVAL 90 DAY) AND price_per_m2 BETWEEN 200 AND 20000 AND (city LIKE :c OR zone LIKE :z) AND (property_type=:t OR :t2='all') AND (operation=:o OR :o2='all')");
        $st->execute([':c'=>'%'.$rCity['label'].'%',':z'=>'%'.$rZone['label'].'%',':t'=>$data['property_type']??'departamento',':t2'=>$data['property_type']??'',':o'=>$data['operation']??'venta',':o2'=>$data['operation']??'']);
        $mkt=$st->fetch(PDO::FETCH_ASSOC);
        if ($mkt&&(int)$mkt['c']>=3) {
            $mp=(float)$mkt['avg']; $w=(int)$mkt['c']>=10?0.65:0.40;
            $ppm2=round($mp*$w+$ppm2Cfg*(1-$w));
            $mktInfo=['used'=>true,'count'=>(int)$mkt['c'],'avg_ppm2'=>$mp,'blend'=>"{$w}*{$mp}+".(1-$w)."*{$ppm2Cfg}={$ppm2}"];
        }
    } catch (\Throwable $e) {}

    $sf=match(true){$area<30=>1.15,$area<50=>1.08,$area<80=>1.00,$area<120=>0.96,$area<200=>0.92,default=>0.88};
    $basePrice=$area*$ppm2*$sf + ($tot>$area?($tot-$area)*$ppm2*0.30:0);

    // ── Multiplicadores ───────────────────────────────────────────────────────
    $factor=1.0; $bd=[];

    // Antigüedad
    $age=(int)($data['age_years']??15);
    $af=match(true){$age===0=>1.20,$age<=3=>1.12,$age<=8=>1.05,$age<=15=>1.00,$age<=25=>0.94,$age<=40=>0.87,$age<=60=>0.80,default=>0.72};
    $bd['Antigüedad']=['factor'=>$af,'label'=>$age===0?'A estrenar':"{$age} años",'cat'=>'propiedad']; $factor*=$af;

    // Estado
    $cond=(string)($data['condition']??'bueno');
    $cf=match($cond){'excelente'=>1.12,'muy_bueno'=>1.06,'bueno'=>1.00,'regular'=>0.88,'a_refaccionar'=>0.75,default=>1.00};
    $bd['Estado']=['factor'=>$cf,'label'=>ucfirst(str_replace('_',' ',$cond)),'cat'=>'propiedad']; $factor*=$cf;

    // Ambientes
    $amb=(int)($data['ambientes']??$data['rooms']??0);
    $dorm=(int)($data['bedrooms']??0);
    if ($amb<=0&&$dorm>0) $amb=$dorm+1;
    if ($amb>0) {
        $ambF=match(true){$amb===1=>0.92,$amb===2=>1.00,$amb===3=>1.05,$amb===4=>1.08,$amb>=5=>1.10,default=>1.00};
        if ($ambF!==1.00) { $bd['Ambientes']=['factor'=>$ambF,'label'=>"{$amb} ambientes",'cat'=>'distribucion']; $factor*=$ambF; }
    }

    // Baños
    $baths=(int)($data['bathrooms']??1);
    if ($baths>=2) {
        $bF=min(1.08,1.00+($baths-1)*0.04);
        $bd['Baños']=['factor'=>$bF,'label'=>"{$baths} baños",'cat'=>'distribucion']; $factor*=$bF;
    }

    // Cochera
    $gar=(int)($data['garages']??0);
    if ($gar>0) { $gf=1.06+($gar-1)*0.03; $bd['Cochera']=['factor'=>$gf,'label'=>"{$gar} cochera".($gar>1?'s':''),'cat'=>'adicional']; $factor*=$gf; }

    // Vista
    $view=(string)($data['view']??'exterior');
    $vf=match($view){'rio','mar'=>1.18,'lago'=>1.14,'parque'=>1.08,'ciudad'=>1.05,'exterior'=>1.02,'interior'=>0.96,default=>1.00};
    if ($vf!==1.00) { $bd['Vista']=['factor'=>$vf,'label'=>ucfirst($view),'cat'=>'ubicacion']; $factor*=$vf; }

    // Orientación
    $or=(string)($data['orientation']??'');
    $of=match($or){'norte'=>1.05,'noreste'=>1.04,'este'=>1.03,'noroeste'=>1.02,'sur'=>0.95,default=>1.00};
    if ($of!==1.00) { $bd['Orientación']=['factor'=>$of,'label'=>ucfirst($or),'cat'=>'ubicacion']; $factor*=$of; }

    // Piso
    $fl=(int)($data['floor_number']??1); $flT=(int)($data['floors_total']??$fl);
    $asc=!empty($data['amenities']['ascensor']);
    if ($flT>3&&$asc) {
        $ff=match(true){$fl>=10=>1.12,$fl>=6=>1.08,$fl>=3=>1.04,$fl===1=>0.96,default=>1.00};
        if ($ff!==1.00) { $bd['Piso']=['factor'=>$ff,'label'=>"Piso {$fl}",'cat'=>'ubicacion']; $factor*=$ff; }
    }

    // Luminosidad
    $lum=(string)($data['luminosity']??'');
    $lf=match($lum){'muy_luminoso'=>1.05,'luminoso'=>1.02,'oscuro'=>0.93,default=>1.00};
    if ($lf!==1.00) { $bd['Luminosidad']=['factor'=>$lf,'label'=>ucfirst(str_replace('_',' ',$lum)),'cat'=>'calidad']; $factor*=$lf; }

    // Amenities
    $amens=is_array($data['amenities']??null)?$data['amenities']:[];
    $aCnt=count(array_filter($amens));
    if ($aCnt>0) { $amf=match(true){$aCnt>=6=>1.10,$aCnt>=4=>1.07,$aCnt>=2=>1.04,default=>1.02}; $bd['Amenities']=['factor'=>$amf,'label'=>"{$aCnt} amenities",'cat'=>'adicional']; $factor*=$amf; }

    // Escritura
    $escrT=(string)($data['escritura']??'escriturado');
    $escrF=match($escrT){'escriturado'=>1.00,'boleto'=>0.94,'posesion'=>0.88,'sucesion'=>0.85,default=>1.00};
    if ($escrF!==1.00) { $bd['Escritura']=['factor'=>$escrF,'label'=>ucfirst(str_replace('_',' ',$escrT)),'cat'=>'legal']; $factor*=$escrF; }

    // Expensas
    $expARS=(float)($data['expensas_ars']??0);
    $expFactor=1.00; $expInfo=null;
    if ($expARS>0) {
        $arsR=(int)($cfg['ars_usd_rate']??1450);
        $expUSD=$expARS/$arsR;
        $baseExpUSD=30; // referencia normal
        $excesoUSD=max(0,$expUSD-$baseExpUSD);
        if ($excesoUSD>0) {
            $descPct=min(15,round($excesoUSD/10*0.5,1));
            $expFactor=1-$descPct/100;
            $expInfo=['ars_mes'=>$expARS,'usd_mes'=>round($expUSD,0),'exceso_usd'=>round($excesoUSD,0),'impacto_pct'=>$descPct];
            $bd['Expensas']=['factor'=>$expFactor,'label'=>"\${$expARS}/mes ARS → -{$descPct}%",'cat'=>'costo'];
            $factor*=$expFactor;
        }
    }

    // Precio ajustado
    $adjusted=$basePrice*$factor;
    $aiScore=isset($data['ai_photo_score'])&&is_numeric($data['ai_photo_score'])?(float)$data['ai_photo_score']:null;
    $aiApplied=false;
    if ($aiScore!==null&&$aiScore!==0.0) { $adjusted*=(1+$aiScore/100); $aiApplied=true; }

    // Deuda
    $deudaUSD=!empty($data['tiene_deuda'])?(float)($data['deuda_usd']??0):0;
    $finalPrice=max(0,$adjusted-$deudaUSD);
    if ($deudaUSD>0) $bd['Deuda hipotecaria']=['factor'=>1.00,'label'=>"USD ".number_format($deudaUSD,0,',','.')." descontado del precio",'cat'=>'legal'];

    // Rango
    $spread=($pr['max']-$pr['min'])/max(1,$pr['avg']);
    $margin=max(10,min(25,(int)round($spread*30)));
    if ($mktInfo['used']&&$mktInfo['count']>=10) $margin=max(8,$margin-3);
    $prMin=(int)(round($finalPrice*(1-($margin-5)/100)/1000)*1000);
    $prSug=(int)(round($finalPrice/1000)*1000);
    $prMax=(int)(round($finalPrice*(1+$margin/100)/1000)*1000);
    $arsRate=(int)($cfg['ars_usd_rate']??1450);

    // Comparables
    $comparables=[];
    if ($pdo&&$area>0) {
        try {
            $cs=$pdo->prepare("SELECT title,price,currency,price_usd,covered_area,price_per_m2,address,zone,url FROM market_listings WHERE active=1 AND price_per_m2>0 AND covered_area BETWEEN :amin AND :amax AND property_type=:type AND (city LIKE :city OR zone LIKE :zone) ORDER BY ABS(covered_area-:area) ASC LIMIT 5");
            $cs->execute([':amin'=>$area*.75,':amax'=>$area*1.25,':type'=>$data['property_type']??'departamento',':city'=>'%'.$rCity['label'].'%',':zone'=>'%'.$rZone['label'].'%',':area'=>$area]);
            $comparables=$cs->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}
    }

    // POI
    $poi=getPOI($rCityKey,$rZoneKey,$lat,$lng);

    // Guardar
    $code='TA-'.strtoupper(substr(md5(uniqid('',true)),0,8));
    try {
        if (!$pdo) $pdo=new PDO("mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",$cfg['db']['user'],$cfg['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->prepare("INSERT INTO tasaciones (code,data_json,result_json,zone,city,ip,created_at) VALUES(?,?,?,?,?,?,NOW())")
            ->execute([$code,json_encode($data,JSON_UNESCAPED_UNICODE),json_encode(['min'=>$prMin,'suggested'=>$prSug,'max'=>$prMax]),$rZoneKey,$rCityKey,$_SERVER['REMOTE_ADDR']??'']);
    } catch (\Throwable $e) { $code.='-NODB'; }

    jOut([
        'success'           => true,
        'code'              => $code,
        'zone'              => ['city'=>$rCity['label'],'zone'=>$rZone['label'],'description'=>$rZone['description']],
        'price'             => ['currency'=>'USD','min'=>$prMin,'suggested'=>$prSug,'max'=>$prMax,'ppm2'=>(int)round($ppm2*$sf),'margin_pct'=>$margin],
        'price_ars'         => ['min'=>$prMin*$arsRate,'suggested'=>$prSug*$arsRate,'max'=>$prMax*$arsRate],
        'precio_bruto'      => $deudaUSD>0?(int)round($adjusted):null,
        'deuda_descontada'  => $deudaUSD>0?$deudaUSD:null,
        'ars_rate'          => $arsRate,
        'multipliers'       => $bd,
        'total_factor'      => round($factor,4),
        'ai'                => ['ai_applied'=>$aiApplied,'ai_score'=>$aiScore??0],
        'market_data'       => $mktInfo,
        'comparables'       => $comparables,
        'poi'               => $poi,
        'expensas'          => $expInfo,
        'timestamp'         => date('c'),
    ]);

} catch (\Throwable $e) { jOut(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()]); }

function getPOI(string $ck, string $zk, float $lat, float $lng): array {
    static $data = [
        'santa_fe_capital' => [
            'centro'         => ['escuelas'=>['Escuela Sarmiento','Colegio Nacional','Instituto Inmaculada'],'parques'=>['Plaza 25 de Mayo','Plaza España'],'shoppings'=>['Shopping del Siglo','Centro Comercial'],'hospitales'=>['HECA','Hospital Provincial'],'transporte'=>['Terminal de ómnibus','Estación ferroviaria']],
            'candioti_norte' => ['escuelas'=>['Escuela Candioti','Colegio Don Bosco'],'parques'=>['Parque Candioti'],'shoppings'=>['Supermercado Libertad','Changomas'],'hospitales'=>['Clínica Privada Norte'],'transporte'=>['Av. Freyre - múltiples líneas']],
            'la_costanera'   => ['escuelas'=>['UNL - Universidad del Litoral'],'parques'=>['Parque España','Parque de la Constitución'],'shoppings'=>['Patio de la Madera'],'hospitales'=>['Sanatorio Norte'],'transporte'=>['Circular Costanera']],
            'candioti_sur'   => ['escuelas'=>['Escuela Esperanza','Colegio Secundario'],'parques'=>['Parque Sur'],'shoppings'=>['Supermercados de la zona'],'hospitales'=>['Clínica Privada'],'transporte'=>['Av. Blas Parera']],
            'el_pozo'        => ['escuelas'=>['Escuelas barriales'],'parques'=>['Espacios verdes'],'shoppings'=>['Comercios locales'],'hospitales'=>['Centro de Salud'],'transporte'=>['Líneas de colectivo']],
            'general'        => ['escuelas'=>['Múltiples escuelas públicas y privadas'],'parques'=>['Parques y plazas'],'shoppings'=>['Supermercados y comercios'],'hospitales'=>['Red hospitalaria pública y privada'],'transporte'=>['Red de colectivos urbanos']],
        ],
        'buenos_aires' => [
            'palermo'    => ['escuelas'=>['ORT','Colegio Nacional B.A.','Escuelas bilingues'],'parques'=>['Parque Tres de Febrero','Plaza Armenia','Jardín Japonés'],'shoppings'=>['Alto Palermo','Palermo Soho','Jardín Shopping'],'hospitales'=>['Hospital Italiano','Fernández','Rivadavia'],'transporte'=>['Líneas D y H','Metrobus Gral. Paz']],
            'recoleta'   => ['escuelas'=>['Colegio del Norte','Escuela Pueyrredón','Instituto Ballester'],'parques'=>['Plaza Francia','Cementerio Recoleta','Parque Thays'],'shoppings'=>['Patio Bullrich','Recoleta Mall','Buenos Aires Design'],'hospitales'=>['Fernández','Mater Dei','Centro Gallego'],'transporte'=>['Línea H','Metrobus 9 de Julio']],
            'belgrano'   => ['escuelas'=>['Colegio Belgrano Day School','Escuelas bilingues'],'parques'=>['Barrancas de Belgrano','Plaza Castelli'],'shoppings'=>['Village Recoleta','Dot Baires'],'hospitales'=>['Hospital Universitario Austral','Del Pilar'],'transporte'=>['Líneas D y H','Tren Mitre']],
            'general'    => ['escuelas'=>['Red de escuelas públicas y privadas CABA'],'parques'=>['Plazas y parques urbanos'],'shoppings'=>['Shoppings, galerías y comercios'],'hospitales'=>['Red hospitalaria CABA'],'transporte'=>['Subtes A-B-C-D-E-H, Metrobus, 140+ líneas']],
        ],
    ];
    $poi = $data[$ck][$zk] ?? $data[$ck]['general'] ?? ['nota'=>'POI no disponible para esta zona'];
    if ($lat&&$lng) {
        $poi['coordenadas']    = ['lat'=>$lat,'lng'=>$lng];
        $poi['ver_en_mapa']    = "https://www.google.com/maps/@{$lat},{$lng},15z";
        $poi['buscar_escuelas']= "https://www.google.com/maps/search/escuelas/@{$lat},{$lng},15z";
        $poi['buscar_farmacias']="https://www.google.com/maps/search/farmacia/@{$lat},{$lng},15z";
    }
    return $poi;
}
