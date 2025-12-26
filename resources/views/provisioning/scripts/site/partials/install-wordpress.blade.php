echo "Installing WordPress..."

cd {{ $fullPath }}

# Download WordPress
echo "Downloading WordPress..."
wget -q http://wordpress.org/latest.tar.gz

# Extract WordPress to the web directory
echo "Extracting WordPress..."
@if($webDirectory === '/')
tar -xf latest.tar.gz -C {{ $fullPath }} --strip-components=1
@else
tar -xf latest.tar.gz -C {{ $fullPath }}{{ $webDirectory }} --strip-components=1
@endif

# Clean up download
rm latest.tar.gz

@if($site->database)
# Configure WordPress
echo "Configuring WordPress..."
cd {{ $fullPath }}{{ $webDirectory }}

# Remove existing wp-config.php if exists
if [[ -f wp-config.php ]]; then
    rm wp-config.php
fi

# Create wp-config.php using WP-CLI
php{{ $phpVersion }} /usr/local/bin/wp core config \
    --dbname="{{ $site->database->name }}" \
    --dbuser="{{ $site->databaseUser?->username ?? 'root' }}" \
    --dbpass="{{ $site->databaseUser?->password ?? '' }}" \
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

echo "WordPress configured successfully"
@else
echo "No database configured - WordPress will need manual configuration"
@endif

# Remove default index.html stub
cd {{ $fullPath }}{{ $webDirectory }}
rm -f index.html

# Set proper ownership
chown -R {{ $user }}:{{ $user }} {{ $fullPath }}

echo "WordPress installed successfully!"
