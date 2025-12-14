    # Install Meilisearch

curl -L https://install.meilisearch.com | sh

chmod +x ./meilisearch

mv ./meilisearch /usr/local/bin/

# Create System Meilisearch User

useradd -d /var/lib/meilisearch -b /bin/false -m -r meilisearch

# Create Config File

curl https://raw.githubusercontent.com/meilisearch/meilisearch/latest/config.toml > /etc/meilisearch.toml
sed -i "s/env.*/env = \"production\"/" /etc/meilisearch.toml
sed -i "s/# master_key.*/master_key = \"{{ $meilisearchKey }}\"/" /etc/meilisearch.toml
sed -i "s/http_addr.*/http_addr = \"0.0.0.0:7700\"/" /etc/meilisearch.toml

# Create Directories

mkdir /var/lib/meilisearch/data /var/lib/meilisearch/dumps /var/lib/meilisearch/snapshots
chown -R meilisearch:meilisearch /var/lib/meilisearch
chmod 750 /var/lib/meilisearch
mkdir /var/log/meilisearch/
touch /var/log/meilisearch/meilisearch.log

# Create Service

cat << EOF > /etc/systemd/system/meilisearch.service
[Unit]
Description=Meilisearch
After=systemd-user-sessions.service

[Service]
Type=simple
WorkingDirectory=/var/lib/meilisearch
ExecStart=/usr/local/bin/meilisearch --config-file-path /etc/meilisearch.toml
User=meilisearch
Group=meilisearch
StandardOutput=file:/var/log/meilisearch/meilisearch.log
StandardError=inherit

[Install]
WantedBy=default.target
EOF

systemctl daemon-reload

# Set And Start The Service

systemctl enable meilisearch.service
systemctl start meilisearch.service

sleep 5

MEILI=$(ps aux | grep meilisearch | grep -v grep)

if [[ ! -z $MEILI ]]; then
  systemctl start meilisearch.service
fi

provisionPing {{ $server->id }} 10
