<?php

namespace App\Repository;

use App\Entity\Commission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commission>
 *
 * @method Commission|null find($id, $lockMode = null, $lockVersion = null)
 * @method Commission|null findOneBy(array $criteria, array $orderBy = null)
 * @method Commission[]    findAll()
 * @method Commission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commission::class);
    }

    public function getCurrentCommission(): ?Commission
    {
        return $this->findOneBy([], ['dateModification' => 'DESC']);
    }

    public function analyserCommissions($dateMin, $dateMax, $crypto = 'tous', $typeAnalyse = 'somme')
    {
        $conn = $this->getEntityManager()->getConnection();

        $whereClause = "WHERE date_action BETWEEN :dateMin AND :dateMax";
        if ($crypto !== 'tous') {
            $whereClause .= " AND nom_crypto = :crypto";
        }

        // CTE de base pour les commissions
        $baseCTE = "WITH commission_actuelle AS (
            SELECT taux_achat, taux_vente 
            FROM commission 
            ORDER BY date_modification DESC 
            LIMIT 1
        ),
        commissions AS (
            SELECT 
                pf.montant_commission, 
                pf.taux_commission, 
                ap.type_action, 
                pf.date_action, 
                c.nom_crypto
            FROM portefeuille_fille pf
            JOIN crypto c ON c.id_crypto = pf.id_crypto
            JOIN action_portefeuille ap ON ap.id_action = pf.id_action
            " . str_replace('nom_crypto', 'c.nom_crypto', $whereClause) . "
            UNION ALL
            SELECT 
                CASE 
                    WHEN ht.type_action = 'achat' THEN ht.prixtotal * ca.taux_achat / 100
                    ELSE ht.prixtotal * ca.taux_vente / 100
                END as montant_commission,
                CASE 
                    WHEN ht.type_action = 'achat' THEN ca.taux_achat
                    ELSE ca.taux_vente
                END as taux_commission,
                ht.type_action, 
                ht.date_action, 
                ht.nom_crypto
            FROM historique_transactions ht
            CROSS JOIN commission_actuelle ca
            " . $whereClause . "
        )";

        // Données générales selon le type d'analyse
        if ($typeAnalyse === 'somme') {
            $sql = $baseCTE . "
            SELECT 
                COALESCE(SUM(montant_commission), 0) as total_commission,
                COUNT(*) as nb_transactions
            FROM commissions";
        } else {
            $sql = $baseCTE . "
            SELECT 
                COALESCE(AVG(montant_commission), 0) as commission_moyenne,
                COUNT(*) as nb_transactions
            FROM commissions";
        }

        $params = [
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ];

        if ($crypto !== 'tous') {
            $params['crypto'] = $crypto;
        }

        // Données générales
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);
        $data = $result->fetchAssociative();

        // Analyse par crypto
        $sqlCrypto = $baseCTE . "
        SELECT 
            nom_crypto as crypto,
            " . ($typeAnalyse === 'somme' ? "COALESCE(SUM(montant_commission), 0)" : "COALESCE(AVG(montant_commission), 0)") . " as valeur_commission,
            COUNT(*) as nb_transactions
        FROM commissions
        GROUP BY nom_crypto
        ORDER BY valeur_commission DESC";

        $stmt = $conn->prepare($sqlCrypto);
        $result = $stmt->executeQuery($params);
        $data['crypto_analysis'] = $result->fetchAllAssociative();

        // Évolution temporelle
        $sqlEvolution = $baseCTE . "
        SELECT 
            DATE(date_action) as date,
            " . ($typeAnalyse === 'somme' ? "
            COALESCE(SUM(CASE WHEN type_action = 'achat' THEN montant_commission ELSE 0 END), 0) as commission_achat,
            COALESCE(SUM(CASE WHEN type_action = 'vente' THEN montant_commission ELSE 0 END), 0) as commission_vente
            " : "
            COALESCE(AVG(CASE WHEN type_action = 'achat' THEN montant_commission ELSE NULL END), 0) as commission_achat,
            COALESCE(AVG(CASE WHEN type_action = 'vente' THEN montant_commission ELSE NULL END), 0) as commission_vente
            ") . "
        FROM commissions
        GROUP BY DATE(date_action)
        ORDER BY date";

        $stmt = $conn->prepare($sqlEvolution);
        $result = $stmt->executeQuery($params);
        $data['evolution_temporelle'] = $result->fetchAllAssociative();

        // Distribution des commissions avec CTE pour les tranches
        $sqlDistribution = $baseCTE . ", tranches AS (
            SELECT 
                CASE 
                    WHEN montant_commission < 10 THEN '0-10'
                    WHEN montant_commission < 50 THEN '10-50'
                    WHEN montant_commission < 100 THEN '50-100'
                    WHEN montant_commission < 500 THEN '100-500'
                    ELSE '500+'
                END as tranche,
                CASE 
                    WHEN montant_commission < 10 THEN 1
                    WHEN montant_commission < 50 THEN 2
                    WHEN montant_commission < 100 THEN 3
                    WHEN montant_commission < 500 THEN 4
                    ELSE 5
                END as ordre,
                montant_commission
            FROM commissions
        )
        SELECT 
            tranche,
            " . ($typeAnalyse === 'somme' ? "COUNT(*)" : "AVG(montant_commission)") . " as valeur
        FROM tranches
        GROUP BY tranche, ordre
        ORDER BY ordre";

        $stmt = $conn->prepare($sqlDistribution);
        $result = $stmt->executeQuery($params);
        $data['distribution'] = $result->fetchAllAssociative();

        return $data;
    }
}
