<?php

namespace App\Entity;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'article_tag')]
#[ORM\UniqueConstraint(name: 'UNIQ_article_tag', columns: ['article_id', 'tag_id'])]
class ArticleTag {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, cascade: ['persist'], inversedBy: 'articleTags')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Tag::class, cascade: ['persist'], fetch: 'EAGER', inversedBy: 'articleTags')]
    private ?Tag $tag = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $created = null;

    function getId(): ?int {
        return $this->id;
    }

    function getArticle(): Article {
        return $this->article;
    }

    function setArticle(Article $article): self {
        $this->article = $article;

        return $this;
    }

    function getTag(): Tag {
        return $this->tag;
    }

    function setTag(Tag $tag): self {
        $this->tag = $tag;

        return $this;
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
