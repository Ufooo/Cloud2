<?php

namespace Nip\SshKey\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SshPublicKeyRule implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $pattern = '/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521)\s+[A-Za-z0-9+\/=]+/';

        if (! preg_match($pattern, $value)) {
            $fail('The :attribute must be a valid SSH public key (ssh-rsa, ssh-ed25519, or ecdsa).');
        }
    }
}
