<?php

namespace Nip\SshKey\Concerns;

trait GeneratesSshKeyFingerprint
{
    protected static function bootGeneratesSshKeyFingerprint(): void
    {
        static::creating(function ($model) {
            if (empty($model->fingerprint) && ! empty($model->public_key)) {
                $model->fingerprint = static::generateFingerprint($model->public_key);
            }
        });
    }

    public static function generateFingerprint(string $publicKey): string
    {
        // Normalize: remove newlines and extra whitespace
        $publicKey = preg_replace('/\s+/', ' ', trim($publicKey));

        // Extract the base64 key data (second part after key type)
        $parts = explode(' ', $publicKey);

        if (count($parts) < 2) {
            return 'Invalid key';
        }

        $keyData = $parts[1];
        $decoded = base64_decode($keyData, true);

        if ($decoded === false) {
            return 'Invalid key';
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $decoded, true)), '=');
    }
}
