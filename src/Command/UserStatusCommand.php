<?php

namespace App\Command;

use App\Entity\User;
use App\Exception\UserNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserStatusCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:user:status')
            ->setDescription('Updates User status')
            ->addArgument('userId', InputArgument::REQUIRED, 'Id or name')
            ->addArgument('disable', InputArgument::REQUIRED, 'true or false');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = (string) $input->getArgument('userId');
        $disabled = 'true' === $input->getArgument('disable');

        $user = $this->entityManager->getRepository(User::class)->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $user->setDisabled($disabled);

        if ($disabled) {
            $output->writeln(sprintf("User '%s' (%d) has been activated", $user->getName(), $user->getId()));
        } else {
            $output->writeln(sprintf("User '%s' (%d) has been deactivated", $user->getName(), $user->getId()));
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
