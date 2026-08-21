<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/*
 * Inhaltselement "Blätterbare Ausgabe".
 *
 * Bewusst ohne eigene Spalte in tt_content: Die Einstellungen liegen im
 * vorhandenen FlexForm-Feld. Die Tabelle stösst in grossen Installationen an
 * das Zeilenlimit von InnoDB, jede zusaetzliche Spalte kostet dort Platz.
 */
$GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['ntflippdf_viewer'] = 'nt-flippdf-module';

ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'CType',
    [
        'label' => 'Blätterbare Ausgabe',
        'description' => 'Bindet eine gebaute Ausgabe ein - eingebettet oder als Schaltfläche.',
        'value' => 'ntflippdf_viewer',
        'icon' => 'nt-flippdf-module',
        'group' => 'default',
    ]
);

$GLOBALS['TCA']['tt_content']['types']['ntflippdf_viewer'] = [
    // Eigene Vorschau im Seitenmodul: zeigt Titelbild und Angaben der Ausgabe,
    // statt nur den Namen des Elements.
    'previewRenderer' => \Netthinks\NtFlippdf\Backend\ViewerPreview::class,
    'showitem' => '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            header; Überschrift,
            pi_flexform,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
            --palette--;;language,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,
    ',
    'columnsOverrides' => [
        'bodytext' => [
            'config' => ['enableRichtext' => true],
        ],
    ],
];

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:nt_flippdf/Configuration/FlexForms/Viewer.xml',
    'ntflippdf_viewer'
);
