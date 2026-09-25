<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Command;

use Forumify\Core\Repository\UserRepository;
use MajesticDev\Discord\Service\DiscordAccountLinker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'discord:members:link',
    description: 'Link a Discord id to an existing forum account, replacing an untouched imported placeholder.',
)]
class LinkMemberCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly DiscordAccountLinker $linker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The forum account to keep')
            ->addArgument('discord-id', InputArgument::REQUIRED, 'The Discord user id (snowflake)')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Actually link; without it nothing is written.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string)$input->getArgument('username');
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if ($user === null) {
            $io->error("No forum account named $username.");
            return Command::FAILURE;
        }

        $apply = (bool)$input->getOption('apply');
        $result = $this->linker->link($user, (string)$input->getArgument('discord-id'), $username, $apply);
        $io->writeln(($apply ? '' : '[dry run] ') . $result['message']);

        return $result['linked'] ? Command::SUCCESS : Command::FAILURE;
    }
}
