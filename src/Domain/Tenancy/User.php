<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Exception\ValidationException;
use App\Domain\Security\PasswordHash;
use DateTimeImmutable;

final class User
{
    private string $displayName;
    private ?string $avatar;
    private string $avatarColor;
    private bool $admin;
    private PasswordHash $passwordHash;

    public function __construct(
        private readonly UserId $id,
        private readonly Login $login,
        PasswordHash $passwordHash,
        string $displayName = '',
        ?string $avatar = null,
        string $avatarColor = '#333333',
        bool $admin = false,
        private readonly ?DateTimeImmutable $createdAt = null,
    ) {
        $this->passwordHash = $passwordHash;
        $this->displayName = $this->validDisplayName($displayName);
        $this->avatar = $avatar === '' ? null : $avatar;
        $this->avatarColor = $this->validColor($avatarColor);
        $this->admin = $admin;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function login(): Login
    {
        return $this->login;
    }

    public function passwordHash(): PasswordHash
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
        return $this->admin;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function rename(string $displayName): void
    {
        $this->displayName = $this->validDisplayName($displayName);
    }

    public function changeAvatar(?string $avatar): void
    {
        $this->avatar = $avatar === '' ? null : $avatar;
    }

    public function recolor(string $avatarColor): void
    {
        $this->avatarColor = $this->validColor($avatarColor);
    }

    public function changePassword(PasswordHash $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function promote(bool $admin): void
    {
        $this->admin = $admin;
    }

    private function validDisplayName(string $displayName): string
    {
        $normalized = trim($displayName);
        if (mb_strlen($normalized) > 40) {
            throw new ValidationException('displayName', 'Display name must have at most 40 characters.');
        }

        return $normalized;
    }

    private function validColor(string $avatarColor): string
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $avatarColor)) {
            throw new ValidationException('avatarColor', 'Avatar color must be a hexadecimal color such as #4c9aff.');
        }

        return $avatarColor;
    }
}
