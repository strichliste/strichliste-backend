<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(targetEntity: Barcode::class, mappedBy: 'article', cascade: ['persist'], fetch: 'EAGER')]
    private Collection $barcodes;

    #[ORM\OneToMany(targetEntity: ArticleTag::class, mappedBy: 'article', cascade: ['persist', 'remove'], fetch: 'EAGER')]
    private Collection $articleTags;

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

    public function __construct()
    {
        $this->barcodes = new ArrayCollection();
        $this->articleTags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Barcode[]
     */
    public function getBarcodes(): array
    {
        return $this->barcodes->getValues();
    }

    public function addBarcode(Barcode $barcode): self
    {
        $barcode->setArticle($this);
        $this->barcodes[] = $barcode;

        return $this;
    }

    /**
     * @return Tag[]
     */
    public function getTags(): array
    {
        return array_map(function (ArticleTag $articleTag) {
            return $articleTag->getTag();
        }, $this->articleTags->getValues());
    }

    /**
     * @return ArticleTag[]
     */
    public function getArticleTags(): array
    {
        return $this->articleTags->getValues();
    }

    public function addTag(Tag $tag): self
    {
        $articleTag = new ArticleTag();
        $articleTag->setArticle($this);
        $articleTag->setTag($tag);

        $this->articleTags[] = $articleTag;

        return $this;
    }

    public function hasTag(Tag $tag): bool
    {
        foreach ($this->getTags() as $existingTag) {
            if ($tag->getId() === $existingTag->getId()) {
                return true;
            }
        }

        return false;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPrecursor(): ?self
    {
        return $this->precursor;
    }

    public function setPrecursor(?self $precursor): self
    {
        $this->precursor = $precursor;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getUsageCount(): ?int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): self
    {
        $this->usageCount = $usageCount;

        return $this;
    }

    public function incrementUsageCount()
    {
        ++$this->usageCount;
    }

    public function decrementUsageCount()
    {
        --$this->usageCount;
    }

    #[ORM\PrePersist]
    public function setHistoryColumnsOnPrePersist(PrePersistEventArgs $event)
    {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }
}
