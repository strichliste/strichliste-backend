<?php

namespace App\Entity;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\HasLifecycleCallbacks()
 * @Orm\Table(name="transactions")
 * @ORM\Entity(repositoryClass="App\Repository\TransactionRepository")
 */
class Transaction {

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", fetch="EAGER", inversedBy="transactions")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="integer")
     */
    private $quantity = 1;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article", fetch="EAGER")
     * @ORM\JoinColumn(nullable=true)
     */
    private $article = null;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", fetch="EAGER", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true)
     * @var Transaction
     */
    private $recipientTransaction = null;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Transaction", fetch="EAGER", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true)
     * @var Transaction
     */
    private $senderTransaction = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment = null;

    /**
     * @ORM\Column(type="integer")
     */
    private $amount;

    /**
     * @ORM\Column(type="boolean")
     */
    private $deleted = false;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    private $created;

    public function getId(): ?int {
        return $this->id;
    }

    public function getUser(): User {
        return $this->user;
    }

    public function setUser(User $user): self {
        $this->user = $user;

        return $this;
    }

    public function getQuantity(): ?int {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self {
        $this->quantity = $quantity;

        return $this;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        $this->article = $article;

        return $this;
    }

    public function getRecipientTransaction(): ?self {
        return $this->recipientTransaction;
    }

    public function setRecipientTransaction(?self $recipientTransaction): self {
        $this->recipientTransaction = $recipientTransaction;

        return $this;
    }

    public function getSenderTransaction(): ?self {
        return $this->senderTransaction;
    }

    public function setSenderTransaction(?self $senderTransaction): self {
        $this->senderTransaction = $senderTransaction;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getAmount(): int {
        return $this->amount;
    }

    public function setAmount(int $amount): self {
        $this->amount = $amount;

        return $this;
    }

    public function isDeleted(): ?bool {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self {
        $this->deleted = $deleted;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self {
        $this->created = $created;

        return $this;
    }

    /**
     * @ORM\PrePersist()
     * @param LifecycleEventArgs $event
     */
    public function setHistoryColumnsOnPrePersist(LifecycleEventArgs $event) {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }
}
