<?php
// config/zones.php — Editado: 2026-04-03 08:52:02
return [

    'santa_fe_capital' => [
        'label'    => 'Santa Fe Capital',
        'country'  => 'AR',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => array (
  'lat_min' => -31.75,
  'lat_max' => -31.55,
  'lng_min' => -60.75,
  'lng_max' => -60.55,
),
        'zones' => [
            'la_costanera' => [
                'label'       => 'Costanera / Universitario',
                'price_m2'    => ['min'=>1700,'max'=>2500,'avg'=>1600],
                'description' => 'Vista al río Paraná, zona premium costera.',
                'coords'      => array (
  'lat' => -31.628,
  'lng' => -60.685,
),
                'keywords'    => array (
  0 => 'costanera',
  1 => 'universitario',
  2 => 'ribera',
  3 => 'parana',
  4 => 'barrio universitario',
),
                'multipliers' => [],
            ],
            'centro' => [
                'label'       => 'Centro / Microcentro',
                'price_m2'    => ['min'=>850,'max'=>1200,'avg'=>1050],
                'description' => 'Zona financiera y comercial.',
                'coords'      => array (
  'lat' => -31.63,
  'lng' => -60.701,
),
                'keywords'    => array (
  0 => 'centro',
  1 => 'microcentro',
  2 => 'san martin',
  3 => '25 de mayo',
  4 => 'sarmiento',
  5 => 'rivadavia',
),
                'multipliers' => [],
            ],
            'candioti_norte' => [
                'label'       => 'Candioti Norte',
                'price_m2'    => ['min'=>1500,'max'=>2500,'avg'=>1900],
                'description' => 'Barrio residencial premium.',
                'coords'      => array (
  'lat' => -31.618,
  'lng' => -60.692,
),
                'keywords'    => array (
  0 => 'candioti norte',
),
                'multipliers' => [],
            ],
            'el_pozo' => [
                'label'       => 'El Pozo / Belgrano',
                'price_m2'    => ['min'=>700,'max'=>1000,'avg'=>850],
                'description' => 'Zona en valorización.',
                'coords'      => array (
  'lat' => -31.62,
  'lng' => -60.71,
),
                'keywords'    => array (
  0 => 'el pozo',
  1 => 'belgrano santa fe',
),
                'multipliers' => [],
            ],
            'candioti_sur' => [
                'label'       => 'Candioti Sur',
                'price_m2'    => ['min'=>1500,'max'=>2400,'avg'=>1900],
                'description' => 'Barrio residencial consolidado.',
                'coords'      => array (
  'lat' => -31.634,
  'lng' => -60.688,
),
                'keywords'    => array (
  0 => 'candioti sur',
),
                'multipliers' => [],
            ],
            'general_obligado' => [
                'label'       => 'Villa del Parque',
                'price_m2'    => ['min'=>550,'max'=>800,'avg'=>660],
                'description' => 'Barrio familiar, casas y PH.',
                'coords'      => array (
  'lat' => -31.645,
  'lng' => -60.705,
),
                'keywords'    => array (
  0 => 'general obligado',
  1 => 'villa del parque',
  2 => 'villa parque',
),
                'multipliers' => [],
            ],
            'alto_verde' => [
                'label'       => 'Alto Verde / Colastiné',
                'price_m2'    => ['min'=>1300,'max'=>2100,'avg'=>1700],
                'description' => 'Zona isleña, casas de fin de semana.',
                'coords'      => array (
  'lat' => -31.61,
  'lng' => -60.65,
),
                'keywords'    => array (
  0 => 'alto verde',
  1 => 'colastine',
  2 => 'isla',
),
                'multipliers' => [],
            ],
            'sur_industrial' => [
                'label'       => 'Zona Sur / Industrial',
                'price_m2'    => ['min'=>400,'max'=>680,'avg'=>520],
                'description' => 'Uso mixto residencial/industrial.',
                'coords'      => array (
  'lat' => -31.67,
  'lng' => -60.71,
),
                'keywords'    => array (
  0 => 'sur industrial',
  1 => 'zona sur',
),
                'multipliers' => [],
            ],
            'general' => [
                'label'       => 'Santa Fe Capital (general)',
                'price_m2'    => ['min'=>1100,'max'=>1900,'avg'=>1400],
                'description' => 'Valor promedio ciudad.',
                'coords'      => array (
  'lat' => -31.63,
  'lng' => -60.701,
),
                'keywords'    => array (
),
                'multipliers' => [],
            ],
        ],
    ],

    'buenos_aires' => [
        'label'    => 'Buenos Aires (CABA)',
        'country'  => 'AR',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => array (
  'lat_min' => -34.71,
  'lat_max' => -34.53,
  'lng_min' => -58.54,
  'lng_max' => -58.33,
),
        'zones' => [
            'palermo' => [
                'label'       => 'Palermo / Soho / Hollywood',
                'price_m2'    => ['min'=>2800,'max'=>4200,'avg'=>3400],
                'description' => 'El barrio más demandado de CABA.',
                'coords'      => array (
  'lat' => -34.583,
  'lng' => -58.433,
),
                'keywords'    => array (
  0 => 'palermo',
  1 => 'palermo soho',
  2 => 'palermo hollywood',
  3 => 'palermo chico',
  4 => 'palermo viejo',
  5 => 'las cañitas',
),
                'multipliers' => [],
            ],
            'recoleta' => [
                'label'       => 'Recoleta / Barrio Norte',
                'price_m2'    => ['min'=>2600,'max'=>4000,'avg'=>3200],
                'description' => 'Zona clásica premium.',
                'coords'      => array (
  'lat' => -34.588,
  'lng' => -58.393,
),
                'keywords'    => array (
  0 => 'recoleta',
  1 => 'barrio norte',
  2 => 'alvear',
  3 => 'quintana',
),
                'multipliers' => [],
            ],
            'belgrano' => [
                'label'       => 'Belgrano / Belgrano R / C',
                'price_m2'    => ['min'=>2200,'max'=>3500,'avg'=>2750],
                'description' => 'Barrio familiar premium del norte.',
                'coords'      => array (
  'lat' => -34.557,
  'lng' => -58.458,
),
                'keywords'    => array (
  0 => 'belgrano caba',
  1 => 'belgrano r',
  2 => 'belgrano c',
  3 => 'cabildo',
  4 => 'juramento',
),
                'multipliers' => [],
            ],
            'nuñez' => [
                'label'       => 'Núñez / Saavedra / Coghlan',
                'price_m2'    => ['min'=>1900,'max'=>2900,'avg'=>2350],
                'description' => 'Zona norte residencial tranquila.',
                'coords'      => array (
  'lat' => -34.542,
  'lng' => -58.462,
),
                'keywords'    => array (
  0 => 'nuñez',
  1 => 'saavedra',
  2 => 'coghlan',
  3 => 'colegiales',
),
                'multipliers' => [],
            ],
            'villa_crespo' => [
                'label'       => 'Villa Crespo / Chacarita',
                'price_m2'    => ['min'=>2000,'max'=>3000,'avg'=>2450],
                'description' => 'Barrios en valorización.',
                'coords'      => array (
  'lat' => -34.601,
  'lng' => -58.443,
),
                'keywords'    => array (
  0 => 'villa crespo',
  1 => 'chacarita',
  2 => 'paternal',
),
                'multipliers' => [],
            ],
            'san_telmo' => [
                'label'       => 'San Telmo / Monserrat',
                'price_m2'    => ['min'=>1800,'max'=>2800,'avg'=>2200],
                'description' => 'Zona histórica y cultural en expansión.',
                'coords'      => array (
  'lat' => -34.622,
  'lng' => -58.373,
),
                'keywords'    => array (
  0 => 'san telmo',
  1 => 'monserrat',
  2 => 'balvanera',
  3 => 'constitucion',
  4 => 'once',
),
                'multipliers' => [],
            ],
            'almagro' => [
                'label'       => 'Almagro / Boedo / Caballito',
                'price_m2'    => ['min'=>1700,'max'=>2600,'avg'=>2100],
                'description' => 'Barrios céntricos residenciales.',
                'coords'      => array (
  'lat' => -34.61,
  'lng' => -58.42,
),
                'keywords'    => array (
  0 => 'almagro',
  1 => 'boedo',
  2 => 'caballito',
  3 => 'flores',
  4 => 'floresta',
),
                'multipliers' => [],
            ],
            'villa_urquiza' => [
                'label'       => 'Villa Urquiza / Devoto',
                'price_m2'    => ['min'=>1600,'max'=>2400,'avg'=>1950],
                'description' => 'Zona norte tranquila.',
                'coords'      => array (
  'lat' => -34.572,
  'lng' => -58.496,
),
                'keywords'    => array (
  0 => 'villa urquiza',
  1 => 'devoto',
  2 => 'villa pueyrredon',
),
                'multipliers' => [],
            ],
            'villa_del_parque_caba' => [
                'label'       => 'Villa del Parque / Agronomía',
                'price_m2'    => ['min'=>1400,'max'=>2200,'avg'=>1750],
                'description' => 'Zona residencial familiar.',
                'coords'      => array (
  'lat' => -34.605,
  'lng' => -58.5,
),
                'keywords'    => array (
  0 => 'villa del parque caba',
  1 => 'agronomia',
  2 => 'la paternal',
),
                'multipliers' => [],
            ],
            'liniers' => [
                'label'       => 'Liniers / Mataderos / Lugano',
                'price_m2'    => ['min'=>1100,'max'=>1800,'avg'=>1400],
                'description' => 'Zona sur-oeste, valores accesibles.',
                'coords'      => array (
  'lat' => -34.642,
  'lng' => -58.522,
),
                'keywords'    => array (
  0 => 'liniers',
  1 => 'mataderos',
  2 => 'lugano',
  3 => 'villa lugano',
  4 => 'soldati',
),
                'multipliers' => [],
            ],
            'general' => [
                'label'       => 'Buenos Aires CABA (general)',
                'price_m2'    => ['min'=>1800,'max'=>3200,'avg'=>2400],
                'description' => 'Promedio CABA.',
                'coords'      => array (
  'lat' => -34.604,
  'lng' => -58.382,
),
                'keywords'    => array (
  0 => 'buenos aires',
  1 => 'caba',
  2 => 'capital federal',
),
                'multipliers' => [],
            ],
        ],
    ],

    'puerto_madero' => [
        'label'    => 'Puerto Madero',
        'country'  => 'AR',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => array (
  'lat_min' => -34.635,
  'lat_max' => -34.59,
  'lng_min' => -58.385,
  'lng_max' => -58.348,
),
        'zones' => [
            'pm_este' => [
                'label'       => 'Puerto Madero Este (Torres)',
                'price_m2'    => ['min'=>4200,'max'=>7000,'avg'=>5600],
                'description' => 'Torres premium frente al río, el m² más caro de Argentina.',
                'coords'      => array (
  'lat' => -34.61,
  'lng' => -58.357,
),
                'keywords'    => array (
  0 => 'puerto madero este',
  1 => 'madero este',
),
                'multipliers' => [],
            ],
            'pm_oeste' => [
                'label'       => 'Puerto Madero Oeste (Diques)',
                'price_m2'    => ['min'=>3500,'max'=>5500,'avg'=>4400],
                'description' => 'Diques históricos, zona cultural.',
                'coords'      => array (
  'lat' => -34.612,
  'lng' => -58.365,
),
                'keywords'    => array (
  0 => 'puerto madero oeste',
  1 => 'dique',
  2 => 'docks',
  3 => 'madero oeste',
),
                'multipliers' => [],
            ],
            'general' => [
                'label'       => 'Puerto Madero (general)',
                'price_m2'    => ['min'=>3500,'max'=>6000,'avg'=>4800],
                'description' => 'Promedio Puerto Madero.',
                'coords'      => array (
  'lat' => -34.609,
  'lng' => -58.363,
),
                'keywords'    => array (
  0 => 'puerto madero',
),
                'multipliers' => [],
            ],
        ],
    ],

    'gba_norte' => [
        'label'    => 'GBA Norte',
        'country'  => 'AR',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => array (
  'lat_min' => -34.48,
  'lat_max' => -34.3,
  'lng_min' => -58.72,
  'lng_max' => -58.48,
),
        'zones' => [
            'san_isidro' => [
                'label'       => 'San Isidro / Acassuso / Martínez',
                'price_m2'    => ['min'=>2200,'max'=>3800,'avg'=>2900],
                'description' => 'La zona más premium del GBA Norte.',
                'coords'      => array (
  'lat' => -34.473,
  'lng' => -58.516,
),
                'keywords'    => array (
  0 => 'san isidro',
  1 => 'acassuso',
  2 => 'martinez',
  3 => 'la lucila',
),
                'multipliers' => [],
            ],
            'vicente_lopez' => [
                'label'       => 'Vicente López / Olivos',
                'price_m2'    => ['min'=>1800,'max'=>3000,'avg'=>2300],
                'description' => 'Zona norte premium.',
                'coords'      => array (
  'lat' => -34.524,
  'lng' => -58.478,
),
                'keywords'    => array (
  0 => 'vicente lopez',
  1 => 'olivos',
  2 => 'florida norte',
  3 => 'munro',
),
                'multipliers' => [],
            ],
            'tigre' => [
                'label'       => 'Tigre / Delta / Nordelta',
                'price_m2'    => ['min'=>1200,'max'=>2500,'avg'=>1750],
                'description' => 'Zona isleña y countries premium.',
                'coords'      => array (
  'lat' => -34.426,
  'lng' => -58.58,
),
                'keywords'    => array (
  0 => 'tigre',
  1 => 'nordelta',
  2 => 'delta',
  3 => 'pacheco',
),
                'multipliers' => [],
            ],
            'general' => [
                'label'       => 'GBA Norte (general)',
                'price_m2'    => ['min'=>1200,'max'=>2500,'avg'=>1700],
                'description' => 'Promedio Gran Buenos Aires Norte.',
                'coords'      => array (
  'lat' => -34.473,
  'lng' => -58.516,
),
                'keywords'    => array (
  0 => 'gba norte',
  1 => 'zona norte gba',
),
                'multipliers' => [],
            ],
        ],
    ],

    'rosario' => [
        'label'    => 'Rosario',
        'country'  => 'AR',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => array (
  'lat_min' => -33.01,
  'lat_max' => -32.86,
  'lng_min' => -60.77,
  'lng_max' => -60.58,
),
        'zones' => [
            'centro_rosario' => [
                'label'       => 'Centro / Pichincha',
                'price_m2'    => ['min'=>1400,'max'=>2200,'avg'=>1750],
                'description' => 'Zona premium de Rosario.',
                'coords'      => array (
  'lat' => -32.947,
  'lng' => -60.639,
),
                'keywords'    => array (
  0 => 'pichincha',
  1 => 'centro rosario',
  2 => 'republica rosario',
),
                'multipliers' => [],
            ],
            'echesortu' => [
                'label'       => 'Echesortu / Fisherton',
                'price_m2'    => ['min'=>1100,'max'=>1700,'avg'=>1350],
                'description' => 'Barrio residencial premium.',
                'coords'      => array (
  'lat' => -32.93,
  'lng' => -60.71,
),
                'keywords'    => array (
  0 => 'echesortu',
  1 => 'fisherton rosario',
),
                'multipliers' => [],
            ],
            'general' => [
                'label'       => 'Rosario (general)',
                'price_m2'    => ['min'=>900,'max'=>1600,'avg'=>1200],
                'description' => 'Promedio ciudad de Rosario.',
                'coords'      => array (
  'lat' => -32.947,
  'lng' => -60.639,
),
                'keywords'    => array (
  0 => 'rosario',
),
                'multipliers' => [],
            ],
        ],
    ],

    'cordoba' => [
        'label'    => 'Córdoba Capital',
        'country'  => 'AR',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => array (
  'lat_min' => -31.505,
  'lat_max' => -31.3,
  'lng_min' => -64.28,
  'lng_max' => -64.1,
),
        'zones' => [
            'nueva_cordoba' => [
                'label'       => 'Nueva Córdoba',
                'price_m2'    => ['min'=>1400,'max'=>2200,'avg'=>1750],
                'description' => 'El barrio universitario más demandado.',
                'coords'      => array (
  'lat' => -31.42,
  'lng' => -64.189,
),
                'keywords'    => array (
  0 => 'nueva cordoba',
  1 => 'nueva córdoba',
),
                'multipliers' => [],
            ],
            'cerro_rosas' => [
                'label'       => 'Cerro de las Rosas / General Paz',
                'price_m2'    => ['min'=>1200,'max'=>1900,'avg'=>1500],
                'description' => 'Zona residencial premium.',
                'coords'      => array (
  'lat' => -31.39,
  'lng' => -64.21,
),
                'keywords'    => array (
  0 => 'cerro de las rosas',
  1 => 'general paz cordoba',
),
                'multipliers' => [],
            ],
            'general' => [
                'label'       => 'Córdoba Capital (general)',
                'price_m2'    => ['min'=>900,'max'=>1600,'avg'=>1200],
                'description' => 'Promedio ciudad de Córdoba.',
                'coords'      => array (
  'lat' => -31.417,
  'lng' => -64.183,
),
                'keywords'    => array (
  0 => 'cordoba capital',
  1 => 'córdoba capital',
  2 => 'cordoba',
  3 => 'córdoba',
),
                'multipliers' => [],
            ],
        ],
    ],

];
