<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use DateTime;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command {
    public function __construct(private readonly EntityManagerInterface $entityManager) {
        parent::__construct();
        ini_set('memory_limit', '1024M');
    }

    protected function configure() {
        $this
            ->setName('app:import')
            ->setDescription('Import strichliste1 database')
            ->addArgument('database', InputArgument::REQUIRED, 'SQLite database file from strichliste 1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $databaseFile = $input->getArgument('database');

        $config = new Configuration();
        $connection = DriverManager::getConnection([
            'url' => \sprintf('sqlite:///%s', $databaseFile),
        ], $config);

        $connection->connect();

        $entityManager = $this->entityManager;

        $entityManager->createQueryBuilder()->delete(Transaction::class)->getQuery()->execute();
        $entityManager->createQueryBuilder()->delete(User::class)->getQuery()->execute();
        $entityManager->createQueryBuilder()->delete(Article::class)->getQuery()->execute();

        try {
            $stmt = $connection->query('select id, name, mailAddress, createDate from users');
        } catch (Exception) {
            $stmt = $connection->query("select id, name, '' as mailAddress, createDate from users");
        }

        $stmt->execute();

        $userMapping = [];
        foreach ($stmt as $user) {
            $id = (int) $user['id'];
            $name = $user['name'];

            // Just in case there is a dub from strichliste1, append id
            if ($entityManager->getRepository(User::class)->findByName($name)) {
                $output->writeln(\sprintf("WARNING: User '%s' (%d) has been renamed to '%s%02d' due to unique constrain", $name, $id, $name, $id));
                $name = \sprintf('%s%02d', $name, $id);
            }

            $newUser = new User();
            $newUser->setName($name);
            $newUser->setCreated(new DateTime($user['createDate']));

            if ($user['mailAddress']) {
                $newUser->setEmail($user['mailAddress']);
            }

            $entityManager->persist($newUser);
            $entityManager->flush();

            $output->writeln(\sprintf("Imported user '%s'", $newUser->getName()));

            $userMapping[$id] = $newUser;
        }

        try {
            $stmt = $connection->query('select t.userId, value, t.comment, t.createDate from transactions as t join users on users.id = t.userId');
        } catch (Exception) {
            $stmt = $connection->query("select t.userId, value, '' as comment, t.createDate from transactions as t join users on users.id = t.userId");
        }

        $stmt->execute();

        $count = 0;
        foreach ($stmt as $transaction) {
            $userId = (int) $transaction['userId'];
            $user = $userMapping[$userId];

            $newTransaction = new Transaction();
            $newTransaction->setUser($user);
            $newTransaction->setAmount((int) ($transaction['value'] * 100));
            $newTransaction->setCreated(new DateTime($transaction['createDate']));

            if ($transaction['comment']) {
                $newTransaction->setComment($transaction['comment']);
            }

            $entityManager->persist($newTransaction);
        }

        $entityManager->flush();
        $output->writeln(\sprintf('Imported %d transactions', $count));

        /**
         * @var User $user
         */
        foreach ($userMapping as $user) {
            $result = $entityManager->createQueryBuilder()
                ->select('SUM(t.amount) as amount, MAX(t.created) as latestTransaction')
                ->from(Transaction::class, 't')
                ->where('t.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleResult();

            $amount = $result['amount'];
            $latestTransaction = $result['latestTransaction'];
            if ($amount) {
                $user->setBalance($amount);

                $output->writeln(\sprintf("Update balance of user '%s' to %.2f", $user->getName(), $amount / 100));

                if ($latestTransaction) {
                    $user->setUpdated(new DateTime($latestTransaction));
                }

                $entityManager->persist($user);
            }
        }

        $entityManager->flush();
        $output->writeln('Import done!');

        return Command::SUCCESS;
    }
}
