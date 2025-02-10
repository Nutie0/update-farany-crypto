<?php

namespace App\Controller\Api;

use App\Entity\Portefeuille;
use App\Service\ApiResponseFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/portefeuille', name: 'api_portefeuille_')]
class PortefeuilleController extends AbstractController
{
    private $entityManager;
    private $responseFormatter;

    public function __construct(
        EntityManagerInterface $entityManager,
        ApiResponseFormatter $responseFormatter
    ) {
        $this->entityManager = $entityManager;
        $this->responseFormatter = $responseFormatter;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): Response
    {
        $user = $this->getUser();
        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        if (!$portefeuille) {
            return $this->responseFormatter->formatError('Portefeuille not found', 404);
        }

        return $this->responseFormatter->formatResponse($portefeuille);
    }

    #[Route('/solde', name: 'update_solde', methods: ['PUT'])]
    public function updateSolde(Request $request): Response
    {
        $user = $this->getUser();
        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        if (!$portefeuille) {
            return $this->responseFormatter->formatError('Portefeuille not found', 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['solde'])) {
            return $this->responseFormatter->formatError('Missing solde field', 400);
        }

        $portefeuille->setSoldeUtilisateur($data['solde']);
        $this->entityManager->flush();

        return $this->responseFormatter->formatResponse($portefeuille);
    }

    #[Route('/transactions', name: 'transactions', methods: ['GET'])]
    public function transactions(): Response
    {
        $user = $this->getUser();
        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        if (!$portefeuille) {
            return $this->responseFormatter->formatError('Portefeuille not found', 404);
        }

        $transactions = $portefeuille->getHistoriqueTransactions();
        return $this->responseFormatter->formatResponse($transactions);
    }
}
