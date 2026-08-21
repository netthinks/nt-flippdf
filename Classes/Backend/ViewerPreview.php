<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Backend;

use Netthinks\NtFlippdf\Service\FlipbookBuilder;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Vorschau des Inhaltselements im Seitenmodul.
 *
 * Ohne eigene Vorschau steht dort nur der Name des Elements - die Redaktion
 * muesste jedes Element oeffnen, um zu sehen, welche Ausgabe darin steckt.
 * Gezeigt werden deshalb das Titelbild der Ausgabe und alles, was sich ohne
 * Klick beantworten laesst: Umfang, Sprache, Baustand, Groesse, Quelle,
 * die Einstellungen dieses Elements.
 */
class ViewerPreview extends StandardContentPreviewRenderer
{
    public function __construct(
        private readonly FlipbookBuilder $builder,
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $daten = $item->getRecord();
        $einstellungen = $this->flexform((string)($daten['pi_flexform'] ?? ''));
        $kennung = trim((string)($einstellungen['kennung'] ?? ''));

        if ($kennung === '') {
            return $this->hinweis('Es ist noch keine Ausgabe ausgewählt.');
        }

        $book = $this->builder->readBook($kennung);
        if ($book === null) {
            return $this->hinweis(
                'Die Ausgabe <strong>' . htmlspecialchars($kennung) . '</strong> gibt es nicht (mehr). '
                . 'Im Modul „Blätterbare Ausgaben“ lässt sie sich neu bauen.'
            );
        }

        return '<div class="ntflippdf-preview" style="display:flex;gap:1rem;align-items:flex-start">'
            . $this->titelbild($kennung, $book)
            . '<div style="min-width:0">'
            . '<div style="font-weight:600;margin-bottom:.15rem">' . htmlspecialchars((string)($book['title'] ?? $kennung)) . '</div>'
            . $this->angaben($kennung, $book)
            . $this->einstellungenZeile($einstellungen, $book)
            . '</div></div>';
    }

    /**
     * Das Titelbild liegt als Vorschaubild der ersten Seite in der Ausgabe.
     *
     * @param array<string, mixed> $book
     */
    private function titelbild(string $kennung, array $book): string
    {
        $seiten = (array)($book['pages'] ?? []);
        $erste = $seiten[0]['thumb'] ?? 'thumbs/1.jpg';
        $pfad = rtrim($this->builder->resolveBasePath(), '/') . '/' . $kennung . '/' . $erste;
        if (!is_file($pfad)) {
            return '';
        }
        $adresse = rtrim($this->builder->resolveBaseUrl(), '/') . '/' . $kennung . '/' . $erste
            . '?v=' . (int)($book['built'] ?? 0);

        return '<a href="' . htmlspecialchars(rtrim($this->builder->resolveBaseUrl(), '/') . '/' . $kennung . '/')
            . '" target="_blank" rel="noopener" title="Ausgabe in einem neuen Fenster öffnen">'
            . '<img src="' . htmlspecialchars($adresse) . '" alt="" loading="lazy"'
            . ' style="width:92px;height:auto;border:1px solid #d7d7d7;border-radius:2px;background:#fff"></a>';
    }

    /**
     * Kennzahlen der Ausgabe.
     *
     * @param array<string, mixed> $book
     */
    private function angaben(string $kennung, array $book): string
    {
        $pfad = rtrim($this->builder->resolveBasePath(), '/') . '/' . $kennung;
        $quelle = (string)($book['source'] ?? '');
        $veraltet = $quelle !== '' && is_file($quelle)
            && (int)@filemtime($quelle) > (int)($book['sourceTime'] ?? 0);

        $zeilen = [
            'Kennung' => '<code>' . htmlspecialchars($kennung) . '</code>',
            'Umfang' => (int)($book['pageCount'] ?? 0) . ' Seiten'
                . (count((array)($book['toc'] ?? [])) > 0
                    ? ', ' . count((array)($book['toc'] ?? [])) . ' Kapitel im Inhaltsverzeichnis'
                    : ', kein Inhaltsverzeichnis'),
            'Sprache' => strtoupper((string)($book['language'] ?? 'de')),
            'Gebaut' => ($book['built'] ?? 0)
                ? date('d.m.Y H:i', (int)$book['built']) . ' Uhr'
                : 'unbekannt',
            'Größe' => number_format($this->verzeichnisGroesse($pfad) / 1048576, 1, ',', '.') . ' MB',
            'Quelle' => $quelle === ''
                ? 'unbekannt'
                : '<code title="' . htmlspecialchars($this->lesbarerPfad($quelle), ENT_QUOTES) . '">'
                    . htmlspecialchars(basename($quelle)) . '</code>'
                    . (is_file($quelle) ? '' : ' <em>(Datei fehlt)</em>')
                    . ($veraltet ? ' <strong style="color:#b8860b">– die Quelldatei ist neuer als die Ausgabe</strong>' : ''),
            'Download' => (string)($book['downloadUrl'] ?? '') !== '' ? 'vorhanden' : 'nicht vorhanden',
        ];

        return $this->liste($zeilen);
    }

    /**
     * Vollstaendige Pfade sagen im Backend wenig; gebraucht wird der Ort im
     * Dateibereich beziehungsweise im Projekt.
     */
    private function lesbarerPfad(string $pfad): string
    {
        foreach ([\TYPO3\CMS\Core\Core\Environment::getPublicPath(), \TYPO3\CMS\Core\Core\Environment::getProjectPath()] as $wurzel) {
            $wurzel = rtrim($wurzel, '/') . '/';
            if (str_starts_with($pfad, $wurzel)) {
                return substr($pfad, strlen($wurzel));
            }
        }

        return $pfad;
    }

