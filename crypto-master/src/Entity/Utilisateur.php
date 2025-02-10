<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id_utilisateur')]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\OneToOne(mappedBy: 'utilisateur', targetEntity: Portefeuille::class, cascade: ['persist', 'remove'])]
    private ?Portefeuille $portefeuille = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function setPassword(string $password): static
    {
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPortefeuille(): ?Portefeuille
    {
        return $this->portefeuille;
    }

    public function setPortefeuille(?Portefeuille $portefeuille): static
    {
        if ($portefeuille === null && $this->portefeuille !== null) {
            $this->portefeuille->setUtilisateur(null);
        }

        if ($portefeuille !== null && $portefeuille->getUtilisateur() !== $this) {
            $portefeuille->setUtilisateur($this);
        }

        $this->portefeuille = $portefeuille;
        return $this;
    }
}
