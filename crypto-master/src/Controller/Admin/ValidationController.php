<?php

namespace App\Controller\Admin;

use App\Entity\HistoriqueUtilisateur;
use App\Entity\Portefeuille;
use App\Repository\HistoriqueUtilisateurRepository;
use App\Repository\PortefeuilleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class ValidationController extends AbstractController
{
    #[Route('/validations', name: 'app_admin_validations')]
    public function index(HistoriqueUtilisateurRepository $historiqueRepo): Response
    {
        $transactionsEnAttente = $historiqueRepo->findBy(['statut' => 'en_attente']);

        return $this->render('admin/validation/index.html.twig', [
            'transactions' => $transactionsEnAttente
        ]);
    }

    #[Route('/validation/{id}/approve', name: 'app_admin_validation_approve')]
    public function approveTransaction(
        HistoriqueUtilisateur $historique,
        EntityManagerInterface $entityManager,
        PortefeuilleRepository $portefeuilleRepo
    ): Response {
        if ($historique->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Cette transaction a déjà été traitée.');
            return $this->redirectToRoute('app_admin_validations');
        }

        $portefeuille = $portefeuilleRepo->findOneBy(['id_utilisateur' => $historique->getIdUtilisateur()]);
        
        if ($historique->getTypeAction() === 'depot') {
            $portefeuille->setSoldeUtilisateur(
                $portefeuille->getSoldeUtilisateur() + $historique->getMontant()
            );
        } elseif ($historique->getTypeAction() === 'retrait') {
            if ($portefeuille->getSoldeUtilisateur() < $historique->getMontant()) {
                $this->addFlash('error', 'Solde insuffisant pour effectuer le retrait.');
                return $this->redirectToRoute('app_admin_validations');
            }
            $portefeuille->setSoldeUtilisateur(
                $portefeuille->getSoldeUtilisateur() - $historique->getMontant()
            );
        }

        $historique->setStatut('approuve');
        $entityManager->flush();

        $this->addFlash('success', 'Transaction approuvée avec succès.');
        return $this->redirectToRoute('app_admin_validations');
    }

    #[Route('/validation/{id}/reject', name: 'app_admin_validation_reject')]
    public function rejectTransaction(
        HistoriqueUtilisateur $historique,
        EntityManagerInterface $entityManager
    ): Response {
        if ($historique->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Cette transaction a déjà été traitée.');
            return $this->redirectToRoute('app_admin_validations');
        }

        $historique->setStatut('rejete');
        $entityManager->flush();

        $this->addFlash('success', 'Transaction rejetée.');
        return $this->redirectToRoute('app_admin_validations');
    }
}
