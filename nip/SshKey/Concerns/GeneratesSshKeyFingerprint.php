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
        $keyData = preg_replace('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521)\s+/', '', trim($publicKey));
        $keyData = preg_replace('/\s+.*$/', '', $keyData);

        $decoded = base64_decode($keyData, true);

        if ($decoded === false) {
            return 'Invalid key';
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $decoded, true)), '=');
    }
}
