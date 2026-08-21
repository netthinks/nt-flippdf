<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Event;

/**
 * Was aus den Formularen des Moduls in die Ausgabe wandert.
 *
 * Das Modul reicht seine eigenen Felder weiter und gibt dem Zusatzpaket die
 * Gelegenheit, die seinen dazuzulegen - beim Bauen wie beim Speichern.
 */
final class ModuleSaveEvent
{
    /**
     * @param array<string, mixed> $formular rohe Formulardaten
     * @param array<string, mixed> $angaben was an den Builder geht
     */
    public function __construct(
        private readonly string $bereich,
        private readonly string $kennung,
        private readonly array $formular,
        private array $angaben,
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
    public function getFormular(): array
    {
        return $this->formular;
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
