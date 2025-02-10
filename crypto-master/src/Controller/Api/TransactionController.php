<?php

namespace App\Controller\Api;

use App\Entity\HistoriqueTransactions;
use App\Entity\Portefeuille;
use App\Service\ApiResponseFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/transactions', name: 'api_transactions_')]
class TransactionController extends AbstractController
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

        $transactions = $this->entityManager->getRepository(HistoriqueTransactions::class)
            ->findBy(['portefeuille' => $portefeuille], ['dateTransaction' => 'DESC']);

        return $this->responseFormatter->formatResponse($transactions);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $user = $this->getUser();
        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        if (!$portefeuille) {
            return $this->responseFormatter->formatError('Portefeuille not found', 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['montant']) || !isset($data['type'])) {
            return $this->responseFormatter->formatError('Missing required fields', 400);
        }

        $transaction = new HistoriqueTransactions();
        $transaction->setPortefeuille($portefeuille);
        $transaction->setMontantTransaction($data['montant']);
        $transaction->setTypeTransaction($data['type']);
        $transaction->setDateTransaction(new \DateTime());

        // Mise à jour du solde du portefeuille
        $soldeActuel = $portefeuille->getSoldeUtilisateur();
        $nouveauSolde = $data['type'] === 'credit' 
            ? $soldeActuel + $data['montant']
            : $soldeActuel - $data['montant'];
        
        $portefeuille->setSoldeUtilisateur($nouveauSolde);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $this->responseFormatter->formatResponse($transaction, 'success', 201);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): Response
    {
        $user = $this->getUser();
        $transaction = $this->entityManager->getRepository(HistoriqueTransactions::class)
            ->find($id);

        if (!$transaction || $transaction->getPortefeuille()->getUtilisateur() !== $user) {
            return $this->responseFormatter->formatError('Transaction not found', 404);
        }

        return $this->responseFormatter->formatResponse($transaction);
    }
}
