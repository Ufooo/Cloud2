#!/bin/bash
set -e

# Netipar Cloud - Delete MySQL User
# Username: {{ $username }}

MYSQL_ROOT_PASSWORD='{{ $mysqlRootPassword }}'

# Drop user (all hosts)
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "DROP USER IF EXISTS '{{ $username }}';"
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "DROP USER IF EXISTS '{{ $username }}'@'{{ $serverIp }}';"
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "DROP USER IF EXISTS '{{ $username }}'@'%';"

mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "FLUSH PRIVILEGES;"

echo "MySQL user {{ $username }} deleted successfully"
