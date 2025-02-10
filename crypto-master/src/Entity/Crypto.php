<?php

namespace App\Entity;

use App\Repository\CryptoRepository;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CryptoRepository::class)]
#[ORM\Table(name: 'crypto')]
#[ApiResource()]
class Crypto
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id_crypto')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom_crypto', length: 50)]
    private ?string $nomCrypto = null;

    #[ORM\Column(name: 'quantite_crypto', type: 'integer')]
    private ?int $nbrCrypto = null;

    #[ORM\Column(name: 'prix_initiale_crypto', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $prixInitialeCrypto = null;

    #[ORM\Column(name: 'date_injection', type: 'datetime')]
    private ?\DateTimeInterface $dateInjection = null;

    /**
     * @var VariationCrypto|null
     */
    public ?VariationCrypto $lastVariation = null;
    
    public function getIdCrypto(): ?int
    {
        return $this->id;
    }

    public function getNomCrypto(): ?string
    {
        return $this->nomCrypto;
    }

    public function setNomCrypto(string $nomCrypto): static
    {
        $this->nomCrypto = $nomCrypto;
        return $this;
    }

    public function getNbrCrypto(): ?int
    {
        return $this->nbrCrypto;
    }

    public function setNbrCrypto(int $nbrCrypto): static
    {
        $this->nbrCrypto = $nbrCrypto;
        return $this;
    }

    public function getPrixInitialeCrypto(): ?string
    {
        return $this->prixInitialeCrypto;
    }

    public function setPrixInitialeCrypto(?string $prixInitialeCrypto): static
    {
        $this->prixInitialeCrypto = $prixInitialeCrypto;
        return $this;
    }

    public function getDateInjection(): ?\DateTimeInterface
    {
        return $this->dateInjection;
    }

    public function setDateInjection(\DateTimeInterface $dateInjection): static
    {
        $this->dateInjection = $dateInjection;
        return $this;
    }

    public function getLastVariation(): ?VariationCrypto
    {
        return $this->lastVariation;
    }

    public function setLastVariation(?VariationCrypto $lastVariation): static
    {
        $this->lastVariation = $lastVariation;
        return $this;
    }
}
