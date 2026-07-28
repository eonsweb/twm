<?php

namespace App\Activity;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Stringable;

class ActivitySanitizer
{
    private const REDACTED = '[REDACTED]';

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitize(array $values): array
    {
        return collect($values)
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [
                (string) $key => $this->isSensitiveKey((string) $key)
                    ? self::REDACTED
                    : $this->sanitizeValue($value),
            ])
            ->all();
    }

    public function maskIdentifier(?string $identifier): ?string
    {
        $identifier = Str::lower(Str::squish((string) $identifier));

        if ($identifier === '') {
            return null;
        }

        if (Str::contains($identifier, '@')) {
            [$local, $domain] = array_pad(explode('@', $identifier, 2), 2, '');

            return $this->maskSegment($local).'@'.$this->maskSegment($domain);
        }

        return $this->maskSegment($identifier);
    }

    public function sanitizeText(?string $value, int $limit = 1000): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::limit(Str::squish($value), $limit);
    }

    public function isSensitiveKey(string $key): bool
    {
        $key = Str::of($key)->snake()->lower()->toString();

        return Str::is([
            'password',
            '*_password',
            'password_*',
            '*_token',
            'token',
            '*_secret',
            'secret',
            '*_key',
            'private_key',
            'credential',
            'credentials',
            '*_credential',
            'two_factor_*',
            'recovery_code*',
            'remember_token',
            'authorization',
            'cookie',
            'cookies',
            '_token',
            'csrf*',
            'session',
            'session_payload',
            'payload',
        ], $key);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => $this->sanitize($value),
            $value instanceof Model => [
                'type' => $value::class,
                'id' => $value->getKey(),
            ],
            $value instanceof UploadedFile => [
                'file' => Str::limit($value->getClientOriginalName(), 255),
                'mime' => $value->getMimeType(),
                'size' => $value->getSize(),
            ],
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof BackedEnum => $value->value,
            $value instanceof Stringable => $this->sanitizeText((string) $value, 500),
            is_string($value) => $this->sanitizeText($value, 500),
            is_scalar($value), $value === null => $value,
            default => '['.class_basename($value).']',
        };
    }

    private function maskSegment(string $value): string
    {
        if (Str::length($value) <= 2) {
            return Str::substr($value, 0, 1).'*';
        }

        return Str::substr($value, 0, 1)
            .str_repeat('*', min(8, Str::length($value) - 2))
            .Str::substr($value, -1);
    }
}
