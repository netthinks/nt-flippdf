<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/*
 * TypoScript des Inhaltselements.
 *
 * Bewusst hier statt in ext_typoscript_setup.typoscript: Diese Datei wird seit
 * TYPO3 13 nicht mehr von allein eingelesen. Ueber addTypoScriptSetup steht das
 * Inhaltselement ohne weiteres Zutun in jeder Site zur Verfuegung, auch in
 * solchen, die noch keine Site Sets verwenden.
 *
 * Kein __DIR__: ext_localconf.php wird zu einer einzigen Datei im
 * Cache-Verzeichnis zusammengefasst, dort zeigt __DIR__ ins Leere.
 */
$verzeichnis = ExtensionManagementUtility::extPath('nt_flippdf') . 'Configuration/TypoScript/';

ExtensionManagementUtility::addTypoScriptConstants(
    (string)file_get_contents($verzeichnis . 'constants.typoscript')
);
ExtensionManagementUtility::addTypoScriptSetup(
    (string)file_get_contents($verzeichnis . 'setup.typoscript')
);

