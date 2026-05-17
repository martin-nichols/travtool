<?php

return [
    'common' => [
        'app_name' => 'Travtool',
        'back_home' => 'Zur Startseite',
        'go_to_login' => 'Zum Login',
        'open_prototype' => 'Prototyp öffnen',
        'language' => 'Sprache',
    ],
    'home' => [
        'meta' => [
            'title' => 'Travtool',
        ],
        'header' => [
            'tagline' => 'Travian-Intelligence, map.sql-Import, private Werkzeuge und zukünftige Community-Module.',
            'inactive_finder' => 'Inaktiven-Suche',
            'login' => 'Anmeldung',
        ],
        'hero' => [
            'badge' => 'Öffentlicher Travian-Build in Arbeit',
            'title_before' => 'Eine saubere Basis, um',
            'title_highlight' => ' map.sql ',
            'title_after' => 'in ein nützliches Werkzeug zu verwandeln.',
            'description' => 'Travtool startet mit einer privaten Inaktiven-Suche, aber die Architektur zielt bereits auf ein echtes Produkt für die Travian-Community: beobachtete Welten, tägliche Importe, Analyse und auf Aufgaben ausgerichtete Oberflächen.',
            'stack_1' => 'Laravel + Inertia + Vue',
            'stack_2' => 'MariaDB',
            'stack_3' => 'map.sql-Pipeline',
        ],
        'cards' => [
            'inactive' => [
                'eyebrow' => 'Prototyp V1',
                'title' => 'Inaktiven-Suche',
                'description' => 'Die map.sql-Engine, nützliche Filter und erste Signale vorbereiten, um Ziele mit geringer Aktivität zu erkennen.',
                'cta' => 'Prototyp öffnen',
            ],
            'login' => [
                'eyebrow' => 'Privater Zugang',
                'title' => 'Anmeldung',
                'description' => 'Zum Travtool-Arbeitsbereich, zu beobachteten Welten und später zu den nur für das Team vorgesehenen Werkzeugen gelangen.',
                'cta' => 'Zur Oberfläche',
            ],
        ],
        'footer' => [
            'line_1' => 'Aktueller Schritt: Landingpage, privater Zugang und Prototyp der Inaktiven-Suche.',
            'line_2' => 'Nächster Schritt: roher map.sql-Import, Staging, Snapshots und Projektion des aktuellen Zustands.',
            'line_3' => 'Travtool.farmitrax.ca',
        ],
    ],
    'login' => [
        'meta' => [
            'title' => 'Anmeldung',
        ],
        'nav' => [
            'prototype' => 'Prototyp ansehen',
        ],
        'panel' => [
            'eyebrow' => 'Privater Bereich',
            'title' => 'Travtool-Login-Oberfläche',
            'description' => 'Dieser erste Bildschirm bereitet den Zugang zu beobachteten Welten, map.sql-Importen und internen Werkzeugen vor. Das Auth-Backend wird als Nächstes angebunden.',
            'future_title' => 'Später',
            'future_text' => 'Private Konten, Teams, beobachtete Welten und ein Protokoll der täglichen Importe.',
            'today_title' => 'Heute',
            'today_text' => 'Visueller Prototyp, der die Produktstruktur vorbereitet, bevor die echte Authentifizierung verbunden wird.',
        ],
        'form' => [
            'eyebrow' => 'Anmeldung',
            'title' => 'Zugriff auf den Steuerbereich',
            'email' => 'E-Mail-Adresse',
            'email_placeholder' => 'martin@travtool.app',
            'password' => 'Passwort',
            'password_placeholder' => '••••••••',
            'remember' => 'Diese Sitzung merken',
            'submit' => 'Weiter',
            'notice' => 'Die Oberfläche ist bereit. Die Backend-Authentifizierung ist noch nicht angeschlossen: dieser Schritt wird später den privaten Laravel-Zugang verbinden.',
        ],
    ],
    'inactive_finder' => [
        'meta' => [
            'title' => 'Inaktiven-Suche',
        ],
        'hero' => [
            'eyebrow' => 'Inaktiven-Suche',
            'title' => 'Prototyp des map.sql-Werkzeugs',
            'description' => 'Dieser Bildschirm bereitet das zukünftige Modul vor: tägliche Importe, Snapshots, geografische Filter und Erkennung von Konten mit geringer Aktivität.',
        ],
        'stats' => [
            'worlds' => [
                'label' => 'Beobachtete Welten',
                'value' => '01',
                'detail' => 'Eine erste Welt wird als Nächstes angebunden',
            ],
            'last_import' => [
                'label' => 'Letzter Import',
                'value' => '—',
                'detail' => 'Die map.sql-Pipeline ist noch nicht angebunden',
            ],
            'signals' => [
                'label' => 'Aktive Signale',
                'value' => '—',
                'detail' => 'Bevölkerungs-, Dorf- und Änderungsmetriken müssen noch berechnet werden',
            ],
        ],
        'filters' => [
            'title' => 'Zielfilter',
            'world' => [
                'label' => 'Welt',
                'value' => 'rof.x2.europe.travian.com',
            ],
            'radius' => [
                'label' => 'Radius',
                'value' => '15 Felder um Ziel-Dörfer',
            ],
            'heuristic' => [
                'label' => 'Heuristik',
                'value' => 'Stabile Bevölkerung + keine sichtbare Entwicklung',
            ],
        ],
        'results' => [
            'title' => 'Ergebnisbereich',
            'card_title' => 'Die ersten Listen werden hier erscheinen',
            'description' => 'Sobald die map.sql-Pipeline angeschlossen ist, zeigt diese Ansicht gefilterte Dörfer, Bevölkerungsdeltas, Koordinaten, Entfernung und wahrscheinliche Inaktivitätswerte an.',
            'bullet_1' => 'Stabile Bevölkerung über mehrere Snapshots',
            'bullet_2' => 'Kein neu erkanntes Dorf',
            'bullet_3' => 'Keine Allianz oder schwache sichtbare Aktivität',
        ],
    ],
];
