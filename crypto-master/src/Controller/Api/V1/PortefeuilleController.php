<?php

namespace App\Controller\Api\V1;

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
use App\Service\UserSyncService;

#[Route('/api/v1/portefeuille', name: 'api_v1_portefeuille_')]
class PortefeuilleController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private UserSyncService $userSyncService;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        UserSyncService $userSyncService
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->userSyncService = $userSyncService;
    }

    /**
     * Récupère les informations du portefeuille
     * 
     * @OA\Response(
     *     response=200,
     *     description="Retourne les informations du portefeuille",
     *     @OA\JsonContent(
     *         @OA\Property(property="solde", type="number"),
     *         @OA\Property(property="positions", type="array", @OA\Items(
     *             @OA\Property(property="crypto", type="string"),
     *             @OA\Property(property="quantite", type="number"),
     *             @OA\Property(property="prix_achat", type="number"),
     *             @OA\Property(property="prix_actuel", type="number"),
     *             @OA\Property(property="evolution", type="number")
     *         ))
     *     )
     * )
     * @OA\Tag(name="Portefeuille")
     * @Security(name="Bearer")
     */
    #[Route('', name: 'details', methods: ['GET'])]
    public function getDetails(Request $request): JsonResponse
    {
        // Valider le token et récupérer l'utilisateur local
        $token = str_replace('Bearer ', '', $request->headers->get('Authorization') ?? '');
        $utilisateur = $this->userSyncService->validateTokenAndGetUser($token);
        
        if (!$utilisateur) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $utilisateur]);

        if (!$portefeuille) {
            return $this->json(['error' => 'Portefeuille non trouvé'], Response::HTTP_NOT_FOUND);
        }

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

        return $this->json([
            'solde' => $portefeuille->getSoldeUtilisateur(),
            'positions' => $positions
        ]);
    }

    /**
     * Effectue un dépôt sur le portefeuille
     * 
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *         required={"montant"},
     *         @OA\Property(property="montant", type="number")
     *     )
     * )
     * @OA\Response(
     *     response=200,
     *     description="Dépôt enregistré avec succès"
     * )
     * @OA\Tag(name="Portefeuille")
     * @Security(name="Bearer")
     */
    #[Route('/depot', name: 'depot', methods: ['POST'])]
    public function depot(Request $request): JsonResponse
    {
        try {
            // Valider le token et récupérer l'utilisateur local
            $token = str_replace('Bearer ', '', $request->headers->get('Authorization') ?? '');
            $utilisateur = $this->userSyncService->validateTokenAndGetUser($token);
            
            if (!$utilisateur) {
                return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $data = json_decode($request->getContent(), true);
            $montant = $data['montant'] ?? null;

            if (!$montant || !is_numeric($montant) || $montant <= 0) {
                throw new \Exception('Montant invalide');
            }

            $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
                ->findOneBy(['utilisateur' => $utilisateur]);

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

            return $this->json([
                'success' => true,
                'message' => 'Votre demande de dépôt a été enregistrée et est en attente de validation'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Effectue un retrait du portefeuille
     * 
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *         required={"montant"},
     *         @OA\Property(property="montant", type="number")
     *     )
     * )
     * @OA\Response(
     *     response=200,
     *     description="Retrait enregistré avec succès"
     * )
     * @OA\Tag(name="Portefeuille")
     * @Security(name="Bearer")
     */
    #[Route('/retrait', name: 'retrait', methods: ['POST'])]
    public function retrait(Request $request): JsonResponse
    {
        try {
            // Valider le token et récupérer l'utilisateur local
            $token = str_replace('Bearer ', '', $request->headers->get('Authorization') ?? '');
            $utilisateur = $this->userSyncService->validateTokenAndGetUser($token);
            
            if (!$utilisateur) {
                return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $data = json_decode($request->getContent(), true);
            $montant = $data['montant'] ?? null;

            if (!$montant || !is_numeric($montant) || $montant <= 0) {
                throw new \Exception('Montant invalide');
            }

            $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
                ->findOneBy(['utilisateur' => $utilisateur]);

            if (!$portefeuille) {
                throw new \Exception('Portefeuille non trouvé');
            }

            if ($portefeuille->getSoldeUtilisateur() < $montant) {
                throw new \Exception('Solde insuffisant');
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

            return $this->json([
                'success' => true,
                'message' => 'Votre demande de retrait a été enregistrée et est en attente de validation'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
