<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Event;

/**
 * Zusaetzliche Felder in den Formularen des Backend-Moduls.
 *
 * Das Zusatzpaket bringt eigene Einstellungen mit - Wasserzeichen, Logo,
 * Hintergrund, Vorschau. Statt das Modul dafuer zu erweitern, liefert es
 * fertige Abschnitte, die das Modul an der vorgesehenen Stelle ausgibt. So
 * bleibt das Basispaket ohne Kenntnis davon, was es nicht kann.
 *
 * Bereiche: "bauen" (neue Ausgabe) und "bearbeiten" (vorhandene Ausgabe).
 */
final class ModuleFormEvent
{
    /** @var list<string> */
    private array $abschnitte = [];

    /**
     * @param array<string, mixed> $buch Angaben der Ausgabe, im Bereich "bearbeiten"
     */
    public function __construct(
        private readonly string $bereich,
        private readonly string $kennung = '',
        private readonly array $buch = [],
    ) {}

    public function getBereich(): string
    {
        return $this->bereich;
    }

    public function getKennung(): string
    {
        return $this->kennung;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBuch(): array
    {
        return $this->buch;
    }

    public function addAbschnitt(string $html): void
    {
        $this->abschnitte[] = $html;
    }

    /**
     * @return list<string>
     */
    public function getAbschnitte(): array
    {
        return $this->abschnitte;
    }
}
