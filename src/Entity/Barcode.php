<?php

namespace App\Entity;

use App\Repository\BarcodeRepository;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BarcodeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'barcode')]
#[ORM\UniqueConstraint(name: 'UNIQ_97AE026697AE0266', columns: ['barcode'])]
class Barcode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'barcodes', fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false)]
    private Article $article;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $created = null;

    public function __construct(
        #[ORM\Column(type: 'string', length: 32, nullable: false)]
        private string $barcode,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBarcode(): string
    {
        return $this->barcode;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function setArticle(Article $article): self
    {
        $this->article = $article;

        return $this;
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
