<?php

namespace App\Command;

use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
            ->addArgument('interval', InputArgument::REQUIRED, 'See http://de.php.net/manual/en/datetime.formats.relative.php')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Skips confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $helper = $this->getHelper('question');

        $interval = $input->getArgument('interval');
        $skipQuestion = $input->getOption('confirm');

        $dateInterval = \DateInterval::createFromDateString($interval);

        $dateTime = new \DateTime();
        $dateTime->sub($dateInterval);

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