<?php

namespace App\Serializer;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Service\TransactionService;

class TransactionSerializer {
    public function __construct(private readonly TransactionService $transactionService, private readonly UserSerializer $userSerializer, private readonly ArticleSerializer $articleSerializer) {}

    public function serialize(Transaction $transaction): array {
        $article = $transaction->getArticle();

        return [
            'id' => $transaction->getId(),
            'user' => $this->userSerializer->serialize($transaction->getUser()),
            'quantity' => $transaction->getQuantity(),
            'article' => $article instanceof Article ? $this->articleSerializer->serialize($article) : null,
            'sender' => $this->getUserOrNull($transaction->getSenderTransaction()),
            'recipient' => $this->getUserOrNull($transaction->getRecipientTransaction()),
            'comment' => $transaction->getComment(),
            'amount' => $transaction->getAmount(),
            'isDeleted' => $transaction->isDeleted(),
            'isDeletable' => $this->transactionService->isDeletable($transaction),
            'created' => $transaction->getCreated()->format('Y-m-d H:i:s'),
        ];
    }

    private function getUserOrNull(?Transaction $transaction): ?array {
        if (!$transaction instanceof Transaction) {
            return null;
        }

        return $this->userSerializer->serialize($transaction->getUser());
    }
}
