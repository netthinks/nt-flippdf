<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Duenne Huelle um die PDF-Werkzeuge des Servers.
 *
 * Gebraucht werden Ghostscript und ImageMagick. Die poppler-Werkzeuge sind
 * nicht ueberall vorhanden, deshalb laeuft alles Notwendige ueber Ghostscript;
 * pdfinfo wird nur genutzt, wenn es da ist, weil es die Seitenzahl schneller
 * liefert.
 */
class PdfToolkit
{
    /**
     * Ist die Verarbeitung auf diesem System moeglich?
     *
     * @return array{ok: bool, missing: string[], found: array<string, string>}
     */
    public function checkEnvironment(): array
    {
        $found = [];
        $missing = [];
        foreach (['gs' => 'Ghostscript', 'identify' => 'ImageMagick'] as $binary => $label) {
            $path = $this->locate($binary);
            if ($path === null) {
                $missing[] = $label . ' (' . $binary . ')';
            } else {
                $found[$label] = $path;
            }
        }
        foreach (['pdfinfo' => 'poppler (pdfinfo)', 'convert' => 'ImageMagick (convert)', 'magick' => 'ImageMagick (magick)'] as $binary => $label) {
            $path = $this->locate($binary);
            if ($path !== null) {
                $found[$label] = $path;
            }
        }

        return ['ok' => $missing === [], 'missing' => $missing, 'found' => $found];
    }

    /**
     * Seitenzahl eines PDFs. Versucht pdfinfo, dann Ghostscript, dann ImageMagick.
     */
    public function countPages(string $file): int
    {
        $pdfinfo = $this->locate('pdfinfo');
        if ($pdfinfo !== null) {
            $output = $this->run([$pdfinfo, $file]);
            if (preg_match('/^Pages:\s+(\d+)/m', $output['stdout'], $matches)) {
                return (int)$matches[1];
            }
        }

        $gs = $this->locate('gs');
        if ($gs !== null) {
            $output = $this->run([
                $gs, '-q', '-dNODISPLAY', '-dNOSAFER', '-dBATCH',
                // In einem PostScript-String sind (, ) und \ Sonderzeichen
                '-c', sprintf(
                    '(%s) (r) file runpdfbegin pdfpagecount = quit',
                    addcslashes($file, '()\\')
                ),
            ]);
            // Nur eine Ausgabe, die ausschliesslich aus der Zahl besteht, zaehlt -
            // sonst wuerde eine Ziffer aus einer Fehlermeldung als Seitenzahl gelten.
            if (preg_match('/^\s*(\d+)\s*$/', $output['stdout'], $matches)) {
                return (int)$matches[1];
            }
        }

        $identify = $this->locate('identify');
        if ($identify !== null) {
            $output = $this->run([$identify, '-format', '%n\n', $file]);
            $first = (int)trim(explode("\n", trim($output['stdout']))[0] ?? '0');
            if ($first > 0) {
                return $first;
            }
        }

        return 0;
    }

    /**
     * Erzeugt eine verkleinerte Fassung zum Herunterladen.
     *
     * Die Schwelle ist der entscheidende Wert: Ghostscript verkleinert Bilder
     * standardmaessig erst, wenn ihre Aufloesung die Zielaufloesung um Faktor
     * 1,5 uebersteigt. Aus InDesign kommende PDFs liegen haeufig knapp darunter
     * und blieben dadurch unangetastet. Mit Schwelle 1,0 wird konsequent auf
     * die Zielaufloesung gerechnet.
     *
     * @throws PdfToolkitException
     */
    public function createDownloadVersion(string $sourceFile, string $targetFile, int $resolution = 120): string
    {
        $gs = $this->requireBinary('gs', 'Ghostscript');
        GeneralUtility::mkdir_deep(dirname($targetFile));

        $result = $this->run([
            $gs,
            '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=pdfwrite',
            '-dPDFSETTINGS=/ebook',
            '-dDownsampleColorImages=true',
            '-dDownsampleGrayImages=true',
            '-dColorImageDownsampleType=/Bicubic',
            '-dGrayImageDownsampleType=/Bicubic',
            '-dColorImageDownsampleThreshold=1.0',
            '-dGrayImageDownsampleThreshold=1.0',
            '-dColorImageResolution=' . $resolution,
            '-dGrayImageResolution=' . $resolution,
            '-dCompatibilityLevel=1.7',
            '-sOutputFile=' . $targetFile,
            $sourceFile,
        ], 600);

        if (!is_file($targetFile) || filesize($targetFile) === 0) {
            @unlink($targetFile);
            throw new PdfToolkitException(
                'Ghostscript konnte die Download-Fassung nicht erzeugen: '
                . $this->firstLine($result['stderr'] ?: $result['stdout'])
            );
        }

        return $targetFile;
    }

