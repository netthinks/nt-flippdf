<?php

use Netthinks\NtFlippdf\Controller\ManagerController;

/**
 * Backend-Modul "Blätterbare Ausgaben".
 *
 * Bewusst ohne Extbase: Das Modul kommt mit ein paar Formularen aus, ein
 * schlichter Controller ist dafür weniger Aufwand und weniger Ballast.
 */
return [
    'file_ntflippdf' => [
        'parent' => 'file',
        'position' => ['after' => 'file_FilelistList'],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'nt-flippdf-module',
        'labels' => 'LLL:EXT:nt_flippdf/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => ManagerController::class . '::handleRequest',
            ],
        ],
    ],
];
