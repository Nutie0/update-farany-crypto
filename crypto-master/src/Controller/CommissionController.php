<?php

namespace App\Controller;

use App\Entity\Commission;
use App\Entity\Crypto;
use App\Entity\PortefeuilleFille;
use App\Repository\CommissionRepository;
use App\Repository\CryptoRepository;
use App\Service\CommissionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/commission')]
class CommissionController extends AbstractController
{
    private $entityManager;
    private $logger;
    private $commissionService;
    private $commissionRepository;
    private $cryptoRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        CommissionService $commissionService,
        CommissionRepository $commissionRepository,
        CryptoRepository $cryptoRepository
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->commissionService = $commissionService;
        $this->commissionRepository = $commissionRepository;
        $this->cryptoRepository = $cryptoRepository;
    }

    #[Route('/', name: 'app_commission_edit')]
    public function edit(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $tauxAchat = $request->request->get('commission_achat');
            $tauxVente = $request->request->get('commission_vente');
            
            if ($tauxAchat < 0 || $tauxAchat > 100 || $tauxVente < 0 || $tauxVente > 100) {
                $this->addFlash('danger', 'Les taux doivent être entre 0 et 100%');
                return $this->redirectToRoute('app_commission_edit');
            }
            
            $commission = new Commission();
            $commission->setTauxAchat((string)$tauxAchat);
            $commission->setTauxVente((string)$tauxVente);
            $commission->setDateModification(new \DateTime());
            
            $this->entityManager->persist($commission);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Les commissions ont été mises à jour');
            return $this->redirectToRoute('app_commission_edit');
        }

        $currentCommission = $this->entityManager->getRepository(Commission::class)
            ->getCurrentCommission();

        return $this->render('commission/edit.html.twig', [
            'commissions' => [
                'achat' => $currentCommission ? $currentCommission->getTauxAchat() : '2.5',
                'vente' => $currentCommission ? $currentCommission->getTauxVente() : '1.5'
            ]
        ]);
    }

    #[Route('/analyse', name: 'app_commission_analyse', methods: ['GET', 'POST'])]
    public function analyse(Request $request, CommissionRepository $commissionRepository, CryptoRepository $cryptoRepository): Response
    {
        $cryptos = $cryptoRepository->findAll();
        $data = null;
        
        // Valeurs par défaut
        $dateMin = $request->query->get('date_min', (new \DateTime())->modify('-1 month')->format('Y-m-d\TH:i'));
        $dateMax = $request->query->get('date_max', (new \DateTime())->format('Y-m-d\TH:i'));
        $crypto = $request->query->get('crypto', 'tous');
        $typeAnalyse = $request->query->get('type_analyse', 'somme');
        
        if ($request->isMethod('POST')) {
            $dateMin = $request->request->get('date_min');
            $dateMax = $request->request->get('date_max');
            $crypto = $request->request->get('crypto', 'tous');
            $typeAnalyse = $request->request->get('type_analyse', 'somme');
            
            // Rediriger vers la même page avec les paramètres en GET
            return $this->redirectToRoute('app_commission_analyse', [
                'date_min' => $dateMin,
                'date_max' => $dateMax,
                'crypto' => $crypto,
                'type_analyse' => $typeAnalyse
            ]);
        }
        
        // Traiter les données seulement pour les requêtes GET avec des paramètres
        if ($request->query->has('date_min')) {
            try {
                $dateMinObj = new \DateTime($dateMin);
                $dateMaxObj = new \DateTime($dateMax);
                
                $data = $commissionRepository->analyserCommissions(
                    $dateMinObj->format('Y-m-d H:i:s'),
                    $dateMaxObj->format('Y-m-d H:i:s'),
                    $crypto,
                    $typeAnalyse
                );
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'analyse des commissions : ' . $e->getMessage());
            }
        }

        return $this->render('commission/analyse.html.twig', [
            'cryptos' => $cryptos,
            'data' => $data,
            'type_analyse' => $typeAnalyse,
            'date_min' => $dateMin,
            'date_max' => $dateMax,
            'crypto_selected' => $crypto
        ]);
    }

    private function getCryptos(): array
    {
        return $this->cryptoRepository->findAll();
    }
}
