<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Command;

use MajesticDev\Discord\Service\DiscordMemberImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'discord:members:import',
    description: 'Create forumify accounts for Discord members who have not logged in yet (dry run unless --apply).',
)]
class ImportMembersCommand extends Command
{
    public function __construct(private readonly DiscordMemberImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Actually create the accounts; without it nothing is written.')
            ->addOption(
                'match',
                null,
                InputOption::VALUE_NONE,
                'Link members whose Discord username exactly equals a forum username to that account.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool)$input->getOption('apply');
        $result = $this->importer->import($apply, (bool)$input->getOption('match'));

        foreach ($result['errors'] as $error) {
            $io->error($error);
        }

        $io->writeln(sprintf('Already linked: %d', $result['linked']));
        $io->writeln(sprintf('%s: %d', $apply ? 'Created' : 'Would create', count($result['created'])));
        if ($result['matched'] !== []) {
            $verb = $apply ? 'Linked' : 'Would link';
            $io->writeln(sprintf('%s to an existing account: %d', $verb, count($result['matched'])));
        }
        if ($result['possibleMatches'] !== []) {
            $io->warning('Skipped, name matches an existing forum account (they should log in with Discord to link it):');
            $io->listing(array_map(
                static fn (string $id, string $name) => "$name ($id)",
                array_keys($result['possibleMatches']),
                $result['possibleMatches'],
            ));
        }
        if (!$apply) {
            $io->note('Dry run. Re-run with --apply to create the accounts.');
        }

        return $result['errors'] === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
