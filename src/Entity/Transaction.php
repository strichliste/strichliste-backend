<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
#[ORM\HasLifecycleCallbacks]
class Transaction {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, fetch: 'EAGER', inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantity = null;

    #[ORM\ManyToOne(targetEntity: Article::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Article $article = null;

    #[ORM\OneToOne(targetEntity: Transaction::class, fetch: 'EAGER', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Transaction $recipientTransaction = null;

    #[ORM\OneToOne(targetEntity: Transaction::class, fetch: 'EAGER', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Transaction $senderTransaction = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'integer')]
    private ?int $amount = null;

    #[ORM\Column(type: 'boolean')]
    private bool $deleted = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created = null;

    function getId(): ?int {
        return $this->id;
    }

    function getUser(): User {
        return $this->user;
    }

    function setUser(User $user): self {
        $this->user = $user;

        return $this;
    }

    function getQuantity(): ?int {
        return $this->quantity;
    }

    function setQuantity(int $quantity): self {
        $this->quantity = $quantity;

        return $this;
    }

    function getArticle(): ?Article {
        return $this->article;
    }

    function setArticle(?Article $article): self {
        $this->article = $article;

        return $this;
    }

    function getRecipientTransaction(): ?self {
        return $this->recipientTransaction;
    }

    function setRecipientTransaction(?self $recipientTransaction): self {
        $this->recipientTransaction = $recipientTransaction;

        return $this;
    }

    function getSenderTransaction(): ?self {
        return $this->senderTransaction;
    }

    function setSenderTransaction(?self $senderTransaction): self {
        $this->senderTransaction = $senderTransaction;

        return $this;
    }

    function getComment(): ?string {
        return $this->comment;
    }

    function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    function getAmount(): int {
        return $this->amount;
    }

    function setAmount(int $amount): self {
        $this->amount = $amount;

        return $this;
    }

    function isDeleted(): ?bool {
        return $this->deleted;
    }

    function setDeleted(bool $deleted): self {
        $this->deleted = $deleted;

        return $this;
    }

    function getCreated(): ?\DateTimeInterface {
        return $this->created;
    }

    function setCreated(\DateTimeInterface $created): self {
        $this->created = $created;

        return $this;
    }

    #[ORM\PrePersist]
    function setHistoryColumnsOnPrePersist(PrePersistEventArgs $event) {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }
}
