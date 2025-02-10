<?php

namespace App\Controller;

use App\Entity\ActionPortefeuille;
use App\Entity\HistoriqueTransactions;
use App\Entity\HistoriqueUtilisateur;
use App\Entity\Portefeuille;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/portefeuille')]
class PortefeuilleController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_portefeuille')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        $historique = $this->entityManager->getRepository(HistoriqueUtilisateur::class)
            ->findBy(
                ['portefeuille' => $portefeuille],
                ['idHistoriqueUtilisateur' => 'DESC']
            );

        return $this->render('portefeuille/index.html.twig', [
            'portefeuille' => $portefeuille,
            'historique' => $historique
        ]);
    }

    #[Route('/depot', name: 'app_portefeuille_depot', methods: ['POST'])]
    public function depot(Request $request): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                throw new \Exception('Utilisateur non connecté');
            }

            $montant = $request->request->get('montant');
            if (!$montant || !is_numeric($montant) || $montant <= 0) {
                throw new \Exception('Montant invalide');
            }

            $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
                ->findOneBy(['utilisateur' => $user]);

            if (!$portefeuille) {
                throw new \Exception('Portefeuille non trouvé');
            }

            // Récupérer l'action de dépôt (id = 1)
            $action = $this->entityManager->getRepository(ActionPortefeuille::class)
                ->find(1);

            // Créer l'historique sans modifier le solde
            $historique = new HistoriqueUtilisateur();
            $historique->setPortefeuille($portefeuille);
            $historique->setAction($action);
            $historique->setSomme((string)$montant);
            $historique->setDateHistorique(new \DateTime());
            $historique->setStatut('en_attente');

            $this->entityManager->persist($historique);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre demande de dépôt a été enregistrée et est en attente de validation par un administrateur');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_portefeuille');
    }

    #[Route('/retrait', name: 'app_portefeuille_retrait', methods: ['POST'])]
    public function retrait(Request $request): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                throw new \Exception('Utilisateur non connecté');
            }

            $montant = $request->request->get('montant');
            if (!$montant || !is_numeric($montant) || $montant <= 0) {
                throw new \Exception('Montant invalide');
            }

            $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
                ->findOneBy(['utilisateur' => $user]);

            if (!$portefeuille) {
                throw new \Exception('Portefeuille non trouvé');
            }

            // Vérifier si le solde serait suffisant (mais ne pas le modifier)
            if ($portefeuille->getSoldeUtilisateur() < $montant) {
                throw new \Exception('Solde insuffisant pour cette demande de retrait');
            }

            // Récupérer l'action de retrait (id = 2)
            $action = $this->entityManager->getRepository(ActionPortefeuille::class)
                ->find(2);

            // Créer l'historique sans modifier le solde
            $historique = new HistoriqueUtilisateur();
            $historique->setPortefeuille($portefeuille);
            $historique->setAction($action);
            $historique->setSomme((string)$montant);
            $historique->setDateHistorique(new \DateTime());
            $historique->setStatut('en_attente');

            $this->entityManager->persist($historique);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre demande de retrait a été enregistrée et est en attente de validation par un administrateur');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_portefeuille');
    }

    #[Route('/transaction', name: 'app_transaction', methods: ['POST'])]
    public function transaction(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                throw new \Exception('Utilisateur non connecté');
            }

            $data = json_decode($request->getContent(), true);
            $type = $data['type'] ?? null;
            $montant = $data['montant'] ?? null;

            if (!$type || !$montant || !is_numeric($montant) || $montant <= 0) {
                throw new \Exception('Données invalides');
            }

            $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
                ->findOneBy(['utilisateur' => $user]);

            if (!$portefeuille) {
                throw new \Exception('Portefeuille non trouvé');
            }

            // Récupérer l'action (1 pour dépôt, 2 pour retrait)
            $actionId = $type === 'depot' ? 1 : ($type === 'retrait' ? 2 : null);
            if ($actionId === null) {
                throw new \Exception('Type de transaction invalide');
            }
            
            $action = $this->entityManager->getRepository(ActionPortefeuille::class)
                ->find($actionId);

            if (!$action) {
                throw new \Exception('Type d\'action non trouvé');
            }

            // Vérifier le solde pour un retrait
            if ($actionId === 2 && $portefeuille->getSoldeUtilisateur() < $montant) {
                throw new \Exception('Solde insuffisant pour cette demande de retrait');
            }

            // Log les valeurs avant l'insertion
            $this->logger->info('Tentative de transaction', [
                'type' => $type,
                'montant' => $montant,
                'portefeuille_id' => $portefeuille->getId(),
                'action_id' => $action->getId()
            ]);

            // Créer l'historique sans modifier le solde
            $historique = new HistoriqueUtilisateur();
            $historique->setPortefeuille($portefeuille);
            $historique->setAction($action);
            $historique->setSomme((string)$montant);
            $historique->setDateHistorique(new \DateTime());
            $historique->setStatut('en_attente');

            $this->entityManager->persist($historique);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Votre demande a été enregistrée et est en attente de validation par un administrateur',
                'nouveau_solde' => (float)$portefeuille->getSoldeUtilisateur()
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la transaction', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/details', name: 'app_portefeuille_details')]
    public function details(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        // Configure the result set mapping
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('id_portefeuille_fille', 'idportefeuillefille');
        $rsm->addScalarResult('nom_crypto', 'nomCrypto');
        $rsm->addScalarResult('date_action', 'date_action');
        $rsm->addScalarResult('nbr_crypto', 'nbr_crypto');
        $rsm->addScalarResult('prix_achat', 'prix_achat');
        $rsm->addScalarResult('prix_actuel', 'prix_actuel');
        $rsm->addScalarResult('prix_total', 'prix_total');
        $rsm->addScalarResult('pourcentage_evolution', 'pourcentage_evolution');

        // Get portfolio positions from the view
        $positions = $this->entityManager->createNativeQuery(
            'SELECT * FROM vue_positions_achat WHERE nbr_crypto > 0 AND id_portefeuille = :portefeuille_id ORDER BY date_action DESC',
            $rsm
        )->setParameter('portefeuille_id', $portefeuille->getId())
         ->getResult();

        // Get transaction history
        $rsm2 = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm2->addScalarResult('date_action', 'dateAction');
        $rsm2->addScalarResult('nom_crypto', 'nomCrypto');
        $rsm2->addScalarResult('type_action', 'typeAction');
        $rsm2->addScalarResult('nbrcrypto', 'nbrcrypto');
        $rsm2->addScalarResult('prix', 'prix');
        $rsm2->addScalarResult('prixtotal', 'prixtotal');

        $transactions = $this->entityManager->createNativeQuery(
            'SELECT * FROM historique_transactions WHERE id_portefeuille = :id ORDER BY date_action DESC LIMIT 20',
            $rsm2
        )->setParameter('id', $portefeuille->getId())
         ->getResult();

        return $this->render('portefeuille/details.html.twig', [
            'portefeuille' => $portefeuille,
            'positions' => $positions,
            'transactions' => $transactions
        ]);
    }

    #[Route('/vendre', name: 'app_vendre_crypto', methods: ['POST'])]
    public function vendreCrypto(Request $request): JsonResponse
    {
        $idPortefeuilleFille = $request->request->get('idPortefeuilleFille');
        $nbrAVendre = $request->request->get('nbrAVendre');

        try {
            // Exécuter la fonction de vente et récupérer les détails
            $result = $this->entityManager->getConnection()->executeQuery(
                'SELECT * FROM effectuer_vente(:id, :nbr)',
                [
                    'id' => $idPortefeuilleFille,
                    'nbr' => $nbrAVendre
                ]
            )->fetchAssociative();

            return new JsonResponse([
                'success' => true,
                'details' => [
                    'quantite' => $result['quantite'],
                    'prix_unitaire' => number_format($result['prix_unitaire'], 2),
                    'prix_total' => number_format($result['prix_total'], 2),
                    'taux_commission' => number_format($result['taux_commission'], 2),
                    'montant_commission' => number_format($result['montant_commission'], 2),
                    'montant_final' => number_format($result['montant_final'], 2)
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/api/positions', name: 'app_portefeuille_positions')]
    public function getPositions(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        // Configure the result set mapping
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('id_portefeuille_fille', 'idportefeuillefille');
        $rsm->addScalarResult('nom_crypto', 'nomCrypto');
        $rsm->addScalarResult('date_action', 'date_action');
        $rsm->addScalarResult('nbr_crypto', 'nbr_crypto');
        $rsm->addScalarResult('prix_achat', 'prix_achat');
        $rsm->addScalarResult('prix_actuel', 'prix_actuel');
        $rsm->addScalarResult('prix_total', 'prix_total');
        $rsm->addScalarResult('pourcentage_evolution', 'pourcentage_evolution');

        // Get portfolio positions from the view
        $positions = $this->entityManager->createNativeQuery(
            'SELECT * FROM vue_positions_achat WHERE nbr_crypto > 0 AND id_portefeuille = :portefeuille_id ORDER BY date_action DESC',
            $rsm
        )->setParameter('portefeuille_id', $portefeuille->getId())
         ->getResult();

        return $this->json(['positions' => $positions]);
    }

    #[Route('/api/transactions', name: 'app_portefeuille_transactions')]
    public function getTransactions(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        // Get transaction history
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('date_action', 'dateAction');
        $rsm->addScalarResult('nom_crypto', 'nomCrypto');
        $rsm->addScalarResult('type_action', 'typeAction');
        $rsm->addScalarResult('nbrcrypto', 'nbrcrypto');
        $rsm->addScalarResult('prix', 'prix');
        $rsm->addScalarResult('prixtotal', 'prixtotal');

        $transactions = $this->entityManager->createNativeQuery(
            'SELECT * FROM historique_transactions WHERE id_portefeuille = :id ORDER BY date_action DESC LIMIT 20',
            $rsm
        )->setParameter('id', $portefeuille->getId())
         ->getResult();

        return $this->json(['transactions' => $transactions]);
    }
}
