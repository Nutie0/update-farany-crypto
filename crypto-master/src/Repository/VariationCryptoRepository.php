<?php

namespace App\Repository;

use App\Entity\VariationCrypto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VariationCrypto>
 *
 * @method VariationCrypto|null find($id, $lockMode = null, $lockVersion = null)
 * @method VariationCrypto|null findOneBy(array $criteria, array $orderBy = null)
 * @method VariationCrypto[]    findAll()
 * @method VariationCrypto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VariationCryptoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VariationCrypto::class);
    }

    public function save(VariationCrypto $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VariationCrypto $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findLastVariationForCrypto($crypto): ?VariationCrypto
    {
        return $this->createQueryBuilder('v')
            ->where('v.crypto = :crypto')
            ->setParameter('crypto', $crypto)
            ->orderBy('v.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
