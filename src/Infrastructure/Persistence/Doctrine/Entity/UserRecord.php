<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
final class UserRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private string $login;

    #[ORM\Column(name: 'password_hash', type: 'string', length: 255)]
    private string $passwordHash;

    #[ORM\Column(name: 'display_name', type: 'string', length: 40)]
    private string $displayName;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $avatar;

    #[ORM\Column(name: 'avatar_color', type: 'string', length: 7)]
    private string $avatarColor;

    #[ORM\Column(name: 'is_admin', type: 'boolean')]
    private bool $isAdmin;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $login,
        string $passwordHash,
        string $displayName,
        ?string $avatar,
        string $avatarColor,
        bool $isAdmin,
        DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->login = $login;
        $this->passwordHash = $passwordHash;
        $this->displayName = $displayName;
        $this->avatar = $avatar;
        $this->avatarColor = $avatarColor;
        $this->isAdmin = $isAdmin;
        $this->createdAt = $createdAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function login(): string
    {
        return $this->login;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function avatar(): ?string
    {
        return $this->avatar;
    }

    public function avatarColor(): string
    {
        return $this->avatarColor;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function sync(string $passwordHash, string $displayName, ?string $avatar, string $avatarColor, bool $isAdmin): void
    {
        $this->passwordHash = $passwordHash;
        $this->displayName = $displayName;
        $this->avatar = $avatar;
        $this->avatarColor = $avatarColor;
        $this->isAdmin = $isAdmin;
    }
}
