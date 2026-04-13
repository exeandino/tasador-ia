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

    // ─── MIAMI / FLORIDA (USA) ────────────────────────────────────
    // Precios en USD/m² (convertidos desde USD/sqft × 10.764)
    // Fuente: Zillow / Realtor.com promedio Q1 2026
    'miami' => [
        'label'    => 'Miami, Florida',
        'country'  => 'US',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => ['lat_min'=>25.70,'lat_max'=>25.95,'lng_min'=>-80.45,'lng_max'=>-80.10],
        'zones' => [
            'brickell' => [
                'label'       => 'Brickell / Downtown',
                'price_m2'    => ['min'=>4800,'avg'=>6500,'max'=>10000],
                'description' => 'Financial district con torres de lujo frente al mar.',
                'coords'      => ['lat'=>25.758,'lng'=>-80.193],
                'keywords'    => ['brickell','downtown miami','financial district miami'],
                'multipliers' => [],
            ],
            'south_beach' => [
                'label'       => 'South Beach / Miami Beach',
                'price_m2'    => ['min'=>5500,'avg'=>8000,'max'=>18000],
                'description' => 'Frente al Atlántico. Precios premium. Art Deco District.',
                'coords'      => ['lat'=>25.782,'lng'=>-80.131],
                'keywords'    => ['south beach','miami beach','ocean drive','collins ave','bal harbour'],
                'multipliers' => [],
            ],
            'wynwood' => [
                'label'       => 'Wynwood / Midtown / Design District',
                'price_m2'    => ['min'=>4000,'avg'=>5800,'max'=>8500],
                'description' => 'Barrio artístico en auge, alta demanda de profesionales.',
                'coords'      => ['lat'=>25.801,'lng'=>-80.199],
                'keywords'    => ['wynwood','midtown miami','design district miami'],
                'multipliers' => [],
            ],
            'coral_gables' => [
                'label'       => 'Coral Gables',
                'price_m2'    => ['min'=>4500,'avg'=>6200,'max'=>9500],
                'description' => 'Ciudad planificada premium con canales y arquitectura mediterránea.',
                'coords'      => ['lat'=>25.748,'lng'=>-80.268],
                'keywords'    => ['coral gables','miracle mile coral gables'],
                'multipliers' => [],
            ],
            'coconut_grove' => [
                'label'       => 'Coconut Grove',
                'price_m2'    => ['min'=>4200,'avg'=>5800,'max'=>9000],
                'description' => 'Barrio arbolado bohemio frente a Biscayne Bay.',
                'coords'      => ['lat'=>25.727,'lng'=>-80.238],
                'keywords'    => ['coconut grove','cocowalk','grove miami'],
                'multipliers' => [],
            ],
            'edgewater' => [
                'label'       => 'Edgewater / Arts & Entertainment District',
                'price_m2'    => ['min'=>3500,'avg'=>5000,'max'=>7500],
                'description' => 'En pleno desarrollo, vistas a Biscayne Bay, torres nuevas.',
                'coords'      => ['lat'=>25.789,'lng'=>-80.187],
                'keywords'    => ['edgewater miami','arts district miami','overtown'],
                'multipliers' => [],
            ],
            'aventura' => [
                'label'       => 'Aventura / Sunny Isles Beach',
                'price_m2'    => ['min'=>3800,'avg'=>5500,'max'=>10000],
                'description' => 'Norte de Miami. Torres de lujo frente al mar. Gran comunidad latinoamericana.',
                'coords'      => ['lat'=>25.953,'lng'=>-80.139],
                'keywords'    => ['aventura','sunny isles','sunny isles beach','hallandale'],
                'multipliers' => [],
            ],
            'doral' => [
                'label'       => 'Doral / Hialeah / Airport Area',
                'price_m2'    => ['min'=>2800,'avg'=>3800,'max'=>5500],
                'description' => 'Zona de familias y negocios. Cerca del aeropuerto internacional.',
                'coords'      => ['lat'=>25.820,'lng'=>-80.352],
                'keywords'    => ['doral','doral miami','hialeah','airport miami','miami lakes'],
                'multipliers' => [],
            ],
            'kendall' => [
                'label'       => 'Kendall / South Miami',
                'price_m2'    => ['min'=>2500,'avg'=>3500,'max'=>5000],
                'description' => 'Suburbios del sur. Casas familiares, buena relación precio/calidad.',
                'coords'      => ['lat'=>25.680,'lng'=>-80.390],
                'keywords'    => ['kendall','south miami','pinecrest','palmetto bay'],
                'multipliers' => [],
            ],
            'miami_general' => [
                'label'       => 'Miami — General',
                'price_m2'    => ['min'=>2500,'avg'=>4200,'max'=>7000],
                'description' => 'Promedio del área metropolitana de Miami-Dade.',
                'coords'      => ['lat'=>25.773,'lng'=>-80.193],
                'keywords'    => ['miami','miami florida','miami fl','miami eeuu','miami usa','miami dade'],
                'multipliers' => [],
            ],
        ],
    ],

    // ─── MONTEVIDEO, URUGUAY ──────────────────────────────────────
    'montevideo' => [
        'label'    => 'Montevideo',
        'country'  => 'UY',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => ['lat_min'=>-34.95,'lat_max'=>-34.75,'lng_min'=>-56.35,'lng_max'=>-56.05],
        'zones' => [
            'pocitos' => [
                'label'       => 'Pocitos / Punta Carretas',
                'price_m2'    => ['min'=>1800,'avg'=>2500,'max'=>3800],
                'description' => 'El barrio más cotizado de Montevideo, frente al Río de la Plata.',
                'coords'      => ['lat'=>-34.908,'lng'=>-56.152],
                'keywords'    => ['pocitos','punta carretas'],
                'multipliers' => [],
            ],
            'ciudad_vieja' => [
                'label'       => 'Ciudad Vieja / Centro',
                'price_m2'    => ['min'=>1200,'avg'=>1800,'max'=>2800],
                'description' => 'Centro histórico con demanda de oficinas y residencias.',
                'coords'      => ['lat'=>-34.906,'lng'=>-56.200],
                'keywords'    => ['ciudad vieja montevideo','centro montevideo','centro'],
                'multipliers' => [],
            ],
            'carrasco' => [
                'label'       => 'Carrasco / Punta Gorda',
                'price_m2'    => ['min'=>2500,'avg'=>3800,'max'=>6000],
                'description' => 'El barrio más exclusivo de Montevideo, casas y apartamentos de lujo.',
                'coords'      => ['lat'=>-34.863,'lng'=>-56.052],
                'keywords'    => ['carrasco montevideo','punta gorda montevideo'],
                'multipliers' => [],
            ],
            'montevideo_general' => [
                'label'       => 'Montevideo (general)',
                'price_m2'    => ['min'=>900,'avg'=>1500,'max'=>2400],
                'description' => 'Promedio Montevideo.',
                'coords'      => ['lat'=>-34.901,'lng'=>-56.165],
                'keywords'    => ['montevideo','montevideo uruguay'],
                'multipliers' => [],
            ],
        ],
    ],

    // ─── SANTIAGO, CHILE ─────────────────────────────────────────
    'santiago' => [
        'label'    => 'Santiago de Chile',
        'country'  => 'CL',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => ['lat_min'=>-33.65,'lat_max'=>-33.35,'lng_min'=>-70.80,'lng_max'=>-70.50],
        'zones' => [
            'las_condes' => [
                'label'       => 'Las Condes / Vitacura',
                'price_m2'    => ['min'=>2800,'avg'=>4000,'max'=>6500],
                'description' => 'Zona oriente premium. Barrio financiero y residencial de lujo.',
                'coords'      => ['lat'=>-33.411,'lng'=>-70.557],
                'keywords'    => ['las condes','vitacura','lo barnechea'],
                'multipliers' => [],
            ],
            'providencia' => [
                'label'       => 'Providencia / Ñuñoa',
                'price_m2'    => ['min'=>2200,'avg'=>3200,'max'=>5000],
                'description' => 'Barrio cosmopolita con oferta cultural y gastronómica.',
                'coords'      => ['lat'=>-33.430,'lng'=>-70.618],
                'keywords'    => ['providencia','providencia santiago','ñuñoa','nunoa'],
                'multipliers' => [],
            ],
            'santiago_centro' => [
                'label'       => 'Santiago Centro / Barrio Italia',
                'price_m2'    => ['min'=>1500,'avg'=>2300,'max'=>3800],
                'description' => 'Centro histórico y barrios en renovación con alta demanda.',
                'coords'      => ['lat'=>-33.447,'lng'=>-70.670],
                'keywords'    => ['santiago centro','barrio italia','barrio lastarria','bellas artes'],
                'multipliers' => [],
            ],
            'maipú' => [
                'label'       => 'Maipú / Pudahuel',
                'price_m2'    => ['min'=>1200,'avg'=>1800,'max'=>2800],
                'description' => 'Comunas populares del poniente, buena conectividad.',
                'coords'      => ['lat'=>-33.514,'lng'=>-70.775],
                'keywords'    => ['maipu','maipú','pudahuel'],
                'multipliers' => [],
            ],
            'santiago_general' => [
                'label'       => 'Santiago (general)',
                'price_m2'    => ['min'=>1500,'avg'=>2500,'max'=>4000],
                'description' => 'Promedio Gran Santiago.',
                'coords'      => ['lat'=>-33.459,'lng'=>-70.648],
                'keywords'    => ['santiago chile','santiago de chile'],
                'multipliers' => [],
            ],
        ],
    ],

    // ─── BOGOTÁ, COLOMBIA ────────────────────────────────────────
    'bogota' => [
        'label'    => 'Bogotá',
        'country'  => 'CO',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => ['lat_min'=>4.45,'lat_max'=>4.85,'lng_min'=>-74.25,'lng_max'=>-73.95],
        'zones' => [
            'chapinero' => [
                'label'       => 'Chapinero / Usaquén',
                'price_m2'    => ['min'=>2000,'avg'=>2800,'max'=>5000],
                'description' => 'Zona norte premium de Bogotá. Restaurantes, centros comerciales y oficinas.',
                'coords'      => ['lat'=>4.653,'lng'=>-74.056],
                'keywords'    => ['chapinero','usaquen','usaquén','parque 93','zona rosa bogota'],
                'multipliers' => [],
            ],
            'chico' => [
                'label'       => 'El Chico / Rosales / Chicó',
                'price_m2'    => ['min'=>2500,'avg'=>3500,'max'=>6000],
                'description' => 'El barrio más exclusivo de Bogotá.',
                'coords'      => ['lat'=>4.676,'lng'=>-74.050],
                'keywords'    => ['el chico','chico bogota','rosales bogota'],
                'multipliers' => [],
            ],
            'bogota_general' => [
                'label'       => 'Bogotá (general)',
                'price_m2'    => ['min'=>800,'avg'=>1500,'max'=>3000],
                'description' => 'Promedio Bogotá.',
                'coords'      => ['lat'=>4.711,'lng'=>-74.073],
                'keywords'    => ['bogota','bogotá','bogota colombia'],
                'multipliers' => [],
            ],
        ],
    ],

    // ─── CIUDAD DE MÉXICO ────────────────────────────────────────
    'cdmx' => [
        'label'    => 'Ciudad de México',
        'country'  => 'MX',
        'currency' => 'USD',
        'updated'  => '2026-04',
        'bounds'   => ['lat_min'=>19.20,'lat_max'=>19.60,'lng_min'=>-99.30,'lng_max'=>-98.95],
        'zones' => [
            'polanco' => [
                'label'       => 'Polanco / Lomas de Chapultepec',
                'price_m2'    => ['min'=>3500,'avg'=>5000,'max'=>9000],
                'description' => 'Las zonas más exclusivas de la Ciudad de México.',
                'coords'      => ['lat'=>19.431,'lng'=>-99.200],
                'keywords'    => ['polanco','lomas de chapultepec','lomas chapultepec','cdmx polanco'],
                'multipliers' => [],
            ],
            'condesa' => [
                'label'       => 'Condesa / Roma',
                'price_m2'    => ['min'=>2800,'avg'=>4000,'max'=>7000],
                'description' => 'Barrios cosmopolitas con alta demanda de jóvenes profesionales.',
                'coords'      => ['lat'=>19.412,'lng'=>-99.173],
                'keywords'    => ['condesa','roma norte','roma sur cdmx','colonia roma'],
                'multipliers' => [],
            ],
            'santa_fe' => [
                'label'       => 'Santa Fe / Álvaro Obregón',
                'price_m2'    => ['min'=>2200,'avg'=>3200,'max'=>5500],
                'description' => 'Zona corporativa moderna al poniente de la ciudad.',
                'coords'      => ['lat'=>19.362,'lng'=>-99.259],
                'keywords'    => ['santa fe cdmx','santa fe mexico','alvaro obregon'],
                'multipliers' => [],
            ],
            'cdmx_general' => [
                'label'       => 'Ciudad de México (general)',
                'price_m2'    => ['min'=>1200,'avg'=>2200,'max'=>4000],
                'description' => 'Promedio CDMX.',
                'coords'      => ['lat'=>19.432,'lng'=>-99.133],
                'keywords'    => ['ciudad de mexico','cdmx','df mexico','mexico city'],
                'multipliers' => [],
            ],
        ],
    ],

];
