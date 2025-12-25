#!/bin/bash
set -e

# Netipar Cloud - Sync MySQL User
# Username: {{ $username }}

MYSQL_ROOT_PASSWORD='{{ $mysqlRootPassword }}'

# Drop existing user (all hosts)
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "DROP USER IF EXISTS '{{ $username }}';"
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "DROP USER IF EXISTS '{{ $username }}'@'{{ $serverIp }}';"
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "DROP USER IF EXISTS '{{ $username }}'@'%';"

# Create user for both hosts
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "CREATE USER IF NOT EXISTS '{{ $username }}'@'{{ $serverIp }}' IDENTIFIED WITH mysql_native_password BY '{{ $password }}';"
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "CREATE USER IF NOT EXISTS '{{ $username }}'@'%' IDENTIFIED WITH mysql_native_password BY '{{ $password }}';"

@if(count($databases) > 0)
# Grant permissions on databases
@foreach($databases as $database)
@if($readonly)
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "GRANT SELECT ON \`{{ $database }}\`.* TO '{{ $username }}'@'{{ $serverIp }}' WITH GRANT OPTION;"
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "GRANT SELECT ON \`{{ $database }}\`.* TO '{{ $username }}'@'%' WITH GRANT OPTION;"
@else
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "GRANT ALL ON \`{{ $database }}\`.* TO '{{ $username }}'@'{{ $serverIp }}' WITH GRANT OPTION;"
mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "GRANT ALL ON \`{{ $database }}\`.* TO '{{ $username }}'@'%' WITH GRANT OPTION;"
@endif
@endforeach
@endif

mysql --user="root" --password="${MYSQL_ROOT_PASSWORD}" -e "FLUSH PRIVILEGES;"

echo "MySQL user {{ $username }} synced successfully"
