<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: "`user`")]
#[ORM\Index(name: "disabled_updated", columns: ["disabled", "updated"])]
#[ORM\Entity(repositoryClass: "App\Repository\UserRepository")]
class User {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "string", length: 64, unique: true)]
    private $name;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private $email = null;

    #[ORM\Column(type: "integer")]
    private $balance = 0;

    #[ORM\Column(type: "boolean")]
    private $disabled = false;

    #[ORM\Column(type: "datetime")]
    private $created;

    #[ORM\Column(type: "datetime", nullable: true)]
    private $updated;

    #[ORM\OneToMany(targetEntity: "App\Entity\Transaction", mappedBy: "user")]
    private $transactions;

    function __construct() {
        $this->transactions = new ArrayCollection();
    }

    function getId(): ?int {
        return $this->id;
    }

    function getName(): ?string {
        return $this->name;
    }

    function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    function getEmail(): ?string {
        return $this->email;
    }

    function setEmail(?string $email): self {
        $this->email = $email;

        return $this;
    }

    function getBalance() {
        return $this->balance;
    }

    function setBalance($balance): self {
        $this->balance = $balance;

        return $this;
    }

    function addBalance($amount): self {
        $this->balance += $amount;

        return $this;
    }

    function isDisabled(): bool {
        return $this->disabled;
    }

    function setDisabled(bool $disabled): self {
        $this->disabled = $disabled;

        return $this;
    }

    function getCreated(): ?\DateTimeInterface {
        return $this->created;
    }

    function setCreated(\DateTimeInterface $created): self {
        $this->created = $created;

        return $this;
    }

    function getUpdated(): ?\DateTimeInterface {
        return $this->updated;
    }

    function setUpdated(?\DateTimeInterface $updated): self {
        $this->updated = $updated;

        return $this;
    }

    /**
     * @return Collection|Transaction[]
     */
    function getTransactions(): Collection {
        return $this->transactions;
    }

    #[ORM\PrePersist]
    function setHistoryColumnsOnPrePersist(LifecycleEventArgs $event) {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }

    #[ORM\PreUpdate]
    function setHistoryColumnsOnPreUpdate(PreUpdateEventArgs $event) {
        if (!$event->hasChangedField('updated')) {
            $this->setUpdated(new \DateTime());
        }
    }
}
