<?php

namespace App\Repository;

use App\Entity\HistoriqueUtilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoriqueUtilisateur>
 *
 * @method HistoriqueUtilisateur|null find($id, $lockMode = null, $lockVersion = null)
 * @method HistoriqueUtilisateur|null findOneBy(array $criteria, array $orderBy = null)
 * @method HistoriqueUtilisateur[]    findAll()
 * @method HistoriqueUtilisateur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HistoriqueUtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueUtilisateur::class);
    }

    public function findByPortefeuilleOrderByDate($portefeuilleId)
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.portefeuille = :portefeuilleId')
            ->setParameter('portefeuilleId', $portefeuilleId)
            ->orderBy('h.dateHistorique', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTime $startDate, \DateTime $endDate)
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.dateHistorique BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('h.dateHistorique', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestByPortefeuille($portefeuilleId, $limit = 10)
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.portefeuille = :portefeuilleId')
            ->setParameter('portefeuilleId', $portefeuilleId)
            ->orderBy('h.dateHistorique', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getStatsByPeriod(\DateTime $startDate, \DateTime $endDate)
    {
        return $this->createQueryBuilder('h')
            ->select('h.actionPortefeuille.typeAction as type, COUNT(h.id) as count, SUM(h.somme) as total')
            ->andWhere('h.dateHistorique BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('h.actionPortefeuille.typeAction')
            ->getQuery()
            ->getResult();
    }
}
