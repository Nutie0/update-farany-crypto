<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Portefeuille;
use App\Service\UserSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class VerificationController extends AbstractController
{
    private $entityManager;
    private $userSyncService;

    public function __construct(EntityManagerInterface $entityManager, UserSyncService $userSyncService)
    {
        $this->entityManager = $entityManager;
        $this->userSyncService = $userSyncService;
    }

    #[Route('/api/auth/verify-email', name: 'verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): Response
    {
        $token = $request->query->get('token');
        
        if (!$token) {
            return $this->render('verification/error.html.twig', [
                'message' => 'Token de vérification manquant.'
            ]);
        }

        try {
            // Vérifier le token avec l'API .NET
            $response = $this->userSyncService->verifyEmail($token);
            
            if ($response['success']) {
                // C'est seulement ici qu'on crée l'utilisateur local
                $userData = $response['userData'];
                
                // Vérifier si l'utilisateur n'existe pas déjà localement
                $existingUser = $this->entityManager->getRepository(Utilisateur::class)
                    ->findOneBy(['email' => $userData['email']]);
                
                if (!$existingUser) {
                    // Créer l'utilisateur local
                    $user = new Utilisateur();
                    $user->setEmail($userData['email']);
                    $user->setNom($userData['nom']);
                    $user->setRoles(['ROLE_USER']);
                    
                    // Créer le portefeuille
                    $portefeuille = new Portefeuille();
                    $portefeuille->setUtilisateur($user);
                    $portefeuille->setSoldeUtilisateur(0);
                    
                    $this->entityManager->persist($user);
                    $this->entityManager->persist($portefeuille);
                    $this->entityManager->flush();
                }

                return $this->render('verification/success.html.twig', [
                    'message' => 'Votre email a été vérifié avec succès. Vous pouvez maintenant vous connecter.'
                ]);
            }

            return $this->render('verification/error.html.twig', [
                'message' => $response['message'] ?? 'Erreur lors de la vérification de l\'email.'
            ]);

        } catch (\Exception $e) {
            return $this->render('verification/error.html.twig', [
                'message' => 'Une erreur est survenue lors de la vérification.'
            ]);
        }
    }
}