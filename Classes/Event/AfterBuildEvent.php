<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Event;

/**
 * Nach dem Einsetzen der fertigen Ausgabe.
 *
 * Das Verzeichnis steht, die Ausgabe ist erreichbar. Hier laesst sich etwas
 * daneben ablegen - das Zusatzpaket baut an dieser Stelle die Vorschau-Ausgabe
 * aus den fertigen Seitenbildern.
 *
 * Was dem Ergebnis hinzugefuegt wird, erreicht den Aufrufer: die
 * Kommandozeile und das Backend-Modul zeigen es an.
 */
final class AfterBuildEvent
{
    /**
     * @param array<string, mixed> $buch
     * @param array<string, mixed> $angaben
     * @param array<string, mixed> $ergebnis
     * @param array<string, string> $texte
     */
    public function __construct(
        private readonly array $buch,
        private readonly array $angaben,
        private readonly array $texte,
        private readonly string $verzeichnis,
        private array $ergebnis,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getBuch(): array
    {
        return $this->buch;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAngaben(): array
    {
        return $this->angaben;
    }

    /**
     * Der Text jeder Seite, wie er in die Suche eingeht.
     *
     * @return array<string, string>
     */
    public function getTexte(): array
    {
        return $this->texte;
    }

    public function getVerzeichnis(): string
    {
        return $this->verzeichnis;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErgebnis(): array
    {
        return $this->ergebnis;
    }

    /**
     * @param array<string, mixed> $ergebnis
     */
    public function setErgebnis(array $ergebnis): void
    {
        $this->ergebnis = $ergebnis;
    }
}
