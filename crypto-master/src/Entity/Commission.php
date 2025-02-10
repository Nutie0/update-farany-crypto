<?php

namespace App\Entity;

use App\Repository\CommissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommissionRepository::class)]
class Commission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $tauxAchat = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $tauxVente = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateModification = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTauxAchat(): ?string
    {
        return $this->tauxAchat;
    }

    public function setTauxAchat(string $tauxAchat): static
    {
        $this->tauxAchat = $tauxAchat;
        return $this;
    }

    public function getTauxVente(): ?string
    {
        return $this->tauxVente;
    }

    public function setTauxVente(string $tauxVente): static
    {
        $this->tauxVente = $tauxVente;
        return $this;
    }

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(\DateTimeInterface $dateModification): static
    {
        $this->dateModification = $dateModification;
        return $this;
    }
}
