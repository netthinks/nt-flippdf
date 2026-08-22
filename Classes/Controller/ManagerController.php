<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Controller;

use Netthinks\NtFlippdf\Service\FlipbookBuilder;
use Netthinks\NtFlippdf\Service\PdfToolkit;
use Netthinks\NtFlippdf\Service\PdfToolkitException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use Netthinks\NtFlippdf\Event\ModuleColumnsEvent;
use Netthinks\NtFlippdf\Event\ModuleFormEvent;
use Netthinks\NtFlippdf\Event\ModuleSaveEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend-Modul: Ausgaben bauen, ansehen, Inhaltsverzeichnis pflegen, löschen.
 */
#[AsController]
class ManagerController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly FlipbookBuilder $builder,
        private readonly PdfToolkit $toolkit,
        private readonly UriBuilder $uriBuilder,
        private readonly ResourceFactory $resourceFactory,
        private readonly ConnectionPool $connectionPool,
        private readonly FlashMessageService $flashMessageService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /** Aufruf der Fortschrittsanzeige: keine Seite zurueckgeben, nur eine Quittung. */
    private bool $stillerAufruf = false;

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $parsed = (array)($request->getParsedBody() ?? []);
        $query = $request->getQueryParams();
        $aktion = (string)($parsed['aktion'] ?? $query['aktion'] ?? 'uebersicht');

        /*
         * Baut die Fortschrittsanzeige im Hintergrund, meldet sich der Aufruf
         * mit diesem Kopf. Dann bleibt es bei einer knappen Antwort: Wuerde
         * hier die Uebersicht gerendert, waere die Erfolgsmeldung darin schon
         * verbraucht - und der Neuaufbau der Seite danach zeigte keine mehr.
         */
        $this->stillerAufruf = $request->getHeaderLine('X-Flippdf-Still') === '1';

        return match ($aktion) {
            'bauen' => $this->bauen($parsed),
            'erneuern' => $this->erneuern((string)($query['kennung'] ?? '')),
            'alleErneuern' => $this->alleErneuern(),
            'neubauen' => $this->neubauen((string)($query['kennung'] ?? '')),
            'loeschen' => $this->loeschen((string)($query['kennung'] ?? '')),
            'bearbeiten' => $this->bearbeiten($request, (string)($query['kennung'] ?? '')),
            'speichern' => $this->speichern($parsed),
            'inhalt' => $this->inhaltBearbeiten($request, (string)($query['kennung'] ?? '')),
            'inhaltSpeichern' => $this->inhaltSpeichern($parsed),
            default => $this->uebersicht($request),
        };
    }

    private function uebersicht(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->text('module.heading'));
        $ausgaben = $this->ausgabenMitAngaben();
        $spalten = $this->eventDispatcher
            ->dispatch(new ModuleColumnsEvent(array_column($ausgaben, 'kennung')))
            ->getSpalten();

        $view->assignMultiple([
            'ausgaben' => $ausgaben,
            'spalten' => $spalten,
            // Wie viele Ausgaben noch mit einem aelteren Betrachter laufen
            'nachzuziehen' => count(array_filter($ausgaben, static fn(array $a): bool => $a['betrachterAlt'])),
            'zusatzfelder' => $this->eventDispatcher
                ->dispatch(new ModuleFormEvent('bauen'))
                ->getAbschnitte(),
            'pdfs' => $this->verfuegbarePdfs(),
            'umgebung' => $this->toolkit->checkEnvironment(),
            'ablage' => $this->lesbarerPfad($this->builder->resolveBasePath()),
            'adresse' => $this->builder->resolveBaseUrl(),
            'modulUri' => (string)$this->uriBuilder->buildUriFromRoute('file_ntflippdf'),
        ]);

        return $view->renderResponse('Backend/Uebersicht');
    }

    /**
     * @param array<string, mixed> $daten
     */
    private function bauen(array $daten): ResponseInterface
    {
        $dateiUid = (int)($daten['datei'] ?? 0);
        $kennung = trim((string)($daten['kennung'] ?? ''));
        $titel = trim((string)($daten['titel'] ?? ''));
        $sprache = (string)($daten['sprache'] ?? 'de');
        $downloadBauen = !empty($daten['download']);

        if ($dateiUid <= 0 || $kennung === '') {
            $this->melden($this->text('msg.build.missing'), ContextualFeedbackSeverity::ERROR);

            return $this->zurueck();
        }

        try {
            $datei = $this->resourceFactory->getFileObject($dateiUid);
            $pfad = $datei->getForLocalProcessing(false);
            $this->fortschrittMitschreiben($kennung);
            $angaben = $this->eventDispatcher->dispatch(new ModuleSaveEvent('bauen', $kennung, $daten, [
                'slug' => $kennung,
                'title' => $titel !== '' ? $titel : $datei->getName(),
                'language' => $sprache,
                'buildDownload' => $downloadBauen,
            ]))->getAngaben();
            $ergebnis = $this->builder->build($pfad, $angaben);
            $meldung = $this->text('msg.build.ok', [
                $ergebnis['slug'] ?? $kennung,
                $ergebnis['pages'],
                number_format($ergebnis['bytes'] / 1048576, 1, ',', '.'),
            ]);
            if (($ergebnis['preview'] ?? null) !== null) {
                $meldung .= $this->text('msg.build.preview', [
                    $ergebnis['preview']['slug'],
                    $ergebnis['preview']['pages'],
                ]);
            }
            $this->melden($meldung, ContextualFeedbackSeverity::OK);
        } catch (PdfToolkitException $e) {
            $this->melden($e->getMessage(), ContextualFeedbackSeverity::ERROR);
        } catch (\Throwable $e) {
            $this->melden($this->text('msg.build.failed', [$e->getMessage()]), ContextualFeedbackSeverity::ERROR);
        } finally {
            $this->fortschrittAufraeumen($kennung);
        }

        return $this->zurueck();
    }

    private function erneuern(string $kennung): ResponseInterface
    {
        if ($kennung !== '' && $this->builder->refreshViewer($kennung)) {
            $this->melden($this->text('msg.refresh.ok', [$kennung]), ContextualFeedbackSeverity::OK);
        } else {
            $this->melden($this->text('msg.unknown', [$kennung]), ContextualFeedbackSeverity::ERROR);
        }

        return $this->zurueck();
    }

    /**
     * Erneuert den Betrachter aller Ausgaben auf einmal.
     *
     * Der Weg nach einem Update der Extension: Die Seitenbilder bleiben, nur
     * Betrachter und Angaben werden neu geschrieben - das dauert Sekunden.
     */
    private function alleErneuern(): ResponseInterface
    {
        $erneuert = 0;
        foreach ($this->builder->listIssues() as $kennung) {
            if ($this->builder->refreshViewer($kennung)) {
                $erneuert++;
            }
        }
        $this->melden(
            $this->text('msg.refreshAll.ok', [$erneuert]),
            $erneuert > 0 ? ContextualFeedbackSeverity::OK : ContextualFeedbackSeverity::INFO
        );

        return $this->zurueck();
    }

    /**
     * Baut eine vorhandene Ausgabe aus derselben Quelle noch einmal - mit
     * allen Angaben, die beim letzten Mal galten.
     */
    private function neubauen(string $kennung): ResponseInterface
    {
        $book = $this->builder->readBook($kennung);
        $quelle = $this->builder->resolveSource((string)($book['source'] ?? ''));
        if ($book === null || $quelle === '') {
            $this->melden($this->text('msg.rebuild.nosource', [$kennung]), ContextualFeedbackSeverity::ERROR);

            return $this->zurueck();
        }

        try {
            $this->fortschrittMitschreiben($kennung);
            $ergebnis = $this->builder->build($quelle, [
                'slug' => $kennung,
                'title' => (string)($book['title'] ?? $kennung),
                'language' => (string)($book['language'] ?? 'de'),
                'toc' => (array)($book['toc'] ?? []),
                'theme' => (array)($book['theme'] ?? []),
                'flip' => (array)($book['flip'] ?? []),
                'ui' => (array)($book['ui'] ?? []),
                'watermark' => (array)($book['watermark'] ?? []),
                'siblings' => (array)($book['siblings'] ?? []),
                'buildDownload' => (string)($book['downloadUrl'] ?? '') !== '',
                // Die Vorschau gehoert zur Ausgabe: Ohne diese beiden Zeilen
                // liefe sie nach der ersten Korrektur der Vollversion hinterher.
                'previewPages' => (int)($book['preview']['pages'] ?? 0),
                'previewSlug' => (string)($book['preview']['slug'] ?? ''),
                'splitSpreads' => !empty($book['splitSpreads']),
            ]);
            $meldung = $this->text('msg.rebuild.ok', [$kennung, $ergebnis['pages']]);
            if (($ergebnis['preview'] ?? null) !== null) {
                $meldung .= $this->text('msg.rebuild.preview', [$ergebnis['preview']['slug']]);
            }
            $this->melden($meldung, ContextualFeedbackSeverity::OK);
        } catch (\Throwable $e) {
            $this->melden($e->getMessage(), ContextualFeedbackSeverity::ERROR);
        } finally {
            $this->fortschrittAufraeumen($kennung);
        }

        return $this->zurueck();
    }

    private function loeschen(string $kennung): ResponseInterface
    {
        if ($kennung !== '' && $this->builder->delete($kennung)) {
            $this->melden($this->text('msg.delete.ok', [$kennung]), ContextualFeedbackSeverity::OK);
        } else {
            $this->melden($this->text('msg.delete.failed', [$kennung]), ContextualFeedbackSeverity::ERROR);
        }

        return $this->zurueck();
    }

    private function bearbeiten(ServerRequestInterface $request, string $kennung): ResponseInterface
    {
        $book = $this->builder->readBook($kennung);
        if ($book === null) {
            $this->melden($this->text('msg.unknown', [$kennung]), ContextualFeedbackSeverity::ERROR);

            return $this->zurueck();
        }

        $vorschau = 0;
        $vorschauDatei = rtrim($this->builder->resolveBasePath(), '/') . '/' . $kennung . '/teaser.json';
        if (is_file($vorschauDatei)) {
            $vorschau = (int)(json_decode((string)file_get_contents($vorschauDatei), true)['pageCount'] ?? 0);
        }

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->text('edit.heading', [(string)($book['title'] ?? $kennung)]));
        $view->assignMultiple([
            'kennung' => $kennung,
            'buch' => $book,
            'quelle' => $this->lesbarerPfad(
                $this->builder->resolveSource((string)($book['source'] ?? '')) ?: (string)($book['source'] ?? '')
            ),
            'theme' => (array)($book['theme'] ?? []),
            'bedienung' => (array)($book['ui'] ?? []),
            'vorschau' => $vorschau,
            'zusatzfelder' => $this->eventDispatcher
                ->dispatch(new ModuleFormEvent('bearbeiten', $kennung, $book))
                ->getAbschnitte(),
            'modulUri' => (string)$this->uriBuilder->buildUriFromRoute('file_ntflippdf'),
        ]);

        return $view->renderResponse('Backend/Bearbeiten');
    }

    /**
     * @param array<string, mixed> $daten
     */
    private function speichern(array $daten): ResponseInterface
    {
        $kennung = trim((string)($daten['kennung'] ?? ''));
        $angaben = $this->eventDispatcher->dispatch(new ModuleSaveEvent('speichern', $kennung, $daten, [
            'title' => (string)($daten['titel'] ?? ''),
            'language' => (string)($daten['sprache'] ?? 'de'),
            'theme' => [
                'bar' => (string)($daten['farbeLeiste'] ?? ''),
                'accent' => (string)($daten['farbeAkzent'] ?? ''),
            ],
            'download' => !empty($daten['download']),
            'teaserPages' => (int)($daten['vorschau'] ?? 0),
            'ui' => $this->bedienung((array)($daten['ui'] ?? [])),
        ]))->getAngaben();
        $erfolg = $this->builder->updateSettings($kennung, $angaben);

        if ($erfolg) {
            $this->melden($this->text('msg.save.ok', [$kennung]), ContextualFeedbackSeverity::OK);
        } else {
            $this->melden($this->text('msg.save.failed'), ContextualFeedbackSeverity::ERROR);
        }

        return $this->zurueck();
    }

    private function inhaltBearbeiten(ServerRequestInterface $request, string $kennung): ResponseInterface
    {
        $book = $this->builder->readBook($kennung);
        if ($book === null) {
            $this->melden($this->text('msg.unknown', [$kennung]), ContextualFeedbackSeverity::ERROR);

            return $this->zurueck();
        }

        $zeilen = [];
        foreach ((array)($book['toc'] ?? []) as $eintrag) {
            $zeilen[] = ($eintrag['page'] ?? '') . ' | ' . ($eintrag['title'] ?? '');
        }

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->text('toc.heading', [(string)($book['title'] ?? $kennung)]));
        $view->assignMultiple([
            'kennung' => $kennung,
            'buch' => $book,
            'verzeichnis' => implode("\n", $zeilen),
            'modulUri' => (string)$this->uriBuilder->buildUriFromRoute('file_ntflippdf'),
        ]);

        return $view->renderResponse('Backend/Inhalt');
    }

    /**
     * @param array<string, mixed> $daten
     */
    private function inhaltSpeichern(array $daten): ResponseInterface
    {
        $kennung = trim((string)($daten['kennung'] ?? ''));
        $eingabe = (string)($daten['verzeichnis'] ?? '');

        $toc = [];
        foreach (preg_split('/\R/', $eingabe) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '') {
                continue;
            }
            // Erlaubt sind "12 | Titel" und "12 Titel"
            if (!preg_match('/^(\d+)\s*(?:\||\s)\s*(.+)$/u', $zeile, $treffer)) {
                continue;
            }
            $toc[] = ['page' => (int)$treffer[1], 'title' => trim($treffer[2])];
        }

        if ($this->builder->updateToc($kennung, $toc)) {
            $this->melden($this->text('msg.toc.ok', [$kennung, count($toc)]), ContextualFeedbackSeverity::OK);
        } else {
            $this->melden($this->text('msg.save.failed'), ContextualFeedbackSeverity::ERROR);
        }

        return $this->zurueck();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ausgabenMitAngaben(): array
    {
        $ausgaben = [];
        $stand = $this->builder->viewerVersion();
        $zeichen = $this->builder->viewerHash();
        foreach ($this->builder->listIssues() as $kennung) {
            $book = $this->builder->readBook($kennung);
            if ($book === null) {
                continue;
            }
            $pfad = rtrim($this->builder->resolveBasePath(), '/') . '/' . $kennung;
            $ausgaben[] = [
                'kennung' => $kennung,
                'quelle' => $this->lesbarerPfad(
                    $this->builder->resolveSource((string)($book['source'] ?? ''))
                        ?: (string)($book['source'] ?? '')
                ),
                'quelleDa' => $this->builder->resolveSource((string)($book['source'] ?? '')) !== '',
                'veraltet' => $this->builder->resolveSource((string)($book['source'] ?? '')) !== ''
                    && (int)@filemtime($this->builder->resolveSource((string)($book['source'] ?? '')))
                        > (int)($book['sourceTime'] ?? 0),
                'titel' => (string)($book['title'] ?? $kennung),
                // Zuordnung der beiden Ausgaben einer Veroeffentlichung
                'vorschauVon' => (string)($book['gehoertZu'] ?? ''),
                'hatVorschau' => (string)($book['preview']['slug'] ?? ''),
                'sprache' => (string)($book['language'] ?? 'de'),
                'seiten' => (int)($book['pageCount'] ?? 0),
                'inhalt' => count((array)($book['toc'] ?? [])),
                'download' => (string)($book['downloadUrl'] ?? ''),
                'gebaut' => is_file($pfad . '/book.json') ? filemtime($pfad . '/book.json') : 0,
                // Der Betrachter liegt als Kopie in der Ausgabe: Nach einem
                // Update der Extension ist er dort erst nach dem Erneuern.
                'betrachterAlt' => ($stand !== '' && (string)($book['viewer'] ?? '') !== $stand)
                    || (string)($book['viewerHash'] ?? '') !== $zeichen,
                'betrachterStand' => (string)($book['viewer'] ?? ''),
                'groesse' => $this->verzeichnisGroesse($pfad),
                // Fertig gerundet aus dem Controller: In der Vorlage braeuchte
                // es dafuer eine Rechnung im ViewHelper-Aufruf, und die ist in
                // Fluid nicht vorgesehen.
                'groesseMb' => number_format($this->verzeichnisGroesse($pfad) / 1048576, 1, ',', '.'),
                'url' => $this->builder->resolveBaseUrl() . $kennung . '/',
            ];
        }

        return $ausgaben;
    }



    /**
     * PDF-Dateien aus dem Dateibereich, neueste zuerst.
     *
     * @return list<array{uid: int, name: string, groesse: string}>
     */
    private function verfuegbarePdfs(): array
    {
        // Ohne Begrenzung stuenden hier alle PDFs der Installation - auch
        // Bewerbungen und anderes, was in einem Baukasten fuer Ausgaben nichts
        // zu suchen hat.
        $ordner = array_filter(array_map(
            'trim',
            explode(',', (string)($this->einstellungen()['pdfFolders'] ?? ''))
        ));

        $bedingung = '';
        $werte = [];
        if ($ordner !== []) {
            $teile = [];
            foreach ($ordner as $eintrag) {
                // "1:/pfad/" und "/pfad/" sind beide erlaubt
                $pfad = preg_replace('/^\d+:/', '', $eintrag) ?? $eintrag;
                $teile[] = 'identifier LIKE ?';
                $werte[] = rtrim($pfad, '/') . '/%';
            }
            $bedingung = ' AND (' . implode(' OR ', $teile) . ')';
        }

        $rows = $this->connectionPool->getConnectionForTable('sys_file')->fetchAllAssociative(
            "SELECT uid, identifier, size FROM sys_file
             WHERE mime_type = 'application/pdf' AND missing = 0" . $bedingung . "
             ORDER BY tstamp DESC
             LIMIT 200",
            $werte
        );

        $pdfs = [];
        foreach ($rows as $row) {
            $pdfs[] = [
                'uid' => (int)$row['uid'],
                'name' => (string)$row['identifier'],
                'groesse' => number_format(((int)$row['size']) / 1048576, 1, ',', '.') . ' MB',
            ];
        }

        return $pdfs;
    }

    /**
     * Macht aus einem vollstaendigen Pfad eine lesbare Angabe.
     *
     * Im Backend interessiert niemanden, wo auf dem Server etwas liegt -
     * gebraucht wird der Ort, an dem man die Datei wiederfindet. Was im
     * Dateibereich liegt, erscheint deshalb als "fileadmin/…", der Rest
     * relativ zum Projektverzeichnis.
     */
    private function lesbarerPfad(string $pfad): string
    {
        if ($pfad === '') {
            return '';
        }
        // Erst der Dateibereich: Dort suchen Redakteure ihre Dateien, und die
        // Angabe deckt sich mit dem, was in den Auswahlfeldern steht.
        $fileadmin = rtrim(Environment::getPublicPath(), '/') . '/fileadmin/';
        if (str_starts_with($pfad, $fileadmin)) {
            return 'fileadmin/' . substr($pfad, strlen($fileadmin));
        }
        $projekt = rtrim(Environment::getProjectPath(), '/') . '/';
        if (str_starts_with($pfad, $projekt)) {
            return substr($pfad, strlen($projekt));
        }

        return $pfad;
    }

    /**
     * Schreibt den Fortschritt in eine kleine Datei neben den Ausgaben.
     *
     * Sie liegt im oeffentlichen Ablageort und wird vom Webserver direkt
     * ausgeliefert - der Browser kann sie waehrend des Baus abfragen, ohne dass
     * dafuer ein zweiter PHP-Aufruf noetig waere. Das ist wichtig: Ein zweiter
     * Aufruf muesste auf die Sitzung warten, die der laufende Bau haelt, und
     * bekaeme seine Antwort erst nach dessen Ende.
     */
    private function fortschrittMitschreiben(string $kennung): void
    {
        // Bauen dauert; ohne das steigt PHP je nach Einstellung mittendrin aus.
        @set_time_limit(0);

        $datei = $this->fortschrittsDatei($kennung);
        $beginn = time();
        @file_put_contents($datei, json_encode([
            'prozent' => 0,
            'text' => 'Der Bau beginnt …',
            'beginn' => $beginn,
        ], JSON_UNESCAPED_UNICODE));

        $this->builder->setProgressHandler(
            static function (int $schritt, int $gesamt, string $text) use ($datei, $beginn): void {
                @file_put_contents($datei, json_encode([
                    'prozent' => (int)round($schritt / max(1, $gesamt) * 100),
                    'text' => $text,
                    'beginn' => $beginn,
                ], JSON_UNESCAPED_UNICODE));
            }
        );
    }

    private function fortschrittAufraeumen(string $kennung): void
    {
        $this->builder->setProgressHandler(null);
        @unlink($this->fortschrittsDatei($kennung));
    }

    private function fortschrittsDatei(string $kennung): string
    {
        // Bewusst ohne fuehrenden Punkt: Apache verweigert Dateien, deren Name
        // mit einem Punkt beginnt - die Anzeige bekaeme nur ein "Forbidden".
        return rtrim($this->builder->resolveBasePath(), '/') . '/fortschritt-'
            . preg_replace('/[^a-zA-Z0-9_-]/', '', $kennung) . '.json';
    }


    /**
     * Baut aus den Formularwerten die vollstaendige Bedienliste.
     *
     * Ein nicht angehaktes Kaestchen schickt gar nichts mit - ohne diese
     * Ergaenzung liesse sich nie etwas abschalten.
     *
     * @param array<string, mixed> $daten
     * @return array<string, bool|string>
     */
    private function bedienung(array $daten): array
    {
        $schalter = [
            'search', 'toc', 'thumbs', 'download', 'zoom', 'print', 'fullscreen',
            'extern', 'sound', 'soundOn', 'logo', 'languages', 'nav', 'slider', 'marks',
            'indicator', 'hint',
        ];
        $werte = ['buttonStyle' => (string)($daten['buttonStyle'] ?? 'text')];
        foreach ($schalter as $name) {
            $werte[$name] = !empty($daten[$name]);
        }

        return $werte;
    }

    /**
     * @return array<string, mixed>
     */
    private function einstellungen(): array
    {
        try {
            return (array)GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
            )->get('nt_flippdf');
        } catch (\Throwable) {
            return [];
        }
    }

    private function verzeichnisGroesse(string $verzeichnis): int
    {
        if (!is_dir($verzeichnis)) {
            return 0;
        }
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($verzeichnis, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $datei) {
            $bytes += $datei->getSize();
        }

        return $bytes;
    }

    /**
     * Uebersetzter Text aus der Sprachdatei des Moduls.
     *
     * @param list<string|int> $werte Platzhalter der Zeichenkette
     */
    private function text(string $schluessel, array $werte = []): string
    {
        $text = LocalizationUtility::translate(
            'LLL:EXT:nt_flippdf/Resources/Private/Language/locallang.xlf:' . $schluessel,
            null,
            $werte
        );

        return $text ?? $schluessel;
    }

    private function melden(string $text, ContextualFeedbackSeverity $schwere): void
    {
        $meldung = GeneralUtility::makeInstance(FlashMessage::class, $text, '', $schwere, true);
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage($meldung);
    }

    private function zurueck(): ResponseInterface
    {
        if ($this->stillerAufruf) {
            // Die Meldung bleibt in der Warteschlange und erscheint, sobald der
            // Browser die Uebersicht neu laedt.
            return new JsonResponse(['fertig' => true]);
        }

        return new RedirectResponse((string)$this->uriBuilder->buildUriFromRoute('file_ntflippdf'));
    }
}
