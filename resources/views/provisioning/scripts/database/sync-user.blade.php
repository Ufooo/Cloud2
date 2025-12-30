#!/bin/bash
set -e

# Netipar Cloud - Sync MySQL User
# Username: {!! $username !!}

# Drop existing user (all hosts)
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "DROP USER IF EXISTS '{!! $username !!}'@'localhost';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "DROP USER IF EXISTS '{!! $username !!}'@'{!! $serverIp !!}';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "DROP USER IF EXISTS '{!! $username !!}'@'%';"

# Create user for localhost, server IP, and wildcard hosts
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "CREATE USER IF NOT EXISTS '{!! $username !!}'@'localhost' IDENTIFIED WITH mysql_native_password BY '{!! $password !!}';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "CREATE USER IF NOT EXISTS '{!! $username !!}'@'{!! $serverIp !!}' IDENTIFIED WITH mysql_native_password BY '{!! $password !!}';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "CREATE USER IF NOT EXISTS '{!! $username !!}'@'%' IDENTIFIED WITH mysql_native_password BY '{!! $password !!}';"

@if(count($databases) > 0)
# Grant permissions on databases
@foreach($databases as $database)
@if($readonly)
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "GRANT SELECT ON \`{!! $database !!}\`.* TO '{!! $username !!}'@'localhost';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "GRANT SELECT ON \`{!! $database !!}\`.* TO '{!! $username !!}'@'{!! $serverIp !!}';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "GRANT SELECT ON \`{!! $database !!}\`.* TO '{!! $username !!}'@'%';"
@else
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "GRANT ALL ON \`{!! $database !!}\`.* TO '{!! $username !!}'@'localhost';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "GRANT ALL ON \`{!! $database !!}\`.* TO '{!! $username !!}'@'{!! $serverIp !!}';"
mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "GRANT ALL ON \`{!! $database !!}\`.* TO '{!! $username !!}'@'%';"
@endif
@endforeach
@endif

mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "FLUSH PRIVILEGES;"

echo "MySQL user {!! $username !!} synced successfully"
