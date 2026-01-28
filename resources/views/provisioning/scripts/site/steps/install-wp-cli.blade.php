#!/bin/bash
set -e

if [ -f /usr/local/bin/wp ]; then
    echo -e '\e[32m=> WP-CLI already installed\e[0m'
    /usr/local/bin/wp --version --allow-root
    exit 0
fi

echo -e '\e[32m=> Installing WP-CLI\e[0m'
curl -s -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp

echo -e '\e[32m=> WP-CLI installed successfully\e[0m'
/usr/local/bin/wp --version --allow-root
