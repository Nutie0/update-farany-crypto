<?php

namespace App\Controller;

use App\Entity\Crypto;
use App\Entity\VariationCrypto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/analyse')]
class AnalyseController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_analyse', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $cryptoRepository = $this->entityManager->getRepository(Crypto::class);
        $cryptos = $cryptoRepository->findAll();

        if ($request->isMethod('POST')) {
            $typeAnalyse = $request->request->get('type_analyse');
            $dateMin = $request->request->get('date_min');
            $dateMax = $request->request->get('date_max');
            $selectedCryptos = $request->request->all('crypto');

            // Si "tous" est sélectionné, on prend tous les IDs des cryptos
            if (in_array('tous', $selectedCryptos)) {
                $selectedCryptos = array_map(function($crypto) {
                    return $crypto->getIdCrypto();
                }, $cryptos);
            }

            // Rediriger vers la page appropriée avec les paramètres
            return $this->redirectToRoute('app_analyse_type', [
                'type' => $typeAnalyse,
                'date_debut' => $dateMin,
                'date_fin' => $dateMax,
                'cryptos' => implode(',', $selectedCryptos)
            ]);
        }

        return $this->render('analyse/index.html.twig', [
            'cryptos' => $cryptos
        ]);
    }

    private function calculateQuartile(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $count = count($variations);
        $position = floor(($count + 1) / 4);
        
        return [
            'valeur' => $variations[$position]->getPrixEvoluer(),
            'date' => $variations[$position]->getDateVariation(),
            'nombre_variations' => $count,
            'pourcentage' => $variations[$position]->getPourcentageVariation()
        ];
    }

    private function calculateMax(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $maxVariation = end($variations);
        
        return [
            'valeur' => $maxVariation->getPrixEvoluer(),
            'date' => $maxVariation->getDateVariation(),
            'nombre_variations' => count($variations),
            'pourcentage' => $maxVariation->getPourcentageVariation()
        ];
    }

    private function calculateMin(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $minVariation = reset($variations);
        
        return [
            'valeur' => $minVariation->getPrixEvoluer(),
            'date' => $minVariation->getDateVariation(),
            'nombre_variations' => count($variations),
            'pourcentage' => $minVariation->getPourcentageVariation()
        ];
    }

    private function calculateMean(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $count = count($variations);
        $sum = 0;
        $sumPercentage = 0;
        $medianVariation = $variations[floor($count/2)];

        foreach ($variations as $variation) {
            $sum += $variation->getPrixEvoluer();
            $sumPercentage += $variation->getPourcentageVariation();
        }

        return [
            'valeur' => $sum / $count,
            'date' => $medianVariation->getDateVariation(),
            'nombre_variations' => $count,
            'pourcentage' => $sumPercentage / $count
        ];
    }

    private function calculateStdDev(array $variations): ?array
    {
        if (empty($variations)) {
            return null;
        }

        $count = count($variations);
        $mean = 0;
        $sumSquares = 0;
        $medianVariation = $variations[floor($count/2)];

        // Calculer la moyenne
        foreach ($variations as $variation) {
            $mean += $variation->getPrixEvoluer();
        }
        $mean = $mean / $count;

        // Calculer la somme des carrés des écarts
        foreach ($variations as $variation) {
            $diff = $variation->getPrixEvoluer() - $mean;
            $sumSquares += $diff * $diff;
        }

        // Calculer l'écart-type
        $stdDev = sqrt($sumSquares / $count);

        return [
            'valeur' => $stdDev,
            'date' => $medianVariation->getDateVariation(),
            'nombre_variations' => $count,
            'pourcentage' => null // Pas pertinent pour l'écart-type
        ];
    }

    private function getAnalyseTitle(string $type): string
    {
        return match($type) {
            'quartile' => 'Premier Quartile',
            'max' => 'Maximum',
            'min' => 'Minimum',
            'moyenne' => 'Moyenne',
            'ecart_type' => 'Écart-type',
            default => 'Analyse',
        };
    }

    #[Route('/{type}', name: 'app_analyse_type', methods: ['GET'])]
    public function analyze(Request $request, string $type): Response
    {
        if (!in_array($type, ['quartile', 'max', 'min', 'moyenne', 'ecart_type'])) {
            throw $this->createNotFoundException('Type d\'analyse non valide');
        }

        $cryptoRepository = $this->entityManager->getRepository(Crypto::class);
        $variationRepository = $this->entityManager->getRepository(VariationCrypto::class);
        
        $cryptos = $cryptoRepository->findAll();
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');
        $selectedCryptoIds = [];
        
        // Récupérer les IDs des cryptos sélectionnées
        $cryptosParam = $request->query->get('cryptos');
        if ($cryptosParam) {
            $selectedCryptoIds = explode(',', $cryptosParam);
        }

        $dateDebut = new \DateTime($dateDebut);
        $dateFin = new \DateTime($dateFin);
        
        // Tableau pour stocker les résultats de chaque crypto
        $cryptoResults = [];

        // Pour chaque crypto sélectionnée
        foreach ($selectedCryptoIds as $cryptoId) {
            // Récupérer les variations pour cette crypto
            $qb = $variationRepository->createQueryBuilder('v')
                ->where('v.dateVariation BETWEEN :debut AND :fin')
                ->andWhere('v.crypto = :crypto')
                ->setParameter('debut', $dateDebut)
                ->setParameter('fin', $dateFin)
                ->setParameter('crypto', $cryptoId)
                ->orderBy('v.prixEvoluer', 'ASC');

            $variations = $qb->getQuery()->getResult();
            
            // Calculer les statistiques pour cette crypto
            $result = match($type) {
                'quartile' => $this->calculateQuartile($variations),
                'max' => $this->calculateMax($variations),
                'min' => $this->calculateMin($variations),
                'moyenne' => $this->calculateMean($variations),
                'ecart_type' => $this->calculateStdDev($variations),
                default => null,
            };
            
            if ($result !== null) {
                $cryptoResults[$cryptoId] = $result;
            }
        }

        return $this->render('analyse/results.html.twig', [
            'cryptos' => $cryptos,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'selected_cryptos' => $selectedCryptoIds,
            'crypto_results' => $cryptoResults,
            'type_analyse' => $this->getAnalyseTitle($type)
        ]);
    }
}
