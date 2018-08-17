<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class MetricsController extends AbstractController
{
    /**
     * @Route("/metrics", methods="GET")
     */
    public function metrics(EntityManagerInterface $entityManager)
    {
        return $this->json([
            'balance' => $this->getBalance($entityManager),
            'transactionCount' => $this->getTransactionCount($entityManager),
            'userCount' => $this->getUserCount($entityManager),
            'days' => $this->getTransactionsPerDay($entityManager)
        ]);
    }

    private function getBalance(EntityManagerInterface $entityManager) {
        return $entityManager->createQueryBuilder()
            ->select('SUM(u.balance) as balance')
            ->from(User::class, 'u')
            ->where('u.active = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getTransactionCount(EntityManagerInterface $entityManager): int {
        return $entityManager->createQueryBuilder()
            ->select('COUNT(t) as count')
            ->from(Transaction::class, 't')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getTransactionsPerDay(EntityManagerInterface $entityManager) {

        $stmt = $entityManager->getConnection()->prepare(
            "select 
                DATE(created) as date,
                COUNT(id) as count,
                COUNT(DISTINCT user_id) as distinctUsers,
                SUM(amount) as balance
             from 
                transactions
             group by DATE(created)");

        $stmt->execute();
        $entries = $stmt->fetchAll();

        $entries = array_map(function($entry) use ($entityManager) {
            $entry['positiveBalance'] = $entityManager
                ->createQueryBuilder()
                ->select('SUM(t.amount)')
                ->from(Transaction::class, 't')
                ->where('t.amount > 0')
                ->getQuery()
                ->getSingleScalarResult();

            $entry['negativeBalance'] = $entityManager
                ->createQueryBuilder()
                ->select('SUM(t.amount)')
                ->from(Transaction::class, 't')
                ->where('t.amount < 0')
                ->getQuery()
                ->getSingleScalarResult();

            $entry['balance'] = sprintf("%.2f", $entry['balance']);

            return $entry;

        }, $entries);

        return $entries;
    }

    private function getUserCount(EntityManagerInterface $entityManager): int {
        return $entityManager->createQueryBuilder()
            ->select('COUNT(u) as count')
            ->from(User::class, 'u')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
