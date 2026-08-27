<?php

namespace App\Settings;

use App\SettingType;
use App\SystemSettingSection;

/**
 * @phpstan-type SettingDefinition array{
 *     group: string,
 *     key: string,
 *     type: SettingType,
 *     label: string,
 *     description: string|null,
 *     default: mixed,
 *     public: bool,
 *     encrypted: bool,
 *     rules: list<string>,
 *     options?: array<string, string>
 * }
 */
class SettingRegistry
{
    /**
     * @return list<SettingDefinition>
     */
    public function forSection(SystemSettingSection $section): array
    {
        return match ($section) {
            SystemSettingSection::General => $this->general(),
            SystemSettingSection::Church => $this->church(),
            SystemSettingSection::Contact => $this->contact(),
            SystemSettingSection::ServiceTimes => [],
            SystemSettingSection::Branding => $this->branding(),
            SystemSettingSection::Social => $this->social(),
            SystemSettingSection::Email => $this->email(),
            SystemSettingSection::Donations => $this->donations(),
            SystemSettingSection::Integrations => $this->integrations(),
            SystemSettingSection::Security => $this->security(),
            SystemSettingSection::Maintenance => $this->maintenance(),
            SystemSettingSection::Localization => $this->localization(),
        };
    }

    /**
     * Return every canonical setting once, even when a setting is surfaced in more than one section.
     *
     * @return list<SettingDefinition>
     */
    public function all(): array
    {
        $settings = [];

        foreach (SystemSettingSection::cases() as $section) {
            foreach ($this->forSection($section) as $definition) {
                $settings[$definition['group'].'.'.$definition['key']] = $definition;
            }
        }

        return array_values($settings);
    }