    /**
     * Rendert alle Seiten als JPEG in ein Verzeichnis.
     *
     * @return string[] Pfade der erzeugten Bilder, Seite 1 zuerst
     * @throws PdfToolkitException
     */
    public function renderAllPages(string $sourceFile, string $targetDirectory, int $width = 1240, int $quality = 80): array
    {
        $gs = $this->requireBinary('gs', 'Ghostscript');
        GeneralUtility::mkdir_deep($targetDirectory);
        // Aufloesung so waehlen, dass die gewuenschte Breite ungefaehr erreicht
        // wird (A4 hoch = 595 pt = 8,27 Zoll).
        $resolution = max(72, (int)round($width / 8.27));

        $this->run([
            $gs,
            '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=jpeg',
            '-dJPEGQ=' . $quality,
            '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4',
            '-r' . $resolution,
            '-sOutputFile=' . rtrim($targetDirectory, '/') . '/%d.jpg',
            $sourceFile,
        ], 900);

        $pages = glob(rtrim($targetDirectory, '/') . '/*.jpg') ?: [];
        if ($pages === []) {
            throw new PdfToolkitException('Die Seiten konnten nicht gerendert werden.');
        }
        usort($pages, static fn(string $a, string $b): int => (int)basename($a, '.jpg') <=> (int)basename($b, '.jpg'));

        return $pages;
    }

    /**
     * Liest den Text einer Seite - Grundlage fuer die Suche in der Ausgabe.
     */
    public function extractPageText(string $sourceFile, int $page): string
    {
        $gs = $this->locate('gs');
        if ($gs === null) {
            return '';
        }
        $target = $this->temporaryFile('text', 'txt');
        $this->run([
            $gs,
            '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=txtwrite',
            '-dFirstPage=' . $page, '-dLastPage=' . $page,
            '-sOutputFile=' . $target,
            $sourceFile,
        ], 120);

        $text = is_file($target) ? (string)file_get_contents($target) : '';
        @unlink($target);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Kann dieses System die Zeilenhoehen lesen? Dafuer braucht es pdftotext
     * aus dem poppler-Paket.
     */
    public function supportsLayoutExtraction(): bool
    {
        return $this->locate('pdftotext') !== null;
    }

    /**
     * Liefert die Zeilen einer Seite samt Lage und Hoehe.
     *
     * Die Zeilenhoehe entspricht der Schriftgroesse und ist das einzige
     * verlaessliche Merkmal, um Ueberschriften von Fliesstext zu trennen -
     * der reine Textauszug kennt sie nicht.
     *
     * @return list<array{text: string, height: float, y: float, xMin: float, xMax: float}>
     */
    public function extractPageLayout(string $sourceFile, int $page): array
    {
        $pdftotext = $this->locate('pdftotext');
        if ($pdftotext === null) {
            return [];
        }

        $result = $this->run([
            $pdftotext, '-bbox-layout',
            '-f', (string)$page, '-l', (string)$page,
            $sourceFile, '-',
        ], 120);
        if (trim($result['stdout']) === '') {
            return [];
        }

        // pdftotext gibt fuer manche Glyphen Steuerzeichen aus (etwa 0x08), die
        // in XML 1.0 unzulaessig sind - ohne sie zu entfernen scheitert das
        // Parsen der halben Ausgabe.
        $xml = preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $result['stdout']
        );
        if ($xml === null) {
            // Kein gueltiges UTF-8: dann wenigstens die ASCII-Steuerzeichen weg
            $xml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $result['stdout']) ?? '';
        }

