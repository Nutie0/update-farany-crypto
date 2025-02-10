<?php

namespace App\EventListener;

use App\Entity\Portefeuille;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

class AuthenticationSuccessListener
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof Utilisateur) {
            return;
        }

        // Vérifie si l'utilisateur a déjà un portefeuille
        $portefeuille = $this->entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $user]);

        // Si pas de portefeuille, en créer un nouveau
        if (!$portefeuille) {
            $portefeuille = new Portefeuille();
            $portefeuille->setUtilisateur($user);
            $portefeuille->setSoldeUtilisateur(0);
            
            $this->entityManager->persist($portefeuille);
            $this->entityManager->flush();
        }
    }
}