    /**
     * @return list<SettingDefinition>
     */
    private function general(): array
    {
        return [
            $this->definition('general', 'application_name', SettingType::String, 'Application name', config('app.name', 'Church Website'), true, ['required', 'string', 'max:120']),
            $this->definition('general', 'website_name', SettingType::String, 'Website name', 'Triumphant World Ministry', true, ['required', 'string', 'max:120']),
            $this->definition('general', 'website_description', SettingType::Text, 'Website description', '', true, ['nullable', 'string', 'max:1000']),
            $this->definition('general', 'tagline', SettingType::String, 'Tagline', '', true, ['nullable', 'string', 'max:180']),
            $this->definition('general', 'website_url', SettingType::String, 'Website URL', config('app.url'), true, ['required', 'url:http,https', 'max:255']),
            $this->definition('general', 'admin_notification_email', SettingType::String, 'Administrator notification email', '', false, ['nullable', 'email:rfc', 'max:255']),
            $this->definition('general', 'registration_enabled', SettingType::Boolean, 'Allow public registration', true, true, ['required', 'boolean']),
            $this->definition('general', 'guest_checkout_enabled', SettingType::Boolean, 'Allow guest donations', true, true, ['required', 'boolean']),
            $this->definition('general', 'comments_enabled', SettingType::Boolean, 'Enable comments', false, true, ['required', 'boolean']),
            $this->definition('general', 'website_enabled', SettingType::Boolean, 'Public website enabled', true, true, ['required', 'boolean']),
            $this->definition('general', 'pagination_size', SettingType::Integer, 'Default items per page', 15, false, ['required', 'integer', 'min:5', 'max:100']),
            $this->definition('general', 'copyright_text', SettingType::String, 'Copyright text', 'Triumphant World Ministry', true, ['nullable', 'string', 'max:255']),
            $this->definition('homepage', 'hero_eyebrow', SettingType::String, 'Homepage hero eyebrow', 'Welcome to', true, ['required', 'string', 'max:100']),
            $this->definition('homepage', 'hero_title', SettingType::String, 'Homepage hero title', 'Triumphant World Ministry', true, ['required', 'string', 'max:180']),
            $this->definition('homepage', 'hero_description', SettingType::Text, 'Homepage hero description', 'A Christ-centered family where lives are transformed by grace, prayer, worship, and the Word.', true, ['nullable', 'string', 'max:1000']),
            $this->definition('homepage', 'hero_scripture', SettingType::String, 'Homepage hero scripture', 'Grace Upon Grace', true, ['nullable', 'string', 'max:180']),
            $this->definition('homepage', 'hero_scripture_reference', SettingType::String, 'Homepage scripture reference', 'John 1:16', true, ['nullable', 'string', 'max:80']),
            $this->definition('homepage', 'welcome_heading', SettingType::String, 'Homepage welcome heading', 'Welcome Home!', true, ['nullable', 'string', 'max:180']),
            $this->definition('homepage', 'welcome_body', SettingType::Text, 'Homepage welcome message', "We are delighted to have you with us.\n\nAt Triumphant World Ministry, we believe in the transforming power of God's grace.\n\nOur prayer is that you encounter God, find purpose, and grow in His Word.", true, ['nullable', 'string', 'max:2000']),
            $this->definition('homepage', 'welcome_leader_id', SettingType::Integer, 'Homepage welcome leader ID', null, true, ['nullable', 'integer', 'exists:people,id']),
            $this->definition('homepage', 'welcome_signature', SettingType::String, 'Homepage welcome signature', null, true, ['nullable', 'string', 'max:180']),
            $this->definition('homepage', 'welcome_pastor_name', SettingType::String, 'Homepage pastor name override', null, true, ['nullable', 'string', 'max:180']),
            $this->definition('homepage', 'welcome_pastor_title', SettingType::String, 'Homepage pastor title override', null, true, ['nullable', 'string', 'max:180']),
            $this->definition('homepage', 'prayer_heading', SettingType::String, 'Prayer call-to-action heading', 'Need Prayer?', true, ['nullable', 'string', 'max:180']),
            $this->definition('homepage', 'prayer_body', SettingType::String, 'Prayer call-to-action text', 'We would love to stand with you in prayer.', true, ['nullable', 'string', 'max:500']),
            $this->definition('homepage', 'giving_heading', SettingType::String, 'Giving call-to-action heading', 'Partner With the Work of God', true, ['nullable', 'string', 'max:180']),
            $this->definition('homepage', 'giving_body', SettingType::String, 'Giving call-to-action text', 'Your giving supports lives, spreads the Gospel, and advances God’s Kingdom.', true, ['nullable', 'string', 'max:500']),
            $this->definition('homepage', 'enabled_sections', SettingType::Json, 'Enabled homepage sections', ['services', 'welcome_upcoming_event', 'ministries', 'calls_to_action', 'featured_book', 'testimonials'], true, ['required', 'json'], 'JSON array containing enabled section keys.'),
            $this->definition('homepage', 'testimonials', SettingType::Json, 'Homepage testimonials', [], true, ['required', 'json'], 'JSON array. Each item may contain quote, name, role, portrait_url, and enabled.'),
            ...$this->coreLocalization(),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function church(): array
    {
        return [
            $this->definition('church', 'official_name', SettingType::String, 'Official church name', 'Triumphant World Ministry', true, ['required', 'string', 'max:180']),
            $this->definition('church', 'short_name', SettingType::String, 'Short church name', 'TWM', true, ['nullable', 'string', 'max:60']),
            $this->definition('church', 'motto', SettingType::String, 'Church motto', '', true, ['nullable', 'string', 'max:255']),
            $this->definition('church', 'legal_name', SettingType::String, 'Legal name', '', false, ['nullable', 'string', 'max:180']),
            $this->definition('church', 'registration_number', SettingType::String, 'Registration number', '', false, ['nullable', 'string', 'max:100']),
            $this->definition('church', 'denomination', SettingType::String, 'Denomination or affiliation', '', true, ['nullable', 'string', 'max:180']),
            $this->definition('church', 'founding_date', SettingType::Date, 'Founding date', null, true, ['nullable', 'date', 'before_or_equal:today']),
            $this->definition('church', 'founder_name', SettingType::String, 'Founder name', '', true, ['nullable', 'string', 'max:180']),
            $this->definition('church', 'co_founder_name', SettingType::String, 'Co-founder name', '', true, ['nullable', 'string', 'max:180']),
            $this->definition('church', 'lead_pastor_name', SettingType::String, 'Lead pastor name', '', true, ['nullable', 'string', 'max:180']),
            $this->definition('church', 'history_summary', SettingType::Text, 'Church history summary', '', true, ['nullable', 'string', 'max:10000']),
            $this->definition('church', 'vision', SettingType::Text, 'Vision', '', true, ['nullable', 'string', 'max:3000']),
            $this->definition('church', 'mission', SettingType::Text, 'Mission', '', true, ['nullable', 'string', 'max:3000']),
            $this->definition('church', 'core_values', SettingType::Text, 'Core values', '', true, ['nullable', 'string', 'max:5000']),
            $this->definition('church', 'statement_of_faith', SettingType::Text, 'Statement of faith', '', true, ['nullable', 'string', 'max:10000']),
            $this->definition('church', 'main_location', SettingType::String, 'Main church location', '', true, ['nullable', 'string', 'max:500']),
            $this->definition('church', 'branch_information', SettingType::Text, 'Additional branch information', '', true, ['nullable', 'string', 'max:10000']),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function contact(): array
    {
        return [
            $this->definition('contact', 'primary_email', SettingType::String, 'Primary email', '', true, ['nullable', 'email:rfc', 'max:255']),
            $this->definition('contact', 'support_email', SettingType::String, 'Support email', '', true, ['nullable', 'email:rfc', 'max:255']),
            $this->definition('contact', 'prayer_request_email', SettingType::String, 'Prayer request email', '', false, ['nullable', 'email:rfc', 'max:255']),
            $this->definition('contact', 'primary_phone', SettingType::String, 'Primary phone', '', true, ['nullable', 'regex:/^[0-9+() .-]{7,40}$/']),
            $this->definition('contact', 'secondary_phone', SettingType::String, 'Secondary phone', '', true, ['nullable', 'regex:/^[0-9+() .-]{7,40}$/']),
            $this->definition('contact', 'whatsapp_number', SettingType::String, 'WhatsApp number', '', true, ['nullable', 'regex:/^[0-9+() .-]{7,40}$/']),
            $this->definition('contact', 'physical_address', SettingType::Text, 'Physical address', '', true, ['nullable', 'string', 'max:1000']),
            $this->definition('contact', 'postal_address', SettingType::Text, 'Postal address', '', true, ['nullable', 'string', 'max:1000']),
            $this->definition('contact', 'city', SettingType::String, 'City', '', true, ['nullable', 'string', 'max:120']),
            $this->definition('contact', 'region', SettingType::String, 'Region or province', '', true, ['nullable', 'string', 'max:120']),
            $this->definition('contact', 'country', SettingType::String, 'Country', '', true, ['nullable', 'string', 'max:120']),
            $this->definition('contact', 'map_url', SettingType::String, 'Map URL', '', true, ['nullable', 'url:http,https', 'max:1000']),
            $this->definition('contact', 'map_embed_url', SettingType::String, 'Map embed URL', '', true, ['nullable', 'url:http,https', 'max:1000']),
            $this->definition('contact', 'office_hours', SettingType::Text, 'Office hours', '', true, ['nullable', 'string', 'max:1000']),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function branding(): array
    {
        return [
            $this->definition('branding', 'primary_logo', SettingType::Image, 'Primary logo', null, true, ['nullable', 'integer', 'exists:media,id']),
            $this->definition('branding', 'secondary_logo', SettingType::Image, 'Secondary logo', null, true, ['nullable', 'integer', 'exists:media,id']),
            $this->definition('branding', 'dark_logo', SettingType::Image, 'Dark-mode logo', null, true, ['nullable', 'integer', 'exists:media,id']),
            $this->definition('branding', 'favicon', SettingType::Image, 'Favicon', null, true, ['nullable', 'integer', 'exists:media,id']),
            $this->definition('branding', 'social_share_image', SettingType::Image, 'Social sharing image', null, true, ['nullable', 'integer', 'exists:media,id']),
            $this->definition('branding', 'homepage_hero_image', SettingType::Image, 'Homepage hero image', null, true, ['nullable', 'integer', 'exists:media,id']),
            $this->definition('branding', 'footer_logo', SettingType::Image, 'Public website footer logo', null, true, ['nullable', 'integer', 'exists:media,id']),
            $this->definition('branding', 'admin_logo', SettingType::Image, 'Admin dashboard logo', null, false, ['nullable', 'integer', 'exists:media,id']),
            $this->definition('branding', 'letterhead', SettingType::Image, 'Organization letterhead', null, false, ['nullable', 'integer', 'exists:media,id']),
            $this->definition('branding', 'primary_color', SettingType::String, 'Primary color', '#681c2d', true, ['required', 'regex:/^#[0-9a-fA-F]{6}$/']),
            $this->definition('branding', 'secondary_color', SettingType::String, 'Secondary color', '#173f35', true, ['required', 'regex:/^#[0-9a-fA-F]{6}$/']),
            $this->definition('branding', 'accent_color', SettingType::String, 'Accent color', '#e8b949', true, ['required', 'regex:/^#[0-9a-fA-F]{6}$/']),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function social(): array
    {
        $definitions = [];

        foreach (['facebook', 'instagram', 'youtube', 'x', 'tiktok', 'linkedin', 'telegram', 'whatsapp_channel', 'livestream', 'podcast'] as $network) {
            $label = $network === 'x' ? 'X' : ucfirst(str_replace('_', ' ', $network));
            $definitions[] = $this->definition('social', "{$network}_url", SettingType::String, "{$label} URL", '', true, ['nullable', 'url:http,https', 'max:1000']);
            $definitions[] = $this->definition('social', "{$network}_enabled", SettingType::Boolean, "Show {$label}", false, true, ['required', 'boolean']);
        }

        return $definitions;
    }

    /**
     * @return list<SettingDefinition>
     */
    private function email(): array
    {
        return [
            $this->definition('email', 'from_name', SettingType::String, 'From name', config('mail.from.name'), false, ['required', 'string', 'max:120']),
            $this->definition('email', 'from_address', SettingType::String, 'From address', config('mail.from.address'), false, ['required', 'email:rfc', 'max:255']),
            $this->definition('email', 'reply_to_name', SettingType::String, 'Reply-to name', '', false, ['nullable', 'string', 'max:120']),
            $this->definition('email', 'reply_to_address', SettingType::String, 'Reply-to address', '', false, ['nullable', 'email:rfc', 'max:255']),
            $this->definition('email', 'notifications_enabled', SettingType::Boolean, 'Enable email notifications', true, false, ['required', 'boolean']),
            $this->definition('email', 'verification_emails', SettingType::Boolean, 'User verification emails', true, false, ['required', 'accepted'], 'This preference cannot disable Fortify email verification safeguards.'),
            $this->definition('email', 'order_emails', SettingType::Boolean, 'Order emails', true, false, ['required', 'boolean']),
            $this->definition('email', 'contact_notifications', SettingType::Boolean, 'Contact form notifications', true, false, ['required', 'boolean']),
            $this->definition('email', 'prayer_notifications', SettingType::Boolean, 'Prayer request notifications', true, false, ['required', 'boolean']),
            $this->definition('email', 'donation_notifications', SettingType::Boolean, 'Donation notifications', true, false, ['required', 'boolean']),
            $this->definition('email', 'notification_recipients', SettingType::Text, 'Notification recipients', '', false, ['nullable', 'string', 'max:2000'], 'One email address per line.'),
            $this->definition('email', 'provider_secret', SettingType::Secret, 'Mail provider secret', null, false, ['nullable', 'string', 'max:4000'], 'Leave blank to preserve the existing encrypted secret.', true),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function donations(): array
    {
        return [
            $this->definition('donations', 'enabled', SettingType::Boolean, 'Enable online giving', true, true, ['required', 'boolean']),
            $this->definition('localization', 'default_currency', SettingType::String, 'Default currency', 'USD', true, ['required', 'string', 'size:3', 'uppercase']),
            $this->definition('donations', 'suggested_amounts', SettingType::Json, 'Suggested amounts', [25, 50, 100, 250], true, ['required', 'json'], 'Enter a valid JSON array of positive amounts.'),
            $this->definition('donations', 'minimum_amount', SettingType::Decimal, 'Minimum donation', '1.00', true, ['required', 'numeric', 'min:0.01']),
            $this->definition('donations', 'allow_anonymous', SettingType::Boolean, 'Allow anonymous donations', true, true, ['required', 'boolean']),
            $this->definition('donations', 'require_phone', SettingType::Boolean, 'Require donor phone number', false, true, ['required', 'boolean']),
            $this->definition('donations', 'require_email', SettingType::Boolean, 'Require donor email', true, true, ['required', 'boolean']),
            $this->definition('donations', 'receipt_prefix', SettingType::String, 'Donation receipt prefix', 'DON', false, ['required', 'alpha_num', 'max:12']),
            $this->definition('donations', 'confirmation_message', SettingType::Text, 'Donation confirmation message', 'Thank you for your generous support.', false, ['nullable', 'string', 'max:3000']),
            $this->definition('donations', 'success_page_message', SettingType::Text, 'Donation success-page message', 'Your donation was received successfully.', true, ['nullable', 'string', 'max:3000']),
            $this->definition('donations', 'bank_transfer_instructions', SettingType::Text, 'Bank transfer instructions', '', true, ['nullable', 'string', 'max:5000'], 'Public instructions only. Do not enter passwords, PINs, or gateway secrets.'),
            $this->definition('donations', 'mobile_money_instructions', SettingType::Text, 'Mobile money instructions', '', true, ['nullable', 'string', 'max:5000'], 'Public instructions only. Do not enter PINs or provider secrets.'),
            $this->definition('donations', 'terms_text', SettingType::Text, 'Donation terms', '', true, ['nullable', 'string', 'max:10000']),
            $this->definition('donations', 'default_category', SettingType::String, 'Default donation category', 'General', false, ['nullable', 'string', 'max:120']),
            $this->definition('donations', 'notify_finance_team', SettingType::Boolean, 'Notify finance team', true, false, ['required', 'boolean']),
            $this->definition('donations', 'finance_notification_email', SettingType::String, 'Finance notification email', '', false, ['nullable', 'email:rfc', 'max:255']),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function integrations(): array
    {
        return [
            $this->definition('integrations', 'payment_gateway_enabled', SettingType::Boolean, 'Payment gateway enabled', false, false, ['required', 'boolean']),
            $this->definition('integrations', 'payment_gateway_public_key', SettingType::String, 'Payment gateway public key', '', false, ['nullable', 'string', 'max:1000']),
            $this->definition('integrations', 'payment_gateway_secret', SettingType::Secret, 'Payment gateway secret', null, false, ['nullable', 'string', 'max:4000'], 'Encrypted at rest. Leave blank to preserve it.', true),
            $this->definition('integrations', 'maps_api_key', SettingType::Secret, 'Maps API key', null, false, ['nullable', 'string', 'max:4000'], 'Encrypted at rest. Leave blank to preserve it.', true),
            $this->definition('integrations', 'youtube_enabled', SettingType::Boolean, 'YouTube integration enabled', false, false, ['required', 'boolean']),
            $this->definition('integrations', 'youtube_channel_id', SettingType::String, 'YouTube channel identifier', '', false, ['nullable', 'string', 'max:255']),
            $this->definition('integrations', 'facebook_enabled', SettingType::Boolean, 'Facebook integration enabled', false, false, ['required', 'boolean']),
            $this->definition('integrations', 'facebook_page_id', SettingType::String, 'Facebook page identifier', '', false, ['nullable', 'string', 'max:255']),
            $this->definition('integrations', 'analytics_id', SettingType::String, 'Analytics identifier', '', false, ['nullable', 'string', 'max:255']),
            $this->definition('integrations', 'tag_manager_id', SettingType::String, 'Google Tag Manager identifier', '', false, ['nullable', 'string', 'max:255']),
            $this->definition('integrations', 'mail_service_enabled', SettingType::Boolean, 'Mail service integration enabled', false, false, ['required', 'boolean']),
            $this->definition('integrations', 'sms_enabled', SettingType::Boolean, 'SMS provider enabled', false, false, ['required', 'boolean']),
            $this->definition('integrations', 'sms_secret', SettingType::Secret, 'SMS provider secret', null, false, ['nullable', 'string', 'max:4000'], 'Encrypted at rest. Leave blank to preserve it.', true),
            $this->definition('integrations', 'whatsapp_enabled', SettingType::Boolean, 'WhatsApp provider enabled', false, false, ['required', 'boolean']),
            $this->definition('integrations', 'whatsapp_secret', SettingType::Secret, 'WhatsApp provider secret', null, false, ['nullable', 'string', 'max:4000'], 'Encrypted at rest. Leave blank to preserve it.', true),
            $this->definition('integrations', 'cloud_storage_enabled', SettingType::Boolean, 'Cloud storage enabled', false, false, ['required', 'boolean']),
            $this->definition('integrations', 'cloud_storage_secret', SettingType::Secret, 'Cloud storage secret', null, false, ['nullable', 'string', 'max:4000'], 'Encrypted at rest. Leave blank to preserve it.', true),
            $this->definition('integrations', 'livestream_enabled', SettingType::Boolean, 'Livestream platform enabled', false, false, ['required', 'boolean']),
            $this->definition('integrations', 'livestream_identifier', SettingType::String, 'Livestream identifier', '', false, ['nullable', 'string', 'max:255']),
            $this->definition('integrations', 'configuration_status', SettingType::String, 'Configuration status', 'not_configured', false, ['required', 'in:not_configured,configured,verified'], 'Provider-specific connection tests are intentionally unavailable until a provider is configured.', options: ['not_configured' => 'Not configured', 'configured' => 'Configured', 'verified' => 'Verified']),
            $this->definition('integrations', 'last_connection_test_at', SettingType::String, 'Last successful connection test', '', false, ['nullable', 'date']),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function security(): array
    {
        return [
            $this->definition('security', 'require_email_verification', SettingType::Boolean, 'Require email verification', true, false, ['required', 'accepted'], 'Core protections cannot be disabled from this screen.'),
            $this->definition('security', 'require_password_confirmation', SettingType::Boolean, 'Require password confirmation for sensitive settings', true, false, ['required', 'accepted']),
            $this->definition('security', 'password_confirmation_timeout', SettingType::Integer, 'Password confirmation timeout (seconds)', 10800, false, ['required', 'integer', 'min:60', 'max:86400']),
            $this->definition('security', 'two_factor_available', SettingType::Boolean, 'Two-factor authentication available', true, false, ['required', 'boolean']),
            $this->definition('security', 'passkeys_available', SettingType::Boolean, 'Passkeys available', true, false, ['required', 'boolean']),
            $this->definition('security', 'email_login_enabled', SettingType::Boolean, 'Email login enabled', true, false, ['required', 'accepted']),
            $this->definition('security', 'username_login_enabled', SettingType::Boolean, 'Username login enabled', false, false, ['required', 'boolean']),
            $this->definition('security', 'maximum_login_attempts', SettingType::Integer, 'Maximum login attempts', 5, false, ['required', 'integer', 'min:1', 'max:20']),
            $this->definition('security', 'rate_limit_minutes', SettingType::Integer, 'Rate-limit duration (minutes)', 1, false, ['required', 'integer', 'min:1', 'max:1440']),
            $this->definition('security', 'session_lifetime_minutes', SettingType::Integer, 'Session lifetime (minutes)', (int) config('session.lifetime', 120), false, ['required', 'integer', 'min:5', 'max:10080']),
            $this->definition('security', 'logout_inactive_users', SettingType::Boolean, 'Log out inactive users', false, false, ['required', 'boolean']),
            $this->definition('security', 'browser_session_management', SettingType::Boolean, 'Enable browser session management', false, false, ['required', 'boolean']),
            $this->definition('security', 'force_admin_password_change', SettingType::Boolean, 'Force initial password change for admin-created users', true, false, ['required', 'accepted']),
            $this->definition('security', 'suspended_user_behavior', SettingType::String, 'Suspended user behavior', 'deny', false, ['required', 'in:deny,logout'], options: ['deny' => 'Deny access', 'logout' => 'Log out immediately']),
            $this->definition('security', 'suspicious_login_notifications', SettingType::Boolean, 'Notify on suspicious login', false, false, ['required', 'boolean']),
            $this->definition('activity_logs', 'retention_days', SettingType::Integer, 'Activity-log retention (days)', 365, false, ['required', 'integer', 'min:30', 'max:3650'], 'The scheduled pruning command applies this setting in bounded batches.'),
            $this->definition('activity_logs', 'retain_security_logs', SettingType::Boolean, 'Retain authentication logs permanently', true, false, ['required', 'boolean']),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function maintenance(): array
    {
        return [
            $this->definition('maintenance', 'enabled', SettingType::Boolean, 'Maintenance mode', false, true, ['required', 'boolean'], 'This preference is available to the public-site middleware when maintenance handling is enabled.'),
            $this->definition('maintenance', 'message', SettingType::Text, 'Maintenance message', 'We will be back soon.', true, ['required', 'string', 'max:3000']),
            $this->definition('maintenance', 'starts_at', SettingType::String, 'Scheduled start', '', true, ['nullable', 'date']),
            $this->definition('maintenance', 'ends_at', SettingType::String, 'Scheduled end', '', true, ['nullable', 'date', 'after:values.starts_at']),
            $this->definition('maintenance', 'allow_admin_access', SettingType::Boolean, 'Allow administrator access', true, false, ['required', 'accepted']),
            $this->definition('maintenance', 'show_countdown', SettingType::Boolean, 'Show countdown', false, true, ['required', 'boolean']),
            $this->definition('maintenance', 'contact_email', SettingType::String, 'Maintenance contact email', '', true, ['nullable', 'email:rfc', 'max:255']),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function localization(): array
    {
        return [
            ...$this->coreLocalization(),
            $this->definition('localization', 'supported_locales', SettingType::Json, 'Supported locales', ['en'], true, ['required', 'json']),
            $this->definition('localization', 'first_day_of_week', SettingType::String, 'First day of week', 'sunday', true, ['required', 'in:sunday,monday'], options: ['sunday' => 'Sunday', 'monday' => 'Monday']),
            $this->definition('localization', 'default_currency', SettingType::String, 'Default currency', 'USD', true, ['required', 'string', 'size:3']),
            $this->definition('localization', 'currency_display_format', SettingType::String, 'Currency display format', 'symbol', true, ['required', 'in:symbol,code'], options: ['symbol' => 'Symbol ($100)', 'code' => 'Code (USD 100)']),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    private function coreLocalization(): array
    {
        return [
            $this->definition('localization', 'default_timezone', SettingType::String, 'Default time zone', 'Africa/Accra', true, ['required', 'timezone']),
            $this->definition('localization', 'default_locale', SettingType::String, 'Default locale', config('app.locale', 'en'), true, ['required', 'string', 'max:20']),
            $this->definition('localization', 'date_format', SettingType::String, 'Date format', 'M j, Y', true, ['required', 'string', 'max:40']),
            $this->definition('localization', 'time_format', SettingType::String, 'Time format', 'g:i A', true, ['required', 'string', 'max:40']),
        ];
    }

    /**
     * @param  list<string>  $rules
     * @param  array<string, string>  $options
     * @return SettingDefinition
     */
    private function definition(
        string $group,
        string $key,
        SettingType $type,
        string $label,
        mixed $default,
        bool $public,
        array $rules,
        ?string $description = null,
        bool $encrypted = false,
        array $options = [],
    ): array {
        return [
            'group' => $group,
            'key' => $key,
            'type' => $type,
            'label' => $label,
            'description' => $description,
            'default' => $default,
            'public' => $public,
            'encrypted' => $encrypted,
            'rules' => $rules,
            'options' => $options,
        ];
    }
}
