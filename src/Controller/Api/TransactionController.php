<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api")
 */
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

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        $article = null;
        $recipientUser = null;
        $recipientTransaction = null;

        // TODO: Validate transaction boundaries
        $amount = (int) $request->request->get('amount', 0);
        $comment = $request->request->get('comment');
        $articleId = $request->request->get('articleId');

        if ($articleId) {
            $article = $entityManager->getRepository(Article::class)->findOneBy(
                ['id' => $articleId, 'active' => true]);

            if (!$article) {
                throw new BadRequestHttpException(sprintf('Article id %d not found', $articleId));
            }

            $amount = $article->getAmount() * -1;
            $article->setUsageCount($article->getUsageCount() + 1);
        }

        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setAmount($amount);
        $transaction->setArticle($article);
        $transaction->setComment($comment);

        $recipientId = $request->request->get('recipientId');
        if ($recipientId) {
            $recipientUser = $entityManager->getRepository(User::class)->find($recipientId);
            if (!$recipientUser) {
                throw new BadRequestHttpException(sprintf('Recipient user id %d not found', $recipientId));
            }

            $recipientTransaction = new Transaction();
            $recipientTransaction->setAmount($amount * -1);
            $recipientTransaction->setArticle($article);
            $recipientTransaction->setComment($comment);
            $recipientTransaction->setUser($recipientUser);

            $recipientTransaction->setSender($user);
            $transaction->setRecipient($recipientUser);

            $recipientUser->setBalance($recipientUser->getBalance() + ($amount * -1));
        }

        $user->setBalance($user->getBalance() + $amount);

        $entityManager->transactional(function () use ($entityManager, $user, $transaction, $article, $recipientUser, $recipientTransaction) {
            $entityManager->persist($user);
            $entityManager->persist($transaction);

            if ($article) {
                $entityManager->persist($article);
            }

            if ($recipientUser) {
                $entityManager->persist($recipientUser);
            }

            if ($recipientTransaction) {
                $entityManager->persist($recipientTransaction);
            }
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
        $limit = $request->request->get('limit', 25);
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
