<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass="App\Repository\ArticleRepository")
 */
class Article {

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
     * @ORM\OneToMany(targetEntity="App\Entity\Barcode", fetch="EAGER", mappedBy="article", cascade={"persist"})
     */
    private $barcodes;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ArticleTag", fetch="EAGER", mappedBy="article", cascade={"persist", "remove"})
     */
    private $articleTags;

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

    function __construct() {
        $this->barcodes = new ArrayCollection();
        $this->articleTags = new ArrayCollection();
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

    /**
     * @return Barcode[]
     */
    function getBarcodes(): array {
        return $this->barcodes->getValues();
    }

    function addBarcode(Barcode $barcode): self {
        $barcode->setArticle($this);
        $this->barcodes[] = $barcode;

        return $this;
    }

    /**
     * @return Tag[]
     */
    function getTags(): array {
        return array_map(function(ArticleTag $articleTag) {
            return $articleTag->getTag();
        }, $this->articleTags->getValues());
    }

    /**
     * @return ArticleTag[]
     */
    function getArticleTags(): array {
        return $this->articleTags->getValues();
    }

    function addTag(Tag $tag): self {
        $articleTag = new ArticleTag();
        $articleTag->setArticle($this);
        $articleTag->setTag($tag);

        $this->articleTags[] = $articleTag;

        return $this;
    }

    function hasTag(Tag $tag): bool {
        foreach($this->getTags() as $existingTag) {
            if ($tag->getId() == $existingTag->getId()) {
                return true;
            }
        }

        return false;
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

    function isActivatable(): bool {
        if ($this->isActive()) {
            return false;
        }

        if ($this->getPrecursor()) {
            return false;
        }

        return true;
    }

    /**
     * @ORM\PrePersist()
     * @param LifecycleEventArgs $event
     */
    function setHistoryColumnsOnPrePersist(LifecycleEventArgs $event) {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }
}
