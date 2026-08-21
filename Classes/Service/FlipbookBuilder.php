<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Service;

use Netthinks\NtFlippdf\Event\AfterBuildEvent;
use Netthinks\NtFlippdf\Event\AfterPagesRenderedEvent;
use Netthinks\NtFlippdf\Event\BeforeBookWrittenEvent;
use Netthinks\NtFlippdf\Event\BeforeBuildEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Erzeugt aus einem PDF eine blaetterbare Fassung als eigenstaendiges
 * Verzeichnis - ohne TYPO3 zur Laufzeit, damit eine einmal gebaute Ausgabe
 * auch nach Aenderungen an der Website unveraendert weiterlaeuft.
 *
 * Aufbau je Ausgabe:
 *   <kennung>/index.html      Betrachter
 *   <kennung>/assets/         Betrachter-Dateien (Kopie aus der Extension)
 *   <kennung>/pages/1.jpg ... Seitenbilder
 *   <kennung>/thumbs/1.jpg ... Vorschaubilder fuer die Seitenuebersicht
 *   <kennung>/<name>.pdf      verkleinerte Fassung zum Herunterladen
 *   <kennung>/book.json       Angaben zur Ausgabe samt Beschriftungen
 *   <kennung>/search.json     Volltext je Seite fuer die Suche
 */
class FlipbookBuilder
{
    /**
     * Beschriftungen des Betrachters. Sie wandern in book.json, damit eine
     * Ausgabe in ihrer eigenen Sprache erscheint, ohne dass am Betrachter
     * etwas geaendert werden muss.
     *
     * @var array<string, array<string, string>>
     */
    private const LABELS = [
        'de' => [
            'pageOf' => 'Seite %1$s von %2$s',
            'search' => 'In dieser Ausgabe suchen',
            'thumbs' => 'Seitenübersicht',
            'toc' => 'Inhalt',
            'tocEmpty' => 'Für diese Ausgabe gibt es kein Inhaltsverzeichnis.',
            'download' => 'PDF herunterladen',
            'fullscreen' => 'Vollbild',
            'prev' => 'Vorherige Seite',
            'next' => 'Nächste Seite',
            'choosePage' => 'Seite wählen',
            'loading' => 'Ausgabe wird geladen …',
            'failed' => 'Die Ausgabe konnte nicht geladen werden.',
            'hint' => 'Blättern mit den Pfeiltasten oder durch Ziehen der Seitenecke',
            'noResults' => 'Kein Treffer',
            'zoom' => 'Vergrößern',
            'zoomIn' => 'Größer',
            'zoomOut' => 'Kleiner',
            'close' => 'Schließen',
            'print' => 'Drucken',
            'extern' => 'In eigenem Fenster öffnen',
            'sound' => 'Blättergeräusch',
            'soundOn' => 'Blättergeräusch einschalten',
            'soundOff' => 'Blättergeräusch ausschalten',
        ],
        'en' => [
            'pageOf' => 'Page %1$s of %2$s',
            'search' => 'Search this issue',
            'thumbs' => 'Page overview',
            'toc' => 'Contents',
            'tocEmpty' => 'There is no table of contents for this issue.',
            'download' => 'Download PDF',
            'fullscreen' => 'Full screen',
            'prev' => 'Previous page',
            'next' => 'Next page',
            'choosePage' => 'Choose page',
            'loading' => 'Loading …',
            'failed' => 'This issue could not be loaded.',
            'hint' => 'Turn pages with the arrow keys or by dragging a corner',
            'noResults' => 'No match',
            'zoom' => 'Zoom',
            'zoomIn' => 'Zoom in',
            'zoomOut' => 'Zoom out',
            'close' => 'Close',
            'print' => 'Print',
            'extern' => 'Open in its own window',
            'sound' => 'Page-turn sound',
            'soundOn' => 'Turn page sound on',
            'soundOff' => 'Turn page sound off',
        ],
    ];

