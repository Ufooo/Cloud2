#!/bin/bash
set -e

echo "Removing Unix user: {{ $username }}"

# Check if user exists
if ! id "{{ $username }}" &>/dev/null; then
    echo "User {{ $username }} does not exist, treating as successful deletion"
    exit 0
fi

#
# Remove PHP-FPM Pools for All PHP Versions
#

echo "Removing PHP-FPM pools for user {{ $username }}..."
@foreach($installedPhpVersions as $version)
if [ -f "/etc/php/{{ $version }}/fpm/pool.d/{{ $username }}.conf" ]; then
    rm -f /etc/php/{{ $version }}/fpm/pool.d/{{ $username }}.conf
    echo "Removed PHP {{ $version }} pool for {{ $username }}"
fi
@endforeach

#
# Remove User from Sudoers
#

echo "Removing user from sudoers..."
if [ -f "/etc/sudoers.d/php-fpm" ]; then
    sed -i "/^{{ $username }} ALL=NOPASSWD/d" /etc/sudoers.d/php-fpm
fi
if [ -f "/etc/sudoers.d/supervisor" ]; then
    sed -i "/^{{ $username }} ALL=NOPASSWD/d" /etc/sudoers.d/supervisor
fi

#
# Reload PHP-FPM Services
#

echo "Reloading PHP-FPM services..."
@foreach($installedPhpVersions as $version)
service php{{ $version }}-fpm reload > /dev/null 2>&1 || true
@endforeach

#
# Kill User Processes
#

echo "Checking for active processes for user {{ $username }}"
PROCESSES=$(ps -u "{{ $username }}" -o pid= 2>/dev/null || true)
if [ ! -z "$PROCESSES" ]; then
    echo "Killing processes for user {{ $username }}"
    pkill -u "{{ $username }}" || true
    sleep 2

    # Force kill if still running
    pkill -9 -u "{{ $username }}" || true
fi

#
# Delete Unix User
#

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
