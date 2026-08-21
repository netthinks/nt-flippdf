<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Event;

/**
 * Zusaetzliche Spalten in der Liste der Ausgaben.
 *
 * Jede Spalte bringt ihre Ueberschrift und je Kennung einen Wert mit. Das
 * Zusatzpaket zeigt so die Zaehlerstaende, ohne dass das Basispaket sie kennt.
 */
final class ModuleColumnsEvent
{
    /** @var list<array{titel: string, werte: array<string, string>}> */
    private array $spalten = [];

    /**
     * @param list<string> $kennungen
     */
    public function __construct(private readonly array $kennungen) {}

    /**
     * @return list<string>
     */
    public function getKennungen(): array
    {
        return $this->kennungen;
    }

    /**
     * @param array<string, string|int> $werte je Kennung ein Wert
     */
    public function addSpalte(string $titel, array $werte): void
    {
        $this->spalten[] = [
            'titel' => $titel,
            'werte' => array_map(static fn($wert): string => (string)$wert, $werte),
        ];
    }

    /**
     * @return list<array{titel: string, werte: array<string, string>}>
     */
    public function getSpalten(): array
    {
        return $this->spalten;
    }
}
