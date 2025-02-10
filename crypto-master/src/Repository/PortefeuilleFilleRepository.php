<?php

namespace App\Repository;

use App\Entity\PortefeuilleFille;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PortefeuilleFille>
 *
 * @method PortefeuilleFille|null find($id, $lockMode = null, $lockVersion = null)
 * @method PortefeuilleFille|null findOneBy(array $criteria, array $orderBy = null)
 * @method PortefeuilleFille[]    findAll()
 * @method PortefeuilleFille[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PortefeuilleFilleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortefeuilleFille::class);
    }
}
