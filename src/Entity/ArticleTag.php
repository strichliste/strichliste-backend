<?php

namespace App\Entity;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *     uniqueConstraints={
 *          @ORM\UniqueConstraint(name="article_tag", columns={"article_id", "tag_id"})
 *     }
 * )
 * @ORM\HasLifecycleCallbacks()
 */
class ArticleTag {

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article", cascade={"persist"})
     */
    private $article;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Tag", cascade={"persist"})
     */
    private $tag;


    /**
     * @ORM\Column(type="datetime")
     */
    private $created;

    function getId(): int {
        return $this->id;
    }

    function getArticle(): Article {
        return $this->article;
    }

    function setArticle($article): self {
        $this->article = $article;

        return $this;
    }

    function getTag(): Tag {
        return $this->tag;
    }

    function setTag($tag): self {
        $this->tag = $tag;

        return $this;
    }

    function getCreated(): ?\DateTime {
        return $this->created;
    }

    function setCreated($created): self {
        $this->created = $created;

        return $this;
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
