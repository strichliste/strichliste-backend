<?php

namespace App\Entity;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="App\Repository\ArticleRepository")
 */
class Article implements \JsonSerializable {

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private $barcode = null;

    /**
     * @ORM\Column(type="integer")
     */
    private $amount;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Article")
     */
    private $precursor = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private $active = true;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @ORM\Column(type="integer")
     */
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

    public function getCreated(): ?\DateTimeInterface {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self {
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

    public function incrementUsageCount() {
        $this->usageCount++;
    }

    public function decrementUsageCount() {
        $this->usageCount--;
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
            'name' => $this->name,
            'barcode' => $this->barcode,
            'amount' => $this->amount,
            'active' => $this->active,
            'usageCount' => $this->usageCount,
            'precursor' => $this->precursor,
            'created' => $this->getCreated()->format('Y-m-d H:i:s')
        ];
    }
}
