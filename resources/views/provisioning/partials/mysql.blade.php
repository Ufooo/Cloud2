    export DEBIAN_FRONTEND=noninteractive

# Add MySQL Keys...

sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 467B942D3A79BD29

# Configure MySQL Repositories If Required

# Convert a version string into an integer.

function version { echo "$@" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }'; }

UBUNTU_VERSION=$(lsb_release -rs)
echo "Server on Ubuntu ${UBUNTU_VERSION}"
if [ $(version $UBUNTU_VERSION) -le $(version "20.04") ]; then
    wget -c https://dev.mysql.com/get/mysql-apt-config_0.8.15-1_all.deb
    dpkg --install mysql-apt-config_0.8.15-1_all.deb

    apt-get update
fi

# Set The Automated Root Password

debconf-set-selections <<< "mysql-community-server mysql-community-server/data-dir select ''"
debconf-set-selections <<< "mysql-community-server mysql-community-server/root-pass password {{ $databasePassword }}"
debconf-set-selections <<< "mysql-community-server mysql-community-server/re-root-pass password {{ $databasePassword }}"

# Install MySQL

apt-get install -y mysql-community-server
apt-get install -y mysql-server

# Configure Password Expiration

echo "default_password_lifetime = 0" >> /etc/mysql/mysql.conf.d/mysqld.cnf

# Set Character Set

echo "" >> /etc/mysql/my.cnf
echo "[mysqld]" >> /etc/mysql/my.cnf
echo "default_authentication_plugin=mysql_native_password" >> /etc/mysql/my.cnf
echo "skip-log-bin" >> /etc/mysql/my.cnf

# Configure Max Connections

RAM=$(awk '/^MemTotal:/{printf "%3.0f", $2 / (1024 * 1024)}' /proc/meminfo)
MAX_CONNECTIONS=$(( 70 * $RAM ))
REAL_MAX_CONNECTIONS=$(( MAX_CONNECTIONS>70 ? MAX_CONNECTIONS : 100 ))
sed -i "s/^max_connections.*=.*/max_connections=${REAL_MAX_CONNECTIONS}/" /etc/mysql/my.cnf

# Configure Access Permissions For Root & netipar Users

if grep -q "bind-address" /etc/mysql/mysql.conf.d/mysqld.cnf; then
  sed -i '/^bind-address/s/bind-address.*=.*/bind-address = */' /etc/mysql/mysql.conf.d/mysqld.cnf
else
  echo "bind-address = *" >> /etc/mysql/mysql.conf.d/mysqld.cnf
fi

export MYSQL_TEST_LOGIN_FILE="$(mktemp --suffix .mylogin.cnf)"

cat > "$MYSQL_TEST_LOGIN_FILE" <<EOF
[client]
user=root
password={{ $databasePassword }}
EOF

chmod 600 "$MYSQL_TEST_LOGIN_FILE"

mysql -e "CREATE USER 'root'@'{{ $server->private_ip_address }}' IDENTIFIED BY '{{ $databasePassword }}';"
mysql -e "CREATE USER 'root'@'%' IDENTIFIED BY '{{ $databasePassword }}';"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO root@'{{ $server->private_ip_address }}' WITH GRANT OPTION;"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO root@'%' WITH GRANT OPTION;"
service mysql restart

mysql -e "CREATE USER 'netipar'@'{{ $server->private_ip_address }}' IDENTIFIED BY '{{ $databasePassword }}';"
mysql -e "CREATE USER 'netipar'@'%' IDENTIFIED BY '{{ $databasePassword }}';"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'netipar'@'{{ $server->private_ip_address }}' WITH GRANT OPTION;"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'netipar'@'%' WITH GRANT OPTION;"
mysql -e "FLUSH PRIVILEGES;"

# Create The Initial Database If Specified

mysql -e "CREATE DATABASE netipar CHARACTER SET utf8 COLLATE utf8_unicode_ci;"

rm -f "$MYSQL_TEST_LOGIN_FILE"
unset MYSQL_TEST_LOGIN_FILE

if [[ $(grep --count "maxsize" /etc/logrotate.d/mysql-server) == 0 ]]; then
    sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" /etc/logrotate.d/mysql-server
else
    sed -i -r "s/^(\s*)maxsize.*$/\1maxsize 100M/" /etc/logrotate.d/mysql-server
