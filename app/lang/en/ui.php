<?php

return [
    'common' => [
        'app_name' => 'Travtool',
        'back_home' => 'Back to home',
        'go_to_login' => 'Go to login',
        'open_prototype' => 'Open prototype',
        'language' => 'Language',
    ],
    'home' => [
        'meta' => [
            'title' => 'Travtool',
        ],
        'header' => [
            'tagline' => 'Travian intelligence, map.sql ingestion, private tools and future community modules.',
            'inactive_finder' => 'Inactive finder',
            'login' => 'Login',
        ],
        'hero' => [
            'badge' => 'Public Travian build in progress',
            'title_before' => 'A clean base to turn',
            'title_highlight' => ' map.sql ',
            'title_after' => 'into something useful.',
            'description' => 'Travtool starts with a private inactive finder, but the architecture is already aimed at a real product for the Travian community: tracked worlds, daily imports, analysis and task-focused interfaces.',
            'stack_1' => 'Laravel + Inertia + Vue',
            'stack_2' => 'MariaDB',
            'stack_3' => 'map.sql pipeline',
        ],
        'cards' => [
            'inactive' => [
                'eyebrow' => 'Prototype V1',
                'title' => 'Inactive finder',
                'description' => 'Prepare the map.sql engine, the useful filters and the first signals needed to identify low-activity targets.',
                'cta' => 'Open the prototype',
            ],
            'login' => [
                'eyebrow' => 'Private access',
                'title' => 'Login',
                'description' => 'Reach the Travtool workspace, tracked worlds and later the tools reserved for the team.',
                'cta' => 'Open the interface',
            ],
        ],
        'footer' => [
            'line_1' => 'Current step: landing page, private access and inactive finder prototype.',
            'line_2' => 'Next step: raw map.sql import, staging, snapshots and current-state projection.',
            'line_3' => 'Travtool.farmitrax.ca',
        ],
    ],
    'login' => [
        'meta' => [
            'title' => 'Login',
        ],
        'nav' => [
            'prototype' => 'View the prototype',
        ],
        'panel' => [
            'eyebrow' => 'Private area',
            'title' => 'Travtool login interface',
            'description' => 'This first screen prepares access to tracked worlds, map.sql imports and internal tools. The authentication backend will be wired in next.',
            'future_title' => 'Eventually',
            'future_text' => 'Private accounts, teams, tracked worlds and a log of daily imports.',
            'today_title' => 'Today',
            'today_text' => 'A visual prototype that prepares the product structure before the real authentication is connected.',
        ],
        'form' => [
            'eyebrow' => 'Login',
            'title' => 'Access the control area',
            'email' => 'Email address',
            'email_placeholder' => 'martin@travtool.app',
            'password' => 'Password',
            'password_placeholder' => '••••••••',
            'remember' => 'Remember this session',
            'submit' => 'Continue',
            'notice' => 'The interface is ready. The backend authentication is not connected yet: this step will later link to the private Laravel access.',
        ],
    ],
    'inactive_finder' => [
        'meta' => [
            'title' => 'Inactive finder',
        ],
        'hero' => [
            'eyebrow' => 'Inactive finder',
            'title' => 'Prototype for the map.sql tool',
            'description' => 'This screen prepares the future module: daily imports, snapshots, geographic filters and low-activity account detection.',
        ],
        'stats' => [
            'worlds' => [
                'label' => 'Tracked worlds',
                'value' => '01',
                'detail' => 'One first world will be connected next',
            ],
            'last_import' => [
                'label' => 'Last import',
                'value' => '—',
                'detail' => 'The map.sql pipeline is not wired yet',
            ],
            'signals' => [
                'label' => 'Active signals',
                'value' => '—',
                'detail' => 'Population, village and change metrics still have to be computed',
            ],
        ],
        'filters' => [
            'title' => 'Target filters',
            'world' => [
                'label' => 'World',
                'value' => 'rof.x2.europe.travian.com',
            ],
            'radius' => [
                'label' => 'Radius',
                'value' => '15 tiles around target villages',
            ],
            'heuristic' => [
                'label' => 'Heuristic',
                'value' => 'Stable population + no visible development',
            ],
        ],
        'results' => [
            'title' => 'Results area',
            'card_title' => 'The first lists will appear here',
            'description' => 'Once the map.sql pipeline is connected, this view will display filtered villages, population deltas, coordinates, distance and probable inactivity scores.',
            'bullet_1' => 'Stable population across several snapshots',
            'bullet_2' => 'No newly detected village',
            'bullet_3' => 'No alliance or weak visible activity',
        ],
    ],
];
