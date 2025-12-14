    echo "netipar ALL=NOPASSWD: /usr/sbin/service php8.4-fpm reload" > /etc/sudoers.d/php-fpm
echo "netipar ALL=NOPASSWD: /usr/sbin/service php8.3-fpm reload" >> /etc/sudoers.d/php-fpm
echo "netipar ALL=NOPASSWD: /usr/sbin/service php8.2-fpm reload" >> /etc/sudoers.d/php-fpm
echo "netipar ALL=NOPASSWD: /usr/sbin/service php8.1-fpm reload" >> /etc/sudoers.d/php-fpm
echo "netipar ALL=NOPASSWD: /usr/sbin/service php8.0-fpm reload" >> /etc/sudoers.d/php-fpm
echo "netipar ALL=NOPASSWD: /usr/sbin/service php7.4-fpm reload" >> /etc/sudoers.d/php-fpm

echo "netipar ALL=NOPASSWD: /usr/sbin/service nginx *" >> /etc/sudoers.d/nginx

echo "netipar ALL=NOPASSWD: /usr/bin/supervisorctl reload" >> /etc/sudoers.d/supervisor
echo "netipar ALL=NOPASSWD: /usr/bin/supervisorctl reread" >> /etc/sudoers.d/supervisor
echo "netipar ALL=NOPASSWD: /usr/bin/supervisorctl restart *" >> /etc/sudoers.d/supervisor
echo "netipar ALL=NOPASSWD: /usr/bin/supervisorctl start *" >> /etc/sudoers.d/supervisor
echo "netipar ALL=NOPASSWD: /usr/bin/supervisorctl status *" >> /etc/sudoers.d/supervisor
echo "netipar ALL=NOPASSWD: /usr/bin/supervisorctl status" >> /etc/sudoers.d/supervisor
echo "netipar ALL=NOPASSWD: /usr/bin/supervisorctl stop *" >> /etc/sudoers.d/supervisor
echo "netipar ALL=NOPASSWD: /usr/bin/supervisorctl update *" >> /etc/sudoers.d/supervisor
echo "netipar ALL=NOPASSWD: /usr/bin/supervisorctl update" >> /etc/sudoers.d/supervisor

# Set The Hostname If Necessary
echo "{{ Str::slug($server->name) }}" > /etc/hostname
sed -i 's/127\.0\.0\.1.*localhost/127.0.0.1	{{ Str::slug($server->name) }}.localdomain {{ Str::slug($server->name) }} localhost/' /etc/hosts
hostname {{ Str::slug($server->name) }}

# Set The Sudo Password For netipar

PASSWORD=$(mkpasswd -m sha-512 '{{ $sudoPassword }}')
usermod --password $PASSWORD netipar
# Create The Server SSH Key

ssh-keygen -f /home/netipar/.ssh/id_rsa -t ed25519 -N ''
chown -R netipar:netipar /home/netipar/.ssh
chmod 700 /home/netipar/.ssh/id_rsa
apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y --force-yes sendmail
# Copy Source Control Public Keys Into Known Hosts File

ssh-keyscan -H github.com >> /home/netipar/.ssh/known_hosts
ssh-keyscan -H bitbucket.org >> /home/netipar/.ssh/known_hosts
ssh-keyscan -H gitlab.com >> /home/netipar/.ssh/known_hosts

chown netipar:netipar /home/netipar/.ssh/known_hosts
# Configure Git Settings

git config --global user.name "netipar"
git config --global user.email "netipar@localhost"
# Setup UFW Firewall

ufw allow 22
ufw allow 80
ufw allow 443


ufw --force enable

provisionPing {{ $server->id }} 4
