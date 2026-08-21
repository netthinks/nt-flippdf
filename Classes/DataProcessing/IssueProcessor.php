<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\DataProcessing;

use Netthinks\NtFlippdf\Service\FlipbookBuilder;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Reicht die Angaben der gewaehlten Ausgabe an die Vorlage weiter.
 *
 * Wichtig ist vor allem der Baustand: Er haengt an der Adresse des Rahmens,
 * damit ein Browser nach einem Neubau nicht die alte Startseite aus seinem
 * Speicher zeigt. Ohne das half nur ein Neuladen unter Umgehung des
 * Zwischenspeichers - und darauf kann man Besucher schlecht hinweisen.
 */
class IssueProcessor implements DataProcessorInterface
{
    public function __construct(private readonly FlipbookBuilder $builder) {}

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $als = (string)($processorConfiguration['as'] ?? 'ausgabe');
        $kennung = trim((string)($processedData['einstellungen']['settings']['kennung'] ?? ''));

        $angaben = ['kennung' => $kennung, 'stand' => '', 'seiten' => 0, 'titel' => '', 'hintergrund' => ''];
        if ($kennung !== '') {
            $book = $this->builder->readBook($kennung);
            if ($book !== null) {
                $angaben['stand'] = (string)($book['built'] ?? '');
                $angaben['seiten'] = (int)($book['pageCount'] ?? 0);
                $angaben['titel'] = (string)($book['title'] ?? '');
            }
        }

        /*
         * Hintergrund je Element. Die Vorlage kann den Namen des Motivs nicht
         * in eine Adresse aufloesen - hier ist bekannt, wo die mitgelieferten
         * Bilder liegen. Ein Bindestrich bedeutet "an dieser Stelle keiner".
         */
        $motiv = trim((string)($processedData['einstellungen']['settings']['hintergrund'] ?? ''));
        if ($motiv === '-') {
            $angaben['hintergrund'] = '-';
        } elseif ($motiv !== '') {
            $datei = $this->builder->backgroundFile($motiv);
            if ($datei !== null) {
                $angaben['hintergrund'] = PathUtility::getAbsoluteWebPath($datei);
            }
        }
        $processedData[$als] = $angaben;

        return $processedData;
    }
}
