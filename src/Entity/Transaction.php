<?php

namespace App\Entity;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\HasLifecycleCallbacks()
 * @Orm\Table(name="transactions")
 * @ORM\Entity(repositoryClass="App\Repository\TransactionRepository")
 */
class Transaction implements \JsonSerializable {

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="transactions")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article")
     */
    private $article = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $comment = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    private $recipient = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    private $sender = null;


    /**
     * @ORM\Column(type="integer")
     */
    private $amount;

    /**
     * @ORM\Column(type="datetime")
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

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        $this->article = $article;

        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): self
    {
        $this->sender = $sender;

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

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'article' => $this->article,
            'sender' => $this->sender,
            'recipient' => $this->recipient,
            'comment' => $this->comment,
            'amount' => $this->amount,
            'created' => $this->getCreated()->format('Y-m-d H:i:s')
        ];
    }
}
