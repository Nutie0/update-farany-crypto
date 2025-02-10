<?php

namespace App\Controller\Admin;

use App\Entity\HistoriqueUtilisateur;
use App\Entity\Portefeuille;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_admin')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupérer toutes les transactions en attente
        $transactions = $entityManager->getRepository(HistoriqueUtilisateur::class)
            ->findBy(['statut' => 'en_attente'], ['dateHistorique' => 'DESC']);

        return $this->render('admin/dashboard.html.twig', [
            'transactions' => $transactions,
        ]);
    }

    #[Route('/transaction/{id}/approve', name: 'app_admin_transaction_approve')]
    public function approveTransaction(HistoriqueUtilisateur $transaction): Response
    {
        // Vérifier si la transaction est toujours en attente
        if ($transaction->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Cette transaction a déjà été traitée.');
            return $this->redirectToRoute('app_admin');
        }

        $portefeuille = $transaction->getPortefeuille();
        $montant = (float)$transaction->getSomme();
        $typeAction = $transaction->getAction()->getTypeAction();

        try {
            // Traiter la transaction selon son type
            if ($typeAction === 'depot') {
                $nouveauSolde = $portefeuille->getSoldeUtilisateur() + $montant;
                $portefeuille->setSoldeUtilisateur($nouveauSolde);
            } elseif ($typeAction === 'retrait') {
                // Vérifier si le solde est suffisant
                if ($portefeuille->getSoldeUtilisateur() < $montant) {
                    throw new \Exception('Solde insuffisant pour effectuer le retrait.');
                }
                $nouveauSolde = $portefeuille->getSoldeUtilisateur() - $montant;
                $portefeuille->setSoldeUtilisateur($nouveauSolde);
            } else {
                throw new \Exception('Type de transaction non reconnu.');
            }

            // Mettre à jour le statut de la transaction
            $transaction->setStatut('approuve');
            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'Transaction %s de %.2f € approuvée avec succès.',
                $typeAction,
                $montant
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/transaction/{id}/reject', name: 'app_admin_transaction_reject')]
    public function rejectTransaction(HistoriqueUtilisateur $transaction): Response
    {
        // Vérifier si la transaction est toujours en attente
        if ($transaction->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Cette transaction a déjà été traitée.');
            return $this->redirectToRoute('app_admin');
        }

        try {
            // Rejeter la transaction sans modifier le solde
            $transaction->setStatut('rejete');
            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'Transaction %s de %.2f € rejetée.',
                $transaction->getAction()->getTypeAction(),
                (float)$transaction->getSomme()
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin');
    }
}
