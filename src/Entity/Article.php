<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Article {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(type: 'integer')]
    private ?int $amount = null;

    #[ORM\OneToOne(targetEntity: Article::class)]
    private ?Article $precursor = null;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created = null;

    #[ORM\Column(type: 'integer')]
    private int $usageCount = 0;

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

    function getBarcode(): ?string {
        return $this->barcode;
    }

    function setBarcode(?string $barcode): self {
        $this->barcode = $barcode;

        return $this;
    }

    function getAmount(): int {
        return $this->amount;
    }

    function setAmount(int $amount): self {
        $this->amount = $amount;

        return $this;
    }

    function getPrecursor(): ?self {
        return $this->precursor;
    }

    function setPrecursor(?self $precursor): self {
        $this->precursor = $precursor;

        return $this;
    }

    function isActive(): ?bool {
        return $this->active;
    }

    function setActive(bool $active): self {
        $this->active = $active;

        return $this;
    }

    function getCreated(): ?\DateTimeInterface {
        return $this->created;
    }

    function setCreated(\DateTimeInterface $created): self {
        $this->created = $created;

        return $this;
    }

    function getUsageCount(): ?int {
        return $this->usageCount;
    }

    function setUsageCount(int $usageCount): self {
        $this->usageCount = $usageCount;

        return $this;
    }

    function incrementUsageCount() {
        $this->usageCount++;
    }

    function decrementUsageCount() {
        $this->usageCount--;
    }

    #[ORM\PrePersist]
    function setHistoryColumnsOnPrePersist(PrePersistEventArgs $event) {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }
}
