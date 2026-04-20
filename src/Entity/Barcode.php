<?php

namespace App\Entity;

use App\Repository\BarcodeRepository;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BarcodeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'barcode')]
#[ORM\UniqueConstraint(name: 'UNIQ_barcode', columns: ['barcode'])]
class Barcode {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32, nullable: false)]
    private string $barcode;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'barcodes', fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false)]
    private Article $article;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $created = null;

    function __construct(string $barcode = '') {
        $this->barcode = $barcode;
    }

    function getId(): ?int {
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

    #[ORM\PrePersist]
    function setHistoryColumnsOnPrePersist(PrePersistEventArgs $event) {
        if (!$this->getCreated()) {
            $this->setCreated(new \DateTime());
        }
    }
}
