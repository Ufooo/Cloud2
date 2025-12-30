#!/bin/bash
set -e

# Netipar Cloud - Delete MySQL Database
# Database: {!! $databaseName !!}

mysql --user="root" --password="{!! $mysqlRootPassword !!}" -e "DROP DATABASE IF EXISTS \`{!! $databaseName !!}\`;"

echo "Database {!! $databaseName !!} deleted successfully"
