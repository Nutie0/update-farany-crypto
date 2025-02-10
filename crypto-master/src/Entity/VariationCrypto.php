<?php

namespace App\Entity;

use App\Repository\VariationCryptoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VariationCryptoRepository::class)]
#[ORM\Table(name: 'variationcrypto')]
class VariationCrypto
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id_variation')]
    private ?int $id = null;

    #[ORM\Column(name: 'pourcentagevariation', type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $pourcentageVariation = null;

    #[ORM\Column(name: 'prixevoluer', type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $prixEvoluer = null;

    #[ORM\Column(name: 'date_variation', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateVariation = null;

    #[ORM\ManyToOne(targetEntity: Crypto::class)]
    #[ORM\JoinColumn(name: 'id_crypto', referencedColumnName: 'id_crypto', nullable: false)]
    private ?Crypto $crypto = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPourcentageVariation(): ?string
    {
        return $this->pourcentageVariation;
    }

    public function setPourcentageVariation(string $pourcentageVariation): static
    {
        $this->pourcentageVariation = $pourcentageVariation;
        return $this;
    }

    public function getPrixEvoluer(): ?string
    {
        return $this->prixEvoluer;
    }

    public function setPrixEvoluer(?string $prixEvoluer): static
    {
        $this->prixEvoluer = $prixEvoluer;
        return $this;
    }

    public function getDateVariation(): ?\DateTimeInterface
    {
        return $this->dateVariation;
    }

    public function setDateVariation(\DateTimeInterface $dateVariation): static
    {
        $this->dateVariation = $dateVariation;
        return $this;
    }

    public function getCrypto(): ?Crypto
    {
        return $this->crypto;
    }

    public function setCrypto(?Crypto $crypto): static
    {
        $this->crypto = $crypto;
        return $this;
    }
}
