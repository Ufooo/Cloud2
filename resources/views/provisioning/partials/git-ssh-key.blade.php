# Setup Git SSH Key for Repository Access

echo "Configuring Git SSH key..."

# Create SSH directory for netipar user if not exists
mkdir -p /home/netipar/.ssh
chmod 700 /home/netipar/.ssh

# Write the git private key
cat > /home/netipar/.ssh/id_ed25519 << 'GITPRIVATEKEYEOF'
{!! $gitPrivateKey !!}
GITPRIVATEKEYEOF

chmod 600 /home/netipar/.ssh/id_ed25519
chown netipar:netipar /home/netipar/.ssh/id_ed25519

# Configure SSH to use this key for git hosts
cat > /home/netipar/.ssh/config << 'SSHCONFIGEOF'
Host github.com
    User git
    IdentityFile ~/.ssh/id_ed25519
    StrictHostKeyChecking accept-new

Host gitlab.com
    User git
    IdentityFile ~/.ssh/id_ed25519
    StrictHostKeyChecking accept-new

Host bitbucket.org
    User git
    IdentityFile ~/.ssh/id_ed25519
    StrictHostKeyChecking accept-new
SSHCONFIGEOF

chmod 600 /home/netipar/.ssh/config
chown netipar:netipar /home/netipar/.ssh/config
chown -R netipar:netipar /home/netipar/.ssh

echo "Git SSH key configured."