        $doc = new \DOMDocument();
        $vorher = libxml_use_internal_errors(true);
        $geladen = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($vorher);
        if (!$geladen) {
            return [];
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('x', 'http://www.w3.org/1999/xhtml');

        $zeilen = [];
        foreach ($xpath->query('//x:line') as $zeile) {
            if (!$zeile instanceof \DOMElement) {
                continue;
            }
            $worte = [];
            foreach ($zeile->getElementsByTagName('word') as $wort) {
                $worte[] = $wort->textContent;
            }
            $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $worte)) ?? '');
            if ($text === '') {
                continue;
            }
            $zeilen[] = [
                'text' => $text,
                'height' => (float)$zeile->getAttribute('yMax') - (float)$zeile->getAttribute('yMin'),
                'y' => (float)$zeile->getAttribute('yMin'),
                'xMin' => (float)$zeile->getAttribute('xMin'),
                'xMax' => (float)$zeile->getAttribute('xMax'),
            ];
        }

        return $zeilen;
    }

    /**
     * Kann dieses System die Verweise einer Seite lesen? Dafuer braucht es
     * pdftohtml aus dem poppler-Paket.
     */
    public function supportsLinkExtraction(): bool
    {
        return $this->locate('pdftohtml') !== null;
    }

    /**
     * Liest die anklickbaren Verweise einer Seite.
     *
     * Die Lage wird auf 0 bis 1 umgerechnet, bezogen auf Breite und Hoehe der
     * PDF-Seite. So passt sie zu jedem Seitenbild, unabhaengig davon, mit
     * welcher Aufloesung es gerendert wurde.
     *
     * @return list<array{href: string, x: float, y: float, w: float, h: float}>
     */
    public function extractPageLinks(string $sourceFile, int $page): array
    {
        $pdftohtml = $this->locate('pdftohtml');
        if ($pdftohtml === null) {
            return [];
        }

        // -stdout statt eines Ausgabenamens: mit "-" als Name legt pdftohtml
        // Dateien im Arbeitsverzeichnis an, statt auf die Standardausgabe zu
        // schreiben. -i laesst die Bilder weg, das spart viel Zeit.
        $result = $this->run([
            $pdftohtml, '-xml', '-i', '-stdout',
            '-f', (string)$page, '-l', (string)$page,
            $sourceFile,
        ], 120);
        $xml = trim($result['stdout']);
        if ($xml === '') {
            return [];
        }

        $doc = new \DOMDocument();
        $vorher = libxml_use_internal_errors(true);
        $geladen = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($vorher);
        if (!$geladen) {
            return [];
        }

        $seite = $doc->getElementsByTagName('page')->item(0);
        if (!$seite instanceof \DOMElement) {
            return [];
        }
        $breite = (float)$seite->getAttribute('width');
        $hoehe = (float)$seite->getAttribute('height');
        if ($breite <= 0.0 || $hoehe <= 0.0) {
            return [];
        }

        $verweise = [];
        foreach ($doc->getElementsByTagName('a') as $a) {
            if (!$a instanceof \DOMElement) {
                continue;
            }
            $ziel = trim($a->getAttribute('href'));
            if ($ziel === '' || !preg_match('#^(https?://|mailto:)#i', $ziel)) {
                continue;
            }
            // Die Lage steht am umgebenden <text>, nicht am Verweis selbst
            $text = $a->parentNode;
            if (!$text instanceof \DOMElement || $text->nodeName !== 'text') {
                continue;
            }
            $verweise[] = [
                'href' => $ziel,
                'x' => round((float)$text->getAttribute('left') / $breite, 5),
                'y' => round((float)$text->getAttribute('top') / $hoehe, 5),
                'w' => round((float)$text->getAttribute('width') / $breite, 5),
                'h' => round((float)$text->getAttribute('height') / $hoehe, 5),
            ];
        }

        return $this->mergeLinks($verweise);
    }

    /**
     * Fasst benachbarte Stuecke desselben Verweises zusammen.
     *
     * Ein ueber mehrere Zeilen umbrochener Verweis kommt als mehrere
     * Textstuecke an. Liegen zwei davon in derselben Zeile nebeneinander,
     * wird daraus eine Flaeche - sonst entstehen Luecken, die sich nicht
     * anklicken lassen.
     *
     * @param list<array{href: string, x: float, y: float, w: float, h: float}> $verweise
     * @return list<array{href: string, x: float, y: float, w: float, h: float}>
     */
    private function mergeLinks(array $verweise): array
    {
        $zusammen = [];
        foreach ($verweise as $verweis) {
            $angehaengt = false;
            foreach ($zusammen as $i => $vorhanden) {
                if ($vorhanden['href'] !== $verweis['href']) {
                    continue;
                }
                // gleiche Zeile: senkrechter Versatz kleiner als die halbe Hoehe
                if (abs($vorhanden['y'] - $verweis['y']) > $vorhanden['h'] / 2) {
                    continue;
                }
                $links = min($vorhanden['x'], $verweis['x']);
                $rechts = max($vorhanden['x'] + $vorhanden['w'], $verweis['x'] + $verweis['w']);
                // nicht ueber die halbe Seite hinweg verbinden
                if ($rechts - $links > 0.9) {
                    continue;
                }
                $zusammen[$i]['x'] = $links;
                $zusammen[$i]['w'] = round($rechts - $links, 5);
                $zusammen[$i]['h'] = max($vorhanden['h'], $verweis['h']);
                $angehaengt = true;
                break;
            }
            if (!$angehaengt) {
                $zusammen[] = $verweis;
            }
        }

        return array_values($zusammen);
    }

    /**
     * Legt ein Wasserzeichen fest in ein Seitenbild.
     *
     * Bewusst in das Bild hineingerechnet und nicht als Ebene im Betrachter:
     * Eine Ebene ist mit einem Rechtsklick weg, und das heruntergeladene oder
     * abfotografierte Seitenbild traegt sie ohnehin nicht. Wer ein
     * Wasserzeichen will, will es genau dort haben.
     *
     * @param array{text?: string, opacity?: int, size?: int, angle?: int, repeat?: bool, color?: string} $angaben
     */
    public function applyWatermark(string $image, array $angaben): bool
    {
        $text = trim((string)($angaben['text'] ?? ''));
        if ($text === '' || !is_file($image)) {
            return false;
        }
        $convert = $this->locate('magick') ?? $this->locate('convert');
        if ($convert === null) {
            return false;
        }

        $masse = @getimagesize($image) ?: [1240, 1754];
        $breite = max(200, (int)$masse[0]);
        $anteil = max(2, min(40, (int)($angaben['size'] ?? 8)));
        $schrift = (int)round($breite * $anteil / 100);
        $deckkraft = max(1, min(100, (int)($angaben['opacity'] ?? 15))) / 100;
        $winkel = max(-90, min(90, (int)($angaben['angle'] ?? -30)));
        $farbe = (string)($angaben['color'] ?? '#000000');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $farbe)) {
            $farbe = '#000000';
        }
        $r = hexdec(substr($farbe, 1, 2));
        $g = hexdec(substr($farbe, 3, 2));
        $b = hexdec(substr($farbe, 5, 2));
        $fuellung = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . round($deckkraft, 3) . ')';
        // Duenner Rand in der Gegenfarbe: Sonst verschwindet ein dunkles
        // Zeichen auf einer dunklen Seite und ein helles auf einer hellen.
        $hell = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 140;
        $rand = $hell ? 'rgba(0,0,0,' . round($deckkraft * 0.8, 3) . ')' : 'rgba(255,255,255,' . round($deckkraft * 0.8, 3) . ')';
        $randbreite = (string)max(1, (int)round($schrift / 40));

        if (!empty($angaben['repeat'])) {
            // Gekachelt: erst eine Kachel mit dem Text bauen, dann darueberlegen.
            $kachel = $image . '.kachel.png';
            $this->run([
                $convert, '-background', 'none', '-fill', $fuellung,
                '-stroke', $rand, '-strokewidth', $randbreite,
                '-pointsize', (string)max(10, (int)round($schrift / 2)),
                'label:' . $text . '   ',
                '-rotate', (string)$winkel,
                '-bordercolor', 'none', '-border', (string)max(10, (int)round($schrift / 3)),
                $kachel,
            ], 60);
            if (is_file($kachel)) {
                $this->run([
                    $convert, $image,
                    '(', $kachel, '-write', 'mpr:marke', '+delete', ')',
                    '-size', $masse[0] . 'x' . $masse[1], 'tile:mpr:marke',
                    '-composite', '-quality', '82', $image,
                ], 120);
                @unlink($kachel);
            }
        } else {
            $this->run([
                $convert, $image,
                '-gravity', 'center',
                '-fill', $fuellung,
                '-stroke', $rand, '-strokewidth', $randbreite,
                '-pointsize', (string)$schrift,
                '-annotate', ($winkel >= 0 ? '+' : '') . $winkel . 'x' . ($winkel >= 0 ? '+' : '') . $winkel, $text,
                '-quality', '82',
                $image,
            ], 120);
        }

        return true;
    }

    /**
     * Verkleinert ein Bild auf die angegebene Breite.
     */
    /**
     * Schneidet ein Bild in der Mitte in zwei Haelften.
     *
     * Gebraucht wird das fuer Doppelseiten: Wer die Fassung fuer den Druck
     * bekommt, hat je Blatt zwei Buchseiten nebeneinander - und nur in dieser
     * Fassung stehen die Seitenzahlen aussen, links auf der linken und rechts
     * auf der rechten Seite. Aus dem Bogen zwei Seiten zu machen ist deshalb
     * genauer, als die Einzelseiten-Fassung zu nehmen.
     *
     * @return bool ob beide Haelften entstanden sind
     */
    public function splitImage(string $sourceImage, string $leftImage, string $rightImage): bool
    {
        $convert = $this->locate('magick') ?? $this->locate('convert');
        if ($convert === null) {
            return false;
        }
        GeneralUtility::mkdir_deep(dirname($leftImage));
        $groesse = @getimagesize($sourceImage) ?: [0, 0];
        $breite = (int)$groesse[0];
        $hoehe = (int)$groesse[1];
        if ($breite < 2 || $hoehe < 1) {
            return false;
        }
        $halb = (int)floor($breite / 2);

        $this->run([$convert, $sourceImage, '-crop', $halb . 'x' . $hoehe . '+0+0', '+repage',
            '-quality', '85', '-strip', $leftImage], 120);
        $this->run([$convert, $sourceImage, '-crop', ($breite - $halb) . 'x' . $hoehe . '+' . $halb . '+0', '+repage',
            '-quality', '85', '-strip', $rightImage], 120);

        return is_file($leftImage) && is_file($rightImage);
    }

    public function createThumbnail(string $sourceImage, string $targetImage, int $width = 200): bool
    {
        $convert = $this->locate('magick') ?? $this->locate('convert');
        if ($convert === null) {
            return false;
        }
        GeneralUtility::mkdir_deep(dirname($targetImage));
        $this->run([$convert, $sourceImage, '-resize', $width . 'x', '-quality', '78', '-strip', $targetImage], 60);

        return is_file($targetImage);
    }

    private function requireBinary(string $binary, string $label): string
    {
        $path = $this->locate($binary);
        if ($path === null) {
            throw new PdfToolkitException($label . ' ist auf diesem Server nicht verfuegbar.');
        }

        return $path;
    }

    private function locate(string $binary): ?string
    {
        $path = CommandUtility::getCommand($binary);

        return is_string($path) && $path !== '' && $path !== '-1' ? $path : null;
    }

    /**
     * @param string[] $arguments
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function run(array $arguments, int $timeout = 120): array
    {
        // Das Argument-Array wird unveraendert uebergeben: proc_open startet
        // dann keine Shell, sodass weder Quoting noch die Locale eine Rolle
        // spielen. Mit escapeshellarg() gingen unter der Locale "C" die
        // Umlaut-Bytes in Dateinamen verloren.
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($arguments, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new PdfToolkitException('Der Aufruf "' . ($arguments[0] ?? '') . '" konnte nicht gestartet werden.');
        }

        $stdout = '';
        $stderr = '';
        $exitCode = -1;
        $deadline = time() + $timeout;
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        do {
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                // Nur dieser Aufruf kennt den Rueckgabewert: proc_close()
                // liefert danach -1, weil der Status hier schon abgeholt wurde.
                $exitCode = (int)$status['exitcode'];
                break;
            }
            if (time() > $deadline) {
                proc_terminate($process);
                throw new PdfToolkitException(
                    'Zeitueberschreitung nach ' . $timeout . ' Sekunden bei "'
                    . basename((string)($arguments[0] ?? '')) . '".'
                );
            }
            usleep(100000);
        } while (true);

        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return ['exitCode' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function temporaryFile(string $prefix, string $extension): string
    {
        $directory = Environment::getVarPath() . '/transient';
        GeneralUtility::mkdir_deep($directory);

        return $directory . '/flippdf-' . $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    }

    private function firstLine(string $text): string
    {
        $line = trim(strtok(trim($text), "\n") ?: '');

        return $line !== '' ? $line : 'keine naehere Angabe';
    }
}
