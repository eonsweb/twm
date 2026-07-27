<?php

namespace App;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case Administrator = 'administrator';
    case Editor = 'editor';
    case MediaManager = 'media-manager';
    case FinanceOfficer = 'finance-officer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Administrator => 'Administrator',
            self::Editor => 'Editor',
            self::MediaManager => 'Media Manager',
            self::FinanceOfficer => 'Finance Officer',
        };
    }

    public function isProtected(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    public static function fromStoredName(string $name): ?self
    {
        foreach (self::cases() as $role) {
            if ($role->value === $name || $role->label() === $name) {
                return $role;
            }
        }

        return null;
    }
}
