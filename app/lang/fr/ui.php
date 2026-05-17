<?php

return [
    'common' => [
        'app_name' => 'Travtool',
        'back_home' => "Retour à l'accueil",
        'go_to_login' => 'Aller à la connexion',
        'open_prototype' => 'Ouvrir le prototype',
        'language' => 'Langue',
    ],
    'home' => [
        'meta' => [
            'title' => 'Travtool',
        ],
        'header' => [
            'tagline' => 'Intelligence Travian, ingestion map.sql, outils privés et futurs modules communautaires.',
            'inactive_finder' => "Chercheur d'inactifs",
            'login' => 'Connexion',
        ],
        'hero' => [
            'badge' => 'Build public Travian en cours',
            'title_before' => 'Une base propre pour transformer',
            'title_highlight' => ' map.sql ',
            'title_after' => 'en outil utile.',
            'description' => "Travtool commence par un chercheur d'inactifs privé, mais l’architecture vise déjà un vrai produit pour la communauté Travian : mondes suivis, imports quotidiens, analyse et interfaces métier.",
            'stack_1' => 'Laravel + Inertia + Vue',
            'stack_2' => 'MariaDB',
            'stack_3' => 'Pipeline map.sql',
        ],
        'cards' => [
            'inactive' => [
                'eyebrow' => 'Prototype V1',
                'title' => "Chercheur d'inactifs",
                'description' => "Préparer le moteur map.sql, les filtres utiles et les premiers signaux pour repérer les cibles à faible activité.",
                'cta' => 'Ouvrir le prototype',
            ],
            'login' => [
                'eyebrow' => 'Accès privé',
                'title' => 'Connexion',
                'description' => "Accéder à l’espace de travail Travtool, aux mondes suivis et plus tard aux outils réservés à l’équipe.",
                'cta' => "Aller à l'interface",
            ],
        ],
        'footer' => [
            'line_1' => "Étape actuelle : landing, accès privé, prototype du chercheur d'inactifs.",
            'line_2' => 'Étape suivante : import brut map.sql, staging, snapshots et projection courante.',
            'line_3' => 'Travtool.farmitrax.ca',
        ],
    ],
    'login' => [
        'meta' => [
            'title' => 'Connexion',
        ],
        'nav' => [
            'prototype' => 'Voir le prototype',
        ],
        'panel' => [
            'eyebrow' => 'Espace privé',
            'title' => 'Interface de connexion Travtool',
            'description' => "Ce premier écran prépare l’accès aux mondes suivis, aux imports map.sql et aux outils internes. Le backend d’authentification sera branché ensuite.",
            'future_title' => 'À terme',
            'future_text' => 'Comptes privés, équipes, mondes suivis et journal des imports quotidiens.',
            'today_title' => "Aujourd'hui",
            'today_text' => "Prototype visuel pour préparer la structure produit avant de brancher l’auth réelle.",
        ],
        'form' => [
            'eyebrow' => 'Connexion',
            'title' => "Accéder à l’espace de pilotage",
            'email' => 'Adresse e-mail',
            'email_placeholder' => 'martin@travtool.app',
            'password' => 'Mot de passe',
            'password_placeholder' => '••••••••',
            'remember' => 'Se souvenir de cette session',
            'submit' => 'Continuer',
            'notice' => "Interface prête. L’auth backend n’est pas encore branchée : cette étape servira ensuite à connecter l’accès privé Laravel.",
        ],
    ],
    'inactive_finder' => [
        'meta' => [
            'title' => "Chercheur d'inactifs",
        ],
        'hero' => [
            'eyebrow' => "Chercheur d'inactifs",
            'title' => 'Prototype de l’outil map.sql',
            'description' => 'Cet écran prépare le futur module : imports quotidiens, snapshots, filtres géographiques et détection des comptes à faible activité.',
        ],
        'stats' => [
            'worlds' => [
                'label' => 'Mondes suivis',
                'value' => '01',
                'detail' => 'Un premier monde branché à venir',
            ],
            'last_import' => [
                'label' => 'Dernier import',
                'value' => '—',
                'detail' => 'Pipeline map.sql pas encore raccordé',
            ],
            'signals' => [
                'label' => 'Signaux actifs',
                'value' => '—',
                'detail' => 'Population, villages et changements à calculer',
            ],
        ],
        'filters' => [
            'title' => 'Filtres cibles',
            'world' => [
                'label' => 'Monde',
                'value' => 'rof.x2.europe.travian.com',
            ],
            'radius' => [
                'label' => 'Rayon',
                'value' => '15 cases autour des villages cibles',
            ],
            'heuristic' => [
                'label' => 'Heuristique',
                'value' => 'Population stable + aucun développement visible',
            ],
        ],
        'results' => [
            'title' => 'Zone de résultats',
            'card_title' => 'Les premières listes sortiront ici',
            'description' => 'Une fois le pipeline map.sql branché, cette vue présentera les villages filtrés, les deltas de population, les coordonnées, la distance et les scores d’inactivité probables.',
            'bullet_1' => 'Population stable sur plusieurs snapshots',
            'bullet_2' => 'Aucun nouveau village détecté',
            'bullet_3' => 'Hors alliance ou activité faible',
        ],
    ],
];
