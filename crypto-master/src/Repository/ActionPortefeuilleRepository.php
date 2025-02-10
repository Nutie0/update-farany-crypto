<?php

namespace App\Repository;

use App\Entity\ActionPortefeuille;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActionPortefeuille>
 *
 * @method ActionPortefeuille|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActionPortefeuille|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActionPortefeuille[]    findAll()
 * @method ActionPortefeuille[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionPortefeuilleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionPortefeuille::class);
    }
}
