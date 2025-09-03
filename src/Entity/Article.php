<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private $barcode;

    #[ORM\Column(type: 'integer')]
    private $amount;

    #[ORM\OneToOne(targetEntity: self::class)]
    private $precursor;

    #[ORM\Column(type: 'boolean')]
    private $active = true;

    #[ORM\Column(type: 'datetime')]
    private $created;

    #[ORM\Column(type: 'integer')]
    private $usageCount = 0;

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

    public function getBarcode(): ?string {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): self {
        $this->barcode = $barcode;

        return $this;
    }

    public function getAmount(): int {
        return $this->amount;
    }

    public function setAmount(int $amount): self {
        $this->amount = $amount;

        return $this;
    }

    public function getPrecursor(): ?self {
        return $this->precursor;
    }

    public function setPrecursor(?self $precursor): self {
        $this->precursor = $precursor;

        return $this;
    }

    public function isActive(): ?bool {
        return $this->active;
    }

    public function setActive(bool $active): self {
        $this->active = $active;

        return $this;
    }

    public function getCreated(): ?DateTimeInterface {
        return $this->created;
    }

    public function setCreated(DateTimeInterface $created): self {
        $this->created = $created;

        return $this;
    }

    public function getUsageCount(): ?int {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): self {
        $this->usageCount = $usageCount;

        return $this;
    }

    public function incrementUsageCount(): void {
        ++$this->usageCount;
    }

    public function decrementUsageCount(): void {
        --$this->usageCount;
    }

    #[ORM\PrePersist]
    public function setHistoryColumnsOnPrePersist(LifecycleEventArgs $event): void {
        if (!$this->getCreated() instanceof DateTimeInterface) {
            $this->setCreated(new DateTime());
        }
    }
}
