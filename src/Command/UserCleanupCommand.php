<?php

namespace App\Command;

use App\Command\Helper\DateIntervalHelper;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UserCleanupCommand extends Command {

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(string $name = null, EntityManagerInterface $entityManager) {
        parent::__construct($name);
        $this->entityManager = $entityManager;
    }

    protected function configure() {
        $this
            ->setName('app:user:cleanup')
            ->setDescription('Deletes or deactivated expired accounts after a given period of time')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Interval days', false)
            ->addOption('months', null, InputOption::VALUE_OPTIONAL, 'Interval month', false)
            ->addOption('years', null, InputOption::VALUE_OPTIONAL, 'Interval years', false)
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Skip question')
            ->addOption('minBalance', null, InputOption::VALUE_OPTIONAL, 'Minimum balance', false)
            ->addOption('maxBalance', null, InputOption::VALUE_OPTIONAL, 'Maximum balance', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $helper = $this->getHelper('question');

        $questions = [];
        $queryBuilder = $this->entityManager->createQueryBuilder();

        if ($input->getOption('delete')) {
            $questions[] = 'delete users';

            $queryBuilder->delete(User::class, 'u');
        } else {
            $questions[] = 'deactivate users';

            $queryBuilder
                ->update(User::class, 'u')
                ->set('u.active', 0);
        }

        if ($input->getOption('days') || $input->getOption('months') || $input->getOption('years')) {
            $dateTime = DateIntervalHelper::fromCommandInput($input)->getDateTime();
            $questions[] = sprintf("with last transaction before '%s'", $dateTime->format('Y-m-d H:i:s'));

            $queryBuilder
                ->where('u.updated <= :date')
                ->setParameter('date', $dateTime);
        }

        $minBalance = $input->getOption('minBalance');
        if ($minBalance !== false) {
            $questions[] = sprintf('a minimum balance of %d', $minBalance);
            $queryBuilder->setParameter('minBalance', $minBalance);

            if ($minBalance > 0) {
                $queryBuilder->andWhere('u.balance >= :minBalance');
            } else {
                $queryBuilder->andWhere('u.balance <= :minBalance');
            }
        }

        $maxBalance = $input->getOption('maxBalance');
        if ($maxBalance !== false) {
            $questions[] = sprintf('a maximum balance of %d', $maxBalance);

            $queryBuilder->setParameter('maxBalance', $maxBalance);

            if ($maxBalance > 0) {
                $queryBuilder->andWhere('u.balance <= :maxBalance');
            } else {
                $queryBuilder->andWhere('u.balance >= :maxBalance');
            }
        }

        if (!$minBalance && !$maxBalance) {
            $questions[] = 'with a balance of 0';
            $queryBuilder->andWhere('u.balance = 0');
        }


        $question = 'Do you want to ' . join(', ', array_slice($questions, 0, count($questions) - 1));
        $question .= ' and ' . $questions[count($questions) - 1] . ' [y/N]?';

        $skipQuestion = $input->getOption('confirm');
        if (!$skipQuestion) {
            $questions = new ConfirmationQuestion($question, false);

            if (!$helper->ask($input, $output, $questions)) {
                return;
            }
        }

        $queryBuilder->getQuery()->execute();
    }
}