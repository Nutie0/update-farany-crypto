<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/test')]
class TestController extends AbstractController
{
    #[Route('/create-user', name: 'test_create_user', methods: ['GET'])]
    public function createTestUser(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = new Utilisateur();
        $user->setEmail('test2@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setUsername('Test User 2');

        $entityManager->persist($user);
        $entityManager->flush();

        // Vérifions si un portefeuille a été créé
        $portefeuille = $entityManager->getRepository('App:Portefeuille')
            ->findOneBy(['utilisateur' => $user]);

        return new JsonResponse([
            'message' => 'User created',
            'id' => $user->getId(),
            'portefeuille_created' => $portefeuille !== null
        ]);
    }
}
