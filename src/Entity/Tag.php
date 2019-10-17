<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
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
     * @ORM\Column(type="string", nullable=false)
     * @var string
     */
    private $tag = '';

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ArticleTag", fetch="EAGER", mappedBy="tag", cascade={"persist", "remove"})
     */
    private $articleTags;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    private $created;

    function __construct(string $tag = '') {
        $this->tag = $tag;
        $this->articleTags = new ArrayCollection();
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
