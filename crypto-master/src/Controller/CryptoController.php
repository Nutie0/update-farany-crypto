<?php

namespace App\Controller;

use App\Entity\Crypto;
use App\Entity\Portefeuille;
use App\Entity\PortefeuilleFille;
use App\Entity\VariationCrypto;
use App\Entity\ActionPortefeuille;
use App\Entity\HistoriqueTransactions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\CommissionService;

#[Route('/crypto')]
class CryptoController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private CommissionService $commissionService;

    public function __construct(
        EntityManagerInterface $entityManager,
        CommissionService $commissionService
    ) {
        $this->entityManager = $entityManager;
        $this->commissionService = $commissionService;
    }

    #[Route('/', name: 'app_crypto_index', methods: ['GET'])]
    public function index(): Response
    {
        $conn = $this->entityManager->getConnection();
        
        // Requête pour obtenir les cryptos avec leurs derniers prix
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
                COALESCE(lp.dernier_prix, c.prixInitialeCrypto) as prix_actuel,
                lp.date_variation as derniere_maj
            FROM crypto c
            LEFT JOIN LastPrices lp ON c.id_crypto = lp.id_crypto AND lp.rn = 1
            ORDER BY c.dateInjection DESC
        ";

        $cryptos = $conn->executeQuery($sql)->fetchAllAssociative();

        return $this->render('crypto/index.html.twig', [
            'cryptos' => $cryptos
        ]);
    }

    #[Route('/new', name: 'app_crypto_new')]
    public function new(Request $request): Response
    {
        $crypto = new Crypto();
        
        $form = $this->createForm(CryptoType::class, $crypto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $crypto->setDateInjection(new \DateTime());
            $this->entityManager->persist($crypto);
            $this->entityManager->flush();

            $this->addFlash('success', 'Crypto ajoutée avec succès!');
            return $this->redirectToRoute('app_crypto_new');
        }

        return $this->render('crypto/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/variations', name: 'app_crypto_variations', methods: ['GET'])]
    public function getVariations(Crypto $crypto): JsonResponse
    {
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
                price_stats AS (
                    SELECT DISTINCT ON (t.time_bucket)
                        t.time_bucket,
                        to_timestamp(t.time_bucket) as interval_time,
                        FIRST_VALUE(g.price) OVER w as open_price,
                        MAX(g.price) OVER w as high_price,
                        MIN(g.price) OVER w as low_price,
                        LAST_VALUE(g.price) OVER w as close_price
                    FROM time_slots t
                    JOIN grouped_data g ON date_trunc('minute', g.date_variation) = t.exact_time
                    WINDOW w AS (PARTITION BY t.time_bucket ORDER BY g.date_variation
                               RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING)
                )
                SELECT 
                    to_char(interval_time, 'YYYY-MM-DD HH24:MI:SS') as time,
                    open_price as open,
                    high_price as high,
                    low_price as low,
                    close_price as close
                FROM price_stats
                ORDER BY interval_time ASC";
        
        $result = $conn->executeQuery($sql, ['cryptoId' => $crypto->getIdCrypto()]);
        $variations = $result->fetchAllAssociative();

        return $this->json($variations);
    }

    #[Route('/{id}', name: 'app_crypto_show', methods: ['GET'])]
    public function show(Crypto $crypto): Response
    {
        // Récupérer le dernier prix
        $conn = $this->entityManager->getConnection();
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
        $prixActuel = $result ? $result['prix_actuel'] : $crypto->getPrixInitialeCrypto();
        $derniereMaj = $result ? $result['derniere_maj'] : null;

        // Récupérer le taux de commission d'achat
        $tauxCommission = $this->commissionService->getTauxCommission('achat');

        return $this->render('crypto/details.html.twig', [
            'crypto' => $crypto,
            'prix_actuel' => $prixActuel,
            'derniere_maj' => $derniereMaj,
            'commission_rate' => $tauxCommission
        ]);
    }

    #[Route('/{id}/buy', name: 'app_crypto_buy', methods: ['POST'])]
    public function buyCrypto(Request $request, Crypto $crypto): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $quantity = $data['quantity'] ?? 0;
            $price = $data['price'] ?? 0;
            
            // Vérifier le prix actuel
            $conn = $this->entityManager->getConnection();
            $sql = "
                SELECT prixevoluer as prix_actuel
                FROM variationcrypto
                WHERE id_crypto = :id_crypto
                ORDER BY date_variation DESC
                LIMIT 1
            ";
            
            $result = $conn->executeQuery($sql, ['id_crypto' => $crypto->getIdCrypto()])->fetchAssociative();
            $prixActuel = $result ? $result['prix_actuel'] : $crypto->getPrixInitialeCrypto();
            
            if (abs($price - $prixActuel) > 0.01) {
                return $this->json([
                    'error' => 'Le prix a changé. Prix actuel: ' . $prixActuel . ' €',
                    'prix_actuel' => $prixActuel
                ], 400);
            }

            if ($quantity <= 0 || $price <= 0) {
                return $this->json(['error' => 'Quantité et prix invalides'], 400);
            }

            $totalPrice = $quantity * $price;
            
            // Calculer la commission
            $commission = $this->commissionService->calculateCommission('achat', $totalPrice);
            $tauxCommission = $this->commissionService->getTauxCommission('achat');
            
            // Calculer le prix unitaire avec commission
            $prixUnitaireAvecCommission = $price + ($price * $tauxCommission / 100);
            $totalWithCommission = $quantity * $prixUnitaireAvecCommission;

            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'Utilisateur non connecté'], 401);
            }

            $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
                ->findOneBy(['utilisateur' => $user]);

            if (!$portefeuille) {
                return $this->json(['error' => 'Portefeuille non trouvé'], 404);
            }

            if ($portefeuille->getSoldeUtilisateur() < $totalWithCommission) {
                return $this->json([
                    'error' => sprintf(
                        'Solde insuffisant (Total: %.2f€ + Commission: %.2f€)',
                        $totalPrice,
                        $commission
                    )
                ], 400);
            }

            $actionPortefeuille = $this->entityManager->getRepository(ActionPortefeuille::class)->find(4);
            if (!$actionPortefeuille) {
                return $this->json(['error' => 'Type d\'action non trouvé'], 400);
            }

            $portefeuilleFille = new PortefeuilleFille();
            $portefeuilleFille->setCrypto($crypto);
            $portefeuilleFille->setPortefeuille($portefeuille);
            $portefeuilleFille->setActionPortefeuille($actionPortefeuille);
            $portefeuilleFille->setDateAction(new \DateTime());
            $portefeuilleFille->setNbrCrypto($quantity);
            $portefeuilleFille->setPrixTotalCrypto((string)$totalPrice);
            $portefeuilleFille->setPrixAchat((string)$prixUnitaireAvecCommission); // Prix unitaire avec commission
            $portefeuilleFille->setMontantCommission((string)$commission);
            $portefeuilleFille->setTauxCommission((string)$tauxCommission);
            $portefeuilleFille->setPrixTotalAvecCommission((string)$totalWithCommission);

            // Insérer dans historique_transactions
            $historique = new HistoriqueTransactions();
            $historique->setIdPortefeuille($portefeuille->getId());
            $historique->setIdCrypto($crypto->getIdCrypto());
            $historique->setNomCrypto($crypto->getNomCrypto());
            $historique->setTypeAction('achat');
            $historique->setNbrcrypto((string)$quantity);
            $historique->setPrix((string)$price);
            $historique->setPrixtotal((string)$totalPrice);
            $historique->setTauxCommission((string)$tauxCommission);
            $historique->setMontantCommission((string)$commission);
            $historique->setPrixTotalAvecCommission((string)$totalWithCommission);
            $historique->setDateAction(new \DateTime());

            $newSolde = $portefeuille->getSoldeUtilisateur() - $totalWithCommission;
            $portefeuille->setSoldeUtilisateur((string)$newSolde);

            $this->entityManager->persist($portefeuilleFille);
            $this->entityManager->persist($historique);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Achat effectué avec succès',
                'newSolde' => $newSolde,
                'commission' => $commission,
                'total' => $totalWithCommission
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de l\'achat: ' . $e->getMessage()], 500);
        }
    }
}
