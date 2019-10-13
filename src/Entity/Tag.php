<?php

namespace App\Entity;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TagRespository")
 * @ORM\HasLifecycleCallbacks()
 */
class Tag {

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article", fetch="EAGER", inversedBy="tags")
     * @ORM\JoinColumn(name="article_id", referencedColumnName="id")
     */
    private $article;

    /**
     * @ORM\Column(type="string", nullable=false)
     * @var string
     */
    private $tag = '';

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    private $created;

    function __construct(string $tag = '') {
        $this->tag = $tag;
    }

    function getId(): int {
        return $this->id;
    }

    function getTag(): string {
        return $this->tag;
    }

    function setTag(string $tag): self {
        $this->tag = $tag;

        return $this;
    }

    function getArticle(): Article {
        return $this->article;
    }

    function setArticle(Article $article): self {
        $this->article = $article;

        return $this;
    }

    function getCreated(): ?\DateTime {
        return $this->created;
    }

    function setCreated(\DateTime $created): self {
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
