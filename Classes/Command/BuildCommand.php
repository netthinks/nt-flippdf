<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Command;

use Netthinks\NtFlippdf\Service\FlipbookBuilder;
use Netthinks\NtFlippdf\Service\PdfToolkit;
use Netthinks\NtFlippdf\Service\PdfToolkitException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Baut aus einem PDF eine blaetterbare Fassung.
 */
class BuildCommand extends Command
{
    public function __construct(
        private readonly FlipbookBuilder $builder,
        private readonly PdfToolkit $toolkit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Baut aus einem PDF eine blätterbare Fassung.')
            ->addArgument('pdf', InputArgument::REQUIRED, 'Pfad zur PDF-Datei')
            ->addArgument('kennung', InputArgument::REQUIRED, 'Kennung der Ausgabe, zugleich Verzeichnisname')
            ->addOption('titel', 't', InputOption::VALUE_REQUIRED, 'Titel über dem Betrachter', '')
            ->addOption('sprache', 'l', InputOption::VALUE_REQUIRED, 'Sprache des Betrachters (de, en, fr oder zh)', 'de')
            ->addOption('download', 'd', InputOption::VALUE_REQUIRED,
                'Adresse für den Download-Knopf. Ohne Angabe wird eine verkleinerte Fassung neben der Ausgabe abgelegt.', '')
            ->addOption('ohne-download', null, InputOption::VALUE_NONE, 'Keinen Download anbieten')
            ->addOption('farbe-leiste', null, InputOption::VALUE_REQUIRED, 'Farbe der Leisten, etwa #1f2933', '')
            ->addOption('farbe-akzent', null, InputOption::VALUE_REQUIRED, 'Akzentfarbe, etwa #ec6602', '')
            ->addOption('vorschau', null, InputOption::VALUE_REQUIRED,
                'Seiten in der Vorschau. Mehr als so viele kann ein Inhaltselement nie zeigen.', '')
            ->addOption('ohne-verweise', null, InputOption::VALUE_NONE, 'Verweise aus dem PDF nicht auslesen')
            ->addOption('ohne-zoom', null, InputOption::VALUE_NONE, 'Kein Vergrößern anbieten')
            ->addOption('blaetterdauer', null, InputOption::VALUE_REQUIRED, 'Dauer einer Blätterbewegung in Millisekunden', '')
            ->addOption('ohne-schatten', null, InputOption::VALUE_NONE, 'Beim Blättern keine Schatten zeichnen')
            ->addOption('vorschau-ausgabe', null, InputOption::VALUE_REQUIRED,
                'Zusätzlich eine eigene Vorschau-Ausgabe mit so vielen Seiten anlegen, ohne Download', '')
            ->addOption('vorschau-kennung', null, InputOption::VALUE_REQUIRED,
                'Kennung der Vorschau-Ausgabe. Ohne Angabe "<kennung>-vorschau"', '')
            ->addOption('zufallskennung', null, InputOption::VALUE_NONE,
                'Der Kennung einen Zufallsschlüssel anhängen, damit die Ausgabe nicht zu erraten ist')
            ->addOption('doppelseiten', null, InputOption::VALUE_NONE,
                'Doppelseiten in der Mitte teilen und als einzelne Seiten führen')
            ->addOption('schwester', null, InputOption::VALUE_REQUIRED,
                'Ausgabe in einer anderen Sprache, Form "en:kennung". Mehrfach mit Komma trennen.', '');
    }

    /**
     * "en:kennung,fr:kennung" in die Form bringen, die der Builder erwartet.
     *
     * @return list<array{language: string, slug: string}>
     */
    private function schwestern(string $eingabe): array
    {
        $liste = [];
        foreach (explode(',', $eingabe) as $stueck) {
            $stueck = trim($stueck);
            if ($stueck === '' || !str_contains($stueck, ':')) {
                continue;
            }
            [$sprache, $kennung] = explode(':', $stueck, 2);
            $liste[] = ['language' => trim($sprache), 'slug' => trim($kennung)];
        }

        return $liste;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $umgebung = $this->toolkit->checkEnvironment();
        if (!$umgebung['ok']) {
            $io->error('Es fehlen Werkzeuge auf diesem Server: ' . implode(', ', $umgebung['missing']));

            return Command::FAILURE;
        }

        $pdf = (string)$input->getArgument('pdf');
        if (!is_file($pdf)) {
            $io->error('Die Datei "' . $pdf . '" gibt es nicht.');

            return Command::FAILURE;
        }

        $kennung = (string)$input->getArgument('kennung');
        $titel = (string)$input->getOption('titel');

        $io->writeln('Baue "' . $kennung . '" aus ' . basename($pdf) . ' …');

        try {
            $ergebnis = $this->builder->build($pdf, [
                'slug' => $kennung,
                'title' => $titel !== '' ? $titel : $kennung,
                'language' => (string)$input->getOption('sprache'),
                'downloadUrl' => (string)$input->getOption('download'),
                'buildDownload' => !$input->getOption('ohne-download'),
                'theme' => [
                    'bar' => (string)$input->getOption('farbe-leiste'),
                    'accent' => (string)$input->getOption('farbe-akzent'),
                ],
                'flip' => array_filter([
                    'duration' => (string)$input->getOption('blaetterdauer'),
                    'shadows' => $input->getOption('ohne-schatten') ? false : null,
                ], static fn($wert): bool => $wert !== '' && $wert !== null),
                'extractLinks' => !$input->getOption('ohne-verweise'),
                'zoom' => !$input->getOption('ohne-zoom'),
                'teaserPages' => (string)$input->getOption('vorschau') !== ''
                    ? (int)$input->getOption('vorschau')
                    : null,
                'siblings' => $this->schwestern((string)$input->getOption('schwester')),
                'previewPages' => (int)$input->getOption('vorschau-ausgabe'),
                'previewSlug' => (string)$input->getOption('vorschau-kennung'),
                'randomSuffix' => (bool)$input->getOption('zufallskennung'),
                'splitSpreads' => (bool)$input->getOption('doppelseiten'),
            ]);
        } catch (PdfToolkitException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%d Seiten, %s MB im Verzeichnis %s',
            $ergebnis['pages'],
            number_format($ergebnis['bytes'] / 1048576, 1, ',', '.'),
            $ergebnis['path']
        ));
        $io->writeln('Adresse: ' . $ergebnis['url']);
        if ($ergebnis['downloadUrl'] !== '') {
            $io->writeln('Download: ' . $ergebnis['downloadUrl']);
        }
        if (($ergebnis['preview'] ?? null) !== null) {
            $io->writeln(sprintf(
                'Vorschau: %s (%d Seiten, ohne Download)',
                $ergebnis['preview']['url'],
                $ergebnis['preview']['pages']
            ));
        }

        return Command::SUCCESS;
    }
}
