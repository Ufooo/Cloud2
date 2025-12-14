    # Create The Root SSH Directory If Necessary

if [ ! -d /root/.ssh ]
then
  mkdir -p /root/.ssh
  touch /root/.ssh/authorized_keys
fi

# Check Permissions Of /root Directory

chown root:root /root
chown -R root:root /root/.ssh

chmod 700 /root/.ssh
chmod 600 /root/.ssh/authorized_keys


# Disable MOTD
touch /root/.hushlogin
    # Setup netipar User

useradd netipar
mkdir -p /home/netipar/.ssh
mkdir -p /home/netipar/.netipar
adduser netipar sudo

# Setup Bash For netipar User

chsh -s /bin/bash netipar
cp /root/.profile /home/netipar/.profile
cp /root/.bashrc /home/netipar/.bashrc

chown -R netipar:netipar /home/netipar
chmod -R 755 /home/netipar

# Disable MOTD
touch /home/netipar/.hushlogin

# Authorize Server Public Key
cat > /root/.ssh/authorized_keys << EOF
{!! $sshPublicKey !!}
EOF

# Copy Root SSH Keys To netipar User
cp /root/.ssh/authorized_keys /home/netipar/.ssh/authorized_keys
chmod 600 /home/netipar/.ssh/authorized_keys
chown -R netipar:netipar /home/netipar/.ssh

# Disable Password Authentication Over SSH
if [ ! -d /etc/ssh/sshd_config.d ]; then mkdir /etc/ssh/sshd_config.d; fi

cat << EOF > /etc/ssh/sshd_config.d/49-netipar.conf
# This file is managed by Cloud.

PasswordAuthentication no

EOF

# Generate SSH Host Keys
ssh-keygen -A

systemctl reload ssh

    sed -i "s/#precedence ::ffff:0:0\/96  100/precedence ::ffff:0:0\/96  100/" /etc/gai.conf
    if [ -f /etc/needrestart/needrestart.conf ]; then
  # Ubuntu 22 has this set to (i)nteractive, but we want (a)utomatic.
  sed -i "s/^#\$nrconf{restart} = 'i';/\$nrconf{restart} = 'a';/" /etc/needrestart/needrestart.conf
fi

    provisionPing {{ $server->id }} 2
