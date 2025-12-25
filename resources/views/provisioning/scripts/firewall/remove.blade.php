#!/bin/bash
set -e

echo "Deleting firewall rule for port {{ $port }}"

# Find rule by port and type
RULE_NUMBERS=$(ufw status numbered | grep "{{ $port }}" | grep "{{ $type }}" | grep -oP '^\[\s*\K[0-9]+' | sort -rn)

if [ -z "$RULE_NUMBERS" ]; then
    echo "Warning: Firewall rule for port {{ $port }} not found in UFW"
    exit 0  # Not an error - rule might already be deleted
fi

# Delete each matching rule (in reverse order to maintain rule numbers)
for RULE_NUM in $RULE_NUMBERS; do
    echo "Deleting UFW rule number $RULE_NUM"
    echo "y" | ufw delete $RULE_NUM

    if [ $? -eq 0 ]; then
        echo "Successfully deleted UFW rule $RULE_NUM"
    else
        echo "Error: Failed to delete UFW rule $RULE_NUM"
        exit 1
    fi
done

# Reload UFW
echo "Reloading firewall"
ufw reload

echo "Firewall rule for port {{ $port }} successfully removed"