fi

    # If MySQL Fails To Start, Re-Install It

    service mysql restart

    if [[ $? -ne 0 ]]; then
        echo "Purging previous MySQL8 installation..."

        sudo apt-get purge mysql-server mysql-community-server
        sudo apt-get autoclean && sudo apt-get clean

        export DEBIAN_FRONTEND=noninteractive

# Add MySQL Keys...

sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 467B942D3A79BD29

# Configure MySQL Repositories If Required

# Convert a version string into an integer.

function version { echo "$@" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }'; }

UBUNTU_VERSION=$(lsb_release -rs)
echo "Server on Ubuntu ${UBUNTU_VERSION}"
if [ $(version $UBUNTU_VERSION) -le $(version "20.04") ]; then
    wget -c https://dev.mysql.com/get/mysql-apt-config_0.8.15-1_all.deb
    dpkg --install mysql-apt-config_0.8.15-1_all.deb

    apt-get update
fi

# Set The Automated Root Password

debconf-set-selections <<< "mysql-community-server mysql-community-server/data-dir select ''"
debconf-set-selections <<< "mysql-community-server mysql-community-server/root-pass password {{ $databasePassword }}"
debconf-set-selections <<< "mysql-community-server mysql-community-server/re-root-pass password {{ $databasePassword }}"

# Install MySQL

apt-get install -y mysql-community-server
apt-get install -y mysql-server

# Configure Password Expiration

echo "default_password_lifetime = 0" >> /etc/mysql/mysql.conf.d/mysqld.cnf

# Set Character Set

echo "" >> /etc/mysql/my.cnf
echo "[mysqld]" >> /etc/mysql/my.cnf
echo "default_authentication_plugin=mysql_native_password" >> /etc/mysql/my.cnf
echo "skip-log-bin" >> /etc/mysql/my.cnf

# Configure Max Connections

RAM=$(awk '/^MemTotal:/{printf "%3.0f", $2 / (1024 * 1024)}' /proc/meminfo)
MAX_CONNECTIONS=$(( 70 * $RAM ))
REAL_MAX_CONNECTIONS=$(( MAX_CONNECTIONS>70 ? MAX_CONNECTIONS : 100 ))
sed -i "s/^max_connections.*=.*/max_connections=${REAL_MAX_CONNECTIONS}/" /etc/mysql/my.cnf

# Configure Access Permissions For Root & netipar Users

if grep -q "bind-address" /etc/mysql/mysql.conf.d/mysqld.cnf; then
  sed -i '/^bind-address/s/bind-address.*=.*/bind-address = */' /etc/mysql/mysql.conf.d/mysqld.cnf
else
  echo "bind-address = *" >> /etc/mysql/mysql.conf.d/mysqld.cnf
fi

export MYSQL_TEST_LOGIN_FILE="$(mktemp --suffix .mylogin.cnf)"

cat > "$MYSQL_TEST_LOGIN_FILE" <<EOF
[client]
user=root
password={{ $databasePassword }}
EOF

chmod 600 "$MYSQL_TEST_LOGIN_FILE"

mysql -e "CREATE USER 'root'@'{{ $server->private_ip_address }}' IDENTIFIED BY '{{ $databasePassword }}';"
mysql -e "CREATE USER 'root'@'%' IDENTIFIED BY '{{ $databasePassword }}';"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO root@'{{ $server->private_ip_address }}' WITH GRANT OPTION;"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO root@'%' WITH GRANT OPTION;"
service mysql restart

mysql -e "CREATE USER 'netipar'@'{{ $server->private_ip_address }}' IDENTIFIED BY '{{ $databasePassword }}';"
mysql -e "CREATE USER 'netipar'@'%' IDENTIFIED BY '{{ $databasePassword }}';"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'netipar'@'{{ $server->private_ip_address }}' WITH GRANT OPTION;"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'netipar'@'%' WITH GRANT OPTION;"
mysql -e "FLUSH PRIVILEGES;"

# Create The Initial Database If Specified

mysql -e "CREATE DATABASE netipar CHARACTER SET utf8 COLLATE utf8_unicode_ci;"

rm -f "$MYSQL_TEST_LOGIN_FILE"
unset MYSQL_TEST_LOGIN_FILE

if [[ $(grep --count "maxsize" /etc/logrotate.d/mysql-server) == 0 ]]; then
    sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" /etc/logrotate.d/mysql-server
else
    sed -i -r "s/^(\s*)maxsize.*$/\1maxsize 100M/" /etc/logrotate.d/mysql-server
fi
    fi

    provisionPing {{ $server->id }} 7
