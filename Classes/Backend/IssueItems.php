<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Backend;

use Netthinks\NtFlippdf\Service\FlipbookBuilder;

/**
 * Fuellt die Auswahlliste im Inhaltselement mit den gebauten Ausgaben.
 *
 * Gelesen wird der Ablageort, nicht die Datenbank: massgeblich ist, was
 * tatsaechlich auf der Platte liegt.
 *
 * Die Klasse wird aus dem TCA heraus ueber ihren Namen aufgerufen. Damit sie
 * dabei ihre Abhaengigkeiten mitbekommt, ist sie in der Services.yaml als
 * public eingetragen - sonst baut TYPO3 sie ohne Konstruktorwerte.
 */
class IssueItems
{
    public function __construct(
        private readonly FlipbookBuilder $builder,
    ) {}

    public function populate(array &$configuration): void
    {
        foreach ($this->builder->listIssues() as $kennung) {
            $book = $this->builder->readBook($kennung);
            $titel = (string)($book['title'] ?? $kennung);
            $seiten = (int)($book['pageCount'] ?? 0);
            $configuration['items'][] = [
                'label' => $titel . ' (' . $kennung . ', ' . $seiten . ' Seiten)',
                'value' => $kennung,
            ];
        }
    }
}
