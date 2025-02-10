<?php

namespace App\Entity;

use App\Repository\PortefeuilleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortefeuilleRepository::class)]
class Portefeuille
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $soldeUtilisateur = null;

    #[ORM\OneToOne(inversedBy: 'portefeuille')]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSoldeUtilisateur(): ?string
    {
        return $this->soldeUtilisateur;
    }

    public function setSoldeUtilisateur(string $soldeUtilisateur): self
    {
        $this->soldeUtilisateur = $soldeUtilisateur;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }
}
