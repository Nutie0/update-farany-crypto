<?php

namespace App\Controller;

use App\Entity\Portefeuille;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Psr\Log\LoggerInterface;

class SecurityController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, rediriger vers la page d'accueil
        if ($this->getUser()) {
            $this->logger->info('Utilisateur déjà connecté, redirection vers la page d\'accueil');
            return $this->redirectToRoute('app_home');
        }

        // Récupérer l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();
        if ($error) {
            $this->logger->error('Erreur d\'authentification', [
                'error_message' => $error->getMessage(),
                'error_type' => get_class($error)
            ]);
        }
        
        // Dernier nom d'utilisateur saisi
        $lastUsername = $authenticationUtils->getLastUsername();
        $this->logger->info('Affichage du formulaire de connexion', [
            'last_username' => $lastUsername,
            'has_error' => $error !== null
        ]);

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Cette méthode peut rester vide,
        // car c'est Symfony qui gère la déconnexion
        $this->logger->info('Déconnexion de l\'utilisateur');
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/api/auth/login', name: 'api_login', methods: ['POST'])]
    public function apiLogin(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;

            // Log la tentative de connexion
            $this->logger->info('Tentative de connexion pour l\'email: ' . $email);

            // Appel à l'API externe
            $response = $this->callExternalApi($data);

            // Log la réponse de l'API
            $this->logger->info('API Response Status: ' . $response->getStatusCode());
            $this->logger->info('API Response Content: ' . $response->getContent());

            $responseData = json_decode($response->getContent(), true);

            if ($response->getStatusCode() === 200 && isset($responseData['token'])) {
                // Rechercher l'utilisateur
                $utilisateur = $this->entityManager->getRepository(Utilisateur::class)
                    ->findOneBy(['email' => $email]);

                if ($utilisateur) {
                    // Mettre à jour le token
                    $utilisateur->setToken($responseData['token']);
                    
                    // Vérifier si l'utilisateur a déjà un portefeuille
                    $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
                        ->findOneBy(['utilisateur' => $utilisateur]);

                    // Si pas de portefeuille, en créer un
                    if (!$portefeuille) {
                        $portefeuille = new Portefeuille();
                        $portefeuille->setUtilisateur($utilisateur);
                        $portefeuille->setSoldeUtilisateur('0.00');
                        $this->entityManager->persist($portefeuille);
                    }

                    $this->entityManager->flush();
                }
            }

            return new JsonResponse($responseData, $response->getStatusCode());
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la connexion: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function callExternalApi(array $data): Response
    {
        $curl = curl_init('http://localhost:5280/api/auth/login');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return new Response($response, $statusCode);
    }
}
