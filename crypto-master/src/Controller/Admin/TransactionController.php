<?php

namespace App\Controller\Admin;

use App\Entity\HistoriqueUtilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/admin/transaction')]
#[IsGranted('ROLE_ADMIN')]
class TransactionController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/', name: 'admin_transaction_index')]
    public function index(): Response
    {
        $transactions = $this->entityManager->getRepository(HistoriqueUtilisateur::class)
            ->findBy(
                ['statut' => 'en_attente'],
                ['dateHistorique' => 'DESC']
            );

        return $this->render('admin/transaction/index.html.twig', [
            'transactions' => $transactions
        ]);
    }

    #[Route('/approve/{id}', name: 'admin_transaction_approve', methods: ['POST'])]
    public function approve(HistoriqueUtilisateur $transaction): JsonResponse
    {
        try {
            $portefeuille = $transaction->getPortefeuille();
            $action = $transaction->getAction();
            $montant = $transaction->getSomme();

            // Log pour le débogage
            $this->logger->info('Approbation de transaction', [
                'transaction_id' => $transaction->getIdHistoriqueUtilisateur(),
                'action_id' => $action->getId(),
                'type_action' => $action->getTypeAction(),
                'montant' => $montant,
                'portefeuille_id' => $portefeuille->getId()
            ]);

            // Mise à jour du solde selon le type d'action
            $soldeActuel = $portefeuille->getSoldeUtilisateur();
            if ($action->getId() === 1) { // Dépôt
                $nouveauSolde = $soldeActuel + $montant;
                $portefeuille->setSoldeUtilisateur($nouveauSolde);
            } elseif ($action->getId() === 2) { // Retrait
                if ($soldeActuel < $montant) {
                    throw new \Exception('Solde insuffisant pour ce retrait');
                }
                $nouveauSolde = $soldeActuel - $montant;
                $portefeuille->setSoldeUtilisateur($nouveauSolde);
            } else {
                throw new \Exception('Type de transaction non reconnu');
            }

            // Mettre à jour le statut de la transaction
            $transaction->setStatut('approuve');
            
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'nouveau_solde' => $nouveauSolde
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'approbation', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->getIdHistoriqueUtilisateur()
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/reject/{id}', name: 'admin_transaction_reject', methods: ['POST'])]
    public function reject(HistoriqueUtilisateur $transaction): JsonResponse
    {
        try {
            $transaction->setStatut('rejete');
            $this->entityManager->flush();

            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
