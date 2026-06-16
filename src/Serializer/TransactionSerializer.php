<?php

namespace App\Serializer;

use App\Dto\Api\Transaction as TransactionDto;
use App\Dto\Api\User as UserDto;
use App\Entity\Transaction;
use App\Service\TransactionService;

class TransactionSerializer
{
    public function __construct(private readonly TransactionService $transactionService, private readonly UserSerializer $userSerializer, private readonly ArticleSerializer $articleSerializer)
    {
    }

    public function serialize(Transaction $transaction): TransactionDto
    {
        $article = $transaction->getArticle();

        return new TransactionDto(
            id: (int) $transaction->getId(),
            user: $this->userSerializer->serialize($transaction->getUser()),
            quantity: $transaction->getQuantity(),
            article: $article ? $this->articleSerializer->serialize($article) : null,
            sender: $this->getUserOrNull($transaction->getSenderTransaction()),
            recipient: $this->getUserOrNull($transaction->getRecipientTransaction()),
            comment: $transaction->getComment(),
            amount: $transaction->getAmount(),
            isDeleted: (bool) $transaction->isDeleted(),
            isDeletable: $this->transactionService->isDeletable($transaction),
            created: $transaction->getCreated()->format('Y-m-d H:i:s'),
        );
    }

    private function getUserOrNull(?Transaction $transaction): ?UserDto
    {
        if (!$transaction) {
            return null;
        }

        return $this->userSerializer->serialize($transaction->getUser());
    }
}
