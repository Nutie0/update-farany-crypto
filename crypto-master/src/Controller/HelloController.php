<?php

namespace App\Controller;

use App\Service\UserApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class HelloController extends AbstractController
{
    #[Route('/hello', name: 'app_hello')]
    public function hello(UserApiService $userApiService): Response
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = $this->getUser();
            if (!$user) {
                return $this->redirectToRoute('app_login_page');
            }

            // Récupérer le token
            $token = $user->getToken();
            if (!$token) {
                throw new CustomUserMessageAuthenticationException('Token non trouvé');
            }

            // Récupérer les informations de l'utilisateur depuis l'API
            $userInfo = $userApiService->getUserInfo($token);

            return $this->render('hello/index.html.twig', [
                'user' => $userInfo
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, rediriger vers la page de connexion
            return $this->redirectToRoute('app_login_page');
        }
    }
}
