<?php

return [
    'common' => [
        'app_name' => 'Travtool',
        'back_home' => 'Volver al inicio',
        'go_to_login' => 'Ir al acceso',
        'open_prototype' => 'Abrir el prototipo',
        'language' => 'Idioma',
    ],
    'home' => [
        'meta' => [
            'title' => 'Travtool',
        ],
        'header' => [
            'tagline' => 'Inteligencia para Travian, ingestión de map.sql, herramientas privadas y futuros módulos comunitarios.',
            'inactive_finder' => 'Buscador de inactivos',
            'login' => 'Acceso',
        ],
        'hero' => [
            'badge' => 'Build público de Travian en progreso',
            'title_before' => 'Una base limpia para convertir',
            'title_highlight' => ' map.sql ',
            'title_after' => 'en una herramienta útil.',
            'description' => 'Travtool comienza con un buscador privado de inactivos, pero la arquitectura ya apunta a un producto real para la comunidad Travian: mundos seguidos, importaciones diarias, análisis e interfaces orientadas al trabajo.',
            'stack_1' => 'Laravel + Inertia + Vue',
            'stack_2' => 'MariaDB',
            'stack_3' => 'Pipeline de map.sql',
        ],
        'cards' => [
            'inactive' => [
                'eyebrow' => 'Prototipo V1',
                'title' => 'Buscador de inactivos',
                'description' => 'Preparar el motor de map.sql, los filtros útiles y las primeras señales para detectar objetivos con baja actividad.',
                'cta' => 'Abrir el prototipo',
            ],
            'login' => [
                'eyebrow' => 'Acceso privado',
                'title' => 'Acceso',
                'description' => 'Entrar al espacio de trabajo de Travtool, a los mundos seguidos y más adelante a las herramientas reservadas al equipo.',
                'cta' => 'Abrir la interfaz',
            ],
        ],
        'footer' => [
            'line_1' => 'Paso actual: landing, acceso privado y prototipo del buscador de inactivos.',
            'line_2' => 'Siguiente paso: importación bruta de map.sql, staging, snapshots y proyección del estado actual.',
            'line_3' => 'Travtool.farmitrax.ca',
        ],
    ],
    'login' => [
        'meta' => [
            'title' => 'Acceso',
        ],
        'nav' => [
            'prototype' => 'Ver el prototipo',
        ],
        'panel' => [
            'eyebrow' => 'Área privada',
            'title' => 'Interfaz de acceso de Travtool',
            'description' => 'Esta primera pantalla prepara el acceso a los mundos seguidos, a las importaciones de map.sql y a las herramientas internas. El backend de autenticación se conectará después.',
            'future_title' => 'Más adelante',
            'future_text' => 'Cuentas privadas, equipos, mundos seguidos y un registro de importaciones diarias.',
            'today_title' => 'Hoy',
            'today_text' => 'Prototipo visual que prepara la estructura del producto antes de conectar la autenticación real.',
        ],
        'form' => [
            'eyebrow' => 'Acceso',
            'title' => 'Entrar en el área de control',
            'email' => 'Correo electrónico',
            'email_placeholder' => 'martin@travtool.app',
            'password' => 'Contraseña',
            'password_placeholder' => '••••••••',
            'remember' => 'Recordar esta sesión',
            'submit' => 'Continuar',
            'notice' => 'La interfaz está lista. La autenticación del backend todavía no está conectada: este paso servirá después para enlazar el acceso privado de Laravel.',
        ],
    ],
    'inactive_finder' => [
        'meta' => [
            'title' => 'Buscador de inactivos',
        ],
        'hero' => [
            'eyebrow' => 'Buscador de inactivos',
            'title' => 'Prototipo de la herramienta map.sql',
            'description' => 'Esta pantalla prepara el futuro módulo: importaciones diarias, snapshots, filtros geográficos y detección de cuentas con baja actividad.',
        ],
        'stats' => [
            'worlds' => [
                'label' => 'Mundos seguidos',
                'value' => '01',
                'detail' => 'Un primer mundo se conectará a continuación',
            ],
            'last_import' => [
                'label' => 'Última importación',
                'value' => '—',
                'detail' => 'El pipeline de map.sql todavía no está conectado',
            ],
            'signals' => [
                'label' => 'Señales activas',
                'value' => '—',
                'detail' => 'Todavía hay que calcular las métricas de población, aldeas y cambios',
            ],
        ],
        'filters' => [
            'title' => 'Filtros de objetivos',
            'world' => [
                'label' => 'Mundo',
                'value' => 'rof.x2.europe.travian.com',
            ],
            'radius' => [
                'label' => 'Radio',
                'value' => '15 casillas alrededor de las aldeas objetivo',
            ],
            'heuristic' => [
                'label' => 'Heurística',
                'value' => 'Población estable + ningún desarrollo visible',
            ],
        ],
        'results' => [
            'title' => 'Zona de resultados',
            'card_title' => 'Las primeras listas aparecerán aquí',
            'description' => 'Una vez conectado el pipeline de map.sql, esta vista mostrará aldeas filtradas, deltas de población, coordenadas, distancia y puntuaciones probables de inactividad.',
            'bullet_1' => 'Población estable en varios snapshots',
            'bullet_2' => 'Ninguna aldea nueva detectada',
            'bullet_3' => 'Sin alianza o con actividad visible débil',
        ],
    ],
];
