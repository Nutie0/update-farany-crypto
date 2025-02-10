<?php

namespace App\Repository;

use App\Entity\Portefeuille;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PortefeuilleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Portefeuille::class);
    }

    public function findByUtilisateur($utilisateur)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
