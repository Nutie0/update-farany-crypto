<?php

namespace App\Repository;

use App\Entity\Crypto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Crypto>
 *
 * @method Crypto|null find($id, $lockMode = null, $lockVersion = null)
 * @method Crypto|null findOneBy(array $criteria, array $orderBy = null)
 * @method Crypto[]    findAll()
 * @method Crypto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CryptoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Crypto::class);
    }

    /**
     * @return Crypto[]
     */
    public function findAllSorted(): array
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT c
            FROM App\Entity\Crypto c
            ORDER BY c.dateInjection DESC'
        );

        return $query->getResult();
    }
}