    public function __construct(
        private readonly PdfToolkit $toolkit,
        private readonly TocBuilder $tocBuilder,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Vorgaben fuers Umblaettern. Alles laesst sich je Ausgabe ueberschreiben;
     * was das Betriebssystem an Bewegungsarmut vorgibt, sticht ohnehin.
     *
     * @var array<string, int|float|bool>
     */
    private const FLIP_DEFAULTS = [
        'duration' => 700,
        'shadows' => true,
        'shadowOpacity' => 0.5,
        'cover' => true,
        'portrait' => true,
        'drag' => true,
        'mobileScroll' => true,
    ];

    /**
     * Was der Betrachter anbietet. Jede Schaltflaeche laesst sich abschalten -
     * als Vorgabe in der Extension-Konfiguration, je Ausgabe im Backend-Modul
     * und je Inhaltselement ueber die Adresse.
     *
     * buttonStyle: 'text' = ausgeschriebene Beschriftung (wie bisher),
     * 'icon' = nur Symbol, Beschriftung bleibt als Tooltip und fuer
     * Vorlesegeraete, 'both' = Symbol und Beschriftung nebeneinander.
     *
     * @var array<string, bool|string>
     */
    private const UI_DEFAULTS = [
        'buttonStyle' => 'text',
        'search' => true,
        'toc' => true,
        'thumbs' => true,
        'download' => true,
        'zoom' => true,
        'print' => true,
        'fullscreen' => true,
        'extern' => true,
        'sound' => true,
        'soundOn' => true,
        'logo' => true,
        'languages' => true,
        'nav' => true,
        'slider' => true,
        'marks' => true,
        'indicator' => true,
        'hint' => true,
    ];

    /** @var list<string> */
    private const BUTTON_STYLES = ['text', 'icon', 'both'];

    /**
     * Wer den Fortschritt sehen will, hinterlegt hier eine Funktion. Sie
     * bekommt den erreichten Schritt, die Gesamtzahl und einen Satz dazu.
     *
     * @var (\Closure(int, int, string): void)|null
     */
    private ?\Closure $fortschritt = null;

    public function setProgressHandler(?callable $handler): void
    {
        $this->fortschritt = $handler === null ? null : \Closure::fromCallable($handler);
    }

    private function melde(int $schritt, int $gesamt, string $text): void
    {
        if ($this->fortschritt !== null) {
            ($this->fortschritt)($schritt, max(1, $gesamt), $text);
        }
    }



    /**
     * @param array{title?: string, slug: string, language?: string, downloadUrl?: string, buildDownload?: bool, toc?: list<array{title: string, page: int}>, suggestToc?: bool, theme?: array<string, string>} $issue
     * @return array{url: string, path: string, pages: int, bytes: int, downloadUrl: string}
     * @throws PdfToolkitException
     */
    public function build(string $pdfFile, array $issue): array
    {
        if (!is_file($pdfFile)) {
            throw new PdfToolkitException('Die Datei "' . $pdfFile . '" gibt es nicht.');
        }
        // Vollstaendiger Pfad: Ein relativer haengt an dem Verzeichnis, aus dem
        // gerade gearbeitet wird - auf der Kommandozeile ein anderes als im
        // Backend. Die Ausgabe waere dort spaeter nicht mehr neu zu bauen.
        $pdfFile = realpath($pdfFile) ?: $pdfFile;

        /* Vor dem Lauf darf sich alles noch aendern - auch die Kennung. Das
           Zusatzpaket haengt hier zum Beispiel den Zufallsschluessel an. */
        $ereignis = $this->eventDispatcher->dispatch(new BeforeBuildEvent($pdfFile, $issue));
        $issue = $ereignis->getAngaben();

        $slug = $this->sanitizeSlug($issue['slug'] ?? '');
        if ($slug === '') {
            throw new PdfToolkitException('Die Ausgabe braucht eine Kennung, unter der sie abgelegt wird.');
        }

        $language = $this->language($issue['language'] ?? 'de');
        /*
         * Gebaut wird in ein Verzeichnis daneben, getauscht wird erst am Ende.
         * Wuerde die vorhandene Ausgabe zuerst geleert, waere sie waehrend des
         * ganzen Laufs - je nach Umfang mehrere Minuten - nicht erreichbar: Wer
         * die Seite in dieser Zeit aufruft, bekaeme vom Webserver ein
         * "Forbidden" zu sehen, weil die Startseite fehlt.
         */
        $endziel = rtrim($this->resolveBasePath(), '/') . '/' . $slug;
        $target = $endziel . '.bau';
        $this->clearDirectory($target);
        GeneralUtility::mkdir_deep($target);

        // Die Schrittzahl steht erst nach dem Rendern fest; bis dahin wird mit
        // der Seitenzahl aus dem PDF gerechnet.
        $seitenZahl = max(1, $this->toolkit->countPages($pdfFile));
        $gesamt = $seitenZahl * 2 + 6;
        $this->melde(1, $gesamt, 'Seiten werden gerendert …');

        $pageFiles = $this->toolkit->renderAllPages(
            $pdfFile,
            $target . '/pages',
            $this->pageWidth(),
            $this->jpegQuality()
        );

        // Herkunft jeder Buchseite: Aus welcher Seite des PDF stammt sie? Ohne
        // Doppelseiten ist das eins zu eins, danach nicht mehr - Text und
        // Verweise werden aber weiterhin aus dem PDF geholt.
        $herkunft = range(1, count($pageFiles));
        $geteilt = array_fill(0, count($pageFiles), false);

        /* Die Seitenbilder liegen fertig da, abgeleitet ist noch nichts. Wer
           sie veraendern will - Wasserzeichen, Doppelseiten teilen -, tut es
           jetzt. */
        $seitenEreignis = $this->eventDispatcher->dispatch(
            new AfterPagesRenderedEvent($pageFiles, $herkunft, $geteilt, $issue, $pdfFile, $target)
        );
        $pageFiles = $seitenEreignis->getSeitenBilder();
        $herkunft = $seitenEreignis->getHerkunft();
        $geteilt = $seitenEreignis->getGeteilt();

        // Zufallskennung in den Dateinamen: In der Vorschau steht nur ein Teil
        // der Seiten in der Angabendatei. Ohne die Kennung liesse sich der Rest
        // schlicht durch Weiterzaehlen der Adresse aufrufen.
        $kennung = bin2hex(random_bytes(5));
        $pageFiles = $this->renamePages($pageFiles, $kennung);

        $pages = [];
        $texts = [];
        $verweiseLesen = ($issue['extractLinks'] ?? ($this->configuration()['extractLinks'] ?? '1') !== '0')
            && $this->toolkit->supportsLinkExtraction();
        foreach ($pageFiles as $index => $pageFile) {
            $number = $index + 1;
            $this->melde(
                $seitenZahl + 2 + $index,
                $gesamt,
                'Vorschaubild und Text: Seite ' . $number . ' von ' . count($pageFiles)
            );
            $size = @getimagesize($pageFile) ?: [0, 0];
            $thumbName = $number . '-' . $kennung . '.jpg';
            $this->toolkit->createThumbnail($pageFile, $target . '/thumbs/' . $thumbName, $this->thumbWidth());
            $pages[] = [
                'n' => $number,
                'src' => 'pages/' . basename($pageFile),
                'thumb' => 'thumbs/' . $thumbName,
                'w' => (int)$size[0],
                'h' => (int)$size[1],
            ];
            $quellSeite = $herkunft[$index] ?? $number;
            $texts[(string)$number] = $this->toolkit->extractPageText($pdfFile, $quellSeite);
            // Bei geteilten Boegen passen die Fundstellen aus dem PDF nicht mehr
            // zur Seite: Sie beziehen sich auf den ganzen Bogen.
            if ($verweiseLesen && !($geteilt[$index] ?? false)) {
                $verweise = $this->toolkit->extractPageLinks($pdfFile, $quellSeite);
                if ($verweise !== []) {
                    $pages[count($pages) - 1]['links'] = $verweise;
                }
            }
        }

        $this->melde($seitenZahl * 2 + 2, $gesamt, 'Download-Fassung wird erzeugt …');
        $downloadUrl = $this->prepareDownload($pdfFile, $target, $issue);
        $this->melde($seitenZahl * 2 + 3, $gesamt, 'Inhaltsverzeichnis wird gelesen …');
        $toc = $this->resolveToc($pdfFile, count($pages), $issue);

        $book = [
            'title' => (string)($issue['title'] ?? $slug),
            'slug' => $slug,
            'language' => $language,
            'pageCount' => count($pages),
            'downloadUrl' => $downloadUrl,
            'labels' => self::LABELS[$language] ?? self::LABELS['en'],
            'toc' => $toc,
            'theme' => $this->theme((array)($issue['theme'] ?? [])),
            'flip' => $this->flip((array)($issue['flip'] ?? [])),
            'ui' => $this->ui((array)($issue['ui'] ?? [])),
            'zoom' => ($issue['zoom'] ?? ($this->configuration()['zoom'] ?? '1') !== '0') !== false,
            'zoomMax' => max(1.5, (float)($this->configuration()['zoomMax'] ?? 3)),
            // Quelle und ihr Zeitstempel: daran erkennt flippdf:autobuild,
            // ob eine Ausgabe veraltet ist.
            'source' => $pdfFile,
            'sourceTime' => (int)@filemtime($pdfFile),
            'built' => time(),
            'pages' => $pages,
        ];
        /* Alles, was der Betrachter kennt, steht in dieser Datei. Das
           Zusatzpaket ergaenzt hier Logo, Hintergrund, weitere Sprachen und
           den Zaehler. */
        $book = $this->eventDispatcher
            ->dispatch(new BeforeBookWrittenEvent($book, $issue, $target))
            ->getBuch();

        file_put_contents(
            $target . '/book.json',
            json_encode($book, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        file_put_contents(
            $target . '/search.json',
            json_encode($texts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->melde($seitenZahl * 2 + 4, $gesamt, 'Angaben werden geschrieben …');
        $this->writeTeaser($target, $book, $texts, $issue);
        $this->copyAssets($target . '/assets');
        // Ein vorhandenes Logo gehoert zur Ausgabe, nicht zum Lauf - es wuerde
        // sonst bei jedem Neubau verschwinden. Muss vor writeIndex geschehen:
        // Die Startseite bindet es nur ein, wenn die Datei da ist.
        foreach (glob($endziel . '/logo.*') ?: [] as $logo) {
            copy($logo, $target . '/' . basename($logo));
        }
        $this->writeIndex($target, $book);
        $this->protectFromIndexing();

        $this->melde($seitenZahl * 2 + 5, $gesamt, 'Ausgabe wird eingesetzt …');
        $this->tauscheVerzeichnis($target, $endziel);
        $ergebnis = [
            'slug' => $slug,
            'url' => $this->resolveBaseUrl() . $slug . '/',
            'path' => $endziel,
            'pages' => count($pages),
            'bytes' => $this->directorySize($endziel),
            'downloadUrl' => $downloadUrl,
        ];

        /* Die Ausgabe steht. Was jetzt noch daneben entstehen soll - etwa die
           Vorschau des Zusatzpakets -, entsteht hier und kann sich im Ergebnis
           bemerkbar machen. */
        $ergebnis = $this->eventDispatcher
            ->dispatch(new AfterBuildEvent($book, $issue, $texts, $endziel, $ergebnis))
            ->getErgebnis();

        $this->melde($gesamt, $gesamt, 'Fertig.');

        return $ergebnis;
    }

    /**
     * Erneuert nur Betrachter und Startseite einer bestehenden Ausgabe.
     *
     * Weil jede Ausgabe eine eigene Kopie der Betrachter-Dateien besitzt,
     * wirken Aenderungen am Betrachter sonst erst beim vollstaendigen Neubau -
     * der wuerde alle Seiten neu rendern.
     */
    public function refreshViewer(string $slug): bool
    {
        $target = rtrim($this->resolveBasePath(), '/') . '/' . $this->sanitizeSlug($slug);
        $bookFile = $target . '/book.json';
        if (!is_file($bookFile)) {
            return false;
        }
        $book = json_decode((string)file_get_contents($bookFile), true);
        if (!is_array($book)) {
            return false;
        }
        // Aeltere Ausgaben kennen die Beschriftungen noch nicht
        $language = $this->language((string)($book['language'] ?? 'de'));
        $book['language'] = $language;
        $book['labels'] = self::LABELS[$language] ?? self::LABELS['en'];
        $book['toc'] = $this->normalizeToc((array)($book['toc'] ?? []), (int)($book['pageCount'] ?? 0));
        // Aeltere Ausgaben kennen die Bedienliste noch nicht; die Vorgaben aus
        // der Extension-Konfiguration greifen dann beim Erneuern.
        $book['ui'] = $this->ui((array)($book['ui'] ?? []));
        $book['theme'] = $this->theme((array)($book['theme'] ?? []));
        // Neuer Stand: Er haengt an den Adressen der Betrachter-Dateien, sonst
        // liefe der Browser weiter mit der alten Fassung.
        $book['built'] = time();
        file_put_contents(
            $bookFile,
            json_encode($book, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $book = $this->eventDispatcher
            ->dispatch(new BeforeBookWrittenEvent($book, [], $target))
            ->getBuch();
        $this->copyAssets($target . '/assets');
        $this->writeIndex($target, $book);
        $this->protectFromIndexing();

        return true;
    }

    /**
     * @return string[] Kennungen der vorhandenen Ausgaben
     */
    public function listIssues(): array
    {
        $basePath = rtrim($this->resolveBasePath(), '/');
        if (!is_dir($basePath)) {
            return [];
        }
        $slugs = [];
        foreach (scandir($basePath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            // Arbeits- und Altverzeichnisse eines laufenden Baus gehoeren nicht
            // in die Liste.
            if (str_ends_with($entry, '.bau') || str_ends_with($entry, '.alt')) {
                continue;
            }
            if (is_file($basePath . '/' . $entry . '/book.json')) {
                $slugs[] = $entry;
            }
        }
        sort($slugs);

        return $slugs;
    }

    public function resolveBasePath(): string
    {
        $configured = trim((string)($this->configuration()['basePath'] ?? ''));
        if ($configured === '') {
            $configured = 'public/blaetterbar';
        }
        if (PathUtility::isAbsolutePath($configured)) {
            return $configured;
        }

        return rtrim(Environment::getProjectPath(), '/') . '/' . ltrim($configured, '/');
    }

    public function resolveBaseUrl(): string
    {
        $configured = trim((string)($this->configuration()['baseUrl'] ?? ''));

        return rtrim($configured === '' ? '/blaetterbar/' : $configured, '/') . '/';
    }

    /**
     * Legt die Fassung zum Herunterladen ab und liefert die Adresse dafuer.
     *
     * Wurde von aussen eine Adresse mitgegeben, gilt die - dann liegt die
     * Datei schon woanders und soll nicht doppelt herumliegen.
     *
     * @param array{downloadUrl?: string, buildDownload?: bool} $issue
     */
    private function prepareDownload(string $pdfFile, string $target, array $issue): string
    {
        $vorgabe = trim((string)($issue['downloadUrl'] ?? ''));
        if ($vorgabe !== '') {
            return $vorgabe;
        }
        $bauen = $issue['buildDownload'] ?? (bool)($this->configuration()['buildDownloadVersion'] ?? true);
        if (!$bauen) {
            return '';
        }

        $datei = $this->downloadName($pdfFile, (string)($issue['title'] ?? ''));

        try {
            $this->toolkit->createDownloadVersion(
                $pdfFile,
                $target . '/' . $datei,
                (int)($this->configuration()['downloadResolution'] ?? 120)
            );
        } catch (PdfToolkitException) {
            // Wenn die verkleinerte Fassung nicht zustande kommt, ist das
            // Original immer noch besser als gar kein Download.
            copy($pdfFile, $target . '/' . $datei);
        }

        return $datei;
    }

    /**
     * Name der Datei zum Herunterladen.
     *
     * Frueher hiess sie immer download.pdf. Im Ordner des Lesers steht dann
     * eine Datei, der man nicht ansieht, was sie enthaelt - und beim zweiten
     * Whitepaper heisst sie download(1).pdf. Deshalb behaelt sie den Namen der
     * Quelldatei, auf das Noetige beschraenkt: Kleinbuchstaben, Ziffern,
     * Bindestrich. Umlaute werden umschrieben, damit der Name auch in der
     * Adresse und auf fremden Dateisystemen heil bleibt.
     */
    private function downloadName(string $pdfFile, string $titel = ''): string
    {
        /* Der Name der Quelldatei hat Vorrang: So heisst das Dokument in der
           Dateiliste, oft schon mit Haus und Thema davor. Nur wenn daraus
           nichts wird, springt der Titel der Ausgabe ein. */
        $name = (string)pathinfo($pdfFile, PATHINFO_FILENAME);
        if ($this->dateiname($name) === '') {
            $name = $titel;
        }

        $name = $this->dateiname($name);

        return ($name === '' ? 'download' : $name) . '.pdf';
    }

    private function dateiname(string $name): string
    {
        /* Umlaute werden umschrieben, uebrige Akzente auf ihren Grundbuchstaben
           zurueckgefuehrt - sonst wird aus "procédés" ein "proc-d-s". */
        $name = strtr($name, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'œ' => 'oe',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A', 'Æ' => 'Ae',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ø' => 'O', 'Œ' => 'Oe',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ]);
        $name = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $name));

        return trim($name, '-');
    }

    /**
     * Die vorhandene Datei zum Herunterladen in einer gebauten Ausgabe.
     *
     * Der Name haengt an der Quelldatei, deshalb steht er nicht fest. Aeltere
     * Ausgaben tragen noch download.pdf; die hat Vorrang, damit sich an ihnen
     * nichts aendert.
     */
    private function vorhandeneDownloadDatei(string $target): string
    {
        if (is_file($target . '/download.pdf')) {
            return 'download.pdf';
        }
        foreach ((array)glob($target . '/*.pdf') as $pfad) {
            return basename((string)$pfad);
        }

        return '';
    }

    /**
     * Inhaltsverzeichnis: entweder wie uebergeben oder aus dem PDF geschaetzt.
     *
     * @param array{toc?: list<array{title: string, page: int}>, suggestToc?: bool} $issue
     * @return list<array{title: string, page: int}>
     */
    private function resolveToc(string $pdfFile, int $pageCount, array $issue): array
    {
        if (isset($issue['toc']) && is_array($issue['toc'])) {
            return $this->normalizeToc($issue['toc'], $pageCount);
        }
        if (($issue['suggestToc'] ?? true) === false) {
            return [];
        }

        return $this->normalizeToc($this->tocBuilder->suggest($pdfFile, $pageCount), $pageCount);
    }

    /**
     * @param array<int, array{title?: string, page?: int|string}> $toc
     * @return list<array{title: string, page: int}>
     */
    public function normalizeToc(array $toc, int $pageCount): array
    {
        $sauber = [];
        foreach ($toc as $eintrag) {
            $titel = trim((string)($eintrag['title'] ?? ''));
            $seite = (int)($eintrag['page'] ?? 0);
            if ($titel === '' || $seite < 1 || ($pageCount > 0 && $seite > $pageCount)) {
                continue;
            }
            $sauber[] = ['title' => $titel, 'page' => $seite];
        }
        usort($sauber, static fn(array $a, array $b): int => $a['page'] <=> $b['page']);

        return $sauber;
    }

    /**
     * Liest die Angaben einer gebauten Ausgabe.
     *
     * @return array<string, mixed>|null
     */
    /**
     * Loest den gemerkten Quellpfad auf.
     *
     * Aeltere Ausgaben tragen unter Umstaenden einen relativen Pfad; der wird
     * hier gegen das Projektverzeichnis geprueft, damit "Neu bauen" auch bei
     * ihnen wieder moeglich ist.
     */
    public function resolveSource(string $quelle): string
    {
        if ($quelle === '') {
            return '';
        }
        if (is_file($quelle)) {
            return (string)(realpath($quelle) ?: $quelle);
        }
        $wurzel = rtrim(Environment::getProjectPath(), '/') . '/' . ltrim($quelle, '/');
        if (is_file($wurzel)) {
            return (string)(realpath($wurzel) ?: $wurzel);
        }
        $oeffentlich = rtrim(Environment::getPublicPath(), '/') . '/' . ltrim($quelle, '/');

        return is_file($oeffentlich) ? (string)(realpath($oeffentlich) ?: $oeffentlich) : '';
    }

    public function readBook(string $slug): ?array
    {
        $bookFile = rtrim($this->resolveBasePath(), '/') . '/' . $this->sanitizeSlug($slug) . '/book.json';
        if (!is_file($bookFile)) {
            return null;
        }
        $book = json_decode((string)file_get_contents($bookFile), true);

        return is_array($book) ? $book : null;
    }

    /**
     * Aendert die Angaben einer Ausgabe, ohne die Seiten neu zu rendern.
     *
     * Titel, Sprache, Farben, Download und Umfang der Vorschau stecken nur in
     * book.json und in der Startseite - dafuer muss nichts gerendert werden.
     *
     * @param array{title?: string, language?: string, theme?: array<string, string>, download?: bool, teaserPages?: int} $angaben
     */
    public function updateSettings(string $slug, array $angaben): bool
    {
        $book = $this->readBook($slug);
        if ($book === null) {
            return false;
        }
        $target = rtrim($this->resolveBasePath(), '/') . '/' . $this->sanitizeSlug($slug);

        if (isset($angaben['title']) && trim($angaben['title']) !== '') {
            $book['title'] = trim($angaben['title']);
        }
        if (isset($angaben['language'])) {
            $book['language'] = $this->language((string)$angaben['language']);
            $book['labels'] = self::LABELS[$book['language']] ?? self::LABELS['en'];
        }
        if (isset($angaben['theme'])) {
            $book['theme'] = $this->theme((array)$angaben['theme']);
        }
        if (isset($angaben['ui'])) {
            // Die gespeicherten Werte sind bereits die vollstaendige Auswahl;
            // ui() haelt sie im Rahmen und faengt Unbekanntes ab.
            $book['ui'] = $this->ui((array)$angaben['ui']);
        }
        if (isset($angaben['download'])) {
            if ($angaben['download'] === false) {
                $book['downloadUrl'] = '';
            } elseif ((string)$book['downloadUrl'] === '') {
                $book['downloadUrl'] = $this->vorhandeneDownloadDatei($target);
            }
        }
        $book['built'] = time();

        /* Auch beim Speichern im Modul gilt: Was das Zusatzpaket zur
           Angabendatei beitraegt, traegt es hier bei. */
        $book = $this->eventDispatcher
            ->dispatch(new BeforeBookWrittenEvent($book, $angaben, $target))
            ->getBuch();

        file_put_contents(
            $target . '/book.json',
            json_encode($book, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        $this->rebuildTeaser($target, $book, (int)($angaben['teaserPages'] ?? 0));
        $this->writeIndex($target, $book);

        return true;
    }

    /**
     * Baut teaser.json aus den vorhandenen Angaben neu - ohne zu rendern.
     *
     * @param array<string, mixed> $book
     */
    private function rebuildTeaser(string $target, array $book, int $anzahl): void
    {
        if ($anzahl < 1) {
            // Umfang unveraendert: den bisherigen uebernehmen
            $bisher = is_file($target . '/teaser.json')
                ? json_decode((string)file_get_contents($target . '/teaser.json'), true)
                : null;
            $anzahl = is_array($bisher) ? (int)($bisher['pageCount'] ?? 0) : 0;
        }
        if ($anzahl < 1) {
            return;
        }

        $texte = is_file($target . '/search.json')
            ? (array)json_decode((string)file_get_contents($target . '/search.json'), true)
            : [];
        $this->writeTeaser($target, $book, $texte, ['teaserPages' => $anzahl]);
    }

    /**
     * Schreibt ein geaendertes Inhaltsverzeichnis und erneuert die Startseite -
     * ohne die Seiten neu zu rendern.
     *
     * @param array<int, array{title?: string, page?: int|string}> $toc
     */
    public function updateToc(string $slug, array $toc): bool
    {
        $book = $this->readBook($slug);
        if ($book === null) {
            return false;
        }
        $book['toc'] = $this->normalizeToc($toc, (int)($book['pageCount'] ?? 0));
        $target = rtrim($this->resolveBasePath(), '/') . '/' . $this->sanitizeSlug($slug);
        file_put_contents(
            $target . '/book.json',
            json_encode($book, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        $this->writeIndex($target, $book);

        return true;
    }

    /**
     * Entfernt eine gebaute Ausgabe vollstaendig.
     */
    public function delete(string $slug): bool
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return false;
        }
        $target = rtrim($this->resolveBasePath(), '/') . '/' . $slug;
        if (!is_dir($target)) {
            return false;
        }
        $this->clearDirectory($target);

        return !is_dir($target);
    }

    /**
     * Haelt die gebauten Ausgaben aus den Suchmaschinen heraus.
     *
     * Die massgebliche Seite ist die Landingpage, nicht der Betrachter - und
     * bei geschuetzten Unterlagen soll das PDF ohnehin nur ueber den dafuer
     * vorgesehenen Weg erreichbar sein. Der Kopf wirkt fuer das ganze
     * Verzeichnis, also auch fuer die Seitenbilder und die Download-Fassung.
     *
     * Greift nur auf Apache. Unter nginx gehoert die gleichwertige Regel in
     * die Serverkonfiguration; die Startseite jeder Ausgabe traegt zusaetzlich
     * ein noindex im Kopfbereich.
     */
    private function protectFromIndexing(): void
    {
        if (($this->configuration()['protectFromIndexing'] ?? '1') === '0') {
            return;
        }
        $datei = rtrim($this->resolveBasePath(), '/') . '/.htaccess';
        $inhalt = <<<'HTACCESS'
# Von EXT:nt_flippdf angelegt. Wird bei jedem Bau erneuert, solange diese
# Zeile darin steht - eigene Ergaenzungen also besser woanders.
#
# Die blaetterbaren Ausgaben sollen nicht in den Suchmaschinen auftauchen -
# massgeblich ist die Seite, die sie einbindet. Der Kopf gilt fuer alles in
# diesem Verzeichnis, also auch fuer Seitenbilder und Download-Fassung.
<IfModule mod_headers.c>
    Header set X-Robots-Tag "noindex, nofollow"

    # Startseite und Angabendateien immer rueckfragen. Ohne das haelt ein
    # Browser, der eine Datei einmal vergeblich gesucht hat, deren 404 fest -
    # und zeigt nach dem naechsten Bau weiter nichts an. Die Seitenbilder
    # tragen eine Zufallskennung im Namen und duerfen liegen bleiben.
    <FilesMatch "\.(html|json)$">
        Header set Cache-Control "no-cache, must-revalidate"
    </FilesMatch>
</IfModule>

HTACCESS;

        // Von Hand angelegte Dateien nicht ueberschreiben
        if (is_file($datei) && !str_contains((string)file_get_contents($datei), 'Von EXT:nt_flippdf angelegt')) {
            return;
        }
        file_put_contents($datei, $inhalt);
    }

    /**
     * Farben des Betrachters. Erlaubt sind nur die vier vorgesehenen Werte,
     * und nur als Farbangabe - der Inhalt landet in einem Stil-Block.
     *
     * @param array<string, string> $theme
     * @return array<string, string>
     */
    /**
     * Farben der Ausgabe.
     *
     * Was hier nicht behandelt wird, bleibt stehen: Das Zusatzpaket legt Logo
     * und Hintergrund in dieselbe Ablage, und die duerfen beim Speichern im
     * Modul nicht verloren gehen.
     *
     * @param array<string, mixed> $theme
     * @return array<string, mixed>
     */
    private function theme(array $theme): array
    {
        $sauber = $theme;
        foreach (['bar', 'barText', 'stage', 'accent'] as $name) {
            $wert = trim((string)($theme[$name] ?? ''));
            if ($wert === '' || !preg_match('/^#[0-9a-fA-F]{3,8}$/', $wert)) {
                unset($sauber[$name]);
                continue;
            }
            $sauber[$name] = $wert;
        }

        return $sauber;
    }






    /**
     * Bedienelemente des Betrachters.
     *
     * Reihenfolge der Geltung: Vorgabe hier, dann die Extension-Konfiguration
     * (Schluessel mit dem Vorsatz "ui", also uiSearch, uiButtonStyle ...),
     * zuletzt die Angaben der Ausgabe selbst.
     *
     * @param array<string, mixed> $ui
     * @return array<string, bool|string>
     */
    private function ui(array $ui): array
    {
        $werte = self::UI_DEFAULTS;
        foreach ($this->configuration() as $name => $wert) {
            if (!str_starts_with((string)$name, 'ui') || $name === 'ui') {
                continue;
            }
            $schluessel = lcfirst(substr((string)$name, 2));
            if (array_key_exists($schluessel, $werte)) {
                $werte[$schluessel] = is_bool($werte[$schluessel]) ? (string)$wert !== '0' : (string)$wert;
            }
        }
        foreach ($ui as $name => $wert) {
            if (!array_key_exists($name, $werte) || $wert === null || $wert === '') {
                continue;
            }
            $werte[$name] = is_bool($werte[$name]) ? (bool)$wert : (string)$wert;
        }
        if (!in_array($werte['buttonStyle'], self::BUTTON_STYLES, true)) {
            $werte['buttonStyle'] = 'text';
        }

        return $werte;
    }

    /**
     * @param array<string, mixed> $flip
     * @return array<string, int|float|bool>
     */
    private function flip(array $flip): array
    {
        $werte = self::FLIP_DEFAULTS;
        foreach ($this->configuration() as $name => $wert) {
            if (str_starts_with((string)$name, 'flip') && $name !== 'flip') {
                $schluessel = lcfirst(substr((string)$name, 4));
                if (array_key_exists($schluessel, $werte)) {
                    $werte[$schluessel] = is_bool($werte[$schluessel]) ? (string)$wert !== '0' : $wert + 0;
                }
            }
        }
        foreach ($flip as $name => $wert) {
            if (array_key_exists($name, $werte) && $wert !== null && $wert !== '') {
                $werte[$name] = is_bool($werte[$name]) ? (bool)$wert : $wert + 0;
            }
        }
        // Mindestens 1: StPageFlip lehnt 0 ab. Wer keine Bewegung will, ist
        // mit 1 bedient - das sieht niemand.
        $werte['duration'] = max(1, min(3000, (int)$werte['duration']));
        $werte['shadowOpacity'] = max(0.0, min(1.0, (float)$werte['shadowOpacity']));

        return $werte;
    }



    /**
     * Benennt die Seitenbilder mit einer Zufallskennung um.
     *
     * @param string[] $pageFiles
     * @return string[]
     */


    private function renamePages(array $pageFiles, string $kennung): array
    {
        $neu = [];
        foreach ($pageFiles as $datei) {
            $nummer = (int)basename($datei, '.jpg');
            $ziel = dirname($datei) . '/' . $nummer . '-' . $kennung . '.jpg';
            if (@rename($datei, $ziel)) {
                $neu[] = $ziel;
                continue;
            }
            $neu[] = $datei;
        }

        return $neu;
    }

    /**
     * Legt die Angaben fuer die Vorschau ab.
     *
     * Die Vorschau bekommt eine eigene Datei mit nur den ersten Seiten und
     * ohne Download. Weil die Seitenbilder eine Zufallskennung im Namen tragen,
     * sind die uebrigen Seiten damit auch nicht zu erraten - genau das war der
     * Grund, bisher eine zweite Fassung zu bauen.
     *
     * @param array<string, mixed> $book
     * @param array<string, string> $texts
     * @param array{teaserPages?: int} $issue
     */
    private function writeTeaser(string $target, array $book, array $texts, array $issue): void
    {
        $anzahl = (int)($issue['teaserPages'] ?? null ?: ($this->configuration()['teaserPages'] ?? 5));
        if ($anzahl < 1) {
            return;
        }
        $anzahl = min($anzahl, (int)$book['pageCount']);

        $vorschau = $book;
        $vorschau['pages'] = array_slice($book['pages'], 0, $anzahl);
        $vorschau['pageCount'] = $anzahl;
        $vorschau['downloadUrl'] = '';
        $vorschau['teaser'] = true;
        $vorschau['toc'] = array_values(array_filter(
            (array)($book['toc'] ?? []),
            static fn(array $eintrag): bool => (int)$eintrag['page'] <= $anzahl
        ));
        file_put_contents(
            $target . '/teaser.json',
            json_encode($vorschau, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $vorschauTexte = array_intersect_key($texts, array_flip(array_map('strval', range(1, $anzahl))));
        file_put_contents(
            $target . '/teaser-search.json',
            json_encode($vorschauTexte, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function language(string $language): string
    {
        $language = strtolower(substr(trim($language), 0, 2));
        // "cn" ist die im Haus uebliche Schreibweise, die Sprache heisst zh.
        if ($language === 'cn') {
            $language = 'zh';
        }

        /* Beschriftungen bringt das Basispaket fuer Deutsch und Englisch mit.
           Franzoesisch und Chinesisch gelten trotzdem als gueltige Sprache:
           Ein Zusatzpaket liefert die Beschriftungen dazu, ohne dass die
           Ausgabe deshalb auf Deutsch zurueckfaellt. Fehlen sie, greift
           Englisch. */
        return in_array($language, ['de', 'en', 'fr', 'zh'], true) ? $language : 'de';
    }

    private function pageWidth(): int
    {
        return max(600, (int)($this->configuration()['pageWidth'] ?? 1240));
    }

    private function jpegQuality(): int
    {
        return min(100, max(40, (int)($this->configuration()['jpegQuality'] ?? 80)));
    }

    private function thumbWidth(): int
    {
        return max(80, (int)($this->configuration()['thumbWidth'] ?? 200));
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        try {
            return (array)$this->extensionConfiguration->get('nt_flippdf');
        } catch (\Throwable) {
            return [];
        }
    }


    private function copyAssets(string $target): void
    {
        GeneralUtility::mkdir_deep($target);
        $viewer = GeneralUtility::getFileAbsFileName('EXT:nt_flippdf/Resources/Public/Viewer/');
        foreach (['viewer.js', 'viewer.css', 'blaettern.mp3'] as $file) {
            if (is_file($viewer . $file)) {
                copy($viewer . $file, $target . '/' . $file);
            }
        }
        $vendor = GeneralUtility::getFileAbsFileName('EXT:nt_flippdf/Resources/Public/Vendor/page-flip/');
        foreach (['page-flip.browser.js', 'page-flip.css', 'LICENSE'] as $file) {
            if (is_file($vendor . $file)) {
                copy($vendor . $file, $target . '/' . $file);
            }
        }
    }

    /**
     * @param array<string, mixed> $book
     */
    private function writeIndex(string $target, array $book): void
    {
        $template = (string)file_get_contents(
            GeneralUtility::getFileAbsFileName('EXT:nt_flippdf/Resources/Private/Templates/index.html')
        );
        $labels = (array)($book['labels'] ?? self::LABELS['de']);
        $ersetzungen = [
            '{{lang}}' => htmlspecialchars((string)($book['language'] ?? 'de'), ENT_QUOTES),
            '{{title}}' => htmlspecialchars((string)$book['title'], ENT_QUOTES),
            '{{pageCount}}' => (string)$book['pageCount'],
            '{{built}}' => (string)($book['built'] ?? time()),
            '{{downloadUrl}}' => htmlspecialchars((string)($book['downloadUrl'] ?? ''), ENT_QUOTES),
            '{{pageIndicator}}' => htmlspecialchars(
                sprintf((string)($labels['pageOf'] ?? 'Seite %1$s von %2$s'), '1', (string)$book['pageCount']),
                ENT_QUOTES
            ),
        ];
        foreach ($labels as $schluessel => $wert) {
            $ersetzungen['{{label.' . $schluessel . '}}'] = htmlspecialchars((string)$wert, ENT_QUOTES);
        }

        $theme = $this->theme((array)($book['theme'] ?? []));
        $zuordnung = ['bar' => '--bar', 'barText' => '--bar-text', 'stage' => '--stage', 'accent' => '--accent'];
        $regeln = [];
        foreach ($zuordnung as $name => $eigenschaft) {
            if (isset($theme[$name])) {
                $regeln[] = $eigenschaft . ':' . $theme[$name];
            }
        }
        // Hintergrundbild: Es liegt als Kopie neben der Startseite, die Angaben
        // stehen als Variablen im Stylesheet.
        if (isset($theme['background'])) {
            $datei = glob($target . '/hintergrund.*');
            if ($datei !== [] && $datei !== false) {
                // Bewusst mit vollem Pfad ab dem Wurzelverzeichnis: Die Variable
                // wird in index.html gesetzt, aber in assets/viewer.css benutzt -
                // und die Browser sind sich nicht einig, gegen welche der beiden
                // Dateien sie eine relative Adresse aufloesen.
                $adresse = rtrim($this->resolveBaseUrl(), '/') . '/'
                    . (string)($book['slug'] ?? basename($target)) . '/' . basename($datei[0]);
                $regeln[] = "--stage-image:url('" . $adresse . '?v=' . (int)($book['built'] ?? 0) . "')";
                $regeln[] = '--stage-dim:' . ((int)($theme['backgroundDim'] ?? 45) / 100);
                $passung = (string)($theme['backgroundFit'] ?? 'cover');
                $regeln[] = '--stage-size:' . ($passung === 'tile' ? 'auto' : $passung);
                $regeln[] = '--stage-repeat:' . ($passung === 'tile' ? 'repeat' : 'no-repeat');
            }
        }
        $ersetzungen['{{theme}}'] = $regeln === []
            ? ''
            : '<style>:root{' . implode(';', $regeln) . '}</style>';

        // Logo: liegt als Kopie in der Ausgabe, die Anordnung steckt in der Klasse.
        $ersetzungen['{{logo}}'] = '';
        if (isset($theme['logo']) && is_file($target . '/' . $theme['logo'])) {
            $bild = '<img src="' . htmlspecialchars((string)$theme['logo'], ENT_QUOTES)
                . '?v=' . (int)($book['built'] ?? 0) . '" alt=""'
                . ' style="width:' . (int)($theme['logoWidth'] ?? 120) . 'px;opacity:'
                . round(((int)($theme['logoOpacity'] ?? 100)) / 100, 2) . '">';
            if (isset($theme['logoLink'])) {
                $bild = '<a href="' . htmlspecialchars((string)$theme['logoLink'], ENT_QUOTES)
                    . '" target="_blank" rel="noopener">' . $bild . '</a>';
            }
            $ersetzungen['{{logo}}'] = '<div data-ui="logo" class="marke marke-'
                . htmlspecialchars((string)($theme['logoPosition'] ?? 'oben-rechts'), ENT_QUOTES)
                . '">' . $bild . '</div>';
        }

        file_put_contents($target . '/index.html', strtr($template, $ersetzungen));
    }

    public function sanitizeSlug(string $slug): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $slug) ?? '';
    }

    /**
     * Setzt die frisch gebaute Ausgabe an die Stelle der alten.
     *
     * Zwei Umbenennungen statt Kopieren: Das dauert Sekundenbruchteile, und
     * nur in dieser Spanne fehlt die Ausgabe.
     */
    /**
     * Bausteine fuer Zusatzpakete
     *
     * Wer neben einer Ausgabe eine zweite anlegen will - etwa eine Vorschau -,
     * braucht dieselben Handgriffe: Betrachter hineinlegen, Startseite
     * schreiben, Verzeichnis einsetzen. Statt sie nachzubauen, stehen sie hier
     * zur Verfuegung.
     */
    public function writeViewer(string $verzeichnis, array $buch): void
    {
        $this->copyAssets($verzeichnis . '/assets');
        $this->writeIndex($verzeichnis, $buch);
    }

    /** Setzt ein fertig gebautes Verzeichnis an die Stelle eines vorhandenen. */
    public function swapDirectory(string $arbeit, string $ziel): void
    {
        $this->tauscheVerzeichnis($arbeit, $ziel);
    }

    /** Leert ein Verzeichnis und legt es bei Bedarf an. */
    public function clearDir(string $verzeichnis): void
    {
        $this->clearDirectory($verzeichnis);
    }

    public function directoryBytes(string $verzeichnis): int
    {
        return $this->directorySize($verzeichnis);
    }

    private function tauscheVerzeichnis(string $arbeit, string $ziel): void
    {
        $alt = $ziel . '.alt';
        $this->clearDirectory($alt);
        if (is_dir($ziel)) {
            rename($ziel, $alt);
        }
        rename($arbeit, $ziel);
        $this->clearDirectory($alt);
    }

    private function clearDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        GeneralUtility::rmdir($directory, true);
    }

    private function directorySize(string $directory): int
    {
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $bytes += $file->getSize();
        }

        return $bytes;
    }
}
