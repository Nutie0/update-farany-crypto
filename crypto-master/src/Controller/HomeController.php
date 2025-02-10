<?php

namespace App\Controller;

use App\Entity\Crypto;
use App\Entity\HistoriqueUtilisateur;
use App\Entity\Portefeuille;
use App\Entity\VariationCrypto;
use App\Repository\VariationCryptoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $entityManager, VariationCryptoRepository $variationRepo): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $data = [
            'user' => $user
        ];

        // Récupérer toutes les cryptos triées par date d'injection
        $cryptoRepo = $entityManager->getRepository(Crypto::class);
        $cryptos = $cryptoRepo->findAllSorted();

        // Pour chaque crypto, récupérer son dernier prix
        $conn = $entityManager->getConnection();
        foreach ($cryptos as $crypto) {
            $sql = "
                SELECT 
                    prixevoluer as prix_actuel,
                    date_variation as derniere_maj
                FROM variationcrypto
                WHERE id_crypto = :id_crypto
                ORDER BY date_variation DESC
                LIMIT 1
            ";
            
            $result = $conn->executeQuery($sql, ['id_crypto' => $crypto->getIdCrypto()])->fetchAssociative();
            
            if ($result) {
                $crypto->prix_actuel = $result['prix_actuel'];
                $crypto->derniere_maj = $result['derniere_maj'];
            } else {
                $crypto->prix_actuel = floatval($crypto->getPrixInitialeCrypto());
                $crypto->derniere_maj = null;
            }
        }

        // Récupérer le portefeuille de l'utilisateur
        $wallet = $entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        // Si l'utilisateur est admin, récupérer le nombre de transactions en attente
        if ($this->isGranted('ROLE_ADMIN')) {
            $transactionsCount = $entityManager->getRepository(HistoriqueUtilisateur::class)
                ->count(['statut' => 'en_attente']);
            $data['transactionsCount'] = $transactionsCount;
        }

        $data['cryptos'] = $cryptos;
        $data['wallet'] = $wallet;

        return $this->render('home/index.html.twig', $data);
    }
}
