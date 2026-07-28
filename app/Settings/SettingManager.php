<?php

namespace App\Settings;

use App\Activity\ActivityLogger;
use App\Models\SystemSetting;
use App\SettingType;
use App\SystemSettingSection;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class SettingManager
{
    public function __construct(
        private readonly CacheFactory $cache,
        private readonly SettingRegistry $registry,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $setting = $this->groupRows($group)[$key] ?? null;

        return $setting === null ? $default : $this->decode($setting, $default);
    }

    public function has(string $group, string $key): bool
    {
        return array_key_exists($key, $this->groupRows($group));
    }

    public function put(string $group, string $key, mixed $value): void
    {
        $definition = collect($this->registry->all())->first(
            fn (array $candidate): bool => $candidate['group'] === $group && $candidate['key'] === $key,
        );

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown system setting [{$group}.{$key}].");
        }

        $this->putMany([[...$definition, 'value' => $value]]);
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        return collect($this->groupRows($group))
            ->mapWithKeys(fn (array $setting, string $key): array => [$key => $this->decode($setting)])
            ->all();
    }

    /**
     * Encrypted values are excluded even if their database metadata is accidentally marked public.
     *
     * @return array<string, mixed>
     */
    public function publicGroup(string $group): array
    {
        return $this->cache->store()->rememberForever(
            $this->publicCacheKey($group),
            fn (): array => SystemSetting::query()
                ->where('group', $group)
                ->where('is_public', true)
                ->where('is_encrypted', false)
                ->get()
                ->mapWithKeys(function (SystemSetting $setting): array {
                    return [$setting->key => $this->decode($this->row($setting))];
                })
                ->all(),
        );
    }

    /**
     * @param  list<string>  $groups
     * @return array<string, array<string, mixed>>
     */
    public function publicGroups(array $groups): array
    {
        return collect($groups)
            ->mapWithKeys(fn (string $group): array => [$group => $this->publicGroup($group)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateSection(SystemSettingSection $section, array $values): void
    {
        $definitions = collect($this->registry->forSection($section))->keyBy('key');
        $updates = [];

        foreach ($values as $key => $value) {
            $definition = $definitions->get($key);

            if ($definition === null || ($definition['encrypted'] && ($value === null || $value === ''))) {
                continue;
            }

            $updates[] = [...$definition, 'value' => $value];
        }

        $this->putMany($updates);
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    public function putMany(array $definitions): void
    {
        if ($definitions === []) {
            return;
        }

        $auditChanges = $this->auditChanges($definitions);

        $groups = DB::transaction(function () use ($definitions): array {
            $groups = [];

            foreach ($definitions as $definition) {
                $groups[] = $definition['group'];

                SystemSetting::query()->updateOrCreate(
                    ['group' => $definition['group'], 'key' => $definition['key']],
                    [
                        'value' => $this->encode($definition['value'] ?? null, $definition['type'], (bool) $definition['encrypted']),
                        'type' => $definition['type'],
                        'label' => $definition['label'],
                        'description' => $definition['description'],
                        'is_public' => $definition['public'] && ! $definition['encrypted'],
                        'is_encrypted' => $definition['encrypted'],
                    ],
                );
            }

            return array_values(array_unique($groups));
        });

        foreach ($groups as $group) {
            $this->forgetGroup($group);
        }

        if (auth()->user() !== null) {
            foreach ($auditChanges as $group => $changes) {
                $this->activityLogger->log(
                    logName: 'system-settings',
                    event: "settings.{$group}.updated",
                    description: 'Updated '.Str::headline($group).' system settings.',
                    causer: auth()->user(),
                    properties: [
                        'group' => $group,
                        'keys' => array_keys([...$changes['new'], ...$changes['sensitive']]),
                        'sensitive_changes' => array_values($changes['sensitive']),
                    ],
                    oldValues: $changes['old'],
                    newValues: $changes['new'],
                );
            }
        }
    }

    public function initializeDefaults(): void
    {
        $groups = DB::transaction(function (): array {
            $groups = [];

            foreach ($this->registry->all() as $definition) {
                $groups[] = $definition['group'];
                $setting = SystemSetting::query()->firstOrNew([
                    'group' => $definition['group'],
                    'key' => $definition['key'],
                ]);

                if (! $setting->exists) {
                    $setting->value = $this->encode($definition['default'], $definition['type'], $definition['encrypted']);
                }

                $setting->fill([
                    'type' => $definition['type'],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'is_public' => $definition['public'] && ! $definition['encrypted'],
                    'is_encrypted' => $definition['encrypted'],
                ])->save();
            }

            return array_values(array_unique($groups));
        });

        foreach ($groups as $group) {
            $this->forgetGroup($group);
        }
    }

    public function isStoredPathReferenced(string $path, string $exceptGroup, string $exceptKey): bool
    {
        return SystemSetting::query()
            ->where('value', $path)
            ->where(function ($query) use ($exceptGroup, $exceptKey): void {
                $query->where('group', '!=', $exceptGroup)
                    ->orWhere('key', '!=', $exceptKey);
            })
            ->exists();
    }

    public function forgetGroup(string $group): void
    {
        $this->cache->store()->forget($this->cacheKey($group));
        $this->cache->store()->forget($this->publicCacheKey($group));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function groupRows(string $group): array
    {
        return $this->cache->store()->rememberForever(
            $this->cacheKey($group),
            fn (): array => SystemSetting::query()
                ->where('group', $group)
                ->get()
                ->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $this->row($setting)])
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(SystemSetting $setting): array
    {
        return [
            'value' => $setting->getRawOriginal('value'),
            'type' => $setting->getRawOriginal('type'),
            'is_encrypted' => $setting->is_encrypted,
        ];
    }

    /**
     * @param  array<string, mixed>  $setting
     */
    private function decode(array $setting, mixed $default = null): mixed
    {
        $value = $setting['value'];

        if ($value === null) {
            return $default;
        }

        if ($setting['is_encrypted']) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException) {
                return $default;
            }
        }

        try {
            return match (SettingType::from($setting['type'])) {
                SettingType::Integer => (int) $value,
                SettingType::Decimal => (float) $value,
                SettingType::Boolean => $value === '1',
                SettingType::Json => json_decode($value, true, flags: JSON_THROW_ON_ERROR),
                default => $value,
            };
        } catch (JsonException) {
            return $default;
        }
    }

    private function encode(mixed $value, SettingType $type, bool $encrypted): ?string
    {
        if ($value === null) {
            return null;
        }

        $encoded = match ($type) {
            SettingType::Boolean => $value ? '1' : '0',
            SettingType::Json => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };

        return $encrypted ? Crypt::encryptString($encoded) : $encoded;
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @return array<string, array{old: array<string, mixed>, new: array<string, mixed>, sensitive: array<string, string>}>
     */
    private function auditChanges(array $definitions): array
    {
        if (auth()->user() === null) {
            return [];
        }

        $groups = collect($definitions)->pluck('group')->unique()->all();
        $existingSettings = SystemSetting::query()
            ->whereIn('group', $groups)
            ->get()
            ->keyBy(fn (SystemSetting $setting): string => $setting->group.'.'.$setting->key);
        $changes = [];

        foreach ($definitions as $definition) {
            $group = $definition['group'];
            $key = $definition['key'];
            $existing = $existingSettings->get($group.'.'.$key);

            if ($definition['encrypted']) {
                $changes[$group]['sensitive'][$key] = $existing === null ? 'Added' : 'Updated';

                continue;
            }

            $oldValue = $existing === null ? null : $this->decode($this->row($existing));
            $newValue = $definition['value'] ?? null;

            if ($oldValue == $newValue) {
                continue;
            }

            $changes[$group]['old'][$key] = $oldValue;
            $changes[$group]['new'][$key] = $newValue;
            $changes[$group]['sensitive'] ??= [];
        }

        return collect($changes)
            ->map(fn (array $change): array => [
                'old' => $change['old'] ?? [],
                'new' => $change['new'] ?? [],
                'sensitive' => $change['sensitive'],
            ])
            ->filter(fn (array $change): bool => $change['old'] !== [] || $change['new'] !== [] || $change['sensitive'] !== [])
            ->all();
    }

    private function cacheKey(string $group): string
    {
        return "system-settings.group.{$group}";
    }

    private function publicCacheKey(string $group): string
    {
        return "system-settings.public.{$group}";
    }
}
