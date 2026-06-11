<?php

namespace App\Serializer;

use App\Entity\Transaction;
use App\Service\TransactionService;

class TransactionSerializer
{
    private TransactionService $transactionService;

    private UserSerializer $userSerializer;

    private ArticleSerializer $articleSerializer;

    public function __construct(TransactionService $transactionService, UserSerializer $userSerializer, ArticleSerializer $articleSerializer)
    {
        $this->transactionService = $transactionService;
        $this->userSerializer = $userSerializer;
        $this->articleSerializer = $articleSerializer;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Transaction $transaction): array
    {
        $article = $transaction->getArticle();

        return [
            'id' => $transaction->getId(),
            'user' => $this->userSerializer->serialize($transaction->getUser()),
            'quantity' => $transaction->getQuantity(),
            'article' => $article ? $this->articleSerializer->serialize($article) : null,
            'sender' => $this->getUserOrNull($transaction->getSenderTransaction()),
            'recipient' => $this->getUserOrNull($transaction->getRecipientTransaction()),
            'comment' => $transaction->getComment(),
            'amount' => $transaction->getAmount(),
            'isDeleted' => $transaction->isDeleted(),
            'isDeletable' => $this->transactionService->isDeletable($transaction),
            'created' => $transaction->getCreated()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getUserOrNull(?Transaction $transaction): ?array
    {
        if (!$transaction) {
            return null;
        }

        return $this->userSerializer->serialize($transaction->getUser());
    }
}
