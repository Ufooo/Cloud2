    # Install Postgres {{ $postgresqlVersion }}
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt/ `lsb_release -cs`-pgdg main" >> /etc/apt/sources.list.d/pgdg.list'
apt-get update
apt-get install -y --force-yes postgresql-{{ $postgresqlVersion }} postgresql-contrib-{{ $postgresqlVersion }}

# Configure Postgres For Remote Access

sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/g" /etc/postgresql/{{ $postgresqlVersion }}/main/postgresql.conf
echo "host    all             all             0.0.0.0/0               md5" | tee -a /etc/postgresql/{{ $postgresqlVersion }}/main/pg_hba.conf
sed -i "s/local   all             all                                     peer/local   all             all                                     md5/g" /etc/postgresql/{{ $postgresqlVersion }}/main/pg_hba.conf
sudo -u postgres psql -c "CREATE ROLE netipar LOGIN PASSWORD '{{ $databasePassword }}' SUPERUSER INHERIT NOCREATEDB NOCREATEROLE NOREPLICATION;"
service postgresql restart

# Configure The Timezone

sudo sed -i "s/localtime/UTC/" /etc/postgresql/{{ $postgresqlVersion }}/main/postgresql.conf
service postgresql restart

# Create The Initial Database If Specified

sudo -u postgres /usr/bin/createdb --echo --owner=netipar netipar

    provisionPing {{ $server->id }} 7
