#!/bin/bash
set -e

AUTH_KEYS="{{ $homeDir }}/.ssh/authorized_keys"

if [ -f "${AUTH_KEYS}" ]; then
    # Remove the comment line and the key line (next line after comment)
    sed -i '/# Netipar\[{{ $keyId }}\]:/,+1d' "${AUTH_KEYS}"
    chown {{ $username }}:{{ $username }} "${AUTH_KEYS}"
    chmod 600 "${AUTH_KEYS}"
    echo "SSH key removed successfully"
else
    echo "authorized_keys file not found"
fi
