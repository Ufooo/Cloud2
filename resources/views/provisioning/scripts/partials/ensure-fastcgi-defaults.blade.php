#
# Ensure FastCGI Defaults Exist
#

if [ ! -f /etc/nginx/netipar_fastcgi_defaults ]; then
    echo "Creating FastCGI defaults file..."
    cat > /etc/nginx/netipar_fastcgi_defaults << 'FASTCGIEOF'
# Netipar Cloud - FastCGI Defaults
# Common FastCGI parameters for PHP applications

# Script path parameters
fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
fastcgi_param PATH_INFO $fastcgi_path_info;

# Buffer settings for optimal performance
fastcgi_buffers 16 16k;
fastcgi_buffer_size 32k;

# Timeouts
fastcgi_connect_timeout 60;
fastcgi_send_timeout 180;
fastcgi_read_timeout 180;

# Intercept errors for custom error pages
fastcgi_intercept_errors off;
FASTCGIEOF
fi
