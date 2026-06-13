<?php

return [
    'locales' => [
        'en' => 'English',
        'fr' => 'Francais',
        'de' => 'Deutsch',
        'es' => 'Espanol',
        'hu' => 'Magyar',
    ],

    'imports' => [
        'disk' => env('TRAVTOOL_IMPORT_DISK', 'local'),
        'directory' => env('TRAVTOOL_IMPORT_DIRECTORY', 'map-imports'),
        'temporary_directory' => env('TRAVTOOL_IMPORT_TEMP_DIRECTORY', 'map-imports-temp'),
        'retain_raw_files' => (bool) env('TRAVTOOL_IMPORT_RETAIN_RAW_FILES', false),
        'staging_chunk_size' => (int) env('TRAVTOOL_IMPORT_STAGING_CHUNK_SIZE', 500),
        'snapshot_chunk_size' => (int) env('TRAVTOOL_IMPORT_SNAPSHOT_CHUNK_SIZE', 500),
    ],

    'catalog' => [
        'calendar_url' => env('TRAVTOOL_CATALOG_CALENDAR_URL', 'https://lobby.legends.travian.com/api/calendar'),
        'metadata_url' => env('TRAVTOOL_CATALOG_METADATA_URL', 'https://lobby.legends.travian.com/api/metadata'),
        'default_server_timezone' => env('TRAVTOOL_CATALOG_DEFAULT_SERVER_TIMEZONE', 'UTC'),
        'default_import_time' => env('TRAVTOOL_CATALOG_DEFAULT_IMPORT_TIME', '00:10'),
        'activate_new_worlds' => (bool) env('TRAVTOOL_CATALOG_ACTIVATE_NEW_WORLDS', false),
    ],

    'worlds' => [
        'rof' => [
            'name' => 'Rise of Fire Europe x2',
            'base_url' => 'https://rof.x2.europe.travian.com/',
            'map_sql_url' => 'https://rof.x2.europe.travian.com/map.sql',
            'map_topology' => 'plane',
            'map_radius' => 400,
            'server_timezone' => 'UTC',
            'import_time' => '00:10',
            'speed' => 2,
            'is_active' => false,
        ],
        'ts2' => [
            'name' => 'TS2 Europe x1',
            'base_url' => 'https://ts2.x1.europe.travian.com/',
            'map_sql_url' => 'https://ts2.x1.europe.travian.com/map.sql',
            'map_topology' => 'torus',
            'map_radius' => 200,
            'server_timezone' => 'UTC',
            'import_time' => '00:10',
            'speed' => 1,
            'is_active' => true,
        ],
    ],
];
