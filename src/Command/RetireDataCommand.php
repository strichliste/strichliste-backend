<?php

namespace App\Command;

use App\Command\Helper\DateIntervalHelper;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RetireDataCommand extends Command {

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
            ->setName('app:retire-data')
            ->setDescription('Deletes older data after a given period')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Interval days', false)
            ->addOption('months', null, InputOption::VALUE_OPTIONAL, 'Interval month', false)
            ->addOption('years', null, InputOption::VALUE_OPTIONAL, 'Interval years', false)
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Skips confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $helper = $this->getHelper('question');
        $dateTime = DateIntervalHelper::fromCommandInput($input)->getDateTime();

        $skipQuestion = $input->getOption('confirm');
        if (!$skipQuestion) {
            $question = new ConfirmationQuestion(sprintf("Delete all transactions before '%s'? [y/N]", $dateTime->format('Y-m-d H:i:s')), false);

            if (!$helper->ask($input, $output, $question)) {
                return;
            }
        }

        $this->entityManager
            ->createQueryBuilder()
            ->delete(Transaction::class, 't')
            ->where('t.created <= :date')
            ->setParameter('date', $dateTime)
            ->getQuery()
            ->execute();
    }
}