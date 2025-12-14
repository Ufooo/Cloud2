    # Install & Configure Redis Server

apt-get install -y redis-server
sed -i 's/bind 127.0.0.1/bind 0.0.0.0/' /etc/redis/redis.conf
service redis-server restart
systemctl enable redis-server

yes '' | pecl install -f redis

# Ensure PHPRedis extension is available
if pecl list | grep redis >/dev/null 2>&1;
then
echo "Configuring PHPRedis"
fi

if [[ $(grep --count "maxsize" /etc/logrotate.d/redis-server) == 0 ]]; then
    sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" /etc/logrotate.d/redis-server
else
    sed -i -r "s/^(\s*)maxsize.*$/\1maxsize 100M/" /etc/logrotate.d/redis-server
fi

    provisionPing {{ $server->id }} 8
