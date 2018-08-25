<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(string $name = null, EntityManagerInterface $entityManager)
    {
        parent::__construct($name);
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->setName('app:import')
            ->setDescription('Import strichliste1 database')
            ->addArgument('database', InputArgument::REQUIRED, 'SQLite database file from strichliste 1');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $databaseFile = $input->getArgument('database');

        $config = new \Doctrine\DBAL\Configuration();
        $connection = DriverManager::getConnection([
            'url' => sprintf('sqlite:///%s', $databaseFile)
        ], $config);

        $connection->connect();

        $entityManager = $this->entityManager;

        $entityManager->createQueryBuilder()->delete(User::class)->getQuery()->execute();
        $entityManager->createQueryBuilder()->delete(Transaction::class)->getQuery()->execute();
        $entityManager->createQueryBuilder()->delete(Article::class)->getQuery()->execute();

        try {
            $stmt = $connection->query('select id, name, mailAddress, createDate from users');
        } catch (\Exception $e) {
            $stmt = $connection->query("select id, name, '' as mailAddress, createDate from users");
        }

        $stmt->execute();

        $userIdMapping = [];
        foreach($stmt->fetchAll() as $user) {
            $id = (int) $user['id'];

            $newUser = new User();
            $newUser->setName($user['name']);
            $newUser->setCreated(new \DateTime($user['createDate']));

            if ($user['mailAddress']) {
                $newUser->setEmail($user['mailAddress']);
            }

            $entityManager->persist($newUser);
            $entityManager->flush();

            $output->writeln(sprintf("Imported user '%s'", $newUser->getName()));

            $userIdMapping[$id] = $newUser;
        }

        try {
            $stmt = $connection->query('select userId, value, comment, createDate from transactions');
        } catch (\Exception $e) {
            $stmt = $connection->query("select userId, value, '' as comment, createDate from transactions");
        }
        
        $stmt->execute();
        $transactions = $stmt->fetchAll();

        foreach($transactions as $transaction) {

            $userId = (int) $transaction['userId'];
            $user = $userIdMapping[$userId];

            $newTransaction = new Transaction();
            $newTransaction->setUser($user);
            $newTransaction->setAmount((int) ($transaction['value'] * 100));
            $newTransaction->setCreated(new \DateTime($transaction['createDate']));

            if ($transaction['comment']) {
                $newTransaction->setComment($transaction['comment']);
            }

            $entityManager->persist($newTransaction);
        }

        $entityManager->flush();

        $output->writeln(sprintf("Imported %d transactions", count($transactions)));

        /**
         * @var User $user
         */
        foreach($userIdMapping as $user) {

            $amount = $entityManager->createQueryBuilder()
                ->select('SUM(t.amount)')
                ->from(Transaction::class, 't')
                ->where('t.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            $latestTransaction = $entityManager->createQueryBuilder()
                ->select('MAX(t.created)')
                ->from(Transaction::class, 't')
                ->where('t.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            if ($amount) {
                $user->setBalance($amount);

                $output->writeln(sprintf("Update balance of user '%s' to %.2f", $user->getName(), $amount / 100));

                if ($latestTransaction) {
                    $user->setUpdated(new \DateTime($latestTransaction));
                }

                $entityManager->persist($user);
            }
        }

        $output->writeln('Import done!');

        $entityManager->flush();
    }
}