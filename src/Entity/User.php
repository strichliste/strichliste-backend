<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: '`user`')]
#[ORM\Index(name: 'disabled_updated', columns: ['disabled', 'updated'])]
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $email;

    #[ORM\Column(type: 'integer')]
    private $balance = 0;

    #[ORM\Column(type: 'boolean')]
    private $disabled = false;

    #[ORM\Column(type: 'datetime')]
    private $created;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $updated;

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'user')]
    private $transactions;

    public function __construct() {
        $this->transactions = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string {
        return $this->email;
    }

    public function setEmail(?string $email): self {
        $this->email = $email;

        return $this;
    }

    public function getBalance() {
        return $this->balance;
    }

    public function setBalance($balance): self {
        $this->balance = $balance;

        return $this;
    }

    public function addBalance($amount): self {
        $this->balance += $amount;

        return $this;
    }

    public function isDisabled(): bool {
        return $this->disabled;
    }

    public function setDisabled(bool $disabled): self {
        $this->disabled = $disabled;

        return $this;
    }

    public function getCreated(): ?DateTimeInterface {
        return $this->created;
    }

    public function setCreated(DateTimeInterface $created): self {
        $this->created = $created;

        return $this;
    }

    public function getUpdated(): ?DateTimeInterface {
        return $this->updated;
    }

    public function setUpdated(?DateTimeInterface $updated): self {
        $this->updated = $updated;

        return $this;
    }

    /**
     * @return Collection|Transaction[]
     */
    public function getTransactions(): Collection {
        return $this->transactions;
    }

    #[ORM\PrePersist]
    public function setHistoryColumnsOnPrePersist(LifecycleEventArgs $event): void {
        if (!$this->getCreated() instanceof DateTimeInterface) {
            $this->setCreated(new DateTime());
        }
    }

    #[ORM\PreUpdate]
    public function setHistoryColumnsOnPreUpdate(PreUpdateEventArgs $event): void {
        if (!$event->hasChangedField('updated')) {
            $this->setUpdated(new DateTime());
        }
    }
}
