@if(version_compare($mariadbVersion, '11.0', '<'))
    # Add MariaDB Repository for {{ $mariadbVersion }}
sudo apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0xF1656F24C74CD1D8

sudo add-apt-repository -y -n 'deb [arch=amd64,arm64] https://archive.mariadb.org/mariadb-{{ $mariadbVersion }}/repo/ubuntu/ noble main'

apt-get update -o Acquire::AllowReleaseInfoChange=true

debconf-set-selections <<< "mariadb-server mysql-server/data-dir select ''"
debconf-set-selections <<< "mariadb-server mysql-server/root_password password {{ $databasePassword }}"
debconf-set-selections <<< "mariadb-server mysql-server/root_password_again password {{ $databasePassword }}"

@endif
    # Install MariaDB {{ $mariadbVersion }}
apt-get install -y mariadb-server="1:{{ $mariadbVersion }}*"

# Activate Error Log

sed -i 's/#log_error = \/var\/log\/mysql\/error\.log/log_error = \/var\/log\/mysql\/error\.log/' /etc/mysql/mariadb.conf.d/50-server.cnf

# Configure Password Expiration

# echo "default_password_lifetime = 0" >> /etc/mysql/my.cnf

# Configure Max Connections

RAM=$(awk '/^MemTotal:/{printf "%3.0f", $2 / (1024 * 1024)}' /proc/meminfo)
MAX_CONNECTIONS=$(( 70 * $RAM ))
REAL_MAX_CONNECTIONS=$(( MAX_CONNECTIONS>70 ? MAX_CONNECTIONS : 100 ))
sed -i "s/^#max_connections.*=.*/max_connections=${REAL_MAX_CONNECTIONS}/" /etc/mysql/mariadb.conf.d/50-server.cnf

# Configure Access Permissions For Root & Cloud Users

sed -i '/^bind-address/s/bind-address.*=.*/bind-address = */' /etc/mysql/mariadb.conf.d/50-server.cnf
mysql --user="root" --password="{{ $databasePassword }}" -e "GRANT ALL ON *.* TO root@'{{ $server->private_ip_address }}' IDENTIFIED BY '{{ $databasePassword }}';"
mysql --user="root" --password="{{ $databasePassword }}" -e "GRANT ALL ON *.* TO root@'%' IDENTIFIED BY '{{ $databasePassword }}';"
service mariadb restart

mysql --user="root" --password="{{ $databasePassword }}" -e "CREATE USER 'netipar'@'{{ $server->private_ip_address }}' IDENTIFIED BY '{{ $databasePassword }}';"
mysql --user="root" --password="{{ $databasePassword }}" -e "GRANT ALL ON *.* TO 'netipar'@'{{ $server->private_ip_address }}' IDENTIFIED BY '{{ $databasePassword }}' WITH GRANT OPTION;"
mysql --user="root" --password="{{ $databasePassword }}" -e "GRANT ALL ON *.* TO 'netipar'@'%' IDENTIFIED BY '{{ $databasePassword }}' WITH GRANT OPTION;"
mysql --user="root" --password="{{ $databasePassword }}" -e "FLUSH PRIVILEGES;"

# Create The Initial Database If Specified

mysql --user="root" --password="{{ $databasePassword }}" -e "CREATE DATABASE netipar;"

if [[ $(grep --count "maxsize" /etc/logrotate.d/mariadb) == 0 ]]; then
    sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" /etc/logrotate.d/mariadb
else
    sed -i -r "s/^(\s*)maxsize.*$/\1maxsize 100M/" /etc/logrotate.d/mariadb
fi

    provisionPing {{ $server->id }} 7
