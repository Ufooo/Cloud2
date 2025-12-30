#!/bin/bash
set -e

# Netipar Cloud - Delete MySQL User
# Username: {!! $username !!}

# Drop user (all hosts)
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "DROP USER IF EXISTS '{!! $username !!}'@'localhost';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "DROP USER IF EXISTS '{!! $username !!}'@'{!! $serverIp !!}';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "DROP USER IF EXISTS '{!! $username !!}'@'%';"

mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "FLUSH PRIVILEGES;"

echo "MySQL user {!! $username !!} deleted successfully"
