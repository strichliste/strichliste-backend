<?php

namespace App\Repository;

use App\Entity\Barcode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Barcode>
 */
class BarcodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Barcode::class);
    }

    public function findByBarcode(string $barcode): ?Barcode
    {
        return $this->findOneBy(['barcode' => $barcode]);
    }
}
