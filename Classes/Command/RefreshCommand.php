<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Command;

use Netthinks\NtFlippdf\Service\FlipbookBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Erneuert den Betrachter vorhandener Ausgaben, ohne die Seiten neu zu rendern.
 *
 * Jede Ausgabe traegt ihre eigene Kopie des Betrachters, damit sie unabhaengig
 * von der Website weiterlaeuft. Nach einer Aenderung am Betrachter holt dieser
 * Befehl die bestehenden Ausgaben nach.
 */
class RefreshCommand extends Command
{
    public function __construct(private readonly FlipbookBuilder $builder)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Erneuert den Betrachter vorhandener Ausgaben.')
            ->addArgument('kennung', InputArgument::OPTIONAL, 'Nur diese Ausgabe erneuern. Ohne Angabe werden alle erneuert.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kennung = (string)$input->getArgument('kennung');
        $ausgaben = $kennung !== '' ? [$kennung] : $this->builder->listIssues();

        if ($ausgaben === []) {
            $io->warning('Es gibt noch keine Ausgabe in ' . $this->builder->resolveBasePath());

            return Command::SUCCESS;
        }

        $erneuert = 0;
        foreach ($ausgaben as $ausgabe) {
            if ($this->builder->refreshViewer($ausgabe)) {
                $io->writeln('erneuert: ' . $ausgabe);
                $erneuert++;
            } else {
                $io->writeln('<comment>übersprungen (keine book.json): ' . $ausgabe . '</comment>');
            }
        }
        $io->success($erneuert . ' von ' . count($ausgaben) . ' Ausgabe(n) erneuert.');

        return Command::SUCCESS;
    }
}
