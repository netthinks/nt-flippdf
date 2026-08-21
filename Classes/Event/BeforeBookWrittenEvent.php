<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Event;

/**
 * Bevor die Angabendatei der Ausgabe geschrieben wird.
 *
 * Die Angabendatei ist alles, was der Betrachter kennt: Seiten, Farben,
 * Beschriftungen, Bedienelemente. Was hier hineingeschrieben wird, wirkt sich
 * unmittelbar auf die fertige Ausgabe aus - das Zusatzpaket ergaenzt hier Logo,
 * Hintergrund, weitere Sprachen und den Zaehler.
 */
final class BeforeBookWrittenEvent
{
    /**
     * @param array<string, mixed> $buch
     * @param array<string, mixed> $angaben
     */
    public function __construct(
        private array $buch,
        private readonly array $angaben,
        private readonly string $verzeichnis,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getBuch(): array
    {
        return $this->buch;
    }

    /**
     * @param array<string, mixed> $buch
     */
    public function setBuch(array $buch): void
    {
        $this->buch = $buch;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAngaben(): array
    {
        return $this->angaben;
    }

    public function getVerzeichnis(): string
    {
        return $this->verzeichnis;
    }
}
