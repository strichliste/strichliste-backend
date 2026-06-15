<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'tag')]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** @var Collection<int, ArticleTag> */
    #[ORM\OneToMany(targetEntity: ArticleTag::class, mappedBy: 'tag', cascade: ['persist', 'remove'], fetch: 'EAGER')]
    private Collection $articleTags;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $created = null;

    public function __construct(#[ORM\Column(type: 'string', nullable: false)]
        private string $tag = '')
    {
        $this->articleTags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function setTag(string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * @return Article[]
     */
    public function getArticles(): array
    {
        return array_map(fn (ArticleTag $articleTag) => $articleTag->getArticle(), $this->articleTags->getValues());
    }

    public function getUsageCount(): int
    {
        return count($this->articleTags);
    }

    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    public function setCreated(\DateTime $created): self
    {
        $this->created = $created;

        return $this;
    }

    #[ORM\PrePersist]
    public function setHistoryColumnsOnPrePersist(PrePersistEventArgs $event): void
    {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }
}
