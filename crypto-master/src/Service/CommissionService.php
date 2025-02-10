<?php

namespace App\Service;

use App\Entity\Commission;
use Doctrine\ORM\EntityManagerInterface;

class CommissionService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function calculateCommission(string $type, float $montant): float
    {
        $commission = $this->entityManager->getRepository(Commission::class)
            ->getCurrentCommission();

        $taux = match($type) {
            'achat' => $commission ? (float)$commission->getTauxAchat() : 2.5,
            'vente' => $commission ? (float)$commission->getTauxVente() : 1.5,
            default => throw new \InvalidArgumentException('Type de transaction invalide')
        };

        return $montant * ($taux / 100);
    }

    public function getTauxCommission(string $type): float
    {
        $commission = $this->entityManager->getRepository(Commission::class)
            ->getCurrentCommission();

        return match($type) {
            'achat' => $commission ? (float)$commission->getTauxAchat() : 2.5,
            'vente' => $commission ? (float)$commission->getTauxVente() : 1.5,
            default => throw new \InvalidArgumentException('Type de commission invalide')
        };
    }
}
