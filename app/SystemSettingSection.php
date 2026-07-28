<?php

namespace App;

enum SystemSettingSection: string
{
    case General = 'general';
    case Church = 'church';
    case Contact = 'contact';
    case ServiceTimes = 'service-times';
    case Branding = 'branding';
    case Social = 'social';
    case Email = 'email';
    case Donations = 'donations';
    case Integrations = 'integrations';
    case Security = 'security';
    case Maintenance = 'maintenance';
    case Localization = 'localization';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Church => 'Church details',
            self::Contact => 'Contact information',
            self::ServiceTimes => 'Service times',
            self::Branding => 'Branding',
            self::Social => 'Social media',
            self::Email => 'Email',
            self::Donations => 'Donations',
            self::Integrations => 'Integrations',
            self::Security => 'Security',
            self::Maintenance => 'Maintenance',
            self::Localization => 'Localization',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::General => 'Website identity and application-wide defaults.',
            self::Church => 'The church profile shown across the public website.',
            self::Contact => 'Public contact channels and office information.',
            self::ServiceTimes => 'Recurring worship services and gatherings.',
            self::Branding => 'Logos, icons, social artwork, and email imagery.',
            self::Social => 'Social network links and visibility controls.',
            self::Email => 'Sender identity, notifications, and mail testing.',
            self::Donations => 'Giving defaults, instructions, and receipts.',
            self::Integrations => 'External providers and encrypted credentials.',
            self::Security => 'Administrative security preferences and safeguards.',
            self::Maintenance => 'Website availability and maintenance messaging.',
            self::Localization => 'Locales, time zone, currency, and date formats.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::General => 'cog-6-tooth',
            self::Church => 'building-library',
            self::Contact => 'phone',
            self::ServiceTimes => 'clock',
            self::Branding => 'photo',
            self::Social => 'share',
            self::Email => 'envelope',
            self::Donations => 'banknotes',
            self::Integrations => 'puzzle-piece',
            self::Security => 'shield-check',
            self::Maintenance => 'wrench-screwdriver',
            self::Localization => 'language',
        };
    }

    public function permission(): PermissionName
    {
        return match ($this) {
            self::General => PermissionName::SettingsGeneralUpdate,
            self::Church => PermissionName::SettingsChurchUpdate,
            self::Contact => PermissionName::SettingsContactUpdate,
            self::ServiceTimes => PermissionName::SettingsServiceTimesUpdate,
            self::Branding => PermissionName::SettingsBrandingUpdate,
            self::Social => PermissionName::SettingsSocialUpdate,
            self::Email => PermissionName::SettingsEmailUpdate,
            self::Donations => PermissionName::SettingsDonationsUpdate,
            self::Integrations => PermissionName::SettingsIntegrationsUpdate,
            self::Security => PermissionName::SettingsSecurityUpdate,
            self::Maintenance => PermissionName::SettingsMaintenanceUpdate,
            self::Localization => PermissionName::SettingsLocalizationUpdate,
        };
    }

    public function requiresPasswordConfirmation(): bool
    {
        return in_array($this, [
            self::Email,
            self::Integrations,
            self::Security,
            self::Maintenance,
        ], true);
    }

    public function routeName(): string
    {
        return 'admin.settings.'.$this->value;
    }
}
