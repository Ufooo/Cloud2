    mkdir -p /etc/apt/keyrings

curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg

NODE_MAJOR={{ $nodeVersion ?? 22 }}
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" > /etc/apt/sources.list.d/nodesource.list

apt-get update
sudo apt-get install -y --force-yes nodejs

npm install -g pm2
npm install -g gulp
npm install -g yarn
npm install -g pnpm
npm install -g bun

    provisionPing {{ $server->id }} 6
