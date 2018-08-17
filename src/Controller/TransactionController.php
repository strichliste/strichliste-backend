<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TransactionController extends AbstractController {

    /**
     * @Route("/transaction", methods="GET")
     */
    public function list(Request $request, EntityManagerInterface $entityManager) {
        $limit = $request->request->get('limit');
        $offset = $request->request->get('offset');

        $transactions = $entityManager->getRepository(Transaction::class)->findAll($limit, $offset);

        return $this->json([
            'transactions' => $transactions
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction", methods="POST")
     */
    public function createUserTransactions($userId, Request $request, EntityManagerInterface $entityManager) {
        /**
         * @var User $user
         */
        $user = $entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        $transaction = new Transaction();
        $transaction->setUser($user);

        $amount = $request->request->get('amount');
        // TODO: Validate transaction boundaries
        $transaction->setAmount($amount);

        // TODO: User something like bignumber
        $user->setBalance($user->getBalance() + $amount);

        $comment = $request->request->get('comment');
        if ($comment) {
            $transaction->setComment($comment);
        }

        $articleId = $request->request->get('articleId');
        if ($articleId) {
            $article = $entityManager->getRepository(Article::class)->findOneBy(
                ['id' => $articleId, 'active' => true]);

            if (!$article) {
                throw new BadRequestHttpException(sprintf('Article id %d not found', $articleId));
            }

            $transaction->setArticle($article);
        }

        $entityManager->transactional(function () use ($entityManager, $user, $transaction) {
            $entityManager->persist($user);
            $entityManager->persist($transaction);
        });

        $entityManager->flush();

        return $this->json([
            'transaction' => $transaction,
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction", methods="GET")
     */
    public function getUserTransactions($userId, Request $request, EntityManagerInterface $entityManager) {
        $limit = $request->request->get('limit');
        $offset = $request->request->get('offset');

        $user = $entityManager->getRepository(User::class)->find($userId, $limit, $offset);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        $transactions = $entityManager->getRepository(Transaction::class)->findByUser($user);

        return $this->json([
            'transactions' => $transactions,
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction/{transactionId}", methods="GET")
     */
    public function getTransaction($userId, $transactionId, EntityManagerInterface $entityManager) {
        $user = $entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        $transaction = $entityManager->getRepository(Transaction::class)->findByUserAndId($user, $transactionId);

        if (!$transaction) {
            throw $this->createNotFoundException();
        }

        return $this->json([
            'transaction' => $transaction,
        ]);
    }
}
