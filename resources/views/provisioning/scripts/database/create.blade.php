#!/bin/bash
set -e

# Netipar Cloud - Create MySQL Database
# Database: {{ $databaseName }}

mysql --user="root" --password="{{ $mysqlRootPassword }}" -e "CREATE DATABASE \`{{ $databaseName }}\` CHARACTER SET utf8 COLLATE utf8_unicode_ci;"

echo "Database {{ $databaseName }} created successfully"
