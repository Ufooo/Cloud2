#!/bin/bash
set -e

echo -e '\e[32m=> Installing WordPress\e[0m'

APPLICATION_PATH="{{ $applicationPath }}"
WEB_DIR="{{ $webDirectory }}"

cd "$APPLICATION_PATH"

# Download WordPress
echo -e '\e[32m=> Downloading WordPress\e[0m'
wget -q http://wordpress.org/latest.tar.gz

# Extract WordPress to the web directory
echo -e '\e[32m=> Extracting WordPress\e[0m'
@if($webDirectory === '/')
tar -xf latest.tar.gz -C "$APPLICATION_PATH" --strip-components=1
@else
mkdir -p "$APPLICATION_PATH{{ $webDirectory }}"
tar -xf latest.tar.gz -C "$APPLICATION_PATH{{ $webDirectory }}" --strip-components=1
@endif

# Clean up download
rm latest.tar.gz

@if($site->database && $site->databaseUser)
# Configure WordPress using WP-CLI
echo -e '\e[32m=> Configuring wp-config.php\e[0m'
@if($webDirectory === '/')
cd "$APPLICATION_PATH"
@else
cd "$APPLICATION_PATH{{ $webDirectory }}"
@endif

# Remove existing wp-config.php if exists
if [[ -f wp-config.php ]]; then
    rm wp-config.php
fi

# Create wp-config.php using WP-CLI
php{{ $phpVersion }} /usr/local/bin/wp core config \
    --dbname="{{ $site->database->name }}" \
    --dbuser="{{ $site->databaseUser->username }}" \
    --dbpass="{{ $site->databaseUser->password }}" \
    --dbhost="127.0.0.1" \
    --skip-check

# Add HTTPS proxy support for load balancers
awk '/\/\* Add any custom values between this line and the "stop editing" line. \*\// {
    print
    print "if (isset($_SERVER[\"HTTP_X_FORWARDED_PROTO\"]) && $_SERVER[\"HTTP_X_FORWARDED_PROTO\"] === \"https\") {"
    print "    $_SERVER[\"HTTPS\"] = \"on\";"
    print "}"
    next
}1' wp-config.php > wp-config.php.new && mv -f wp-config.php.new wp-config.php

echo -e '\e[32m=> WordPress configured with database credentials\e[0m'
@else
echo -e '\e[33m=> No database configured - WordPress will need manual configuration\e[0m'
@endif

# Remove default index.html stub
@if($webDirectory === '/')
rm -f "$APPLICATION_PATH/index.html"
@else
rm -f "$APPLICATION_PATH{{ $webDirectory }}/index.html"
@endif

# Set proper ownership
chown -R {{ $user }}:{{ $user }} "$APPLICATION_PATH"

echo -e '\e[32m=> WordPress installed successfully!\e[0m'
