<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Event;

/**
 * Nach dem Rendern der Seitenbilder, vor allem Weiteren.
 *
 * An dieser Stelle liegen die Bilder fertig im Arbeitsverzeichnis, aber es ist
 * noch nichts daraus abgeleitet - weder Vorschaubilder noch Text noch die
 * Angabendatei. Wer die Seiten veraendern oder ihre Zahl aendern will, tut es
 * hier: Das Zusatzpaket legt hier das Wasserzeichen hinein und teilt
 * Doppelseiten.
 *
 * Die Herkunft haelt fest, aus welcher Seite des PDF eine Buchseite stammt.
 * Ohne sie fielen Text und Verweise auseinander, sobald sich die Zahl der
 * Seiten aendert.
 */
final class AfterPagesRenderedEvent
{
    /**
     * @param list<string> $seitenBilder
     * @param list<int> $herkunft
     * @param list<bool> $geteilt
     * @param array<string, mixed> $angaben
     */
    public function __construct(
        private array $seitenBilder,
        private array $herkunft,
        private array $geteilt,
        private readonly array $angaben,
        private readonly string $pdfDatei,
        private readonly string $verzeichnis,
    ) {}

    /**
     * @return list<string>
     */
    public function getSeitenBilder(): array
    {
        return $this->seitenBilder;
    }

    /**
     * @param list<string> $seitenBilder
     * @param list<int> $herkunft
     * @param list<bool> $geteilt
     */
    public function setSeiten(array $seitenBilder, array $herkunft, array $geteilt): void
    {
        $this->seitenBilder = $seitenBilder;
        $this->herkunft = $herkunft;
        $this->geteilt = $geteilt;
    }

    /**
     * @return list<int>
     */
    public function getHerkunft(): array
    {
        return $this->herkunft;
    }

    /**
     * @return list<bool>
     */
    public function getGeteilt(): array
    {
        return $this->geteilt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAngaben(): array
    {
        return $this->angaben;
    }

    public function getPdfDatei(): string
    {
        return $this->pdfDatei;
    }

    /** Das Arbeitsverzeichnis, in dem die Ausgabe entsteht. */
    public function getVerzeichnis(): string
    {
        return $this->verzeichnis;
    }
}
