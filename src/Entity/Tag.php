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
class Tag {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $tag = '';

    #[ORM\OneToMany(targetEntity: ArticleTag::class, mappedBy: 'tag', cascade: ['persist', 'remove'], fetch: 'EAGER')]
    private Collection $articleTags;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $created = null;

    function __construct(string $tag = '') {
        $this->tag = $tag;
        $this->articleTags = new ArrayCollection();
    }

    function getId(): ?int {
        return $this->id;
    }

    function getTag(): string {
        return $this->tag;
    }

    function setTag(string $tag): self {
        $this->tag = $tag;

        return $this;
    }

    /**
     * @return Article[]
     */
    function getArticles(): array {
        return array_map(function(ArticleTag $articleTag) {
            return $articleTag->getArticle();
        }, $this->articleTags->getValues());
    }

    function getUsageCount(): int {
        return count($this->articleTags);
    }

    function getCreated(): ?\DateTime {
        return $this->created;
    }

    function setCreated(\DateTime $created): self {
        $this->created = $created;

        return $this;
    }

    #[ORM\PrePersist]
    function setHistoryColumnsOnPrePersist(PrePersistEventArgs $event) {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }
}
