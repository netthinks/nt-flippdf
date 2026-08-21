<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Event;

/**
 * Vor dem Bau einer Ausgabe.
 *
 * Hier laesst sich noch alles aendern, was den Lauf bestimmt - auch die
 * Kennung, unter der die Ausgabe abgelegt wird. Das Zusatzpaket haengt hier
 * etwa den Zufallsschluessel an.
 */
final class BeforeBuildEvent
{
    /**
     * @param array<string, mixed> $angaben
     */
    public function __construct(
        private readonly string $pdfDatei,
        private array $angaben,
    ) {}

    public function getPdfDatei(): string
    {
        return $this->pdfDatei;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAngaben(): array
    {
        return $this->angaben;
    }

    /**
     * @param array<string, mixed> $angaben
     */
    public function setAngaben(array $angaben): void
    {
        $this->angaben = $angaben;
    }
}
