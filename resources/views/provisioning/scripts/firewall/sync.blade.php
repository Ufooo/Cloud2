#!/bin/bash
set -e

echo "Configuring firewall rule for port {{ $port }}"

# Ensure UFW is installed
if ! command -v ufw >/dev/null 2>&1; then
    echo "UFW not installed, installing..."
    apt-get update && apt-get install -y ufw
fi

# Enable UFW if not already enabled
if ufw status | grep -q "Status: inactive"; then
    echo "Enabling UFW"
    ufw --force enable
fi

# Add the firewall rule
echo "Adding firewall rule: {{ $ruleName }}"
{{ $ufwRule }}

# Reload UFW
echo "Reloading firewall"
ufw reload

# Verify the rule was added
echo "Verifying firewall rule"
if ufw status numbered | grep -q "{{ $port }}"; then
    echo "Firewall rule for port {{ $port }} verified"
else
    echo "Warning: Could not verify firewall rule for port {{ $port }}"
    exit 1
fi

echo "Firewall rule installed successfully"
