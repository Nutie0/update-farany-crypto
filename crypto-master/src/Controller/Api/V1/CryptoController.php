<?php

namespace App\Controller\Api\V1;

use App\Entity\Crypto;
use App\Entity\Portefeuille;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\CommissionService;
use App\Service\UserSyncService;
use Symfony\Component\HttpClient\HttpClient;

#[Route('/cryptos', name: 'api_v1_crypto_')]
class CryptoController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private CommissionService $commissionService;
    private UserSyncService $userSyncService;
    private string $userApiUrl;

    public function __construct(
        EntityManagerInterface $entityManager,
        CommissionService $commissionService,
        UserSyncService $userSyncService,
        string $userApiUrl = 'http://localhost:5280/api'
    ) {
        $this->entityManager = $entityManager;
        $this->commissionService = $commissionService;
        $this->userSyncService = $userSyncService;
        $this->userApiUrl = $userApiUrl;
    }

    private function validateToken(string $token): ?array
    {
        try {
            $utilisateur = $this->userSyncService->validateTokenAndGetUser($token);
            if (!$utilisateur) {
                error_log("Token invalide ou utilisateur non trouvé");
                return null;
            }

            // Décoder le token pour retourner les données
            $tokenParts = explode('.', $token);
            $payload = base64_decode(strtr($tokenParts[1], '-_', '+/'));
            $payloadData = json_decode($payload, true);

            error_log("Token validé avec succès pour l'utilisateur: " . $utilisateur->getEmail());
            return $payloadData;
        } catch (\Exception $e) {
            error_log("Erreur lors de la validation du token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Liste toutes les cryptomonnaies
     * 
     * @OA\Response(
     *     response=200,
     *     description="Retourne la liste des cryptomonnaies",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref=@Model(type=Crypto::class))
     *     )
     * )
     * @OA\Tag(name="Cryptomonnaies")
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $authHeader = $request->headers->get('Authorization');
            if (!$authHeader) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Authentification requise'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $token = str_replace('Bearer ', '', $authHeader);
            $utilisateur = $this->userSyncService->validateTokenAndGetUser($token);
            
            if (!$utilisateur) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token invalide ou expiré'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $conn = $this->entityManager->getConnection();
            
            $sql = "
                WITH LastPrices AS (
                    SELECT 
                        v.id_crypto,
                        v.prixevoluer as dernier_prix,
                        v.date_variation,
                        ROW_NUMBER() OVER (PARTITION BY v.id_crypto ORDER BY v.date_variation DESC) as rn
                    FROM variationcrypto v
                )
                SELECT 
                    c.*,
                    COALESCE(lp.dernier_prix, CAST(c.prixInitialeCrypto AS numeric)) as prix_actuel,
                    lp.date_variation as derniere_maj
                FROM crypto c
                LEFT JOIN LastPrices lp ON c.id_crypto = lp.id_crypto AND lp.rn = 1
                ORDER BY c.date_injection DESC
            ";

            $cryptos = $conn->executeQuery($sql)->fetchAllAssociative();

            return new JsonResponse([
                'success' => true,
                'data' => $cryptos
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur interne du serveur',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère les variations d'une cryptomonnaie
     * 
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="ID de la cryptomonnaie",
     *     required=true,
     *     @OA\Schema(type="integer")
     * )
     * @OA\Response(
     *     response=200,
     *     description="Retourne les variations de prix",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             type="object",
     *             @OA\Property(property="time", type="string"),
     *             @OA\Property(property="open", type="number"),
     *             @OA\Property(property="high", type="number"),
     *             @OA\Property(property="low", type="number"),
     *             @OA\Property(property="close", type="number")
     *         )
     *     )
     * )
     * @OA\Tag(name="Cryptomonnaies")
     */
    #[Route('/{id}/variations', name: 'variations', methods: ['GET'])]
    public function getVariations(Request $request, Crypto $crypto): JsonResponse
    {
        try {
            $authHeader = $request->headers->get('Authorization');
            if (!$authHeader) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Authentification requise'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $token = str_replace('Bearer ', '', $authHeader);
            $utilisateur = $this->userSyncService->validateTokenAndGetUser($token);
            
            if (!$utilisateur) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token invalide ou expiré'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $conn = $this->entityManager->getConnection();
            $sql = "WITH grouped_data AS (
                        SELECT 
                            date_variation,
                            prixevoluer::numeric as price
                        FROM variationcrypto 
                        WHERE id_crypto = :cryptoId
                    ),
                    time_slots AS (
                        SELECT 
                            date_trunc('minute', date_variation) as exact_time,
                            EXTRACT(EPOCH FROM date_trunc('minute', date_variation)) as time_bucket
                        FROM grouped_data
                    ),
                    candles AS (
                        SELECT 
                            to_char(t.exact_time, 'YYYY-MM-DD HH24:MI:SS') as time,
                            FIRST_VALUE(g.price) OVER (PARTITION BY t.time_bucket ORDER BY g.date_variation) as open,
                            MAX(g.price) OVER (PARTITION BY t.time_bucket) as high,
                            MIN(g.price) OVER (PARTITION BY t.time_bucket) as low,
                            LAST_VALUE(g.price) OVER (PARTITION BY t.time_bucket ORDER BY g.date_variation) as close
                        FROM time_slots t
                        JOIN grouped_data g ON date_trunc('minute', g.date_variation) = t.exact_time
                    )
                    SELECT DISTINCT *
                    FROM candles
                    ORDER BY time;";

            $variations = $conn->executeQuery($sql, ['cryptoId' => $crypto->getIdCrypto()])->fetchAllAssociative();

            return new JsonResponse([
                'success' => true,
                'data' => $variations
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur interne du serveur',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Achète une cryptomonnaie
     * 
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *         required={"quantity", "price"},
     *         @OA\Property(property="quantity", type="number"),
     *         @OA\Property(property="price", type="number")
     *     )
     * )
     * @OA\Response(
     *     response=200,
     *     description="Achat effectué avec succès",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean"),
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="details", type="object")
     *     )
     * )
     * @OA\Tag(name="Cryptomonnaies")
     * @Security(name="Bearer")
     */
    #[Route('/{id}/buy', name: 'buy', methods: ['POST'])]
    public function buy(Request $request, Crypto $crypto): JsonResponse
    {
        try {
            $authHeader = $request->headers->get('Authorization');
            if (!$authHeader) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Authentification requise'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $token = str_replace('Bearer ', '', $authHeader);
            $utilisateur = $this->userSyncService->validateTokenAndGetUser($token);
            
            if (!$utilisateur) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token invalide ou expiré'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $data = json_decode($request->getContent(), true);
            $quantity = $data['quantity'] ?? null;
            $price = $data['price'] ?? null;

            if (!$quantity || !$price) {
                throw new \Exception('Quantité et prix requis');
            }

            $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
                ->findOneBy(['utilisateur' => $utilisateur]);

            if (!$portefeuille) {
                throw new \Exception('Portefeuille non trouvé');
            }

            $total = $quantity * $price;
            $commission = $this->commissionService->calculerCommission($total, 'achat');
            $totalWithCommission = $total + $commission;

            if ($portefeuille->getSoldeUtilisateur() < $totalWithCommission) {
                throw new \Exception('Solde insuffisant');
            }

            // Exécuter la procédure d'achat
            $conn = $this->entityManager->getConnection();
            $result = $conn->executeQuery(
                'SELECT * FROM effectuer_achat($1, $2, $3, $4)',
                [$portefeuille->getId(), $crypto->getIdCrypto(), $quantity, $price]
            )->fetchAssociative();

            return new JsonResponse([
                'success' => true,
                'message' => 'Achat effectué avec succès',
                'details' => [
                    'quantite' => $quantity,
                    'prix_unitaire' => $price,
                    'prix_total' => $total,
                    'commission' => $commission,
                    'montant_final' => $totalWithCommission
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'achat',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
