<?php

return [
    'locales' => [
        'en' => 'English',
        'fr' => 'Francais',
        'de' => 'Deutsch',
        'es' => 'Espanol',
    ],

    'imports' => [
        'disk' => env('TRAVTOOL_IMPORT_DISK', 'local'),
        'directory' => env('TRAVTOOL_IMPORT_DIRECTORY', 'map-imports'),
        'staging_chunk_size' => (int) env('TRAVTOOL_IMPORT_STAGING_CHUNK_SIZE', 500),
        'snapshot_chunk_size' => (int) env('TRAVTOOL_IMPORT_SNAPSHOT_CHUNK_SIZE', 500),
    ],

    'worlds' => [
        'rof' => [
            'name' => 'Rise of Fire Europe x2',
            'base_url' => 'https://rof.x2.europe.travian.com/',
            'map_sql_url' => 'https://rof.x2.europe.travian.com/map.sql',
            'server_timezone' => 'UTC',
            'import_time' => '00:10',
            'speed' => 2,
            'is_active' => true,
        ],
        'ts2' => [
            'name' => 'TS2 Europe x1',
            'base_url' => 'https://ts2.x1.europe.travian.com/',
            'map_sql_url' => 'https://ts2.x1.europe.travian.com/map.sql',
            'server_timezone' => 'UTC',
            'import_time' => '00:10',
            'speed' => 1,
            'is_active' => true,
        ],
    ],
];
