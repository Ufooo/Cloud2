    # Ensure apt-get Is Up To Date
apt-get update -o Acquire::AllowReleaseInfoChange=true

# Install Base PHP Packages

apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y --force-yes \
php{{ $phpVersion }}-fpm php{{ $phpVersion }}-cli php{{ $phpVersion }}-dev \
php{{ $phpVersion }}-pgsql php{{ $phpVersion }}-sqlite3 php{{ $phpVersion }}-gd php{{ $phpVersion }}-curl \
php{{ $phpVersion }}-imap php{{ $phpVersion }}-mysql php{{ $phpVersion }}-mbstring \
php{{ $phpVersion }}-xml php{{ $phpVersion }}-zip php{{ $phpVersion }}-bcmath php{{ $phpVersion }}-soap \
php{{ $phpVersion }}-intl php{{ $phpVersion }}-readline php{{ $phpVersion }}-gmp \
php{{ $phpVersion }}-redis php{{ $phpVersion }}-memcached php{{ $phpVersion }}-msgpack php{{ $phpVersion }}-igbinary

@if(version_compare($phpVersion, '8.0', '>='))
apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y --force-yes php{{ $phpVersion }}-swoole
@endif

@if(version_compare($phpVersion, '8.0', '<'))
apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y --force-yes php{{ $phpVersion }}-json
@endif

# Install Composer Package Manager

if [ ! -f /usr/local/bin/composer ]; then
  curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

echo "netipar ALL=(root) NOPASSWD: /usr/local/bin/composer self-update*" > /etc/sudoers.d/composer
fi

# Misc. PHP CLI Configuration

sudo sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/{{ $phpVersion }}/cli/php.ini
sudo sed -i "s/display_errors = .*/display_errors = On/" /etc/php/{{ $phpVersion }}/cli/php.ini
sudo sed -i "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/" /etc/php/{{ $phpVersion }}/cli/php.ini
sudo sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/{{ $phpVersion }}/cli/php.ini
sudo sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/{{ $phpVersion }}/cli/php.ini

# Misc. PHP FPM Configuration

sudo sed -i "s/display_errors = .*/display_errors = Off/" /etc/php/{{ $phpVersion }}/fpm/php.ini



# Configure FPM Pool Settings

sed -i "s/^user = www-data/user = netipar/" /etc/php/{{ $phpVersion }}/fpm/pool.d/www.conf
sed -i "s/^group = www-data/group = netipar/" /etc/php/{{ $phpVersion }}/fpm/pool.d/www.conf
sed -i "s/;listen\.owner.*/listen.owner = netipar/" /etc/php/{{ $phpVersion }}/fpm/pool.d/www.conf
sed -i "s/;listen\.group.*/listen.group = netipar/" /etc/php/{{ $phpVersion }}/fpm/pool.d/www.conf
sed -i "s/;listen\.mode.*/listen.mode = 0666/" /etc/php/{{ $phpVersion }}/fpm/pool.d/www.conf
sed -i "s/;request_terminate_timeout .*/request_terminate_timeout = 60/" /etc/php/{{ $phpVersion }}/fpm/pool.d/www.conf

# Optimize FPM Processes

sed -i "s/^pm.max_children.*=.*/pm.max_children = 20/" /etc/php/{{ $phpVersion }}/fpm/pool.d/www.conf

# Ensure Sudoers Is Up To Date

LINE="ALL=NOPASSWD: /usr/sbin/service php{{ $phpVersion }}-fpm reload"
FILE="/etc/sudoers.d/php-fpm"
grep -q -- "^netipar $LINE" "$FILE" || echo "netipar $LINE" >> "$FILE"

# Configure Sessions Directory Permissions

chmod 733 /var/lib/php/sessions
chmod +t /var/lib/php/sessions

# Write Systemd File For Linode








if [[ $(grep --count "maxsize" /etc/logrotate.d/php{{ $phpVersion }}-fpm) == 0 ]]; then
    sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" /etc/logrotate.d/php{{ $phpVersion }}-fpm
else
    sed -i -r "s/^(\s*)maxsize.*$/\1maxsize 100M/" /etc/logrotate.d/php{{ $phpVersion }}-fpm
fi
    update-alternatives --set php /usr/bin/php{{ $phpVersion }}

provisionPing {{ $server->id }} 5
