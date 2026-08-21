<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Service;

/**
 * Schlaegt ein Inhaltsverzeichnis vor.
 *
 * PDFs bringen ihre Lesezeichen nur selten mit, und die Werkzeuge, die sie
 * auslesen koennten (mutool, pdftk), sind auf den Servern nicht vorhanden.
 * Deshalb wird das Verzeichnis aus der Seite selbst hergeleitet: Ueberschriften
 * heben sich durch ihre Zeilenhoehe vom Fliesstext ab. Der Vorschlag ist
 * ausdruecklich ein Vorschlag - im Backend-Modul laesst er sich nachbessern.
 */
class TocBuilder
{
    /**
     * Ab dem Wievielfachen der ueblichen Zeilenhoehe eine Zeile als
     * Ueberschrift gilt. 1,6 hat sich als brauchbar erwiesen: Zwischentitel
     * im Fliesstext liegen darunter, Kapiteltitel darueber.
     */
    private const HEADING_FACTOR = 1.6;

    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 120;

    public function __construct(private readonly PdfToolkit $toolkit) {}

    public function isSupported(): bool
    {
        return $this->toolkit->supportsLayoutExtraction();
    }

    /**
     * @return list<array{title: string, page: int}>
     */
    public function suggest(string $pdfFile, int $pageCount): array
    {
        if (!$this->isSupported()) {
            return [];
        }

        $kandidaten = [];
        $hoehen = [];
        for ($seite = 1; $seite <= $pageCount; $seite++) {
            $zeilen = $this->toolkit->extractPageLayout($pdfFile, $seite);
            if ($zeilen === []) {
                continue;
            }
            $seitenHoehen = array_map(static fn(array $z): float => $z['height'], $zeilen);
            $hoehen = array_merge($hoehen, $seitenHoehen);
            $ueblich = $this->median($seitenHoehen);
            if ($ueblich <= 0.0) {
                continue;
            }

            // Je Seite hoechstens eine Ueberschrift: die groesste. Mehrere
            // Titel je Seite machen das Verzeichnis unuebersichtlich statt
            // genauer.
            $beste = $this->largestHeading($zeilen, $ueblich);
            if ($beste !== null) {
                $kandidaten[] = ['title' => $beste['title'], 'page' => $seite, 'height' => $beste['height']];
            }
        }

        return $this->tidy($kandidaten, $this->median($hoehen));
    }

    /**
     * Groesste Ueberschrift einer Seite.
     *
     * Ueberschriften laufen haeufig ueber mehrere Zeilen ("Slurries als
     * Alternative / zu Pulver"). Wuerde nur die einzelne groesste Zeile zaehlen,
     * bliebe im Verzeichnis ein Bruchstueck stehen. Deshalb werden direkt
     * aufeinander folgende Zeilen aehnlicher Hoehe zu einem Titel verbunden.
     *
     * @param list<array{text: string, height: float, y: float, xMin: float, xMax: float}> $zeilen
     * @return array{title: string, height: float}|null
     */
    private function largestHeading(array $zeilen, float $ueblich): ?array
    {
        $schwelle = $ueblich * self::HEADING_FACTOR;
        $gruppen = [];
        $aktuell = null;

        foreach ($zeilen as $zeile) {
            if ($zeile['height'] < $schwelle) {
                $aktuell = null;
                continue;
            }
            $text = $this->clean($zeile['text']);
            if ($text === '') {
                $aktuell = null;
                continue;
            }
            $passtDazu = $aktuell !== null
                && abs($zeile['height'] - $aktuell['height']) <= $aktuell['height'] * 0.2
                && abs($zeile['y'] - $aktuell['y']) <= $aktuell['height'] * 2.5;

            if ($passtDazu) {
                $aktuell['parts'][] = $text;
                $aktuell['y'] = $zeile['y'];
                $gruppen[count($gruppen) - 1] = $aktuell;
                continue;
            }

            $aktuell = ['parts' => [$text], 'height' => $zeile['height'], 'y' => $zeile['y']];
            $gruppen[] = $aktuell;
        }

        $beste = null;
        foreach ($gruppen as $gruppe) {
            $titel = $this->clean(implode(' ', $gruppe['parts']));
            if (!$this->isPlausible($titel)) {
                continue;
            }
            if ($beste === null || $gruppe['height'] > $beste['height']) {
                $beste = ['title' => $titel, 'height' => $gruppe['height']];
            }
        }

        return $beste;
    }

    /**
     * Entfernt Dopplungen und offensichtliche Kopfzeilen.
     *
     * Ein Titel, der auf vielen Seiten in derselben Groesse wiederkehrt, ist
     * eine laufende Kopfzeile und kein Kapitel.
     *
     * @param list<array{title: string, page: int, height: float}> $kandidaten
     * @return list<array{title: string, page: int}>
     */
    private function tidy(array $kandidaten, float $ueblich): array
    {
        $haeufigkeit = [];
        foreach ($kandidaten as $kandidat) {
            $schluessel = mb_strtolower($kandidat['title']);
            $haeufigkeit[$schluessel] = ($haeufigkeit[$schluessel] ?? 0) + 1;
        }

        $verzeichnis = [];
        $zuletzt = '';
        foreach ($kandidaten as $kandidat) {
            $schluessel = mb_strtolower($kandidat['title']);
            if (($haeufigkeit[$schluessel] ?? 0) > 2) {
                continue;
            }
            if ($schluessel === $zuletzt) {
                continue;
            }
            if ($ueblich > 0.0 && $kandidat['height'] < $ueblich * self::HEADING_FACTOR) {
                continue;
            }
            $verzeichnis[] = ['title' => $kandidat['title'], 'page' => $kandidat['page']];
            $zuletzt = $schluessel;
        }

        return $verzeichnis;
    }

    private function clean(string $text): string
    {
        // Weiche Trennstriche und ueberzaehlige Leerzeichen raus
        $text = str_replace(["\u{00AD}", "\u{200B}"], '', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function isPlausible(string $text): bool
    {
        $laenge = mb_strlen($text);
        if ($laenge < self::MIN_LENGTH || $laenge > self::MAX_LENGTH) {
            return false;
        }
        // Reine Seitenzahlen, Datumsangaben und Versalienreihen ohne Buchstaben
        if (preg_match('/^[\d\s.,\/-]+$/u', $text)) {
            return false;
        }

        return (bool)preg_match('/\p{L}{3}/u', $text);
    }

    /**
     * @param list<float> $werte
     */
    private function median(array $werte): float
    {
        if ($werte === []) {
            return 0.0;
        }
        sort($werte);
        $mitte = (int)floor(count($werte) / 2);

        return count($werte) % 2 === 1
            ? $werte[$mitte]
            : ($werte[$mitte - 1] + $werte[$mitte]) / 2;
    }
}
