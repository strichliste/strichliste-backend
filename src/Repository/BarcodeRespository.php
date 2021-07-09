<?php

namespace App\Repository;

use App\Entity\Barcode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Barcode|null find($id, $lockMode = null, $lockVersion = null)
 * @method Barcode|null findOneBy(array $criteria, array $orderBy = null)
 * @method Barcode[]    findAll()
 * @method Barcode[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BarcodeRespository extends ServiceEntityRepository {

    function __construct(RegistryInterface $registry) {
        parent::__construct($registry, Barcode::class);
    }

    function findByBarcode(string $barcode): ?Barcode {
        return $this->findOneBy(['barcode' => $barcode]);
    }
}
