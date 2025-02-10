<?php

namespace App\EventSubscriber;

use App\Entity\Portefeuille;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class UserCreationSubscriber implements EventSubscriberInterface
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Utilisateur) {
            return;
        }

        $entityManager = $args->getObjectManager();
        
        // Vérifie si un portefeuille existe déjà
        $portefeuille = $entityManager->getRepository(Portefeuille::class)
            ->findOneBy(['utilisateur' => $entity]);
            
        if (!$portefeuille) {
            $portefeuille = new Portefeuille();
            $portefeuille->setUtilisateur($entity);
            $portefeuille->setSoldeUtilisateur(0);
            
            $entityManager->persist($portefeuille);
            $entityManager->flush();
        }
    }
}
