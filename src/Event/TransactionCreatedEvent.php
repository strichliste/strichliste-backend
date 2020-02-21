<?php
/**
 * Created by PhpStorm.
 * User: flo
 * Date: 01.03.19
 * Time: 23:39
 */

namespace App\Event;


use App\Entity\Transaction;
use Symfony\Component\EventDispatcher\Event;

class TransactionCreatedEvent extends Event
{
    public const NAME = 'transaction.created';

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * TransactionCreatedEvent constructor.
     *
     * @param \App\Entity\Transaction $transaction
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * @return Transaction
     */
    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }
}
