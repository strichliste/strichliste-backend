<?php

namespace App\Entity;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BarcodeRespository")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Table(name="barcode", uniqueConstraints={
 *     @UniqueConstraint(name="barcode", columns={"barcode"})
 * })
 */
class Barcode {

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32, nullable=false)
     */
    private $barcode;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article", fetch="EAGER", inversedBy="barcodes")
     * @ORM\JoinColumn(name="article_id", referencedColumnName="id")
     */
    private $article;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    private $created;

    function __construct(string $barcode = '') {
        $this->barcode = $barcode;
    }

    function getId(): int {
        return $this->id;
    }

    function getBarcode(): string {
        return $this->barcode;
    }

    function setBarcode(string $barcode): self {
        $this->barcode = $barcode;

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