    /**
     * Was an diesem Element eingestellt ist.
     *
     * @param array<string, string> $einstellungen
     * @param array<string, mixed> $book
     */
    private function einstellungenZeile(array $einstellungen, array $book): string
    {
        $darstellung = ($einstellungen['darstellung'] ?? 'eingebettet') === 'knopf'
            ? 'nur eine Schaltfläche („' . htmlspecialchars((string)($einstellungen['knopftext'] ?? 'Durchblättern')) . '“)'
            : 'eingebettet, ' . (int)($einstellungen['hoehe'] ?? 700) . ' px hoch';

        $zeilen = ['Darstellung' => $darstellung];

        if (!empty($einstellungen['teaser'])) {
            $zeilen['Vorschau'] = 'nur die ersten ' . (int)($einstellungen['teaserseiten'] ?? 5)
                . ' Seiten, ohne Download';
        }
        $stil = (string)($einstellungen['stil'] ?? '');
        $stilNamen = ['text' => 'ausgeschrieben', 'icon' => 'nur Symbole', 'both' => 'Symbol und Beschriftung'];
        $ausAusgabe = (string)(($book['ui'] ?? [])['buttonStyle'] ?? 'text');
        $zeilen['Schaltflächen'] = $stil !== ''
            ? ($stilNamen[$stil] ?? $stil) . ' (nur hier)'
            : ($stilNamen[$ausAusgabe] ?? $ausAusgabe) . ' (wie in der Ausgabe)';

        // Hintergrund: Ein Element kann einen anderen zeigen als die Ausgabe -
        // ohne diesen Hinweis sucht man den Unterschied lange.
        $motiv = trim((string)($einstellungen['hintergrund'] ?? ''));
        $ausAusgabeMotiv = (string)(($book['theme'] ?? [])['background'] ?? '');
        if ($motiv === '-') {
            $zeilen['Hintergrund'] = 'keiner (nur hier)';
        } elseif ($motiv !== '') {
            $zeilen['Hintergrund'] = htmlspecialchars($motiv) . ' (nur hier)';
        } elseif ($ausAusgabeMotiv !== '') {
            $zeilen['Hintergrund'] = htmlspecialchars($ausAusgabeMotiv) . ' (wie in der Ausgabe)';
        }

        $ohne = array_filter(explode(',', (string)($einstellungen['ohne'] ?? '')));
        if ($ohne !== []) {
            $namen = [
                'search' => 'Suche', 'toc' => 'Inhaltsverzeichnis', 'thumbs' => 'Seitenübersicht',
                'download' => 'Download', 'zoom' => 'Vergrößern', 'print' => 'Drucken',
                'fullscreen' => 'Vollbild', 'languages' => 'Sprachumschaltung',
                'nav' => 'Pfeile', 'slider' => 'Seitenregler', 'marks' => 'Kapitelmarken',
                'indicator' => 'Seitenzahl', 'hint' => 'Bedienhinweis',
            ];
            $zeilen['Hier ausgeblendet'] = htmlspecialchars(implode(', ', array_map(
                static fn(string $name): string => $namen[trim($name)] ?? trim($name),
                $ohne
            )));
        }

        // Was schon in der Ausgabe selbst abgeschaltet ist, gilt ueberall -
        // das gehoert in die Vorschau, sonst sucht die Redaktion am falschen Ort.
        $ui = (array)($book['ui'] ?? []);
        $ausInAusgabe = [];
        foreach ($ui as $name => $wert) {
            if ($name !== 'buttonStyle' && $wert === false) {
                $ausInAusgabe[] = $name;
            }
        }
        if ($ausInAusgabe !== []) {
            $zeilen['In der Ausgabe aus'] = htmlspecialchars(implode(', ', $ausInAusgabe));
        }

        return '<div style="margin-top:.35rem;padding-top:.35rem;border-top:1px solid #e0e0e0">'
            . $this->liste($zeilen) . '</div>';
    }

    /**
     * @param array<string, string> $zeilen
     */
    private function liste(array $zeilen): string
    {
        $html = '<dl style="display:grid;grid-template-columns:auto 1fr;gap:.1rem .6rem;margin:0;font-size:.9em">';
        foreach ($zeilen as $name => $wert) {
            $html .= '<dt style="color:#6b6b6b;font-weight:400;white-space:nowrap">' . htmlspecialchars((string)$name) . '</dt>'
                . '<dd style="margin:0">' . $wert . '</dd>';
        }

        return $html . '</dl>';
    }

    private function hinweis(string $text): string
    {
        return '<div class="alert alert-warning" style="margin:0">' . $text . '</div>';
    }

    /**
     * Liest die Einstellungen aus dem FlexForm.
     *
     * @return array<string, string>
     */
    private function flexform(string $inhalt): array
    {
        if (trim($inhalt) === '') {
            return [];
        }
        $daten = GeneralUtility::xml2array($inhalt);
        if (!is_array($daten)) {
            return [];
        }
        $werte = [];
        foreach ((array)($daten['data']['sDEF']['lDEF'] ?? []) as $name => $feld) {
            $werte[str_replace('settings.', '', (string)$name)] = (string)($feld['vDEF'] ?? '');
        }

        return $werte;
    }


    private function verzeichnisGroesse(string $pfad): int
    {
        if (!is_dir($pfad)) {
            return 0;
        }
        $summe = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pfad, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $datei) {
            /** @var \SplFileInfo $datei */
            if ($datei->isFile()) {
                $summe += $datei->getSize();
            }
        }

        return $summe;
    }
}
