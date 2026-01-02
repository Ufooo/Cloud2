{{-- PHP Configuration --}}
{{-- Variables: $version, $unixUsers (optional, defaults to ['netipar']) --}}
@php
    $unixUsers = $unixUsers ?? ['netipar'];
@endphp

#
# Install Composer (if not present)
#

if [ ! -f /usr/local/bin/composer ]; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    echo "netipar ALL=(root) NOPASSWD: /usr/local/bin/composer self-update*" > /etc/sudoers.d/composer
fi

#
# PHP CLI Configuration
#

sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/{{ $version }}/cli/php.ini
sed -i "s/display_errors = .*/display_errors = On/" /etc/php/{{ $version }}/cli/php.ini
sed -i "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/" /etc/php/{{ $version }}/cli/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/{{ $version }}/cli/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/{{ $version }}/cli/php.ini

#
# PHP FPM Configuration
#

sed -i "s/display_errors = .*/display_errors = Off/" /etc/php/{{ $version }}/fpm/php.ini
sed -i "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/" /etc/php/{{ $version }}/fpm/php.ini
sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/{{ $version }}/fpm/php.ini
sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/{{ $version }}/fpm/php.ini

#
# PHP Extensions
#

# PHPRedis Extension
echo "Configuring PHPRedis..."
echo "extension=redis.so" > /etc/php/{{ $version }}/mods-available/redis.ini
apt-get install -y php{{ $version }}-redis > /dev/null 2>&1 || true

# Imagick Extension
echo "Configuring Imagick..."
apt-get install -y libmagickwand-dev > /dev/null 2>&1 || true
echo "extension=imagick.so" > /etc/php/{{ $version }}/mods-available/imagick.ini
apt-get install -y php{{ $version }}-imagick > /dev/null 2>&1 || true

#
# FPM Pool Settings
#

sed -i "s/^user = www-data/user = netipar/" /etc/php/{{ $version }}/fpm/pool.d/www.conf
sed -i "s/^group = www-data/group = netipar/" /etc/php/{{ $version }}/fpm/pool.d/www.conf
sed -i "s/;listen\.owner.*/listen.owner = netipar/" /etc/php/{{ $version }}/fpm/pool.d/www.conf
sed -i "s/;listen\.group.*/listen.group = netipar/" /etc/php/{{ $version }}/fpm/pool.d/www.conf
sed -i "s/;listen\.mode.*/listen.mode = 0666/" /etc/php/{{ $version }}/fpm/pool.d/www.conf
sed -i "s/;request_terminate_timeout .*/request_terminate_timeout = 60/" /etc/php/{{ $version }}/fpm/pool.d/www.conf

# Optimize FPM Processes
sed -i "s/^pm.max_children.*=.*/pm.max_children = 20/" /etc/php/{{ $version }}/fpm/pool.d/www.conf

#
# Sudoers for PHP-FPM reload (all unix users)
#

LINE="ALL=NOPASSWD: /usr/sbin/service php{{ $version }}-fpm reload"
FILE="/etc/sudoers.d/php-fpm"
@foreach($unixUsers as $username)
grep -q -- "^{{ $username }} $LINE" "$FILE" 2>/dev/null || echo "{{ $username }} $LINE" >> "$FILE"
@endforeach

#
# Configure Sessions Directory Permissions
#

chmod 733 /var/lib/php/sessions
chmod +t /var/lib/php/sessions

#
# Configure logrotate for PHP-FPM
#

if [[ $(grep --count "maxsize" /etc/logrotate.d/php{{ $version }}-fpm 2>/dev/null) == 0 ]]; then
    sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" /etc/logrotate.d/php{{ $version }}-fpm 2>/dev/null || true
else
    sed -i -r "s/^(\s*)maxsize.*$/\1maxsize 100M/" /etc/logrotate.d/php{{ $version }}-fpm 2>/dev/null || true
fi
