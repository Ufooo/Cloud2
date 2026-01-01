#!/bin/bash
set -e

echo "Creating Unix user: {{ $username }}"

# Check if user already exists
if id "{{ $username }}" &>/dev/null; then
    echo "User {{ $username }} already exists, skipping creation"
    exit 0
fi

# Create the user with home directory and bash shell
useradd -m \
    -d "{{ $homeDir }}" \
    -s /bin/bash \
    "{{ $username }}"

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to create user"
    exit 1
fi

# Set home directory permissions to 755
chmod 755 "{{ $homeDir }}"

# Create .ssh directory for the user
mkdir -p "{{ $homeDir }}/.ssh"
chmod 700 "{{ $homeDir }}/.ssh"

# Add server SSH key to authorized_keys
cat > "{{ $homeDir }}/.ssh/authorized_keys" << 'EOF'
# Netipar Cloud Server Key
{{ $serverPublicKey }}
EOF

chmod 600 "{{ $homeDir }}/.ssh/authorized_keys"
chown -R "{{ $username }}:{{ $username }}" "{{ $homeDir }}/.ssh"

# Configure sudoers permissions
echo "Configuring sudoers for {{ $username }}..."

# Allow PHP-FPM reload without password
grep -q "^{{ $username }} " /etc/sudoers.d/php-fpm 2>/dev/null || echo "{{ $username }} ALL=NOPASSWD: /usr/sbin/service php*-fpm reload" >> /etc/sudoers.d/php-fpm

# Allow Supervisor control without password
grep -q "^{{ $username }} " /etc/sudoers.d/supervisor 2>/dev/null || echo "{{ $username }} ALL=NOPASSWD: /usr/bin/supervisorctl *" >> /etc/sudoers.d/supervisor

# Allow Nginx control without password
grep -q "^{{ $username }} .*nginx reload" /etc/sudoers.d/nginx 2>/dev/null || echo "{{ $username }} ALL=NOPASSWD: /usr/sbin/service nginx *" >> /etc/sudoers.d/nginx

# Verify user was created
if id "{{ $username }}" &>/dev/null; then
    echo "User {{ $username }} created successfully"
    echo "Home directory: {{ $homeDir }}"
else
    echo "ERROR: User creation verification failed"
    exit 1
fi
