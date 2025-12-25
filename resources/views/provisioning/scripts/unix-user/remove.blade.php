#!/bin/bash
set -e

echo "Removing Unix user: {{ $username }}"

# Check if user exists
if ! id "{{ $username }}" &>/dev/null; then
    echo "User {{ $username }} does not exist, treating as successful deletion"
    exit 0
fi

# Kill all processes belonging to the user
echo "Checking for active processes for user {{ $username }}"
PROCESSES=$(ps -u "{{ $username }}" -o pid= 2>/dev/null || true)
if [ ! -z "$PROCESSES" ]; then
    echo "Killing processes for user {{ $username }}"
    pkill -u "{{ $username }}" || true
    sleep 2

    # Force kill if still running
    pkill -9 -u "{{ $username }}" || true
fi

# Delete the user and their home directory
userdel -r "{{ $username }}"

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to delete user"
    exit 1
fi

# Verify user was deleted
if ! id "{{ $username }}" &>/dev/null; then
    echo "User {{ $username }} deleted successfully"

    # Verify home directory was removed
    if [ ! -d "{{ $homeDir }}" ]; then
        echo "Home directory {{ $homeDir }} removed"
    else
        echo "Removing remaining home directory {{ $homeDir }}"
        rm -rf "{{ $homeDir }}" || true
    fi
else
    echo "ERROR: User deletion verification failed"
    exit 1
fi
