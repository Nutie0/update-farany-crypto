<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

class HistoriqueController extends AbstractController
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    #[Route('/historique', name: 'app_historique')]
    public function index(Request $request): Response
    {
        $idUtilisateur = $request->query->get('utilisateur');
        $idCrypto = $request->query->get('crypto');
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        // Récupérer la liste des utilisateurs pour le filtre
        $utilisateurs = $this->connection->fetchAllAssociative('
            SELECT DISTINCT u.id_utilisateur, u.email 
            FROM utilisateur u 
            JOIN portefeuille p ON u.id_utilisateur = p.id_utilisateur 
            JOIN historique_transactions ht ON p.id = ht.id_portefeuille
            ORDER BY u.email
        ');

        // Récupérer la liste des cryptos pour le filtre
        $cryptos = $this->connection->fetchAllAssociative('
            SELECT DISTINCT c.id_crypto, c.nom_crypto 
            FROM crypto c 
            JOIN historique_transactions ht ON c.id_crypto = ht.id_crypto
            ORDER BY c.nom_crypto
        ');

        // Appeler la fonction historique_transactions_utilisateur avec les filtres
        $sql = 'SELECT * FROM historique_transactions_utilisateur($1, $2, $3, $4)';
        $params = [
            $idUtilisateur ?: null,
            $idCrypto ?: null,
            $dateDebut ? new \DateTime($dateDebut) : null,
            $dateFin ? new \DateTime($dateFin) : null
        ];
        $types = [
            Types::INTEGER,
            Types::INTEGER,
            Types::DATETIME_MUTABLE,
            Types::DATETIME_MUTABLE
        ];
        
        $transactions = $this->connection->fetchAllAssociative($sql, $params, $types);

        return $this->render('historique/index.html.twig', [
            'transactions' => $transactions,
            'utilisateurs' => $utilisateurs,
            'cryptos' => $cryptos,
            'filtres' => [
                'utilisateur' => $idUtilisateur,
                'crypto' => $idCrypto,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ]
        ]);
    }
}
