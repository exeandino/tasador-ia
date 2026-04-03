<?php
// tasador/config/zones.php — Q1 2025
// Editá desde: /tasador/admin.php

return [

    'santa_fe_capital' => [
        'label'    => 'Santa Fe Capital',
        'country'  => 'AR', 'currency' => 'USD', 'updated' => '2025-01',
        'bounds'   => ['lat_min' => -31.75, 'lat_max' => -31.55, 'lng_min' => -60.75, 'lng_max' => -60.55],
        'zones' => [
            'la_costanera'     => ['label' => 'Costanera / Universitario', 'price_m2' => ['min' => 850, 'max' => 1150, 'avg' => 980],  'description' => 'Vista al río Paraná, zona premium costera.', 'coords' => ['lat' => -31.628, 'lng' => -60.685], 'keywords' => ['costanera','universitario','ribera','parana','barrio universitario'], 'multipliers' => []],
            'centro'           => ['label' => 'Centro / Microcentro',      'price_m2' => ['min' => 850, 'max' => 1200, 'avg' => 1020], 'description' => 'Zona financiera y comercial.', 'coords' => ['lat' => -31.630, 'lng' => -60.701], 'keywords' => ['centro','microcentro','san martin','25 de mayo','sarmiento','rivadavia'], 'multipliers' => []],
            'candioti_norte'   => ['label' => 'Candioti Norte',            'price_m2' => ['min' => 750, 'max' => 1050, 'avg' => 880],  'description' => 'Barrio residencial premium.', 'coords' => ['lat' => -31.618, 'lng' => -60.692], 'keywords' => ['candioti norte'], 'multipliers' => []],
            'el_pozo'          => ['label' => 'El Pozo / Belgrano',        'price_m2' => ['min' => 700, 'max' => 1000, 'avg' => 840],  'description' => 'Zona en valorización.', 'coords' => ['lat' => -31.620, 'lng' => -60.710], 'keywords' => ['el pozo','belgrano santa fe'], 'multipliers' => []],
            'candioti_sur'     => ['label' => 'Candioti Sur',              'price_m2' => ['min' => 650, 'max' => 920,  'avg' => 780],  'description' => 'Barrio residencial consolidado.', 'coords' => ['lat' => -31.634, 'lng' => -60.688], 'keywords' => ['candioti sur'], 'multipliers' => []],
            'general_obligado' => ['label' => 'Villa del Parque',          'price_m2' => ['min' => 550, 'max' => 800,  'avg' => 660],  'description' => 'Barrio familiar, casas y PH.', 'coords' => ['lat' => -31.645, 'lng' => -60.705], 'keywords' => ['general obligado','villa del parque','villa parque'], 'multipliers' => []],
            'alto_verde'       => ['label' => 'Alto Verde / Colastiné',   'price_m2' => ['min' => 350, 'max' => 650,  'avg' => 480],  'description' => 'Zona isleña, casas de fin de semana.', 'coords' => ['lat' => -31.610, 'lng' => -60.650], 'keywords' => ['alto verde','colastine','isla'], 'multipliers' => []],
            'sur_industrial'   => ['label' => 'Zona Sur / Industrial',    'price_m2' => ['min' => 400, 'max' => 680,  'avg' => 520],  'description' => 'Uso mixto residencial/industrial.', 'coords' => ['lat' => -31.670, 'lng' => -60.710], 'keywords' => ['sur industrial','zona sur'], 'multipliers' => []],
            'general'          => ['label' => 'Santa Fe Capital (general)','price_m2' => ['min' => 580, 'max' => 860,  'avg' => 700],  'description' => 'Valor promedio ciudad.', 'coords' => ['lat' => -31.630, 'lng' => -60.701], 'keywords' => [], 'multipliers' => []],
        ],
    ],

    'buenos_aires' => [
        'label'    => 'Buenos Aires (CABA)',
        'country'  => 'AR', 'currency' => 'USD', 'updated' => '2025-01',
        'bounds'   => ['lat_min' => -34.710, 'lat_max' => -34.530, 'lng_min' => -58.540, 'lng_max' => -58.330],
        'zones' => [
            'palermo'          => ['label' => 'Palermo / Soho / Hollywood',    'price_m2' => ['min' => 2800, 'max' => 4200, 'avg' => 3400], 'description' => 'El barrio más demandado de CABA.', 'coords' => ['lat' => -34.583, 'lng' => -58.433], 'keywords' => ['palermo','palermo soho','palermo hollywood','palermo chico','palermo viejo','las cañitas'], 'multipliers' => []],
            'recoleta'         => ['label' => 'Recoleta / Barrio Norte',       'price_m2' => ['min' => 2600, 'max' => 4000, 'avg' => 3200], 'description' => 'Zona clásica premium.', 'coords' => ['lat' => -34.588, 'lng' => -58.393], 'keywords' => ['recoleta','barrio norte','alvear','quintana'], 'multipliers' => []],
            'belgrano'         => ['label' => 'Belgrano / Belgrano R / C',     'price_m2' => ['min' => 2200, 'max' => 3500, 'avg' => 2750], 'description' => 'Barrio familiar premium del norte.', 'coords' => ['lat' => -34.557, 'lng' => -58.458], 'keywords' => ['belgrano caba','belgrano r','belgrano c','cabildo','juramento'], 'multipliers' => []],
            'nuñez'            => ['label' => 'Núñez / Saavedra / Coghlan',    'price_m2' => ['min' => 1900, 'max' => 2900, 'avg' => 2350], 'description' => 'Zona norte residencial tranquila.', 'coords' => ['lat' => -34.542, 'lng' => -58.462], 'keywords' => ['nuñez','saavedra','coghlan','colegiales'], 'multipliers' => []],
            'villa_crespo'     => ['label' => 'Villa Crespo / Chacarita',      'price_m2' => ['min' => 2000, 'max' => 3000, 'avg' => 2450], 'description' => 'Barrios en valorización.', 'coords' => ['lat' => -34.601, 'lng' => -58.443], 'keywords' => ['villa crespo','chacarita','paternal'], 'multipliers' => []],
            'san_telmo'        => ['label' => 'San Telmo / Monserrat',         'price_m2' => ['min' => 1800, 'max' => 2800, 'avg' => 2200], 'description' => 'Zona histórica y cultural en expansión.', 'coords' => ['lat' => -34.622, 'lng' => -58.373], 'keywords' => ['san telmo','monserrat','balvanera','constitucion','once'], 'multipliers' => []],
            'almagro'          => ['label' => 'Almagro / Boedo / Caballito',   'price_m2' => ['min' => 1700, 'max' => 2600, 'avg' => 2100], 'description' => 'Barrios céntricos residenciales.', 'coords' => ['lat' => -34.610, 'lng' => -58.420], 'keywords' => ['almagro','boedo','caballito','flores','floresta'], 'multipliers' => []],
            'villa_urquiza'    => ['label' => 'Villa Urquiza / Devoto',        'price_m2' => ['min' => 1600, 'max' => 2400, 'avg' => 1950], 'description' => 'Zona norte tranquila.', 'coords' => ['lat' => -34.572, 'lng' => -58.496], 'keywords' => ['villa urquiza','devoto','villa pueyrredon'], 'multipliers' => []],
            'villa_del_parque_caba' => ['label' => 'Villa del Parque / Agronomía', 'price_m2' => ['min' => 1400, 'max' => 2200, 'avg' => 1750], 'description' => 'Zona residencial familiar.', 'coords' => ['lat' => -34.605, 'lng' => -58.500], 'keywords' => ['villa del parque caba','agronomia','la paternal'], 'multipliers' => []],
            'liniers'          => ['label' => 'Liniers / Mataderos / Lugano',  'price_m2' => ['min' => 1100, 'max' => 1800, 'avg' => 1400], 'description' => 'Zona sur-oeste, valores accesibles.', 'coords' => ['lat' => -34.642, 'lng' => -58.522], 'keywords' => ['liniers','mataderos','lugano','villa lugano','soldati'], 'multipliers' => []],
            'general'          => ['label' => 'Buenos Aires CABA (general)',   'price_m2' => ['min' => 1800, 'max' => 3200, 'avg' => 2400], 'description' => 'Promedio CABA.', 'coords' => ['lat' => -34.604, 'lng' => -58.382], 'keywords' => ['buenos aires','caba','capital federal'], 'multipliers' => []],
        ],
    ],

    'puerto_madero' => [
        'label'    => 'Puerto Madero',
        'country'  => 'AR', 'currency' => 'USD', 'updated' => '2025-01',
        'bounds'   => ['lat_min' => -34.635, 'lat_max' => -34.590, 'lng_min' => -58.385, 'lng_max' => -58.348],
        'zones' => [
            'pm_este'  => ['label' => 'Puerto Madero Este (Torres)', 'price_m2' => ['min' => 4200, 'max' => 7000, 'avg' => 5600], 'description' => 'Torres premium frente al río, el m² más caro de Argentina.', 'coords' => ['lat' => -34.610, 'lng' => -58.357], 'keywords' => ['puerto madero este','madero este'], 'multipliers' => []],
            'pm_oeste' => ['label' => 'Puerto Madero Oeste (Diques)', 'price_m2' => ['min' => 3500, 'max' => 5500, 'avg' => 4400], 'description' => 'Diques históricos, zona cultural.', 'coords' => ['lat' => -34.612, 'lng' => -58.365], 'keywords' => ['puerto madero oeste','dique','docks','madero oeste'], 'multipliers' => []],
            'general'  => ['label' => 'Puerto Madero (general)', 'price_m2' => ['min' => 3500, 'max' => 6000, 'avg' => 4800], 'description' => 'Promedio Puerto Madero.', 'coords' => ['lat' => -34.609, 'lng' => -58.363], 'keywords' => ['puerto madero'], 'multipliers' => []],
        ],
    ],

    'gba_norte' => [
        'label'    => 'GBA Norte',
        'country'  => 'AR', 'currency' => 'USD', 'updated' => '2025-01',
        'bounds'   => ['lat_min' => -34.480, 'lat_max' => -34.300, 'lng_min' => -58.720, 'lng_max' => -58.480],
        'zones' => [
            'san_isidro'    => ['label' => 'San Isidro / Acassuso / Martínez', 'price_m2' => ['min' => 2200, 'max' => 3800, 'avg' => 2900], 'description' => 'La zona más premium del GBA Norte.', 'coords' => ['lat' => -34.473, 'lng' => -58.516], 'keywords' => ['san isidro','acassuso','martinez','la lucila'], 'multipliers' => []],
            'vicente_lopez' => ['label' => 'Vicente López / Olivos',           'price_m2' => ['min' => 1800, 'max' => 3000, 'avg' => 2300], 'description' => 'Zona norte premium.', 'coords' => ['lat' => -34.524, 'lng' => -58.478], 'keywords' => ['vicente lopez','olivos','florida norte','munro'], 'multipliers' => []],
            'tigre'         => ['label' => 'Tigre / Delta / Nordelta',          'price_m2' => ['min' => 1200, 'max' => 2500, 'avg' => 1750], 'description' => 'Zona isleña y countries premium.', 'coords' => ['lat' => -34.426, 'lng' => -58.580], 'keywords' => ['tigre','nordelta','delta','pacheco'], 'multipliers' => []],
            'general'       => ['label' => 'GBA Norte (general)',               'price_m2' => ['min' => 1200, 'max' => 2500, 'avg' => 1700], 'description' => 'Promedio Gran Buenos Aires Norte.', 'coords' => ['lat' => -34.473, 'lng' => -58.516], 'keywords' => ['gba norte','zona norte gba'], 'multipliers' => []],
        ],
    ],

    'rosario' => [
        'label'    => 'Rosario',
        'country'  => 'AR', 'currency' => 'USD', 'updated' => '2025-01',
        'bounds'   => ['lat_min' => -33.010, 'lat_max' => -32.860, 'lng_min' => -60.770, 'lng_max' => -60.580],
        'zones' => [
            'centro_rosario' => ['label' => 'Centro / Pichincha', 'price_m2' => ['min' => 1400, 'max' => 2200, 'avg' => 1750], 'description' => 'Zona premium de Rosario.', 'coords' => ['lat' => -32.947, 'lng' => -60.639], 'keywords' => ['pichincha','centro rosario','republica rosario'], 'multipliers' => []],
            'echesortu'      => ['label' => 'Echesortu / Fisherton', 'price_m2' => ['min' => 1100, 'max' => 1700, 'avg' => 1350], 'description' => 'Barrio residencial premium.', 'coords' => ['lat' => -32.930, 'lng' => -60.710], 'keywords' => ['echesortu','fisherton rosario'], 'multipliers' => []],
            'general'        => ['label' => 'Rosario (general)', 'price_m2' => ['min' => 900, 'max' => 1600, 'avg' => 1200], 'description' => 'Promedio ciudad de Rosario.', 'coords' => ['lat' => -32.947, 'lng' => -60.639], 'keywords' => ['rosario'], 'multipliers' => []],
        ],
    ],

    'cordoba' => [
        'label'    => 'Córdoba Capital',
        'country'  => 'AR', 'currency' => 'USD', 'updated' => '2025-01',
        'bounds'   => ['lat_min' => -31.505, 'lat_max' => -31.300, 'lng_min' => -64.280, 'lng_max' => -64.100],
        'zones' => [
            'nueva_cordoba' => ['label' => 'Nueva Córdoba', 'price_m2' => ['min' => 1400, 'max' => 2200, 'avg' => 1750], 'description' => 'El barrio universitario más demandado.', 'coords' => ['lat' => -31.420, 'lng' => -64.189], 'keywords' => ['nueva cordoba','nueva córdoba'], 'multipliers' => []],
            'cerro_rosas'   => ['label' => 'Cerro de las Rosas / General Paz', 'price_m2' => ['min' => 1200, 'max' => 1900, 'avg' => 1500], 'description' => 'Zona residencial premium.', 'coords' => ['lat' => -31.390, 'lng' => -64.210], 'keywords' => ['cerro de las rosas','general paz cordoba'], 'multipliers' => []],
            'general'       => ['label' => 'Córdoba Capital (general)', 'price_m2' => ['min' => 900, 'max' => 1600, 'avg' => 1200], 'description' => 'Promedio ciudad de Córdoba.', 'coords' => ['lat' => -31.417, 'lng' => -64.183], 'keywords' => ['cordoba capital','córdoba capital','cordoba','córdoba'], 'multipliers' => []],
        ],
    ],

];
