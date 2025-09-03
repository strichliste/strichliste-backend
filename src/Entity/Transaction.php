<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: "transactions")]
#[ORM\Entity(repositoryClass: "App\Repository\TransactionRepository")]
class Transaction {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\ManyToOne(targetEntity: "App\Entity\User", fetch: "EAGER", inversedBy: "transactions")]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    #[ORM\Column(type: "integer", nullable: true)]
    private $quantity = null;

    #[ORM\ManyToOne(targetEntity: "App\Entity\Article", fetch: "EAGER")]
    #[ORM\JoinColumn(nullable: true)]
    private $article = null;

    #[ORM\OneToOne(targetEntity: "App\Entity\Transaction", fetch: "EAGER", cascade: ["persist", "remove"])]
    #[ORM\JoinColumn(nullable: true, onDelete: "CASCADE")]
    private $recipientTransaction = null;

    #[ORM\OneToOne(targetEntity: "App\Entity\Transaction", fetch: "EAGER", cascade: ["persist", "remove"])]
    #[ORM\JoinColumn(nullable: true, onDelete: "CASCADE")]
    private $senderTransaction = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private $comment = null;

    #[ORM\Column(type: "integer")]
    private $amount;

    #[ORM\Column(type: "boolean")]
    private $deleted = false;

    #[ORM\Column(type: "datetime")]
    private $created;

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
    function setHistoryColumnsOnPrePersist(LifecycleEventArgs $event) {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }
}
