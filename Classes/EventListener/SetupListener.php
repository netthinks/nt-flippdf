<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\EventListener;

use Netthinks\NtFlippdf\Service\FlipbookBuilder;
use TYPO3\CMS\Core\Package\Event\AfterPackageActivationEvent;
use TYPO3\CMS\Core\Package\Event\PackageInitializationEvent;

/**
 * Zieht die gebauten Ausgaben nach, wenn die Extension eingerichtet wird.
 *
 * Eine Ausgabe traegt ihren Betrachter als Kopie in sich - das ist der Sinn
 * der Sache, sie laeuft dadurch unabhaengig von TYPO3 weiter. Der Preis: Ein
 * Update der Extension erreicht sie nicht von selbst. Wer `extension:setup`
 * fahrt - die meisten Deploys tun das -, bekommt sie hier mitgenommen.
 *
 * Gerendert wird dabei nichts: Nur Betrachter und Angaben werden neu
 * geschrieben, das dauert je Ausgabe Sekundenbruchteile.
 */
final class SetupListener
{
    public function __construct(private readonly FlipbookBuilder $builder) {}

    /**
     * TYPO3 13 und 14: `extension:setup` meldet sich fuer jede Extension.
     */
    public function beimEinrichten(PackageInitializationEvent $ereignis): void
    {
        $this->ziehNach($ereignis->getExtensionKey());
    }

    /**
     * TYPO3 12.4 kennt das Ereignis oben noch nicht; dort greift die
     * Aktivierung.
     */
    public function beimAktivieren(AfterPackageActivationEvent $ereignis): void
    {
        $this->ziehNach($ereignis->getPackageKey());
    }

    private function ziehNach(string $schluessel): void
    {
        if ($schluessel !== 'nt_flippdf') {
            return;
        }
        foreach ($this->builder->listIssues() as $kennung) {
            $this->builder->refreshViewer($kennung);
        }
    }
}
